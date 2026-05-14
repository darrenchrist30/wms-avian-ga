<?php
/**
 * Assign item ke cell WMS berdasarkan data blok/grup dari MSpart.sql
 *
 * Mapping: blok=4, grup=A → cell code "4-A"
 *          blok=2, grup=2 → angka dikonversi: 1=A,2=B,...,7=G → cell "2-B"
 *
 * Usage:
 *   php assign_cells_from_sql.php          → dry-run (preview only)
 *   php assign_cells_from_sql.php --apply  → write ke DB
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv);
echo "=== Assign Cell WMS dari MSpart.sql " . ($apply ? "[APPLY MODE]" : "[DRY-RUN]") . " ===\n\n";

// ── 1. Parse MSpart.sql ─────────────────────────────────────────────
$sqlFile = 'C:\Users\TUF-GAMING\Downloads\MSpart.sql';
$lines = file($sqlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$mspartData = [];  // kode => [nama, blok, grup]
$validGrup  = ['A','B','C','D','E','F','G'];
// Konversi angka grup ke huruf (level WMS 1-7 = A-G)
$numToLetter = ['1'=>'A','2'=>'B','3'=>'C','4'=>'D','5'=>'E','6'=>'F','7'=>'G'];

foreach ($lines as $line) {
    if (!str_starts_with(trim($line), 'insert')) continue;

    // Parse: values('kode','nama',stok,'sat',min,max,'blok','grup','kolom','baris',note,'isdel')
    if (!preg_match("/values\('([^']+)','([^']+)',[^,]*,'[^']*',[^,]*,[^,]*,('[^']*'|NULL),('[^']*'|NULL),('[^']*'|NULL),('[^']*'|NULL)/i", $line, $m)) continue;

    $kode = $m[1];
    $nama = $m[2];
    $blok = trim($m[3], "' ");
    $grup = trim($m[4], "' ");

    // Skip NULL atau blok bukan angka positif
    if ($blok === 'NULL' || $blok === '' || !ctype_digit($blok) || (int)$blok <= 0) continue;

    // Normalise grup: huruf → uppercase, angka → konversi ke huruf
    $grup = strtoupper($grup);
    if (isset($numToLetter[$grup])) {
        $grup = $numToLetter[$grup];  // "2" → "B"
    }

    // Skip grup di luar range A-G (misal H, 0, kosong)
    if (!in_array($grup, $validGrup)) continue;

    $mspartData[$kode] = ['nama' => $nama, 'blok' => (int)$blok, 'grup' => $grup];
}

echo "Item dengan data blok/grup valid di SQL: " . count($mspartData) . "\n\n";

// ── 2. Load cell map dari WMS ──────────────────────────────────────
// cells: code = "{rack_id}-{level_letter}"  (contoh: "4-A")
$cells = DB::table('cells')
    ->join('racks', 'racks.id', '=', 'cells.rack_id')
    ->select('cells.id', 'cells.code', 'racks.code as rack_code', 'cells.level', 'cells.capacity_max', 'cells.capacity_used')
    ->get()
    ->keyBy('code');  // key by cell code e.g. "4-A"

echo "Total cells di WMS: " . $cells->count() . "\n\n";

// ── 3. Match item ke cell ──────────────────────────────────────────
$matched    = 0;
$noItem     = 0;
$noCell     = 0;
$skipped    = 0;  // item ada tapi tidak punya stock_record

$updates = [];  // [stock_record_id => new_cell_id, old_cell_id]

$levelLetter = ['A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5,'F'=>6,'G'=>7];

foreach ($mspartData as $kode => $data) {
    $targetCode = $data['blok'] . '-' . $data['grup'];

    // Cari item di WMS by SKU
    $item = DB::table('items')->where('sku', $kode)->first();
    if (!$item) {
        $noItem++;
        continue;
    }

    // Cari cell target
    if (!$cells->has($targetCode)) {
        $noCell++;
        echo "  [NO CELL] {$kode} → target cell {$targetCode} tidak ada di WMS\n";
        continue;
    }
    $targetCell = $cells->get($targetCode);

    // Cari stock_record item ini
    $sr = DB::table('stock_records')->where('item_id', $item->id)->first();
    if (!$sr) {
        $skipped++;
        continue;
    }

    // Sudah di cell yang benar?
    $currentCell = $cells->firstWhere('id', $sr->cell_id);
    $currentCode = $currentCell ? $currentCell->code : '?';

    if ($sr->cell_id == $targetCell->id) {
        // Sudah benar, tidak perlu update
        continue;
    }

    $updates[] = [
        'sr_id'       => $sr->id,
        'item_id'     => $item->id,
        'item_name'   => $item->name,
        'sku'         => $kode,
        'old_cell_id' => $sr->cell_id,
        'old_code'    => $currentCode,
        'new_cell_id' => $targetCell->id,
        'new_code'    => $targetCode,
        'qty'         => $sr->quantity,
    ];
    $matched++;
}

echo "Hasil matching:\n";
echo "  Item ditemukan & perlu dipindah  : $matched\n";
echo "  Item tidak ditemukan di WMS      : $noItem\n";
echo "  Cell target tidak ada di WMS     : $noCell\n";
echo "  Item ada tapi tidak ada stok     : $skipped\n\n";

if (empty($updates)) {
    echo "Tidak ada perubahan yang perlu dilakukan.\n";
    exit(0);
}

// ── 4. Preview ─────────────────────────────────────────────────────
echo "=== Preview Perubahan (max 30) ===\n";
echo str_pad("SKU", 25) . str_pad("Nama Item", 40) . str_pad("Dari Cell", 10) . "→ Ke Cell\n";
echo str_repeat("-", 90) . "\n";
foreach (array_slice($updates, 0, 30) as $u) {
    echo str_pad($u['sku'], 25)
       . str_pad(mb_substr($u['item_name'], 0, 38), 40)
       . str_pad($u['old_code'], 10)
       . "→ " . $u['new_code'] . "\n";
}
if (count($updates) > 30) {
    echo "... dan " . (count($updates) - 30) . " item lainnya\n";
}

// ── 5. Apply ───────────────────────────────────────────────────────
if (!$apply) {
    echo "\nJalankan dengan --apply untuk menyimpan ke database.\n";
    exit(0);
}

echo "\nMenyimpan ke database...\n";
DB::beginTransaction();
try {
    $affected = 0;
    foreach ($updates as $u) {
        DB::table('stock_records')
            ->where('id', $u['sr_id'])
            ->update(['cell_id' => $u['new_cell_id'], 'updated_at' => now()]);
        $affected++;
    }

    // Recalculate capacity_used untuk semua cell yang terdampak
    $allCellIds = array_unique(
        array_merge(
            array_column($updates, 'old_cell_id'),
            array_column($updates, 'new_cell_id')
        )
    );

    foreach ($allCellIds as $cellId) {
        $itemCount = DB::table('stock_records')
            ->where('cell_id', $cellId)
            ->where('quantity', '>', 0)
            ->count();

        $cap = DB::table('cells')->where('id', $cellId)->value('capacity_max');
        $status = match(true) {
            $itemCount <= 0        => 'available',
            $itemCount >= $cap     => 'full',
            default                => 'partial',
        };

        DB::table('cells')->where('id', $cellId)->update([
            'capacity_used' => $itemCount,
            'status'        => $status,
            'updated_at'    => now(),
        ]);
    }

    DB::commit();
    echo "Selesai! {$affected} stock record dipindah ke cell yang sesuai.\n";
    echo count($allCellIds) . " cell di-recalculate capacity_used-nya.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
