<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\Item;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\ItemCategory;
use App\Services\CellCapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * StockController
 *
 * Menampilkan kondisi stok real-time hasil dari proses inbound + put-away.
 * Data bersumber dari:
 *   - stock_records   : posisi & qty barang per cell (FIFO)
 *   - stock_movements : riwayat setiap pergerakan barang
 *
 * Halaman:
 *   index()      → Stok Saat Ini (per item, aggregated dari semua cell)
 *   show()       → Detail stok 1 item (per cell/batch + riwayat mutasi)
 *   movements()  → Semua mutasi stok (inbound/outbound/transfer)
 *   lowStock()   → Barang di bawah minimum / reorder point
 *   nearExpiry() → Barang yang mendekati kadaluarsa
 */
class StockController extends Controller
{
    // =========================================================================
    // 1. STOK SAAT INI — index()
    //    Aggregate qty per item dari semua cell, tampilkan status vs min_stock
    // =========================================================================

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->indexDatatable($request);
        }

        $summary = [
            'total_skus'   => Stock::available()->distinct('item_id')->count('item_id'),
            'total_qty'    => Stock::available()->sum('quantity'),
            'total_cells'  => Stock::available()->where('quantity', '>', 0)
                                   ->distinct('cell_id')->count('cell_id'),
            'below_min'    => $this->countBelowMin(),
        ];

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $categories = ItemCategory::orderBy('name')->get();

        $defaultWarehouseId = $warehouses->firstWhere('code', 'WH-001')?->id
            ?? $warehouses->first(fn($w) => stripos($w->name, 'sparepart') !== false)?->id;

        return view('stock.index', compact('summary', 'warehouses', 'categories', 'defaultWarehouseId'));
    }

    private function indexDatatable(Request $request)
    {
        // Mulai dari items agar item tanpa stok pun tetap muncul (stok = 0)
        $query = DB::table('items')
            ->leftJoin('item_categories as cat', 'cat.id', '=', 'items.category_id')
            ->leftJoin('units', 'units.id', '=', 'items.unit_id')
            ->leftJoin(DB::raw('(SELECT sr.item_id, sr.warehouse_id, SUM(sr.quantity) as qty,
                                        COUNT(DISTINCT sr.cell_id) as loc_count,
                                        MIN(sr.inbound_date) as fifo_date,
                                        GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR "|") as cell_codes
                                 FROM stock_records sr
                                 LEFT JOIN cells c ON c.id = sr.cell_id
                                 WHERE sr.status = "available"
                                 GROUP BY sr.item_id, sr.warehouse_id) as stk'),
                'stk.item_id', '=', 'items.id')
            ->select([
                'items.id',
                'items.name',
                'items.sku',
                'items.erp_item_code',
                'items.min_stock',
                'items.max_stock',
                'items.reorder_point',
                'cat.name as category_name',
                'cat.color_code',
                'units.code as unit_code',
                DB::raw('COALESCE(SUM(stk.qty), 0) as total_qty'),
                DB::raw('COALESCE(SUM(stk.loc_count), 0) as locations_count'),
                DB::raw('MIN(stk.fifo_date) as fifo_date'),
                DB::raw("GROUP_CONCAT(DISTINCT stk.cell_codes ORDER BY stk.cell_codes SEPARATOR '|') as cell_codes"),
            ])
            ->where('items.is_active', true)
            ->groupBy(
                'items.id','items.name','items.sku','items.erp_item_code',
                'items.min_stock','items.max_stock','items.reorder_point',
                'cat.name','cat.color_code','units.code'
            )
            ->when($request->filled('category_id'),
                fn($q) => $q->where('items.category_id', $request->category_id))
            ->when($request->filled('warehouse_id'), fn($q) => $q->where(function ($q2) use ($request) {
                // Sertakan item dengan 0 qty (stk NULL dari LEFT JOIN) agar tetap tampil
                $q2->where('stk.warehouse_id', $request->warehouse_id)
                   ->orWhereNull('stk.warehouse_id');
            }))
            ->when($request->filled('status_filter'), function ($q) use ($request) {
                if ($request->status_filter === 'critical') {
                    $q->havingRaw('COALESCE(SUM(stk.qty), 0) <= items.min_stock AND items.min_stock > 0');
                } elseif ($request->status_filter === 'reorder') {
                    $q->havingRaw('COALESCE(SUM(stk.qty), 0) > items.min_stock AND COALESCE(SUM(stk.qty), 0) <= items.reorder_point AND items.reorder_point > 0');
                }
            })
            ->orderByRaw('COALESCE(SUM(stk.qty), 0) DESC')
            ;

        return DataTables::of($query)
            ->addIndexColumn()
            ->filterColumn('item_info', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('items.name', 'like', "%{$keyword}%")
                      ->orWhere('items.sku',  'like', "%{$keyword}%");
                });
            })
            ->addColumn('item_info', function ($row) {
                $erp = ($row->erp_item_code && $row->erp_item_code !== $row->sku)
                    ? '<br><small class="text-muted">ERP: ' . e($row->erp_item_code) . '</small>'
                    : '';
                return '<div class="font-weight-bold">' . e($row->name) . '</div>
                         <small class="text-muted">' . e($row->sku) . '</small>' . $erp;
            })
            ->addColumn('category_badge', function ($row) {
                if (!$row->category_name) return '<span class="text-muted">—</span>';
                $color = $row->color_code ?? '#6c757d';
                return '<span class="badge px-2" style="background:' . $color . ';color:#fff;font-size:11px;">'
                    . e($row->category_name) . '</span>';
            })
            ->addColumn('qty_display', function ($row) {
                $qty    = (int) $row->total_qty;
                $min    = (int) $row->min_stock;
                $reorder= (int) $row->reorder_point;
                $unit   = e($row->unit_code ?? '');
                if ($qty === 0) {
                    return '<span class="badge badge-danger">0</span>'
                         . '<small class="text-muted ml-1">' . $unit . '</small>';
                }
                $badge = '';
                if ($min > 0 && $qty <= $min) {
                    $badge = '<span class="badge badge-danger ml-1">Kritis</span>';
                } elseif ($reorder > 0 && $qty <= $reorder) {
                    $badge = '<span class="badge badge-warning ml-1">Reorder</span>';
                }
                return '<span class="font-weight-bold" style="font-size:14px;">'
                    . number_format($qty) . '</span>
                    <small class="text-muted ml-1">' . $unit . '</small>' . $badge;
            })
            ->addColumn('min_reorder', function ($row) {
                $min = $row->min_stock ? number_format($row->min_stock) : '—';
                $max = $row->max_stock ? number_format($row->max_stock) : '—';
                return '<small class="text-muted">Min: <strong>' . $min . '</strong></small><br>
                         <small class="text-muted">Max: <strong>' . $max . '</strong></small>';
            })
            ->addColumn('locations_display', function ($row) {
                if (!$row->cell_codes) return '<span class="text-muted">—</span>';
                $codes = explode('|', $row->cell_codes);
                $html  = '';
                foreach ($codes as $code) {
                    $html .= '<span class="badge badge-light border text-dark mr-1 mb-1" style="white-space:nowrap;">'
                           . '<i class="fas fa-map-marker-alt text-primary mr-1"></i>'
                           . e($code) . '</span>';
                }
                return $html;
            })
            ->addColumn('fifo_display', function ($row) {
                if (!$row->fifo_date) return '<span class="text-muted">—</span>';
                return '<small>' . \Carbon\Carbon::parse($row->fifo_date)->format('d M Y') . '</small>';
            })
            ->addColumn('action', function ($row) {
                return '<a href="' . route('stock.show', $row->id) . '"
                            class="btn btn-xs btn-info" title="Lihat lokasi & mutasi">
                            <i class="fas fa-search-location mr-1"></i>Detail
                        </a>';
            })
            ->rawColumns(['item_info', 'category_badge', 'qty_display',
                          'min_reorder', 'locations_display', 'fifo_display', 'action'])
            ->make(true);
    }

    // =========================================================================
    // 2. DETAIL STOK PER ITEM — show()
    //    Semua stock_records item ini per cell (FIFO), + riwayat mutasi via AJAX
    // =========================================================================

    public function show(Request $request, Item $item)
    {
        if ($request->ajax() && $request->type === 'movements') {
            return $this->itemMovementsDatatable($item);
        }

        $stocks = Stock::with(['cell.rack', 'warehouse'])
            ->where('item_id', $item->id)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('inbound_date', 'asc')   // FIFO
            ->orderBy('created_at', 'asc')
            ->get();

        $totalQty     = $stocks->sum('quantity');
        $cellCount    = $stocks->pluck('cell_id')->unique()->count();
        $oldestFifo   = $stocks->first()?->inbound_date;

        // Status stok vs parameter item
        $minStock     = (int) $item->min_stock;
        $maxStock     = (int) $item->max_stock;
        $reorderPoint = (int) $item->reorder_point;
        $stockStatus  = 'ok';
        if ($totalQty === 0)                                      $stockStatus = 'empty';
        elseif ($minStock > 0 && $totalQty <= $minStock)          $stockStatus = 'critical';
        elseif ($reorderPoint > 0 && $totalQty <= $reorderPoint)  $stockStatus = 'reorder';

        return view('stock.show', compact(
            'item', 'stocks', 'totalQty', 'cellCount',
            'oldestFifo', 'stockStatus', 'minStock', 'maxStock'
        ));
    }

    private function itemMovementsDatatable(Item $item)
    {
        $query = StockMovement::with([
                'fromCell.rack', 'toCell.rack',
                'performedBy', 'warehouse',
            ])
            ->where('item_id', $item->id)
            ->latest('moved_at');

        return DataTables::of($query)
            ->addColumn('date_display', fn($m) =>
                $m->moved_at ? $m->moved_at->format('d M Y, H:i') : '—'
            )
            ->addColumn('type_badge', function ($m) {
                $map = [
                    'inbound'  => ['success', 'fas fa-arrow-down',   'Masuk'],
                    'outbound' => ['danger',  'fas fa-arrow-up',     'Keluar'],
                    'transfer' => ['info',    'fas fa-exchange-alt', 'Transfer'],
                    'adjust'   => ['warning', 'fas fa-sliders-h',    'Penyesuaian'],
                ];
                [$cls, $ico, $lbl] = $map[$m->movement_type] ?? ['secondary', 'fas fa-circle', $m->movement_type];
                return '<span class="badge badge-' . $cls . '">
                            <i class="' . $ico . ' mr-1"></i>' . $lbl . '
                        </span>';
            })
            ->addColumn('location_display', function ($m) {
                $from = $m->fromCell ? '<strong>' . e($m->fromCell->code) . '</strong>' : '—';
                $to   = $m->toCell   ? '<strong>' . e($m->toCell->code)   . '</strong>' : '—';
                return match ($m->movement_type) {
                    'inbound'  => '<i class="fas fa-arrow-down text-success mr-1"></i>ke ' . $to,
                    'outbound' => '<i class="fas fa-arrow-up text-danger mr-1"></i>dari ' . $from,
                    default    => $from . ' <i class="fas fa-long-arrow-alt-right text-info mx-1"></i> ' . $to,
                };
            })
            ->addColumn('qty_display', fn($m) =>
                '<span class="font-weight-bold">' . number_format($m->quantity) . '</span>'
            )
            ->addColumn('by_display', fn($m) =>
                e($m->performedBy?->name ?? 'Sistem')
            )
            ->rawColumns(['type_badge', 'location_display', 'qty_display'])
            ->make(true);
    }

    // =========================================================================
    // 3. MUTASI STOK — movements()
    //    Semua pergerakan stok dengan filter tipe, gudang, dan rentang tanggal
    // =========================================================================

    public function movements(Request $request)
    {
        if ($request->ajax()) {
            return $this->movementsDatatable($request);
        }

        $summary = [
            'today_in'    => StockMovement::where('movement_type', 'inbound')->today()->sum('quantity'),
            'today_out'   => StockMovement::where('movement_type', 'outbound')->today()->sum('quantity'),
            'today_trans' => StockMovement::where('movement_type', 'transfer')->today()->count(),
            'total_today' => StockMovement::today()->count(),
        ];

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        return view('stock.movements', compact('summary', 'warehouses'));
    }

    private function movementsDatatable(Request $request)
    {
        $query = StockMovement::with([
                'item.unit', 'item.category',
                'fromCell.rack', 'toCell.rack',
                'performedBy', 'warehouse',
            ])
            ->when($request->filled('type'),
                fn($q) => $q->where('movement_type', $request->type))
            ->when($request->filled('warehouse_id'),
                fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date_from'),
                fn($q) => $q->whereDate('moved_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),
                fn($q) => $q->whereDate('moved_at', '<=', $request->date_to))
            ->latest('moved_at');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('date_display', fn($m) =>
                $m->moved_at ? $m->moved_at->format('d M Y, H:i') : '—'
            )
            ->orderColumn('date_display', 'moved_at $1')
            ->addColumn('item_info', function ($m) {
                if (!$m->item) return '<span class="text-muted">—</span>';
                return '<div class="font-weight-bold">' . e($m->item->name) . '</div>
                         <small class="text-muted">' . e($m->item->sku) . '</small>';
            })
            ->addColumn('type_badge', function ($m) {
                $map = [
                    'inbound'  => ['success', 'fas fa-arrow-down',   'Masuk'],
                    'outbound' => ['danger',  'fas fa-arrow-up',     'Keluar'],
                    'transfer' => ['info',    'fas fa-exchange-alt', 'Transfer'],
                    'adjust'   => ['warning', 'fas fa-sliders-h',   'Penyesuaian'],
                ];
                [$cls, $ico, $lbl] = $map[$m->movement_type] ?? ['secondary', 'fas fa-circle', $m->movement_type];
                return '<span class="badge badge-' . $cls . '">
                            <i class="' . $ico . ' mr-1"></i>' . $lbl . '
                        </span>';
            })
            ->addColumn('from_display', fn($m) =>
                $m->fromCell ? e($m->fromCell->code) : '<span class="text-muted">—</span>'
            )
            ->addColumn('to_display', fn($m) =>
                $m->toCell ? e($m->toCell->code) : '<span class="text-muted">—</span>'
            )
            ->addColumn('location_display', function ($m) {
                $chip = fn(?string $code) => $code
                    ? '<span style="color:#212529;">' . e($code) . '</span>'
                    : '<span class="text-muted">—</span>';
                $from = $chip($m->fromCell?->code);
                $to   = $chip($m->toCell?->code);
                return match ($m->movement_type) {
                    'inbound'  => '<i class="fas fa-arrow-down text-success mr-1"></i>' . $to,
                    'outbound' => '<i class="fas fa-arrow-up text-danger mr-1"></i>' . $from,
                    default    => $from . ' <i class="fas fa-long-arrow-alt-right text-info mx-1"></i> ' . $to,
                };
            })
            ->addColumn('qty_display', function ($m) {
                $sign = match ($m->movement_type) {
                    'inbound'  => '+',
                    'outbound' => '−',
                    default    => '',
                };
                return '<span class="font-weight-bold">'
                    . $sign . number_format($m->quantity) . '</span>
                    <small class="text-muted"> ' . e($m->item?->unit?->code ?? '') . '</small>';
            })
            ->addColumn('by_display', fn($m) =>
                e($m->performedBy?->name ?? 'Sistem')
            )
            ->addColumn('notes_display', fn($m) =>
                $m->notes
                    ? '<small class="text-muted">' . e($m->notes) . '</small>'
                    : '<span class="text-muted">—</span>'
            )
            ->rawColumns(['item_info', 'type_badge', 'from_display', 'to_display', 'location_display', 'qty_display', 'notes_display'])
            ->make(true);
    }

    // =========================================================================
    // 4. STOK KRITIS — lowStock()
    //    Item di bawah min_stock atau reorder_point, urut paling kritis dulu
    // =========================================================================

    public function lowStock(Request $request)
    {
        $type = $request->input('type', 'all');
        $type = in_array($type, ['all', 'empty', 'low', 'reorder'], true) ? $type : 'all';
        $qtySubSql = '(SELECT COALESCE(SUM(sr.quantity),0) FROM stock_records sr
                       WHERE sr.item_id = items.id AND sr.status = "available")';

        $baseQuery = Item::with(['category', 'unit'])
            ->select('items.*')
            ->selectRaw("{$qtySubSql} as current_stock")
            ->where('is_active', true)
            ->where('min_stock', '>', 0);

        $summaryItems = (clone $baseQuery)->get();

        $items = (clone $baseQuery)
            ->when($type === 'empty', fn($q) => $q->whereRaw("{$qtySubSql} = 0"))
            ->when($type === 'low', fn($q) => $q
                ->whereRaw("{$qtySubSql} > 0")
                ->whereRaw("{$qtySubSql} < items.min_stock"))
            ->when($type === 'reorder', fn($q) => $q
                ->where('reorder_point', '>', 0)
                ->whereRaw("{$qtySubSql} >= items.min_stock")
                ->whereRaw("{$qtySubSql} <= items.reorder_point"))
            ->when($type === 'all', fn($q) => $q
                ->where(function ($q) use ($qtySubSql) {
                    $q->whereRaw("{$qtySubSql} = 0")
                        ->orWhereRaw("{$qtySubSql} < items.min_stock")
                        ->orWhere(function ($q2) use ($qtySubSql) {
                            $q2->where('reorder_point', '>', 0)
                                ->whereRaw("{$qtySubSql} <= items.reorder_point");
                        });
                }))
            ->orderByRaw("{$qtySubSql} ASC")
            ->get();

        $summary = [
            'empty'    => $summaryItems->where('current_stock', 0)->count(),
            'critical' => $summaryItems->filter(fn($i) => $i->current_stock > 0
                                && $i->current_stock <= $i->min_stock)->count(),
            'reorder'  => $summaryItems->filter(fn($i) => $i->current_stock > $i->min_stock
                                && $i->current_stock <= $i->reorder_point)->count(),
        ];

        return view('stock.low-stock', compact('items', 'summary', 'type'));
    }

    public function deadstock(Request $request)
    {
        $days = max(1, (int) $request->input('days', 90));

        $stocks = Stock::with(['item.category', 'item.unit', 'cell.rack', 'warehouse'])
            ->deadstock($days)
            ->orderByRaw('COALESCE(last_moved_at, inbound_date) ASC')
            ->get();

        $summary = [
            'sku_count' => $stocks->pluck('item_id')->unique()->count(),
            'record_count' => $stocks->count(),
            'total_qty' => $stocks->sum('quantity'),
            'days' => $days,
        ];

        return view('stock.deadstock', compact('stocks', 'summary', 'days'));
    }

    // =========================================================================
    // 5. NEAR EXPIRY — nearExpiry()
    //    Stock records dengan expiry_date dalam X hari ke depan (default 30)
    // =========================================================================

    public function nearExpiry(Request $request)
    {
        $days = (int) ($request->days ?? 30);
        $days = in_array($days, [7, 30, 60, 90]) ? $days : 30;

        $stocks = Stock::with(['item.category', 'item.unit', 'cell.rack', 'warehouse'])
            ->available()
            ->whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($s) {
                $s->days_remaining = (int) now()->startOfDay()
                    ->diffInDays($s->expiry_date->startOfDay(), false);
                return $s;
            });

        $summary = [
            'expired'  => $stocks->where('days_remaining', '<', 0)->count(),
            'today'    => $stocks->where('days_remaining', 0)->count(),
            'this_week'=> $stocks->filter(fn($s) => $s->days_remaining >= 1
                                && $s->days_remaining <= 7)->count(),
            'this_month'=> $stocks->filter(fn($s) => $s->days_remaining >= 8
                                && $s->days_remaining <= 30)->count(),
        ];

        return view('stock.near-expiry', compact('stocks', 'summary', 'days'));
    }

    // =========================================================================
    // 6. TRANSFER STOK — transfer()
    //    Pindahkan qty dari satu cell ke cell lain (admin/supervisor)
    // =========================================================================

    public function transferScan()
    {
        return view('stock.transfer-scan');
    }

    public function transferScanCell(Request $request): \Illuminate\Http\JsonResponse
    {
        $code = $this->normalizeScanCode((string) $request->input('code', ''));
        $purpose = $request->input('purpose', 'source') === 'target' ? 'target' : 'source';
        if ($code === '') {
            return response()->json(['found' => false, 'message' => 'Kode cell tidak boleh kosong.'], 422);
        }

        $cell = Cell::with(['rack.warehouse'])
            ->where(function ($q) use ($code) {
                $q->where('code', $code)
                    ->orWhere('qr_code', $code)
                    ->orWhere('label', $code);
            })
            ->where('is_active', true)
            ->first();

        if ($cell) {
            $hasStock = Stock::where('cell_id', $cell->id)
                ->where('status', 'available')
                ->where('quantity', '>', 0)
                ->exists();

            if (!$hasStock) {
                $childCells = Cell::with(['rack.warehouse'])
                    ->where('code', 'like', $code . '-%')
                    ->where('is_active', true)
                    ->get();

                if ($childCells->isNotEmpty()) {
                    if ($purpose === 'source') {
                        return $this->transferScanColumnPayload($code, $childCells);
                    }
                    // Target: tampilkan pilihan baris ke operator
                    $stock = Stock::find($request->input('stock_id'));
                    $options = $childCells
                        ->when($stock, fn($col) => $col->reject(fn(Cell $c) => $c->id === $stock?->cell_id))
                        ->map(fn(Cell $c) => [
                            'id'                 => $c->id,
                            'code'               => $c->code,
                            'qr_code'            => $c->qr_code ?? $c->code,
                            'baris'              => $c->baris,
                            'status'             => $c->status,
                            'capacity_remaining' => $this->remainingTransferCapacity($c),
                            'capacity_max'       => (int) $c->capacity_max,
                        ])
                        ->sortBy(fn($c) => $c['capacity_remaining'] === 0 ? 1 : 0) // full cells last
                        ->values();
                    return response()->json([
                        'found'             => true,
                        'is_column_target'  => true,
                        'column_code'       => strtoupper($code),
                        'child_cells'       => $options,
                    ]);
                }
            }

            return $this->transferScanCellPayload($cell);
        }

        $rackParts = $this->parseMspartRackCode($code);
        if (!$rackParts) {
            return response()->json(['found' => false, 'message' => "Cell atau rak {$code} tidak ditemukan atau tidak aktif."], 404);
        }

        if ($purpose === 'target') {
            return $this->transferScanTargetRackPayload($rackParts['blok'], $rackParts['grup'], $request);
        }

        return $this->transferScanSourceRackPayload($rackParts['blok'], $rackParts['grup']);
    }

    private function transferScanCellPayload(Cell $cell, ?string $resolvedFromRack = null): \Illuminate\Http\JsonResponse
    {
        $stocks = Stock::with(['item.unit', 'item.category', 'cell'])
            ->where('cell_id', $cell->id)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('inbound_date')
            ->orderBy('created_at')
            ->get();

        $remaining = $this->remainingTransferCapacity($cell);
        $used = max(0, (int) $cell->capacity_max - $remaining);

        return response()->json([
            'found' => true,
            'cell' => [
                'id' => $cell->id,
                'code' => $cell->code,
                'label' => $cell->label ?? $cell->code,
                'status' => $cell->status,
                'rack' => $cell->rack?->code ?? '-',
                'warehouse' => $cell->rack?->warehouse?->name ?? '-',
                'capacity_max' => (int) $cell->capacity_max,
                'capacity_used' => $used,
                'capacity_remaining' => $remaining,
                'capacity_unit' => 'unit kapasitas',
                'stock_count' => $stocks->count(),
                'resolved_from_rack' => $resolvedFromRack,
            ],
            'stocks' => $stocks->map(fn (Stock $stock) => $this->transferScanStockPayload($stock))->values(),
        ]);
    }

    private function transferScanSourceRackPayload(int $blok, string $grup): \Illuminate\Http\JsonResponse
    {
        $cells = Cell::where('blok', $blok)
            ->whereRaw('UPPER(grup) = ?', [strtoupper($grup)])
            ->where('is_active', true)
            ->get();

        if ($cells->isEmpty()) {
            return response()->json(['found' => false, 'message' => "Rak {$blok}-{$grup} tidak ditemukan atau tidak aktif."], 404);
        }

        $cellIds = $cells->pluck('id');
        $stocks = Stock::with(['item.unit', 'item.category', 'cell'])
            ->whereIn('cell_id', $cellIds)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('cell_id')
            ->orderBy('inbound_date')
            ->orderBy('created_at')
            ->get();

        $capacityMax = (int) $cells->sum('capacity_max');
        $usedPoints = (int) $cells->sum(fn(Cell $cell) => app(CellCapacityService::class)->usedPoints($cell));

        return response()->json([
            'found' => true,
            'cell' => [
                'id' => null,
                'code' => strtoupper("{$blok}-{$grup}"),
                'label' => strtoupper("{$blok}-{$grup}") . ' (Rak)',
                'status' => 'rack',
                'rack' => strtoupper("{$blok}-{$grup}"),
                'warehouse' => '-',
                'capacity_max' => $capacityMax,
                'capacity_used' => $usedPoints,
                'capacity_remaining' => max(0, $capacityMax - $usedPoints),
                'capacity_unit' => 'unit kapasitas',
                'stock_count' => $stocks->count(),
                'is_rack_scan' => true,
            ],
            'stocks' => $stocks->map(fn (Stock $stock) => $this->transferScanStockPayload($stock))->values(),
        ]);
    }

    private function transferScanColumnPayload(string $columnCode, \Illuminate\Support\Collection $cells): \Illuminate\Http\JsonResponse
    {
        $cellIds = $cells->pluck('id');
        $stocks  = Stock::with(['item.unit', 'item.category', 'cell'])
            ->whereIn('cell_id', $cellIds)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('cell_id')
            ->orderBy('inbound_date')
            ->orderBy('created_at')
            ->get();

        $capacityMax = (int) $cells->sum('capacity_max');
        $usedPoints  = (int) $cells->sum(fn(Cell $c) => app(CellCapacityService::class)->usedPoints($c));

        return response()->json([
            'found' => true,
            'cell'  => [
                'id'                 => null,
                'code'               => strtoupper($columnCode),
                'label'              => strtoupper($columnCode) . ' (Kolom)',
                'status'             => 'column',
                'rack'               => strtoupper($columnCode),
                'warehouse'          => $cells->first()?->rack?->warehouse?->name ?? '-',
                'capacity_max'       => $capacityMax,
                'capacity_used'      => $usedPoints,
                'capacity_remaining' => max(0, $capacityMax - $usedPoints),
                'capacity_unit'      => 'unit kapasitas',
                'stock_count'        => $stocks->count(),
                'is_rack_scan'       => true,
            ],
            'stocks' => $stocks->map(fn(Stock $stock) => $this->transferScanStockPayload($stock))->values(),
        ]);
    }

    private function transferScanTargetRackPayload(int $blok, string $grup, Request $request): \Illuminate\Http\JsonResponse
    {
        $stock = Stock::with(['cell', 'item'])->find($request->input('stock_id'));
        if (!$stock) {
            return response()->json(['found' => false, 'message' => 'Scan item dan qty dulu sebelum scan rak tujuan.'], 422);
        }

        $rackCode = strtoupper("{$blok}-{$grup}");
        $cells = Cell::with('rack.warehouse')
            ->where('blok', $blok)
            ->whereRaw('UPPER(grup) = ?', [strtoupper($grup)])
            ->where('is_active', true)
            ->get()
            ->filter(function (Cell $cell) use ($stock) {
                if ($cell->id === $stock->cell_id) {
                    return false;
                }

                $capacityDemand = $this->transferCapacityDemand($stock, $cell, 1);
                if (in_array($cell->status, ['blocked', 'reserved'], true)) {
                    return false;
                }
                if ($cell->status === 'full' && $capacityDemand > 0) {
                    return false;
                }

                return $capacityDemand <= $this->remainingTransferCapacity($cell);
            })
            ->sortByDesc(function (Cell $cell) use ($stock) {
                $sameSku = Stock::where('item_id', $stock->item_id)
                    ->where('cell_id', $cell->id)
                    ->where('quantity', '>', 0)
                    ->where('status', 'available')
                    ->exists();

                return sprintf('%d%05d', $sameSku ? 1 : 0, $this->remainingTransferCapacity($cell));
            });

        $cell = $cells->first();
        if (!$cell) {
            return response()->json(['found' => false, 'message' => "Rak {$rackCode} tidak punya cell tujuan yang cukup kapasitas."], 422);
        }

        return $this->transferScanCellPayload($cell, $rackCode);
    }

    private function transferScanStockPayload(Stock $stock): array
    {
        return [
                'stock_id' => $stock->id,
                'item_id' => $stock->item_id,
                'cell_id' => $stock->cell_id,
                'cell_code' => $stock->cell?->code ?? '-',
                'baris'     => $stock->cell?->baris,
                'sku' => $stock->item?->sku ?? '-',
                'erp_item_code' => $stock->item?->erp_item_code,
                'barcode' => $stock->item?->barcode,
                'name' => $stock->item?->name ?? '-',
                'category' => $stock->item?->category?->name ?? '-',
                'unit' => $stock->item?->unit?->code ?? 'unit',
                'quantity' => (int) $stock->quantity,
                'lpn' => $stock->lpn,
                'batch_no' => $stock->batch_no,
                'inbound_date' => $stock->inbound_date?->format('Y-m-d'),
        ];
    }

    public function transfer(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'stock_id'   => 'required|exists:stock_records,id',
            'to_cell_id' => 'required|exists:cells,id',
            'quantity'   => 'required|integer|min:1',
            'notes'      => 'nullable|string|max:255',
        ]);

        $stock  = Stock::with(['cell', 'item.unit', 'warehouse'])->findOrFail($validated['stock_id']);
        $toCell = Cell::with('rack')->findOrFail($validated['to_cell_id']);
        $qty    = (int) $validated['quantity'];

        if ($stock->status !== 'available') {
            return response()->json(['status' => 'error', 'message' => 'Stok tidak dalam status tersedia.'], 422);
        }
        if ($qty > $stock->quantity) {
            return response()->json(['status' => 'error', 'message' => "Qty transfer ({$qty}) melebihi stok tersedia ({$stock->quantity})."], 422);
        }
        if ($stock->cell_id === $toCell->id) {
            return response()->json(['status' => 'error', 'message' => 'Sel tujuan sama dengan sel asal.'], 422);
        }
        $capacityDemand = $this->transferCapacityDemand($stock, $toCell, $qty);
        if (!$toCell->is_active
            || in_array($toCell->status, ['blocked', 'reserved'])
            || ($toCell->status === 'full' && $capacityDemand > 0)
        ) {
            return response()->json(['status' => 'error', 'message' => "Sel {$toCell->code} tidak dapat menerima stok (status: {$toCell->status})."], 422);
        }
        $remCap = $this->remainingTransferCapacity($toCell);
        if ($capacityDemand > $remCap) {
            $unit = 'unit kapasitas';
            return response()->json(['status' => 'error', 'message' => "Kapasitas sel {$toCell->code} tidak cukup (tersisa: {$remCap} {$unit})."], 422);
        }

        try {
            DB::transaction(function () use ($stock, $toCell, $qty, $validated) {
                $fromCell    = $stock->cell;
                $itemId      = $stock->item_id;
                $warehouseId = $stock->warehouse_id;

                // a) Kurangi/hapus stock asal
                if ($qty === (int) $stock->quantity) {
                    $stock->delete();
                } else {
                    $stock->update(['quantity' => $stock->quantity - $qty, 'last_moved_at' => now()]);
                }

                // b) Tambah ke sel tujuan (merge jika sudah ada)
                $destStock = Stock::where('item_id', $itemId)
                    ->where('cell_id', $toCell->id)
                    ->where('status', 'available')
                    ->first();

                if ($destStock) {
                    $destStock->update(['quantity' => $destStock->quantity + $qty, 'last_moved_at' => now()]);
                } else {
                    Stock::create([
                        'item_id'               => $itemId,
                        'cell_id'               => $toCell->id,
                        'warehouse_id'          => $toCell->rack->warehouse_id ?? $warehouseId,
                        'inbound_order_item_id' => $stock->inbound_order_item_id,
                        'lpn'                   => $stock->lpn,
                        'batch_no'              => $stock->batch_no,
                        'quantity'              => $qty,
                        'inbound_date'          => $stock->inbound_date,
                        'expiry_date'           => $stock->expiry_date,
                        'last_moved_at'         => now(),
                        'status'                => 'available',
                    ]);
                }

                // c) Update direct capacity from stock_records quantity.
                $this->refreshTransferCapacity($fromCell, -$qty);
                $this->refreshTransferCapacity($toCell, $qty);

                // d) Catat stock_movement
                StockMovement::create([
                    'item_id'        => $itemId,
                    'warehouse_id'   => $warehouseId,
                    'from_cell_id'   => $fromCell->id,
                    'to_cell_id'     => $toCell->id,
                    'performed_by'   => auth()->id(),
                    'lpn'            => $stock->lpn,
                    'quantity'       => $qty,
                    'movement_type'  => 'transfer',
                    'notes'          => $validated['notes'] ?? null,
                    'moved_at'       => now(),
                ]);
            });

            $unitCode = $stock->item?->unit?->code ?? 'unit';
            return response()->json(['status' => 'success', 'message' => "Transfer {$qty} {$unitCode} ke sel {$toCell->code} berhasil."]);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 7. SEARCH STOK — search()
    //    AJAX: cari item yang memiliki stok tersedia (untuk Select2 / autocomplete)
    // =========================================================================

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $term = $request->input('q', '');

        $items = Item::where('is_active', true)
            ->whereHas('stocks', fn($q) => $q->where('status', 'available')->where('quantity', '>', 0))
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%");
            })
            ->with('unit')
            ->limit(20)
            ->get()
            ->map(fn($i) => [
                'id'   => $i->id,
                'text' => "{$i->sku} — {$i->name}",
                'sku'  => $i->sku,
                'name' => $i->name,
            ]);

        return response()->json(['results' => $items]);
    }

    // =========================================================================
    // HELPER PRIVATE
    // =========================================================================

    private function isMspartCell(Cell $cell): bool
    {
        return $cell->blok !== null
            && $cell->grup !== null
            && $cell->kolom !== null
            && $cell->baris !== null;
    }

    private function normalizeScanCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        if (str_contains($code, '/c/')) {
            $code = trim(last(explode('/c/', $code)), "/ \t\n\r\0\x0B");
        }

        return $code;
    }

    private function parseMspartRackCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^(\d+)-([A-Z])$/', $code, $matches)) {
            return null;
        }

        return [
            'blok' => (int) $matches[1],
            'grup' => $matches[2],
        ];
    }

    private function remainingTransferCapacity(Cell $cell): int
    {
        return app(CellCapacityService::class)->remainingPoints($cell);
    }

    private function transferCapacityDemand(Stock $stock, Cell $toCell, int $qty): int
    {
        return app(CellCapacityService::class)->demandForPlacement($stock->item, $toCell, $qty);
    }

    private function refreshTransferCapacity(Cell $cell, int $legacyQtyDelta): void
    {
        app(CellCapacityService::class)->refresh($cell);
    }

    private function countBelowMin(): int
    {
        return Item::where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM stock_records
                         WHERE item_id = items.id AND status = "available") <= min_stock')
            ->count();
    }
}
