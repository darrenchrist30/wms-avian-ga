<?php

namespace App\Http\Controllers\Outbound;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\FifoPickingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutboundController extends Controller
{
    public function __construct(private FifoPickingService $service) {}

    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        return view('outbound.index', compact('warehouses'));
    }

    public function datatable(Request $request)
    {
        $query = StockMovement::with(['item', 'warehouse', 'fromCell.rack.zone', 'performedBy'])
            ->where('reference_type', 'FIFO_PICKING')
            ->when($request->filled('warehouse_id'), fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date_from'),    fn($q) => $q->whereDate('moved_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),      fn($q) => $q->whereDate('moved_at', '<=', $request->date_to));

        if ($search = $request->input('search.value')) {
            $query->whereHas('item', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        $total    = $query->count();
        $orderCol = $request->input('order.0.column', 1);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $colMap   = [1 => 'moved_at', 6 => 'quantity'];
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
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        return view('outbound.create', compact('warehouses'));
    }

    /** Find item by barcode or SKU (for POS scanner). */
    public function findItem(Request $request)
    {
        $barcode = trim($request->get('barcode', ''));
        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'Barcode kosong.'], 422);
        }

        $item = Item::where('is_active', true)
            ->where(fn($q) => $q->where('barcode', $barcode)->orWhere('sku', $barcode))
            ->withSum(
                ['stocks as available_stock' => fn($q) => $q->where('status', 'available')->where('quantity', '>', 0)],
                'quantity'
            )
            ->first(['id', 'name', 'sku', 'barcode', 'merk']);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan: ' . $barcode], 404);
        }

        return response()->json([
            'success' => true,
            'item'    => [
                'id'              => $item->id,
                'name'            => $item->name,
                'sku'             => $item->sku,
                'barcode'         => $item->barcode,
                'merk'            => $item->merk,
                'available_stock' => (int) ($item->available_stock ?? 0),
            ],
        ]);
    }

    /** Preview FIFO for all cart items (no data changes). */
    public function batchPreview(Request $request)
    {
        $data = $request->validate([
            'warehouse_id'    => 'required|exists:warehouses,id',
            'cart'            => 'required|array|min:1',
            'cart.*.item_id'  => 'required|exists:items,id',
            'cart.*.quantity' => 'required|integer|min:1',
        ]);

        $previews = [];
        foreach ($data['cart'] as $cartItem) {
            $item = Item::find($cartItem['item_id']);
            try {
                $picks = $this->service->preview(
                    (int) $cartItem['item_id'],
                    (int) $data['warehouse_id'],
                    (int) $cartItem['quantity'],
                );
                $previews[] = [
                    'item_id'   => $item->id,
                    'item_name' => $item->name,
                    'item_sku'  => $item->sku,
                    'quantity'  => (int) $cartItem['quantity'],
                    'picks'     => $picks,
                    'total_qty' => array_sum(array_column($picks, 'take_qty')),
                ];
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok tidak cukup untuk <strong>' . e($item->name) . '</strong>: ' . $e->getMessage(),
                ], 422);
            }
        }

        return response()->json(['success' => true, 'previews' => $previews]);
    }

    /** Confirm FIFO outbound for all cart items atomically. */
    public function batchConfirm(Request $request)
    {
        $data = $request->validate([
            'warehouse_id'    => 'required|exists:warehouses,id',
            'cart'            => 'required|array|min:1',
            'cart.*.item_id'  => 'required|exists:items,id',
            'cart.*.quantity' => 'required|integer|min:1',
            'notes'           => 'nullable|string|max:500',
        ]);

        $results = [];
        try {
            DB::transaction(function () use ($data, &$results) {
                foreach ($data['cart'] as $cartItem) {
                    $picks = $this->service->confirm(
                        (int) $cartItem['item_id'],
                        (int) $data['warehouse_id'],
                        (int) $cartItem['quantity'],
                        $data['notes'] ?? null,
                    );
                    $item = Item::find($cartItem['item_id']);
                    $results[] = [
                        'item_name' => $item->name,
                        'item_sku'  => $item->sku,
                        'quantity'  => (int) $cartItem['quantity'],
                        'picks'     => $picks,
                    ];
                }
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $warehouse = Warehouse::find($data['warehouse_id']);
        $this->sendOutboundWhatsapp($results, $warehouse?->name ?? '—', $data['notes'] ?? null);

        return response()->json([
            'success'     => true,
            'message'     => 'Outbound berhasil dikonfirmasi.',
            'results'     => $results,
            'total_items' => count($results),
            'total_qty'   => array_sum(array_column($results, 'quantity')),
        ]);
    }

    private function sendOutboundWhatsapp(array $results, string $warehouseName, ?string $notes): void
    {
        $numbers = config('services.fonnte.supervisor_numbers', []);
        if (empty($numbers)) {
            Log::info('[WA Outbound] WA_SUPERVISOR_NUMBERS kosong, notifikasi tidak dikirim.');
            return;
        }

        $token   = config('services.fonnte.token');
        $message = $this->buildOutboundMessage($results, $warehouseName, $notes);

        if (empty($token)) {
            Log::info('[WA Outbound] FONNTE_TOKEN belum diisi. Pesan:' . PHP_EOL . $message);
            return;
        }

        foreach ($numbers as $number) {
            $number = preg_replace('/[^0-9]/', '', $number);
            if (str_starts_with($number, '0')) {
                $number = '62' . substr($number, 1);
            }
            try {
                $response = Http::withHeaders(['Authorization' => $token])
                    ->asForm()
                    ->post('https://api.fonnte.com/send', [
                        'target'  => $number,
                        'message' => $message,
                    ]);
                if ($response->successful() && ($response->json('status') ?? false)) {
                    Log::info("[WA Outbound] Terkirim ke {$number}");
                } else {
                    Log::warning("[WA Outbound] Gagal ke {$number}: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("[WA Outbound] Error ke {$number}: " . $e->getMessage());
            }
        }
    }

    private function buildOutboundMessage(array $results, string $warehouseName, ?string $notes): string
    {
        $now      = now()->locale('id')->isoFormat('dddd, D MMMM Y HH:mm');
        $operator = auth()->user()?->name ?? '—';

        $totalItems = count($results);
        $totalQty   = array_sum(array_column($results, 'quantity'));

        $lines   = [];
        $lines[] = '*OUTBOUND — WMS AVIAN*';
        $lines[] = "{$now}";
        $lines[] = "Operator: {$operator}";
        $lines[] = "Gudang: {$warehouseName}";
        $lines[] = str_repeat('─', 30);
        $lines[] = '*ITEM YANG DIKELUARKAN:*';
        $lines[] = '';

        foreach ($results as $i => $result) {
            $no   = $i + 1;
            $name = $result['item_name'];
            $sku  = $result['item_sku'];
            $qty  = number_format($result['quantity']);
            $lines[] = "{$no}. *{$name}*";
            $lines[] = "   SKU: {$sku} | Qty: {$qty} unit";

            foreach ($result['picks'] as $pick) {
                $cell     = $pick['cell_code'] ?? ($pick['cell'] ?? '—');
                $takeQty  = number_format($pick['take_qty'] ?? ($pick['quantity'] ?? 0));
                $date     = isset($pick['inbound_date']) ? ' (masuk: ' . $pick['inbound_date'] . ')' : '';
                $lines[]  = "   → Cell {$cell}: {$takeQty} unit{$date}";
            }
            $lines[] = '';
        }

        $lines[] = str_repeat('─', 30);
        $lines[] = "Total: *{$totalItems} jenis item*, *{$totalQty} unit*";

        if (!empty($notes)) {
            $lines[] = "Catatan: {$notes}";
        }

        return implode("\n", $lines);
    }

    public function searchItems(Request $request)
    {
        $q = trim($request->get('q', ''));

        $items = Item::where('is_active', true)
            ->whereHas('stocks', fn($query) => $query->where('status', 'available')->where('quantity', '>', 0))
            ->when($q, fn($query) => $query->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'sku']);

        return response()->json(
            $items->map(fn($item) => ['id' => $item->id, 'text' => $item->name . ' (' . $item->sku . ')'])
        );
    }
}
