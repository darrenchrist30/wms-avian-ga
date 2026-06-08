<?php

namespace App\Http\Controllers\Outbound;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OutboundRequest;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\FifoPickingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        $query = StockMovement::with(['item', 'warehouse', 'fromCell.rack', 'performedBy'])
            ->where('reference_type', 'FIFO_PICKING')
            ->when($request->filled('warehouse_id'), fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date_from'),    fn($q) => $q->whereDate('moved_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),      fn($q) => $q->whereDate('moved_at', '<=', $request->date_to));

        $total = (clone $query)->count();

        if ($search = $request->input('search.value')) {
            $query->whereHas('item', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        $filtered = (clone $query)->count();
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
                'from_cell'    => '<span style="color:#212529;">' . e($m->fromCell?->code ?? '—') . '</span>',
                'quantity'     => $m->quantity,
                'notes'        => '<small class="text-muted">' . e($m->notes ?? '-') . '</small>',
                'performed_by' => '<small>' . e($m->performedBy?->name ?? '-') . '</small>',
            ];
        });

        return response()->json([
            'draw'            => (int) $request->get('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();

        // Operator wajib punya approved request
        $approvedRequest = null;
        if ($request->filled('request_id')) {
            $approvedRequest = OutboundRequest::with(['items.item.unit', 'approvedBy'])
                ->where('id', $request->request_id)
                ->where('status', 'approved')
                ->where('operator_id', $user->id)
                ->first();

            if (!$approvedRequest) {
                return redirect()->route('outbound.requests.index')
                    ->with('error', 'Request tidak ditemukan atau belum disetujui.');
            }
        } elseif ($user->hasRole('operator')) {
            // Operator tanpa request_id → redirect ke list request
            return redirect()->route('outbound.requests.index')
                ->with('error', 'Anda harus mengajukan permintaan outbound dan mendapat persetujuan supervisor terlebih dahulu.');
        }

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $defaultWarehouseId = $warehouses->firstWhere('code', 'WH-001')?->id
            ?? $warehouses->first(fn($w) => stripos($w->name, 'sparepart') !== false)?->id;

        $approvedCartItems = $approvedRequest
            ? $approvedRequest->items->map(fn($it) => [
                'id'              => $it->item_id,
                'name'            => $it->item->name,
                'sku'             => $it->item->sku,
                'merk'            => $it->item->merk ?? '',
                'available_stock' => $it->quantity_requested,
                'qty'             => $it->quantity_requested,
            ])->values()
            : null;

        return view('outbound.create', compact('warehouses', 'defaultWarehouseId', 'approvedRequest', 'approvedCartItems'));
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
            ->with([
                'unit:id,code',
                'stocks' => fn($q) => $q->where('status', 'available')->where('quantity', '>', 0)
                                        ->with('cell:id,blok,grup,kolom,baris,code'),
            ])
            ->first(['id', 'name', 'sku', 'barcode', 'merk', 'unit_id']);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan: ' . $barcode], 404);
        }

        $locations = $item->stocks
            ->map(fn($s) => $s->cell?->physical_code)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'item'    => [
                'id'              => $item->id,
                'name'            => $item->name,
                'sku'             => $item->sku,
                'barcode'         => $item->barcode,
                'merk'            => $item->merk,
                'unit'            => $item->unit?->code,
                'available_stock' => (int) ($item->available_stock ?? 0),
                'locations'       => $locations,
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
            'request_id'      => 'nullable|exists:outbound_requests,id',
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

        // Mark outbound request sebagai completed
        if (!empty($data['request_id'])) {
            OutboundRequest::where('id', $data['request_id'])
                ->where('status', 'approved')
                ->update(['status' => 'completed', 'executed_at' => now()]);
        }

        $warehouse = Warehouse::find($data['warehouse_id']);
        $this->sendOutboundWhatsapp($results, $warehouse?->name ?? '—', $data['notes'] ?? null, $data['request_id'] ?? null);

        return response()->json([
            'success'     => true,
            'message'     => 'Outbound berhasil dikonfirmasi.',
            'results'     => $results,
            'total_items' => count($results),
            'total_qty'   => array_sum(array_column($results, 'quantity')),
        ]);
    }

    private function sendOutboundWhatsapp(array $results, string $warehouseName, ?string $notes, ?int $requestId = null): void
    {
        $numbers = config('services.fonnte.supervisor_numbers', []);
        if (empty($numbers)) {
            Log::info('[WA Outbound] WA_SUPERVISOR_NUMBERS kosong, notifikasi tidak dikirim.');
            return;
        }

        $token = config('services.fonnte.token');
        if (empty($token)) {
            $msg = $this->buildOutboundMessage($results, $warehouseName, $notes);
            Log::info('[WA Outbound] FONNTE_TOKEN belum diisi. Pesan:' . PHP_EOL . $msg);
            return;
        }

        $now           = now()->locale('id')->isoFormat('dddd, D MMMM Y HH:mm');
        $operator      = auth()->user()?->name ?? '—';
        $obrRequest    = $requestId ? OutboundRequest::with('approvedBy')->find($requestId) : null;
        $signaturePath = $obrRequest?->signature_path
            ? 'file://' . public_path('../storage/app/' . str_replace('storage/', 'public/', $obrRequest->signature_path))
            : null;
        $approvedBy    = $obrRequest?->approvedBy?->name;
        $approvedAt    = $obrRequest?->approved_at?->locale('id')->isoFormat('D MMMM Y, HH:mm');
        $requestNumber = $obrRequest?->request_number;
        $logoPath      = 'file://' . public_path('images/avian-logo-normal.png');
        $footerPath    = 'file://' . public_path('images/avian-footer.png');

        $pdf         = Pdf::loadView('outbound.wa-pdf', compact(
            'results', 'warehouseName', 'notes', 'now', 'operator',
            'logoPath', 'footerPath', 'signaturePath', 'approvedBy', 'approvedAt', 'requestNumber'
        ))->setPaper('a4', 'portrait');
        $filename    = 'outbound_' . now()->format('Ymd_His') . '_' . uniqid() . '.pdf';
        Storage::put('public/outbound_wa/' . $filename, $pdf->output());
        $publicUrl   = url('storage/outbound_wa/' . $filename);

        $caption = $this->buildOutboundMessage($results, $warehouseName, $notes);

        // Jika APP_URL adalah localhost, kirim teks saja (PDF tidak bisa diakses publik)
        $isLocalhost = str_contains(config('app.url'), 'localhost') || str_contains(config('app.url'), '127.0.0.1');

        foreach ($numbers as $number) {
            $number = preg_replace('/[^0-9]/', '', $number);
            if (str_starts_with($number, '0')) {
                $number = '62' . substr($number, 1);
            }
            try {
                $payload = ['target' => $number, 'message' => $caption];
                if (!$isLocalhost) {
                    $payload['url']      = $publicUrl;
                    $payload['filename'] = 'Outbound_' . now()->format('d-m-Y') . '.pdf';
                }
                $response = Http::withHeaders(['Authorization' => $token])
                    ->asForm()
                    ->post('https://api.fonnte.com/send', $payload);
                if ($response->successful() && ($response->json('status') ?? false)) {
                    Log::info("[WA Outbound] Terkirim ke {$number}" . ($isLocalhost ? ' (teks, localhost)' : ' (PDF)'));
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
        $lines[] = '*OUTBOUND - WMS AVIAN*';
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
