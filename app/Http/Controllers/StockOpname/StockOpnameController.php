<?php

namespace App\Http\Controllers\StockOpname;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index — Daftar sesi opname
    // GET /opname
    // ─────────────────────────────────────────────────────────────────────────
    public function index()
    {
        $opnames = StockOpname::with(['warehouse', 'createdBy'])
            ->withCount([
                'items',
                'items as counted_count' => fn($q) => $q->where('status', 'counted'),
            ])
            ->orderByDesc('id')
            ->paginate(15);

        return view('opname.index', compact('opnames'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create — Form buat sesi opname baru
    // GET /opname/create
    // ─────────────────────────────────────────────────────────────────────────
    public function create()
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        return view('opname.create', compact('warehouses'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store — Simpan sesi opname baru
    // POST /opname
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'opname_date'  => 'required|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        $opname = StockOpname::create([
            'warehouse_id'  => $request->warehouse_id,
            'opname_number' => StockOpname::generateNumber(),
            'opname_date'   => $request->opname_date,
            'notes'         => $request->notes,
            'created_by'    => auth()->id(),
            'status'        => 'draft',
        ]);

        return redirect()->route('opname.scan', $opname->id)
            ->with('success', "Sesi opname {$opname->opname_number} berhasil dibuat. Mulai scan item.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scan — Halaman utama scan item
    // GET /opname/{opname}/scan
    // ─────────────────────────────────────────────────────────────────────────
    public function scan(StockOpname $opname)
    {
        if ($opname->status === 'completed' || $opname->status === 'cancelled') {
            return redirect()->route('opname.show', $opname->id)
                ->with('info', 'Sesi opname ini sudah selesai.');
        }

        // Set status ke in_progress saat pertama kali dibuka
        if ($opname->status === 'draft') {
            $opname->update(['status' => 'in_progress', 'started_at' => now()]);
        }

        $scannedItems = StockOpnameItem::with(['item.unit', 'item.category', 'cell', 'scannedBy'])
            ->where('stock_opname_id', $opname->id)
            ->orderByDesc('scanned_at')
            ->get();

        $totalCounted  = $scannedItems->where('status', 'counted')->count();
        $totalSurplus  = $scannedItems->where('difference_status', 'surplus')->count();
        $totalShortage = $scannedItems->where('difference_status', 'shortage')->count();
        $totalMatch    = $scannedItems->where('difference_status', 'match')->count();

        return view('opname.scan', compact(
            'opname', 'scannedItems',
            'totalCounted', 'totalSurplus', 'totalShortage', 'totalMatch'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lookup Item — Resolve item dari barcode scan (AJAX)
    // GET /opname/lookup-item?sku=SP-BRG-001
    // ─────────────────────────────────────────────────────────────────────────
    public function lookupItem(Request $request)
    {
        $request->validate(['sku' => 'required|string']);

        $code = trim($request->sku);

        // Cari berdasarkan SKU atau erp_item_code
        $item = Item::where('is_active', true)
            ->where(fn($q) => $q->where('sku', $code)->orWhere('erp_item_code', $code))
            ->with(['unit', 'category'])
            ->first();

        if (!$item) {
            return response()->json([
                'status'  => 'error',
                'message' => "Item dengan kode '{$code}' tidak ditemukan di master data.",
            ], 404);
        }

        // Hitung qty sistem (dari stock_records, status available)
        $systemQty = Stock::where('item_id', $item->id)
            ->where('status', 'available')
            ->where('warehouse_id', $request->warehouse_id)
            ->sum('quantity');

        // Lokasi cell dengan stok terbesar untuk item ini
        $topCells = Stock::with('cell.rack.zone')
            ->where('item_id', $item->id)
            ->where('status', 'available')
            ->where('warehouse_id', $request->warehouse_id)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->take(3)
            ->get()
            ->map(fn($s) => [
                'id'   => $s->cell_id,
                'code' => $s->cell?->code ?? '-',
                'zone' => $s->cell?->rack?->zone?->name ?? '-',
                'qty'  => $s->quantity,
            ]);

        return response()->json([
            'status' => 'found',
            'item'   => [
                'id'            => $item->id,
                'sku'           => $item->sku,
                'erp_item_code' => $item->erp_item_code,
                'name'          => $item->name,
                'category'      => $item->category?->name ?? '-',
                'unit'          => $item->unit?->symbol ?? 'PCS',
                'system_qty'    => (int) $systemQty,
                'top_cells'     => $topCells,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Save Item — Simpan hasil hitung fisik (AJAX)
    // POST /opname/{opname}/items
    // ─────────────────────────────────────────────────────────────────────────
    public function saveItem(Request $request, StockOpname $opname)
    {
        $request->validate([
            'item_id'      => 'required|exists:items,id',
            'cell_id'      => 'nullable|exists:cells,id',
            'system_qty'   => 'required|integer|min:0',
            'physical_qty' => 'required|integer|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        if (!in_array($opname->status, ['draft', 'in_progress'])) {
            return response()->json(['status' => 'error', 'message' => 'Opname sudah selesai.'], 422);
        }

        $physicalQty = (int) $request->physical_qty;
        $systemQty   = (int) $request->system_qty;
        $difference  = $physicalQty - $systemQty;

        // Jika item yang sama sudah pernah di-scan di sesi ini, update (bukan tambah baru)
        $existing = StockOpnameItem::where('stock_opname_id', $opname->id)
            ->where('item_id', $request->item_id)
            ->when($request->cell_id, fn($q) => $q->where('cell_id', $request->cell_id))
            ->first();

        if ($existing) {
            $existing->update([
                'cell_id'      => $request->cell_id,
                'system_qty'   => $systemQty,
                'physical_qty' => $physicalQty,
                'difference'   => $difference,
                'status'       => 'counted',
                'scanned_by'   => auth()->id(),
                'scanned_at'   => now(),
                'notes'        => $request->notes,
            ]);
            $opnameItem = $existing;
        } else {
            $opnameItem = StockOpnameItem::create([
                'stock_opname_id' => $opname->id,
                'item_id'         => $request->item_id,
                'cell_id'         => $request->cell_id,
                'system_qty'      => $systemQty,
                'physical_qty'    => $physicalQty,
                'difference'      => $difference,
                'status'          => 'counted',
                'scanned_by'      => auth()->id(),
                'scanned_at'      => now(),
                'notes'           => $request->notes,
            ]);
        }

        $opnameItem->load(['item.unit', 'cell', 'scannedBy']);

        return response()->json([
            'status'  => 'success',
            'message' => "Item {$opnameItem->item->name} berhasil dicatat.",
            'item'    => [
                'id'           => $opnameItem->id,
                'item_name'    => $opnameItem->item->name,
                'sku'          => $opnameItem->item->sku,
                'unit'         => $opnameItem->item->unit?->symbol ?? 'PCS',
                'cell_code'    => $opnameItem->cell?->code ?? '-',
                'system_qty'   => $opnameItem->system_qty,
                'physical_qty' => $opnameItem->physical_qty,
                'difference'   => $opnameItem->difference,
                'diff_status'  => $opnameItem->difference_status,
                'scanned_by'   => $opnameItem->scannedBy?->name ?? '-',
                'scanned_at'   => $opnameItem->scanned_at?->format('H:i:s'),
                'notes'        => $opnameItem->notes,
            ],
            'summary' => [
                'total_counted'  => StockOpnameItem::where('stock_opname_id', $opname->id)->where('status', 'counted')->count(),
                'total_surplus'  => StockOpnameItem::where('stock_opname_id', $opname->id)->where('difference', '>', 0)->count(),
                'total_shortage' => StockOpnameItem::where('stock_opname_id', $opname->id)->where('difference', '<', 0)->count(),
                'total_match'    => StockOpnameItem::where('stock_opname_id', $opname->id)->where('difference', 0)->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show — Detail hasil opname (review sebelum finalize)
    // GET /opname/{opname}
    // ─────────────────────────────────────────────────────────────────────────
    public function show(StockOpname $opname)
    {
        $opname->load(['warehouse', 'createdBy', 'completedBy']);

        $items = StockOpnameItem::with(['item.unit', 'item.category', 'cell.rack.zone', 'scannedBy'])
            ->where('stock_opname_id', $opname->id)
            ->where('status', 'counted')
            ->orderBy('difference')
            ->get();

        $summary = [
            'total'    => $items->count(),
            'match'    => $items->filter(fn($i) => $i->difference === 0)->count(),
            'surplus'  => $items->filter(fn($i) => $i->difference > 0)->count(),
            'shortage' => $items->filter(fn($i) => $i->difference < 0)->count(),
        ];

        return view('opname.show', compact('opname', 'items', 'summary'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Complete — Finalize sesi opname
    // POST /opname/{opname}/complete
    // ─────────────────────────────────────────────────────────────────────────
    public function complete(Request $request, StockOpname $opname)
    {
        if ($opname->status !== 'in_progress') {
            return back()->with('error', 'Opname tidak dalam status in_progress.');
        }

        $counted = $opname->items()->where('status', 'counted')->count();
        if ($counted === 0) {
            return back()->with('error', 'Belum ada item yang di-scan. Minimal 1 item harus dihitung.');
        }

        $opname->update([
            'status'       => 'completed',
            'completed_by' => auth()->id(),
            'completed_at' => now(),
        ]);

        return redirect()->route('opname.show', $opname->id)
            ->with('success', "Opname {$opname->opname_number} berhasil diselesaikan. {$counted} item tercatat.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delete item — Hapus 1 baris hasil scan (AJAX)
    // DELETE /opname/{opname}/items/{item}
    // ─────────────────────────────────────────────────────────────────────────
    public function destroyItem(StockOpname $opname, StockOpnameItem $item)
    {
        if ($item->stock_opname_id !== $opname->id) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak valid.'], 422);
        }

        $item->delete();

        return response()->json(['status' => 'success', 'message' => 'Item dihapus dari daftar opname.']);
    }
}
