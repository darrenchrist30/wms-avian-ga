<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\FifoPickingService;
use Illuminate\Http\Request;

class FifoPickingController extends Controller
{
    public function __construct(private FifoPickingService $service) {}

    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        return view('stock.fifo-picking-index', compact('warehouses'));
    }

    public function datatable(Request $request)
    {
        $query = StockMovement::with(['item', 'warehouse', 'fromCell.rack.zone', 'performedBy'])
            ->where('reference_type', 'FIFO_PICKING')
            ->when($request->filled('warehouse_id'), fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date_from'),    fn($q) => $q->whereDate('moved_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),      fn($q) => $q->whereDate('moved_at', '<=', $request->date_to));

        // DataTables global search
        if ($search = $request->input('search.value')) {
            $query->whereHas('item', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        $total = $query->count();

        // Ordering
        $orderCol  = $request->input('order.0.column', 1);
        $orderDir  = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $colMap    = [1 => 'moved_at', 6 => 'quantity'];
        $query->orderBy($colMap[$orderCol] ?? 'moved_at', $orderDir);

        $movements = $query->skip((int) $request->get('start', 0))
                           ->take((int) $request->get('length', 25))
                           ->get();

        $rowIndex = (int) $request->get('start', 0) + 1;
        $data = $movements->map(function ($m) use (&$rowIndex) {
            return [
                'DT_RowIndex'  => $rowIndex++,
                'moved_at'     => $m->moved_at?->format('d M Y H:i'),
                'item'         => '<strong>' . e($m->item?->name ?? '-') . '</strong><br><small class="text-muted">' . e($m->item?->sku ?? '-') . '</small>',
                'warehouse'    => e($m->warehouse?->name ?? '-'),
                'from_cell'    => '<span class="badge badge-light border font-weight-bold">' . e($m->fromCell?->code ?? '-') . '</span>',
                'zone_rack'    => '<small class="text-muted">' . e(($m->fromCell?->rack?->zone?->code ?? '-') . ' / ' . ($m->fromCell?->rack?->code ?? '-')) . '</small>',
                'quantity'     => $m->quantity,
                'notes'        => '<small class="text-muted">' . e($m->notes ?? '-') . '</small>',
                'performed_by' => '<small>' . e($m->performedBy?->name ?? '-') . '</small>',
            ];
        });

        return response()->json([
            'draw'            => (int) $request->get('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('stock.fifo-picking', compact('warehouses'));
    }

    public function searchItems(Request $request)
    {
        $q = trim($request->get('q', ''));

        $items = Item::where('is_active', true)
            ->whereHas('stocks', fn($query) => $query->where('status', 'available')->where('quantity', '>', 0))
            ->when($q, fn($query) => $query->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'sku']);

        return response()->json(
            $items->map(fn($item) => [
                'id'   => $item->id,
                'text' => $item->name . ' (' . $item->sku . ')',
            ])
        );
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'item_id'       => 'required|exists:items,id',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'quantity'      => 'required|integer|min:1',
        ]);

        try {
            $picks = $this->service->preview(
                (int) $data['item_id'],
                (int) $data['warehouse_id'],
                (int) $data['quantity'],
            );

            $item      = Item::find($data['item_id']);
            $warehouse = Warehouse::find($data['warehouse_id']);

            return response()->json([
                'success'    => true,
                'picks'      => $picks,
                'item'       => ['name' => $item->name, 'sku' => $item->sku],
                'warehouse'  => $warehouse->name,
                'total_qty'  => array_sum(array_column($picks, 'take_qty')),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'item_id'      => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity'     => 'required|integer|min:1',
            'notes'        => 'nullable|string|max:500',
        ]);

        try {
            $picks = $this->service->confirm(
                (int) $data['item_id'],
                (int) $data['warehouse_id'],
                (int) $data['quantity'],
                $data['notes'] ?? null,
            );

            $item = Item::find($data['item_id']);

            return response()->json([
                'success'   => true,
                'message'   => 'Pengambilan FIFO berhasil dikonfirmasi.',
                'picks'     => $picks,
                'item_name' => $item->name,
                'total_qty' => array_sum(array_column($picks, 'take_qty')),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
