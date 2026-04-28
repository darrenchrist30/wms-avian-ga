<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\Item;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\ItemCategory;
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

        return view('stock.index', compact('summary', 'warehouses', 'categories'));
    }

    private function indexDatatable(Request $request)
    {
        // Aggregate dari stock_records, join ke items
        $query = DB::table('stock_records as sr')
            ->join('items', 'items.id', '=', 'sr.item_id')
            ->leftJoin('item_categories as cat', 'cat.id', '=', 'items.category_id')
            ->leftJoin('units', 'units.id', '=', 'items.unit_id')
            ->select([
                'items.id',
                'items.name',
                'items.sku',
                'items.erp_item_code',
                'items.min_stock',
                'items.reorder_point',
                'cat.name as category_name',
                'cat.color_code',
                'units.code as unit_code',
                DB::raw('SUM(sr.quantity) as total_qty'),
                DB::raw('COUNT(DISTINCT sr.cell_id) as locations_count'),
                DB::raw('MIN(sr.inbound_date) as fifo_date'),
            ])
            ->where('sr.status', 'available')
            ->where('items.is_active', true)
            ->groupBy(
                'items.id','items.name','items.sku','items.erp_item_code',
                'items.min_stock','items.reorder_point',
                'cat.name','cat.color_code','units.code'
            )
            ->havingRaw('SUM(sr.quantity) > 0')
            ->when($request->filled('category_id'),
                fn($q) => $q->where('items.category_id', $request->category_id))
            ->when($request->filled('warehouse_id'),
                fn($q) => $q->where('sr.warehouse_id', $request->warehouse_id))
            ->when($request->filled('status_filter'), function ($q) use ($request) {
                match ($request->status_filter) {
                    'critical' => $q->havingRaw('SUM(sr.quantity) <= items.min_stock AND items.min_stock > 0'),
                    'reorder'  => $q->havingRaw('SUM(sr.quantity) > items.min_stock AND SUM(sr.quantity) <= items.reorder_point AND items.reorder_point > 0'),
                    default    => null,
                };
            });

        return DataTables::of($query)
            ->addIndexColumn()
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
                $badge  = '';
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
                $min    = $row->min_stock    ? number_format($row->min_stock)    : '—';
                $reorder= $row->reorder_point? number_format($row->reorder_point): '—';
                return '<small class="text-muted">Min: <strong>' . $min . '</strong></small><br>
                         <small class="text-muted">Reorder: <strong>' . $reorder . '</strong></small>';
            })
            ->addColumn('locations_display', function ($row) {
                $n = (int) $row->locations_count;
                if ($n === 0) return '<span class="text-muted">—</span>';
                return '<span class="badge badge-light border text-dark">
                            <i class="fas fa-map-marker-alt mr-1 text-primary"></i>' . $n . ' cell
                        </span>';
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

        $stocks = Stock::with(['cell.rack.zone', 'warehouse'])
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
        $reorderPoint = (int) $item->reorder_point;
        $stockStatus  = 'ok';
        if ($totalQty === 0)                            $stockStatus = 'empty';
        elseif ($minStock > 0 && $totalQty <= $minStock)    $stockStatus = 'critical';
        elseif ($reorderPoint > 0 && $totalQty <= $reorderPoint) $stockStatus = 'reorder';

        return view('stock.show', compact(
            'item', 'stocks', 'totalQty', 'cellCount',
            'oldestFifo', 'stockStatus', 'minStock', 'reorderPoint'
        ));
    }

    private function itemMovementsDatatable(Item $item)
    {
        $query = StockMovement::with([
                'fromCell.rack.zone', 'toCell.rack.zone',
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
                'fromCell.rack.zone', 'toCell.rack.zone',
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
            ->addColumn('location_display', function ($m) {
                $from = $m->fromCell ? e($m->fromCell->code) : '—';
                $to   = $m->toCell   ? e($m->toCell->code)   : '—';
                return match ($m->movement_type) {
                    'inbound'  => '<i class="fas fa-arrow-down text-success mr-1"></i><strong>' . $to . '</strong>',
                    'outbound' => '<i class="fas fa-arrow-up text-danger mr-1"></i><strong>' . $from . '</strong>',
                    default    => $from . ' <i class="fas fa-long-arrow-alt-right text-info mx-1"></i> ' . $to,
                };
            })
            ->addColumn('qty_display', function ($m) {
                $cls  = match ($m->movement_type) {
                    'inbound'  => 'text-success',
                    'outbound' => 'text-danger',
                    default    => 'text-info',
                };
                $sign = match ($m->movement_type) {
                    'inbound'  => '+',
                    'outbound' => '−',
                    default    => '',
                };
                return '<span class="font-weight-bold ' . $cls . '">'
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
            ->rawColumns(['item_info', 'type_badge', 'location_display', 'qty_display', 'notes_display'])
            ->make(true);
    }

    // =========================================================================
    // 4. STOK KRITIS — lowStock()
    //    Item di bawah min_stock atau reorder_point, urut paling kritis dulu
    // =========================================================================

    public function lowStock(Request $request)
    {
        $qtySubSql = '(SELECT COALESCE(SUM(sr.quantity),0) FROM stock_records sr
                       WHERE sr.item_id = items.id AND sr.status = "available")';

        $items = Item::with(['category', 'unit'])
            ->select('items.*')
            ->selectRaw("{$qtySubSql} as current_stock")
            ->where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->whereRaw("{$qtySubSql} <= reorder_point")
            ->orderByRaw("{$qtySubSql} ASC")
            ->get();

        $summary = [
            'empty'    => $items->where('current_stock', 0)->count(),
            'critical' => $items->filter(fn($i) => $i->current_stock > 0
                                && $i->current_stock <= $i->min_stock)->count(),
            'reorder'  => $items->filter(fn($i) => $i->current_stock > $i->min_stock
                                && $i->current_stock <= $i->reorder_point)->count(),
        ];

        return view('stock.low-stock', compact('items', 'summary'));
    }

    // =========================================================================
    // 5. NEAR EXPIRY — nearExpiry()
    //    Stock records dengan expiry_date dalam X hari ke depan (default 30)
    // =========================================================================

    public function nearExpiry(Request $request)
    {
        $days = (int) ($request->days ?? 30);
        $days = in_array($days, [7, 30, 60, 90]) ? $days : 30;

        $stocks = Stock::with(['item.category', 'item.unit', 'cell.rack.zone', 'warehouse'])
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

    public function transfer(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'stock_id'   => 'required|exists:stock_records,id',
            'to_cell_id' => 'required|exists:cells,id',
            'quantity'   => 'required|integer|min:1',
            'notes'      => 'nullable|string|max:255',
        ]);

        $stock  = Stock::with(['cell', 'item', 'warehouse'])->findOrFail($validated['stock_id']);
        $toCell = Cell::with('rack.zone')->findOrFail($validated['to_cell_id']);
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
        if (!$toCell->is_active || in_array($toCell->status, ['blocked', 'full'])) {
            return response()->json(['status' => 'error', 'message' => "Sel {$toCell->code} tidak dapat menerima stok (status: {$toCell->status})."], 422);
        }
        $remCap = $toCell->capacity_max - $toCell->capacity_used;
        if ($qty > $remCap) {
            return response()->json(['status' => 'error', 'message' => "Kapasitas sel {$toCell->code} tidak cukup (tersisa: {$remCap})."], 422);
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

                // b) Update kapasitas sel asal
                $fromCell->update(['capacity_used' => max(0, $fromCell->capacity_used - $qty)]);
                $fromCell->updateStatus();

                // c) Tambah ke sel tujuan (merge jika sudah ada)
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
                        'warehouse_id'          => $toCell->rack->zone->warehouse_id ?? $warehouseId,
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

                // d) Update kapasitas sel tujuan
                $toCell->update(['capacity_used' => $toCell->capacity_used + $qty]);
                $toCell->updateStatus();

                // e) Catat stock_movement
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

            return response()->json(['status' => 'success', 'message' => "Transfer {$qty} unit ke sel {$toCell->code} berhasil."]);
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

    private function countBelowMin(): int
    {
        return Item::where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM stock_records
                         WHERE item_id = items.id AND status = "available") <= min_stock')
            ->count();
    }
}
