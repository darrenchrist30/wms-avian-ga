<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\FifoPickingService;
use Illuminate\Http\Request;

class FifoPickingController extends Controller
{
    public function __construct(private FifoPickingService $service) {}

    public function index()
    {
        $items      = Item::orderBy('name')->get(['id', 'name', 'sku']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'code']);

        return view('stock.fifo-picking', compact('items', 'warehouses'));
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
