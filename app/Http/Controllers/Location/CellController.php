<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\ItemCategory;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\ColumnCategoryAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class CellController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,supervisor')->only(['create', 'store', 'edit', 'update', 'destroy', 'applyColumnCategory']);
    }

    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $bloks      = Cell::where('is_active', true)->whereNotNull('blok')
                         ->distinct()->orderByRaw('CAST(blok AS UNSIGNED)')->pluck('blok');
        $defaultWarehouseId = Cell::where('cells.is_active', true)
                         ->join('racks', 'cells.rack_id', '=', 'racks.id')
                         ->where('racks.is_active', true)
                         ->value('racks.warehouse_id');
        return view('location.cells.index', compact('warehouses', 'bloks', 'defaultWarehouseId'));
    }

    public function create()
    {
        $racks      = Rack::with('warehouse')->where('is_active', true)->orderBy('code')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.cells.form', [
            'typeForm'   => 'create',
            'data'       => null,
            'racks'      => $racks,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'rack_id'              => 'required|exists:racks,id',
            'level'                => 'required|integer|min:1|max:26',
            'capacity_max'         => 'required|integer|min:1',
            'dominant_category_id' => 'nullable|exists:item_categories,id',
            'status'               => 'required|in:available,partial,full,blocked,reserved',
            'is_active'            => 'boolean',
        ]);
        $exists = Cell::where('rack_id', $request->rack_id)
            ->where('level', $request->level)->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['level' => 'Sel dengan level tersebut sudah ada di rak ini.']);
        }
        $rack   = Rack::findOrFail($request->rack_id);
        $letter = chr(64 + $request->level); // 1=A, 2=B, ..., 7=G
        DB::beginTransaction();
        try {
            Cell::create([
                'rack_id'              => $request->rack_id,
                'dominant_category_id' => $request->dominant_category_id,
                'code'                 => $rack->code . '-' . $letter,
                'level'                => $request->level,
                'column'               => 1,
                'capacity_max'         => $request->capacity_max,
                'capacity_used'        => 0,
                'status'               => $request->status,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.cells.index')->with('success', 'Sel berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan sel. Silakan coba lagi.');
        }
    }

    public function show($id) { return redirect()->route('location.cells.index'); }

    public function edit($id)
    {
        $data       = Cell::with('rack.warehouse')->findOrFail($id);
        $racks      = Rack::with('warehouse')->where('is_active', true)->orderBy('code')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.cells.form', [
            'typeForm'   => 'edit',
            'data'       => $data,
            'racks'      => $racks,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, $id)
    {
        $cell = Cell::findOrFail($id);
        $request->validate([
            'capacity_max'         => [
                'required', 'integer', 'min:1',
                function ($attribute, $value, $fail) use ($cell) {
                    $currentUsed = (int) $cell->physical_capacity_used;
                    if ((int) $value < $currentUsed) {
                        $fail("Kapasitas maks tidak boleh kurang dari kapasitas terpakai saat ini ({$currentUsed} unit).");
                    }
                },
            ],
            'dominant_category_id' => 'nullable|exists:item_categories,id',
            'status'               => 'required|in:available,partial,full,blocked,reserved',
            'is_active'            => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            $cell->update([
                'dominant_category_id' => $request->dominant_category_id,
                'capacity_max'         => $request->capacity_max,
                'status'               => $request->status,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.cells.index')->with('success', 'Sel berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui sel. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $cell = Cell::withCount('stocks')->findOrFail($id);
        if ($cell->stocks_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Sel tidak dapat dihapus karena masih memiliki ' . $cell->stocks_count . ' stok aktif.'], 422);
        }
        DB::beginTransaction();
        try {
            $cell->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Sel berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    // ─── AJAX lookup cell berdasarkan QR / kode ──────────────────────────────────
    public function lookup(Request $request)
    {
        $code = trim($request->input('code', ''));
        if (!$code) {
            return response()->json(['found' => false, 'message' => 'Kode tidak boleh kosong.']);
        }

        $cell = Cell::with(['rack.warehouse', 'dominantCategory'])
            ->where(function ($q) use ($code) {
                $q->where('code', $code)
                  ->orWhere('qr_code', $code)
                  ->orWhere('label', $code);
            })
            ->where('is_active', true)
            ->first();

        // Column record (baris=null) → kembalikan column_found agar baris picker muncul
        if ($cell && $cell->isColumnCell()) {
            $columnCells = Cell::where('blok', $cell->blok)
                ->whereRaw('UPPER(grup) = ?', [strtoupper($cell->grup)])
                ->where('kolom', $cell->kolom)
                ->whereNotNull('baris')
                ->where('is_active', true)
                ->orderBy('baris')
                ->get();

            return response()->json([
                'found'        => false,
                'column_found' => true,
                'column_code'  => $cell->code,
                'column_cells' => $columnCells->map(fn($c) => [
                    'id'                 => $c->id,
                    'code'               => $c->physical_code,
                    'baris'              => $c->baris,
                    'status'             => $c->status,
                    'capacity_remaining' => max(0, $c->capacity_max - $c->physical_capacity_used),
                    'capacity_max'       => $c->capacity_max,
                ]),
            ]);
        }

        if (!$cell) {
            // Cell ditemukan tapi nonaktif → beri pesan spesifik
            $inactive = Cell::where(function ($q) use ($code) {
                $q->where('code', $code)->orWhere('qr_code', $code)->orWhere('label', $code);
            })->where('is_active', false)->first();
            if ($inactive) {
                return response()->json(['found' => false, 'message' => "Cell \"{$code}\" tidak aktif dan tidak bisa digunakan."]);
            }

            // Kode kolom: blok-GRUP-kolom (e.g. "1-A-1") → tampilkan pilihan baris
            if (preg_match('/^(\d+)-([A-Za-z])-(\d+)$/', $code, $m)) {
                $columnCells = Cell::where('blok', $m[1])
                    ->where('grup', strtoupper($m[2]))
                    ->where('kolom', $m[3])
                    ->where('is_active', true)
                    ->orderBy('baris')
                    ->get();

                if ($columnCells->isNotEmpty()) {
                    return response()->json([
                        'found'        => false,
                        'column_found' => true,
                        'column_code'  => $code,
                        'column_cells' => $columnCells->map(fn($c) => [
                            'id'                 => $c->id,
                            'code'               => $c->physical_code,
                            'baris'              => $c->baris,
                            'status'             => $c->status,
                            'capacity_remaining' => max(0, $c->capacity_max - $c->physical_capacity_used),
                            'capacity_max'       => $c->capacity_max,
                        ]),
                    ]);
                }
            }

            // Kode 2-segmen blok-GRUP (e.g. "1-F") — berikan contoh format yang benar
            if (preg_match('/^(\d+)-([A-Za-z])$/', $code, $m)) {
                $exists = Cell::where('blok', $m[1])
                    ->where('grup', strtoupper($m[2]))
                    ->where('is_active', true)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'found'   => false,
                        'message' => "Kode \"{$code}\" terlalu singkat. Tambahkan nomor kolom, contoh: {$code}-1",
                    ]);
                }
            }

            return response()->json(['found' => false, 'message' => "Cell \"{$code}\" tidak ditemukan."]);
        }

        // Stok aktual dari stock_records (FIFO)
        $stocks = Stock::with(['item.unit', 'item.category'])
            ->where('cell_id', $cell->id)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('inbound_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $totalQty  = $stocks->sum('quantity');
        $totalSkus = $stocks->unique('item_id')->count();
        $capacityUsed = $cell->physical_capacity_used;
        $utilPct   = $cell->capacity_max > 0
            ? min(100, round($capacityUsed / $cell->capacity_max * 100))
            : 0;

        return response()->json([
            'found' => true,
            'cell'  => [
                'id'            => $cell->id,
                'code'          => $cell->code,
                'label'         => $cell->label ?? $cell->code,
                'rack'          => $cell->rack?->code ?? '—',
                'warehouse'     => $cell->rack?->warehouse?->name ?? '—',
                'level'         => chr(64 + $cell->level),
                'status'        => $cell->status,
                'capacity_max'  => $cell->capacity_max,
                'capacity_used' => $capacityUsed,
                'capacity_remaining' => max(0, $cell->capacity_max - $capacityUsed),
                'utilization'   => $utilPct,
                'total_qty'     => (int) $totalQty,
                'total_skus'    => $totalSkus,
                'qr_label_url'  => route('location.cells.qr-label', $cell->id),
                'stock_url'     => route('location.cells.stock', $cell->id),
            ],
            'stocks' => $stocks->values()->map(fn ($s) => [
                'item_id'        => $s->item_id,
                'sku'            => $s->item?->sku ?? '—',
                'name'           => $s->item?->name ?? '—',
                'category'       => $s->item?->category?->name ?? '—',
                'category_color' => $s->item?->category?->color_code ?? '#6c757d',
                'unit'           => $s->item?->unit?->code ?? '',
                'quantity'       => (int) $s->quantity,
                'inbound_date'   => $s->inbound_date?->format('d M Y'),
                'days_in_cell'   => $s->inbound_date
                    ? (int) $s->inbound_date->diffInDays(now()) : null,
                'expiry_date'    => $s->expiry_date?->format('d M Y'),
                'lpn'            => $s->lpn,
            ]),
        ]);
    }

    // ─── Print QR Label untuk seluruh cell dalam satu rak ───────────────────────
    public function bulkQrLabel(Request $request)
    {
        $rackId = $request->input('rack_id');
        if (!$rackId) {
            return back()->with('error', 'Pilih rak terlebih dahulu.');
        }

        $rack  = Rack::with('warehouse')->findOrFail($rackId);
        $cells = Cell::where('rack_id', $rackId)
            ->where('is_active', true)
            ->where(function ($q) {
                // Hanya cetak cell level kolom (3 format: blok-grup-kolom, baris=null)
                // atau cell non-MSpart (blok=null)
                $q->whereNull('baris')
                  ->orWhereNull('blok');
            })
            ->orderBy('level')
            ->get();

        if ($cells->isEmpty()) {
            return back()->with('error', 'Tidak ada cell aktif di rak ' . $rack->code . '.');
        }

        // Auto-generate qr_code jika belum ada
        $cells->each(function ($cell) {
            if (!$cell->qr_code) {
                $cell->qr_code = $cell->code;
                $cell->saveQuietly();
            }
        });

        return view('location.cells.bulk-qr', compact('cells', 'rack'));
    }

    // ─── Print QR Label per Kolom (blok-grup-kolom) ─────────────────────────────
    public function columnQrLabel(Request $request)
    {
        $rackId      = $request->input('rack_id');
        $warehouseId = $request->input('warehouse_id');

        $query = Cell::with('rack.warehouse')
            ->where('is_active', true)
            ->whereNotNull('blok')
            ->whereNotNull('grup')
            ->whereNotNull('kolom')
            ->whereNotNull('baris')
            ->orderBy('blok')
            ->orderByRaw('UPPER(grup)')
            ->orderBy('kolom')
            ->orderBy('baris');

        $rack = null;
        if ($columnCode = $request->input('column')) {
            // Satu kolom spesifik dari tombol QR di DataTable
            $parts = explode('-', $columnCode);
            if (count($parts) === 3) {
                $query->where('blok', $parts[0])
                      ->whereRaw('UPPER(grup) = ?', [strtoupper($parts[1])])
                      ->where('kolom', $parts[2]);
            }
        } elseif ($rackId) {
            $rack = Rack::with('warehouse')->find($rackId);
            $query->where('rack_id', $rackId);
        } elseif ($warehouseId) {
            $query->whereHas('rack', fn($q) => $q->where('warehouse_id', $warehouseId));
        }

        $cells = $query->get();

        if ($cells->isEmpty()) {
            return back()->with('error', 'Tidak ada cell dengan kode kolom (format blok-grup-kolom-baris) yang ditemukan. Coba pilih rak seperti "1F", "1G", dst yang berisi sel berformat tersebut.');
        }

        $columns = $cells->groupBy(fn($c) => $c->blok . '-' . strtoupper($c->grup) . '-' . $c->kolom);

        return view('location.cells.column-qr', compact('columns', 'rack'));
    }

    public function columnCategoryPreview(Request $request, ColumnCategoryAssignmentService $service)
    {
        $data = $request->validate([
            'blok'      => 'required|integer|min:1',
            'grup'      => 'required|string|size:1',
            'kolom'     => 'required|integer|min:1',
            'threshold' => 'nullable|numeric|min:0|max:100',
        ]);

        return response()->json($service->preview(
            $data['blok'],
            $data['grup'],
            $data['kolom'],
            (float) ($data['threshold'] ?? ColumnCategoryAssignmentService::DEFAULT_THRESHOLD)
        ));
    }

    public function applyColumnCategory(Request $request, ColumnCategoryAssignmentService $service)
    {
        $data = $request->validate([
            'blok'                 => 'required|integer|min:1',
            'grup'                 => 'required|string|size:1',
            'kolom'                => 'required|integer|min:1',
            'dominant_category_id' => 'required|exists:item_categories,id',
            'mode'                 => 'required|in:neutral_only,overwrite',
            'threshold'            => 'nullable|numeric|min:0|max:100',
        ]);

        $result = $service->apply(
            $data['blok'],
            $data['grup'],
            $data['kolom'],
            (int) $data['dominant_category_id'],
            $data['mode'],
            (float) ($data['threshold'] ?? ColumnCategoryAssignmentService::DEFAULT_THRESHOLD)
        );

        return response()->json([
            'status'  => 'success',
            'message' => "Kategori kolom berhasil diterapkan ke {$result['updated_count']} cell.",
            'result'  => $result,
        ]);
    }

    // ─── Print QR Label untuk Cell ───────────────────────────────────────────────
    public function qrLabel(Cell $cell)
    {
        $cell->load(['rack.warehouse', 'dominantCategory']);

        // Generate & simpan qr_code jika belum ada
        if (!$cell->qr_code) {
            $cell->qr_code = $cell->code;
            $cell->saveQuietly();
        }

        // Hitung stok aktual
        $totalQty = Stock::where('cell_id', $cell->id)
            ->where('status', 'available')
            ->sum('quantity');

        return view('location.cells.qr-label', compact('cell', 'totalQty'));
    }

    public function stockDetail($id)
    {
        $cell = Cell::with([
            'rack.warehouse',
            'dominantCategory',
            'stocks.item.category',
            'stocks.item.unit',
        ])->findOrFail($id);
        return view('location.cells.stock', compact('cell'));
    }

    public function datatable(Request $request)
    {
        $query = Cell::with(['rack.warehouse', 'dominantCategory'])->withCount('stocks')->where('cells.is_active', true);

        if ($request->filled('warehouse_id')) {
            $query->whereHas('rack', fn($q) => $q->where('warehouse_id', $request->warehouse_id));
        }
        if ($request->filled('blok')) {
            $query->where('blok', $request->blok);
        }
        if ($request->filled('status')) {
            $query->whereNotNull('baris')->where('status', $request->status);
        }

        // Pre-compute agregasi untuk sel tipe parent (format "blok-grup", e.g. "1-A")
        $parentAggregates = [];
        $parentCandidates = Cell::whereNull('blok')->get(['id', 'code']);
        foreach ($parentCandidates as $c) {
            $parts = explode('-', $c->code ?? '');
            if (count($parts) === 2 && is_numeric($parts[0]) && ctype_alpha($parts[1])) {
                $agg = Cell::where('blok', $parts[0])
                    ->whereRaw('UPPER(grup) = ?', [strtoupper($parts[1])])
                    ->whereNotNull('baris')
                    ->where('is_active', true)
                    ->selectRaw('SUM(capacity_used) as used, SUM(capacity_max) as max, COUNT(*) as cnt,
                                 SUM(CASE WHEN status="full" THEN 1 ELSE 0 END) as cnt_full,
                                 SUM(CASE WHEN status="available" THEN 1 ELSE 0 END) as cnt_avail')
                    ->first();
                if ($agg && $agg->cnt > 0) {
                    $parentAggregates[$c->id] = $agg;
                }
            }
        }

        // Pre-compute agregasi untuk column records (blok-grup-kolom, baris=null) — satu query GROUP BY
        $columnAggByKey = Cell::whereNotNull('blok')
            ->whereNotNull('grup')
            ->whereNotNull('kolom')
            ->whereNotNull('baris')
            ->where('is_active', true)
            ->selectRaw('blok, UPPER(grup) as grup, kolom,
                         SUM(capacity_used) as used, SUM(capacity_max) as cap_max, COUNT(*) as cnt,
                         SUM(CASE WHEN status="full" THEN 1 ELSE 0 END) as cnt_full,
                         SUM(CASE WHEN status="available" THEN 1 ELSE 0 END) as cnt_avail,
                         SUM(CASE WHEN dominant_category_id IS NULL THEN 1 ELSE 0 END) as cnt_neutral,
                         SUM(CASE WHEN dominant_category_id IS NOT NULL THEN 1 ELSE 0 END) as cnt_categorized')
            ->groupBy('blok', 'grup', 'kolom')
            ->get()
            ->keyBy(fn($r) => $r->blok . '-' . strtoupper($r->grup) . '-' . $r->kolom);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('is_column', fn($row) => $row->isColumnCell() ? 1 : 0)
            ->addColumn('column_code', fn($row) => ($row->blok !== null && $row->grup !== null && $row->kolom !== null && $row->baris !== null)
                ? $row->blok . '-' . strtoupper($row->grup) . '-' . $row->kolom
                : null)
            ->addColumn('level_label', function ($row) {
                if ($row->isColumnCell()) {
                    return '<span class="badge badge-light border" style="color:#004230;font-size:10px;">Kolom</span>';
                }
                return $row->level > 0 ? chr(64 + $row->level) : '-';
            })
            ->addColumn('lokasi', function ($row) {
                $wh   = $row->rack->warehouse->name ?? '-';
                $rack = $row->rack->code ?? '-';
                return '<small class="text-muted">' . e($wh) . ' / <strong>' . e($rack) . '</strong></small>';
            })
            ->addColumn('kapasitas', function ($row) use ($parentAggregates, $columnAggByKey) {
                if (isset($parentAggregates[$row->id])) {
                    $agg   = $parentAggregates[$row->id];
                    $used  = (int) $agg->used;
                    $max   = (int) $agg->max;
                    $pct   = $max > 0 ? round($used / $max * 100) : 0;
                    $color = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                    $html  = '<div class="progress" style="height:14px;"><div class="progress-bar ' . $color . '" style="width:' . min(100, $pct) . '%"><small>' . $pct . '%</small></div></div>';
                    $html .= '<small class="text-muted">' . $used . '/' . $max . '</small>';
                    $html .= '<small class="text-info" style="font-size:10px;"> (' . $agg->cnt . ' sel)</small>';
                    return $html;
                }
                if ($row->isColumnCell()) {
                    $key = $row->blok . '-' . strtoupper($row->grup) . '-' . $row->kolom;
                    $agg = $columnAggByKey->get($key);
                    if ($agg && (int) $agg->cnt > 0) {
                        $used  = (int) $agg->used;
                        $max   = (int) $agg->cap_max;
                        $pct   = $max > 0 ? round($used / $max * 100) : 0;
                        $color = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                        $html  = '<div class="progress" style="height:14px;"><div class="progress-bar ' . $color . '" style="width:' . min(100, $pct) . '%"><small>' . $pct . '%</small></div></div>';
                        $html .= '<small class="text-muted">' . $used . '/' . $max . '</small>';
                        $html .= '<small class="text-info" style="font-size:10px;"> (' . $agg->cnt . ' baris)</small>';
                        return $html;
                    }
                    return '<small class="text-muted">—</small>';
                }
                $pct   = $row->capacity_max > 0 ? round(($row->capacity_used / $row->capacity_max) * 100) : 0;
                $color = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                $html  = '<div class="progress" style="height:14px;"><div class="progress-bar ' . $color . '" style="width:' . $pct . '%"><small>' . $pct . '%</small></div></div>';
                $html .= '<small class="text-muted">' . $row->capacity_used . '/' . $row->capacity_max . '</small>';
                return $html;
            })
            ->addColumn('status_badge', function ($row) use ($parentAggregates, $columnAggByKey) {
                if (isset($parentAggregates[$row->id])) {
                    $agg = $parentAggregates[$row->id];
                    if ((int) $agg->cnt_full === (int) $agg->cnt)  return '<span class="badge badge-danger">Full</span>';
                    if ((int) $agg->cnt_avail === (int) $agg->cnt) return '<span class="badge badge-success">Available</span>';
                    return '<span class="badge badge-warning">Partial</span>';
                }
                if ($row->isColumnCell()) {
                    $key = $row->blok . '-' . strtoupper($row->grup) . '-' . $row->kolom;
                    $agg = $columnAggByKey->get($key);
                    if ($agg && (int) $agg->cnt > 0) {
                        if ((int) $agg->cnt_full === (int) $agg->cnt)  return '<span class="badge badge-danger">Full</span>';
                        if ((int) $agg->cnt_avail === (int) $agg->cnt) return '<span class="badge badge-success">Available</span>';
                        return '<span class="badge badge-warning">Partial</span>';
                    }
                    return '<span class="badge badge-secondary">—</span>';
                }
                $map = [
                    'available' => 'badge-success',
                    'partial'   => 'badge-warning',
                    'full'      => 'badge-danger',
                    'blocked'   => 'badge-dark',
                    'reserved'  => 'badge-info',
                ];
                $cls = $map[$row->status] ?? 'badge-secondary';
                return '<span class="badge ' . $cls . '">' . ucfirst($row->status) . '</span>';
            })
            ->addColumn('action', function ($row) use ($columnAggByKey) {
                if ($row->isColumnCell()) {
                    $colQrUrl = route('location.cells.column-qr') . '?column=' . urlencode($row->code);
                    $columnCode = e($row->code);
                    $key = $row->blok . '-' . strtoupper($row->grup) . '-' . $row->kolom;
                    $agg = $columnAggByKey->get($key);
                    $alreadyApplied = $agg
                        && (int) $agg->cnt > 0
                        && (int) $agg->cnt_neutral === 0
                        && (int) $agg->cnt_categorized > 0;
                    $categoryButton = $alreadyApplied
                        ? '<button type="button" class="btn btn-xs btn-secondary" disabled title="Kategori kolom sudah diterapkan"><i class="fas fa-check"></i></button>'
                        : '<button type="button" class="btn btn-xs btn-success btnColumnCategory" data-column="' . $columnCode . '" title="Set Kategori Kolom"><i class="fas fa-tags"></i></button>';

                    return '<div style="display:flex;gap:3px;flex-wrap:nowrap;justify-content:center;">'
                        . $categoryButton
                        . '<a href="' . $colQrUrl . '" class="btn btn-xs btn-success" title="Print QR Kolom"><i class="fas fa-qrcode"></i></a>'
                        . '</div>';
                }
                $stockUrl = route('location.cells.stock', $row->id);
                $editUrl  = route('location.cells.edit', $row->id);
                $html  = '<div style="display:flex;gap:3px;flex-wrap:nowrap;justify-content:center;">';
                $html .= '<a href="' . $stockUrl . '" class="btn btn-xs btn-info" title="Lihat Stok"><i class="fas fa-box"></i></a>';
                if (!auth()->user()->isOperator()) {
                    $html .= '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a>';
                    $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->code) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['level_label', 'lokasi', 'kapasitas', 'status_badge', 'action'])
            ->make(true);
    }
}
