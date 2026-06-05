<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Console\Command;

class RecategorizeItems extends Command
{
    protected $signature = 'items:recategorize
        {--dry-run : Preview perubahan tanpa menyimpan ke database}
        {--apply   : Terapkan perubahan ke database}
        {--detail  : Tampilkan setiap item yang dipindah (verbose)}';

    protected $description = 'Recategorize item spare part berdasarkan keyword rules dari nama item';

    // ── Target nama & warna kategori ─────────────────────────────────────────
    protected array $categoryUpdates = [
        'CAT-01' => ['name' => 'Bearing, Seal & Suku Cadang', 'color' => '#795548',
                     'desc' => 'Bearing semua jenis, mechanical seal, oil seal, O-ring, suku cadang umum'],
        'CAT-02' => ['name' => 'Elektrikal & Otomasi',        'color' => '#1565C0',
                     'desc' => 'Kabel, lampu, MCB, kontaktor, inverter, sensor, PLC, motor listrik'],
        'CAT-03' => ['name' => 'Perpipaan & Fitting',         'color' => '#37474F',
                     'desc' => 'Valve, elbow, flange, camlock, fitting pipa, hose connector'],
        'CAT-04' => ['name' => 'Transmisi & Penggerak',       'color' => '#E65100',
                     'desc' => 'Belt, chain, coupling, gear, pulley, sprocket'],
        'CAT-05' => ['name' => 'Forklift & Kendaraan',        'color' => '#F57F17',
                     'desc' => 'Spare part forklift, ban kendaraan, filter kendaraan'],
        'CAT-06' => ['name' => 'Pneumatik & Hidraulik',       'color' => '#006064',
                     'desc' => 'Silinder pneumatik, solenoid valve, regulator, filter kompresor'],
        'CAT-07' => ['name' => 'Pompa & Diafragma',           'color' => '#1B5E20',
                     'desc' => 'Membran pompa, mechanical seal pompa, O/H kit pompa, crankshaft'],
        'CAT-08' => ['name' => 'Material & Fastener',         'color' => '#546E7A',
                     'desc' => 'Besi profil, baja, plat, baut, mur, as besi, raw material'],
        'CAT-09' => ['name' => 'Safety & APD',                'color' => '#B71C1C',
                     'desc' => 'Helm safety, sarung tangan, masker, goggle, sepatu safety, harness'],
        'CAT-10' => ['name' => 'Tools, Perkakas & Consumables', 'color' => '#4A148C',
                     'desc' => 'Kunci, tang, obeng, bor, oli, grease, chemicals, abrasif, alat tulis'],
    ];

    /**
     * Keyword rules diproses secara berurutan (prioritas atas = lebih dulu).
     * Item di-match ke aturan PERTAMA yang cocok (case-insensitive substring).
     * Item yang tidak cocok dengan rule apapun TIDAK dipindah.
     */
    protected function getRules(): array
    {
        return [

            // ── [1] Safety & APD — paling spesifik, prioritas tertinggi ───────
            'CAT-09' => [
                'safety helmet', 'safety boots', 'safety glasses',
                'sarung tangan', 'masker 3m', 'masker 8247', 'masker 9105',
                'masker 3 ply', 'masker kain putih', 'masker karbon', 'masker carbon',
                'ear plug', 'ear muff', 'face shield', 'chemical goggle',
                'full body harness', 'lanyard',
                'apron kain', 'apron kulit', 'apron light blue',
                'jas laboratorium', 'laboratorium cover all',
                'kerpus ninja', 'kerpus teknik',
                'cartridge 3m 3301', 'cartridge 3m 7007',
                'particulate holder 3m', 'filter 3m 7711',
                'back support belt', 'cattle pack biru',
                'fast trac suspension helmet', 'standard boots',
            ],

            // ── [2] Forklift & Kendaraan ───────────────────────────────────────
            'CAT-05' => [
                '6fd25', '8fd25', '5fd25', '7fd30', '6fd30',
                'jungheinrich', 'nichiyu', 'komatsu wa',
                'ban luar', 'ban dalam', 'ban mati', 'marset ban', 'solid tyre forklift',
                'busi 4 tak', 'busi honda win', 'busi mobil', 'glow plug',
                'engine mounting 6fd',
                'assy brake toyota', 'assy ring syncro', 'assy seal trans',
                'assy master coupling',
                'drag laker forklift',
                'brace double deep', 'buffer rubber etr', 'cek valve etr',
                'o/h kit boom', 'brake chamber',
                // model codes kendaraan (part no. forklift tanpa merk)
                '43421-', '43735-', '43760-', '45660-', '47405-',
                '04436-', '04451-', '04456-', '04653-', '04671-', '04676-',
                '31280-', 'loader wa 200', 'loader wa-200',
                'holden stater forklift', 'bendix stater forklift',
                'kipas mesin forklift', 'kuku macan 6fd',
                'holder rectifier 6fd', 'busi pemanas',
                'hose air cleaner toyota', 'hose high press pump',
                'hose pump to tank',
                'filter udara 6 fd', 'filter udara u/loader',
                'filter olie hyd', 'filter hidrolis toyota', 'filter hidrolis nichiu',
                'filter olie u/loader', 'filter olie tranmisi u/loader',
                'axle crown', 'o/h kit center pin',
                'o/h kit m.coup', 'o/h kit master coup',
            ],

            // ── [3] Pneumatik & Hidraulik ──────────────────────────────────────
            'CAT-06' => [
                'air cylinder', 'silinder pneumatic', 'air tack sdad',
                'air regulator', 'air combination', 'air filter regulator pneumatic',
                'air distribution valve', 'air valve assembly', 'air valve aodd',
                'solenoid valve', 'directional control valve',
                'compact cylinder', 'frl 1/',
                'air presure lgwa', 'air pdam',
                'coil selenoid valve', 'coil solenoid hidrolis',
                'hydraulic check valve lurus',
                'filter sparator', 'filter separator',
                'filter dush colector',
                'filter sparator compresor', 'filter udara compresor',
                'filter olie kaeser', 'filter olie compresor kaezer',
                'coupling compresor kaezer', 'coupling kaezer',
                'control line kit kompresor',
                'carbon remover kaeser', 'f.olie kaeser', 'f.udara kaeser',
                'filter sparator 2901', 'filter sparator compresor',
                'couple kompresor', 'coupling u/ compresor',
                'f.udara 1613', 'f.udara 6.4139',
                'filter udara kompresor puma', 'filter udara compresor kaezer',
            ],

            // ── [4] Pompa & Diafragma ──────────────────────────────────────────
            'CAT-07' => [
                'membran ', 'membrane ', 'membran karet', 'membran netune',
                'membran teflon', 'membran dosing',
                'mechanical seal', 'mecanical seal', 'mechanic seal',
                'crankshaft', 'dosing pump',
                'plunger assy',
                'pump+can', 'pump can', 'pump+lid',
                'piston kit-22', 'piston inner', 'piston outer', 'piston-22',
                'piston 22/', 'piston assy-22',
                'back up 0.5"', 'back up 1" blag', 'back up 1" sp',
                'back up 3" m-pump', 'back up aro 3"', 'back up santoprene',
                'back up sun paper', 'back up dia',
                'valve assy std viton', 'valve assy-22', 'valve handle assembly',
                'nozzle valve 4mm',
                'insetr can', 'insert can',
                'pin valves flow', 'cylinder body flo',
                'gasket set pompa', 'gasket cell ac',
                'cap-tube bush kit', 'cap tube bush',
                'bush-tm300 valve kit', 'bush kit caniser',
                'detent handle assy',
                'spring valve lever return',
                'locking arm', 'low arm assembly',
            ],

            // ── [5] Transmisi & Penggerak ──────────────────────────────────────
            'CAT-04' => [
                'v-belt', 'v belt a-', 'v belt b-',
                'tooth belt', 'belt elevator',
                'conveyor belt', 'belt conveyor', 'belt pvc',
                'belt 50x1330', 'belt carton erector',
                'chain coupling', 'chain copling', 'roller chain',
                'flexible coupling jaw', 'spider insert coupling',
                'karet coupling', 'karet copling',
                'bevel gear', 'gear pinion', 'crown gear', 'small crown gear',
                'tooth pulley', 'gearmotor', 'kamprat rantai',
                'gear 30 z ', 'gear 60 z ',
                'blocker 2" best pack', 'belt dressing',
            ],

            // ── [6] Bearing & Seal (fokus ke bearing & seal saja) ─────────────
            'CAT-01' => [
                'bearing ', 'pillow block bearing', 'square flanged ball bearing',
                'linier bearing', 'linear bearing', 'cebter bearing', 'center bearing',
                'bearing release', 'asm cable encoder bearing',
                'oil seal tc', 'oil seal',
                'o-ring ', 'o ring', 'o/ring',
            ],

            // ── [7] Elektrikal & Otomasi ───────────────────────────────────────
            'CAT-02' => [
                'kabel nyaf', 'kabel nyy', 'kabel nyyhy', 'kabel awg', 'kabel vga',
                'kabel gas', 'kabel anti panas', 'cable welding olflex',
                'lampu led', 'lampu mercury', 'lampu philips', 'lampu tl', 'lampu uv',
                'lampu leutech', 'lampu exproff', 'lampu warm', 'lampu day light',
                'balak lampu', 'down light panasonic',
                'mcb ', 'nfb ', 'mccb ', 'breaker schneider', 'breaker siemens',
                'breaker mccb', 'breaker lv4', 'circuit breker', 'circuit protector',
                'breaker telemekanik', 'chint electromagnet',
                'capasitor', 'capacitor',
                'kontaktor', 'contactor tesys', 'contactor ilh', 'contactor palet',
                'magnetic cont.sn', 'magnetic contactor',
                'reversing contactor', 'cam starter',
                'carbon brush',
                'load cell',
                'power supply', 'power suply',
                'trafo matsuyama', 'trafo ballast', 'trafo ',
                'transformer',
                'relay omron', 'timer twin', 'dual timer',
                'axial fan', 'exhaust fan',
                'kap lampu', 'fitting lampu', 'fitting mercury', 'ballast osram',
                'accu 6v', 'accu gs', 'accu forlkift', 'accu n200', 'accu ns', 'accu sepeda',
                'baterai vrla', 'bateray ups', 'batrai timbangan', 'battery dry cell',
                'batre emergancy', 'bateray etr',
                'inverter 1 hp', 'inverter 11 kw', 'inverter 200 hp',
                'inverter 22 kw', 'inverter 37 kw', 'inverter 5.5kw',
                'inverter 55 kw', 'inverter merk invt',
                'dinamo ampere', 'dinamo stater', 'dynamo ampere',
                'monitor lcd', 'cpu dualcore', 'motherboard', 'keyboard cpu', 'keyboard wyse',
                'flash disk', 'display board', 'door sensor', 'smart label printer',
                'modem huawei', 'cctv ahd',
                'fiber sensor', 'amplifier fiber', 'autonies sensor',
                'limit switch', 'limit swith',
                'plc ', 'siemens 6es', 'melsec fx', 'cpu 1510',
                'ai module 6es', 'ao module 6es', 'base unit io type',
                'bus adapter et200',
                'analog input 4 channel siemens', 'analog output 2 channel siemens',
                'contact block', 'aux switch block',
                'busbar sisir', 'busbar tembaga 20x',
                'busbar ground tinned', 'busbar untuk power',
                'tembaga busbar', 'plat tembaga busbar',
                'amper meter', 'amplimeter',
                'ct 100', 'ct 150 / 5', 'ct 300 a', 'ct 400 a', 'ct 50 a',
                'cos 500a socomec',
                'ic regulator + diode',
                'coil selenoid valve dmc', 'coil solenoid hidrolis yuken',
                'contactor jyro',
                'aligator clip jepit accu',
                'dc18 wb', 'baterai bor cas makita', 'battery milwaukee',
                'battery pack', 'milwaukee m18',
                'adaptor timbangan', 'adaptor charger timbangan',
                'adaptor and tb-', 'adaptor led strip', 'adaptor control',
                'ac adapter nbs', 'ac fused inlet',
                'adapter nbs',
                'ac 1/2 pk', 'ac mitsubishi', 'ac 1.5 pk',
                'alarm bell',
                'activator pepperl',
            ],

            // ── [8] Perpipaan & Fitting ────────────────────────────────────────
            'CAT-03' => [
                'ball valve', 'butterfly valve', 'gate valve', 'check valve 4',
                'check valve angin', 'check valve swing', 'knife gate',
                'ball matic',
                'elbow galv', 'elbow las galv', 'elbow las ss', 'elbow las 6"', 'elbow pvc',
                'elbow galv 1½"', 'elbow galv 1¼',
                'knee galv', 'knee pvc', 'knee drat', 'knee ss',
                'flange pvc', 'flendes', 'blind flange',
                'camlock kuningan', 'camlock ss dia',
                'double nipple galv', 'doubel nepel galv',
                'nepel air kuningan', 'double n. galv',
                'pipe clamp 2 1/2',
                'sanitary ferrule', 'clamp ferrule sanitary',
                'flex.joint', 'flexibel join',
                'bending pipa ss',
                'dop galv', 'dop pvc',
                'air distribution valve assembly blag',
                'black 4"x1500mm',
                'armaflex', 'insulasi armaflex',
            ],

            // ── [9] Material & Fastener ────────────────────────────────────────
            'CAT-08' => [
                'besi behel', 'besi beton', 'besi cor dia',
                'besi hollow galvalum', 'besi wf sni', 'besi wr 250',
                'besi unp sni', 'besi plat dia',
                'baja assab', 'baja hss', 'baja k2379', 'baja 2510', 'baja ringan c75',
                'atap spandek', 'atap transparan kr10',
                'alumunium profil 40x80', 'alumunium bracket corner siku',
                'alumunium foil', 'alumunium roll 50m',
                'cnp 100x50',
                'as besi dia', 'as besi ss304', 'as besi sus',
                'as vcn ', 'as vcn ø', 'as vitalcn',
                'as stainless', 'as stanles',
                'as pe putih dia', 'as segi enam dia',
                'as teflon dia', 'as teflon pa diameter', 'as teflon pa 116',
                'as teflon ptfe', 'as tembaga', 'as kuningan', 'as rod dia',
                'as carbon dia', 'as bronze dia', 'as brown 6.5"',
                'as hexagonal dia', 'as k 100',
                'ask baja dia',
                'baut drilling sds', 'baut drilling s12', 'baut drilling s8',
                'baut hexagonal flange m',
                'baut l galv', 'baut l m10', 'baut l m8', 'baut l ss m',
                'baut l stainles', 'baut l galvanis',
                'baut m12x', 'baut m6x', 'baut m5x', 'baut philip m8',
                'baut roda belakang', 'baut roda forklift', 'baut roda truk',
                'baut+mur grade', 'baut+mur m', 'baut ss m20',
                'baut bm hex', 'baut hexa m5', 'baut pengunci manhole',
                'baut self drilling screw 12x20',
                'baut t bolt hammer screw',
                'baut roofing', 'baut jp m',
                'dynabolt', 'angkur l m20', 'angkur m16',
                'mur hex m10', 'mur+baut+ring',
                'siku angle bracket 40',
                'bolt l8 x 20', 'bolt nut (bronze)',
                'flooring baut',
                'akhrilik', 'akrilik tebal',
                'impra board 3mm', 'dr.houz char coal',
                'beton fast track mutu',
                'letter timbul galvanis',
                'atap transparan kr10',
                'besi wf sni 200x100',
                'as kingpin forklift',
                'baut gardan samping forklift',
                'baut tanam roda depan',
            ],

            // ── [10] Tools, Perkakas & Consumables ────────────────────────────
            'CAT-10' => [
                // Hand tools
                'kunci bintang pendek', 'kunci inggris', 'kunci l pendek',
                'kunci pas ring', 'kunci ring pas set', 'kunci pintu',
                'obeng (-)', 'obeng (+)', 'obeng tes pen', 'obeng / pembuka kaleng',
                'tang clamping', 'tang pembuka pail', 'tang snap ring', 'tang universal',
                'palu karet',
                'toolskit', 'tools box', 'kunci bintang',
                // Power tools
                'gerinda makita 9553', 'bor dc bosch', 'bor m18 fpd', 'bor bosch cordless',
                'bor gerinda 2uul',
                'milwaukee m18 redlithium',
                // Measurement & Test
                'digital caliper', 'adjustable hook spanner',
                'sata combination rache', 'magnetic stearer',
                // Hoisting
                'chain block', 'chain hoist blok', 'chain hook',
                // Lubricants
                'gemuk pertamina', 'grease shell alvania',
                'cartridge grease memolub', 'cartridge memolub',
                'oli pelumas mesin',
                // Gases & Chemicals
                'acytelene', 'argon ',
                'alkohol 70', 'aquades', 'air murni',
                'caustic soda', 'soda ash',
                'alumunium sulfate', 'anti klorine r10',
                'chlorine', 'hcl', 'naoh padat',
                'bakteri biotank',
                'buffer solution hanna', 'buffered peptone water',
                'bel cleane', 'chemical x-rein',
                'boiler mate', 'boilermates',
                'additive 5191', 'make up @ 1 lt 1006',
                // Cleaning & Maintenance
                'contac cleaner', 'cleaning 5100',
                'carbon remover ', 'chain lube',
                'air radiator coolant',
                // Abrasives
                'kertas gosok', 'amplas besi rol', 'amplas bulat',
                'mata gerinda amplas', 'mata gerinda potong', 'mata gerinda poles sponge',
                'batu gerinda', 'batu hijau langsol',
                'wd batu gerinda', 'mata jigsaw',
                'gergaji s.400', 'gergaji sandflex',
                'ceramic sandblasting nozzle',
                // Tapes & Sealing
                'lakban kuning', 'lakban merah', 'lakban putih',
                'isolasi listrik', 'isolasi double tape', 'isolasi scot',
                // Office / Stationery
                'amplop', 'bolpoin', 'buku tulis',
                'kertas ukuran a4', 'kertas ukuran f4',
                'kertas karton ukuran a4', 'kertas tarik polos', 'kertas faxs',
                'isi staples', 'isi cutter', 'clip board', 'binder clips',
                'business file a4', 'business file f4', 'box file uk',
                // Misc consumables
                'alat pel lantai', 'dorongan air karet',
                'cool box nesco', 'dongle solo prima',
                'stiker av.mesin', 'stiker cutting vinyl',
                'cutting sticker jyro',
                'corong talang galvalum',
                'biuged aplikator',
                'air accu kemasan', 'air zur accu',
                'snap ring', 'cotter pin baja',
                'alat pembuka drum', 'alat pembuka kaleng',
                'pail clamper', 'pembuka pail plastik', 'tang pembuka pail',
                'beaker glass', 'buret iwaki', 'buret volume', 'labu leher',
                'dropping funnel', 'flow table electric',
                'anemometer digital', 'anak batu timbangan', 'anak timbang',
            ],
        ];
    }

    public function handle(): int
    {
        $apply  = $this->option('apply');
        $dryRun = !$apply;
        $detail = $this->option('detail');

        $this->newLine();
        if ($dryRun) {
            $this->warn('  ▸ DRY RUN MODE tidak ada perubahan disimpan.');
            $this->warn('    Gunakan --apply untuk menerapkan perubahan.');
        } else {
            $this->info('  ▸ APPLY MODE perubahan akan disimpan ke database.');
        }
        $this->newLine();

        // ── STEP 1: Update nama kategori ─────────────────────────────────────
        $this->info('══════════════════════════════════════════════════');
        $this->info('  STEP 1 — Update nama & warna kategori');
        $this->info('══════════════════════════════════════════════════');

        foreach ($this->categoryUpdates as $code => $data) {
            $cat = ItemCategory::where('code', $code)->first();
            if (!$cat) {
                $this->error("  $code tidak ditemukan!");
                continue;
            }
            $this->line(sprintf('  %-8s %-32s → <fg=green>%s</>', $code, '"' . $cat->name . '"', '"' . $data['name'] . '"'));
            if (!$dryRun) {
                $cat->update([
                    'name'        => $data['name'],
                    'description' => $data['desc'],
                    'color_code'  => $data['color'],
                ]);
            }
        }

        $this->newLine();

        // ── STEP 2: Keyword-based recategorize ───────────────────────────────
        $this->info('══════════════════════════════════════════════════');
        $this->info('  STEP 2 — Recategorize item berdasarkan keyword');
        $this->info('══════════════════════════════════════════════════');

        $catMap = ItemCategory::pluck('id', 'code')->toArray();
        $rules  = $this->getRules();
        $items  = Item::with('category')->get();

        $moves   = [];
        $nomatch = 0;

        foreach ($items as $item) {
            $nameLower = mb_strtolower($item->name);
            $matched   = null;

            foreach ($rules as $catCode => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($nameLower, mb_strtolower($kw))) {
                        $matched = $catCode;
                        break 2;
                    }
                }
            }

            if (!$matched || !isset($catMap[$matched])) {
                $nomatch++;
                continue;
            }

            $newCatId   = $catMap[$matched];
            $oldCatCode = optional($item->category)->code ?? '?';

            if ($item->category_id === $newCatId) continue;

            $moves[] = [
                'item_id'    => $item->id,
                'item_name'  => $item->name,
                'from'       => $oldCatCode,
                'to'         => $matched,
                'new_cat_id' => $newCatId,
            ];

            if (!$dryRun) {
                $item->category_id = $newCatId;
                $item->save();
            }
        }

        // ── STEP 3: Ringkasan ─────────────────────────────────────────────────
        $this->newLine();
        $this->info('══════════════════════════════════════════════════');
        $this->info('  STEP 3 — Ringkasan perubahan');
        $this->info('══════════════════════════════════════════════════');

        $grouped = collect($moves)->groupBy(fn($m) => $m['from'] . ' → ' . $m['to']);

        $tableRows = $grouped->map(function ($group, $key) {
            [$from, $to] = explode(' → ', $key);
            return [$from, $to, $group->count()];
        })->sortBy(0)->values()->toArray();

        $this->table(['Dari', 'Ke', 'Jumlah Item'], $tableRows);

        $this->newLine();
        $this->line('  Total item yang DIPINDAH : <fg=yellow>' . count($moves) . '</>');
        $this->line('  Item tidak ter-match     : <fg=cyan>' . $nomatch . '</> (tetap di kategori semula)');

        // Detail verbose
        if ($detail && count($moves) > 0) {
            $this->newLine();
            $this->info('  ── Detail perubahan ──');
            foreach ($moves as $m) {
                $this->line(sprintf('  [%s → %s]  %s', $m['from'], $m['to'], $m['item_name']));
            }
        }

        // ── Distribusi akhir ──────────────────────────────────────────────────
        $this->newLine();
        $this->info('══════════════════════════════════════════════════');
        $this->info('  Distribusi item per kategori (SETELAH migrasi)');
        $this->info('══════════════════════════════════════════════════');

        // Hitung distribusi akhir dari data saat ini + moves
        $finalDist = Item::selectRaw('category_id, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id')
            ->toArray();

        // Proyeksikan moves ke distribusi final (untuk dry run)
        if ($dryRun) {
            foreach ($moves as $m) {
                $oldCatId = $catMap[$m['from']] ?? null;
                $newCatId = $m['new_cat_id'];
                if ($oldCatId && isset($finalDist[$oldCatId])) {
                    $finalDist[$oldCatId]--;
                }
                $finalDist[$newCatId] = ($finalDist[$newCatId] ?? 0) + 1;
            }
        }

        $cats = ItemCategory::orderBy('code')->get();
        $catUpdates = $this->categoryUpdates;
        $distRows = $cats->map(function ($cat) use ($finalDist, $catUpdates) {
            $count = $finalDist[$cat->id] ?? 0;
            $catData = $catUpdates[$cat->code] ?? null;
            $name = $catData ? $catData['name'] : $cat->name;
            return [$cat->code, $name, $count];
        })->toArray();
        $this->table(['Kode', 'Nama Kategori', 'Jumlah Item'], $distRows);

        $this->newLine();
        if ($dryRun) {
            $this->warn('  Jalankan dengan --apply untuk menerapkan:');
            $this->line('    php artisan items:recategorize --apply');
            $this->line('    php artisan items:recategorize --apply --detail   (tampilkan setiap item)');
        } else {
            $this->info('  ✅ Recategorize selesai! Semua perubahan disimpan.');
        }
        $this->newLine();

        return 0;
    }
}
