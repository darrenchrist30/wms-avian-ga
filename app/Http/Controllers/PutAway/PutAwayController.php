<?php

namespace App\Http\Controllers\PutAway;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Models\GaRecommendationDetail;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\FastSlowMovingService;
use App\Services\PutAwayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PutAwayController extends Controller
{
    public function __construct(
        private readonly PutAwayService $putAwayService,
        private readonly FastSlowMovingService $fastSlowService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Index — Daftar Order yang Siap di-Put-Away + Riwayat Completed
    // GET /putaway
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $search       = trim($request->input('search', ''));
        $filterStatus = $request->input('status', '');  // '' | 'put_away' | 'completed'
        $warehouseId  = $request->input('warehouse_id', '');

        $empty = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);

        $base = InboundOrder::with(['warehouse', 'receivedBy'])
            ->withCount([
                'items',
                'items as put_away_count' => fn($q) => $q->where('status', 'put_away'),
            ])
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'))
            ->when($search, fn($q) => $q->where('do_number', 'like', "%{$search}%"))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId));

        // Antrian aktif — disembunyikan kalau filter 'completed'
        if ($filterStatus === 'completed') {
            $orders = $empty;
        } else {
            $activeStatuses = $filterStatus === 'put_away'
                ? ['put_away']
                : ['put_away'];

            $orders = (clone $base)
                ->whereIn('status', $activeStatuses)
                ->orderByDesc('updated_at')
                ->paginate(15)
                ->withQueryString();
        }

        // Riwayat completed — hanya tampil kalau filter 'completed' dipilih eksplisit
        if ($filterStatus !== 'completed') {
            $completedOrders = $empty;
        } else {
            $completedOrders = (clone $base)
                ->where('status', 'completed')
                ->orderByDesc('updated_at')
                ->paginate(10, ['*'], 'completed_page')
                ->withQueryString();
        }

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        return view('putaway.index', compact('orders', 'completedOrders', 'warehouses'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queue — Consolidated put-away list across all pending orders
    // GET /putaway/queue
    // ─────────────────────────────────────────────────────────────────────────

    public function queue()
    {
        $activeOrderScope = fn($q) =>
            $q->where('status', 'accepted')
              ->whereHas('inboundOrder', fn($q2) => $q2->where('status', 'put_away'));

        $items = GaRecommendationDetail::with([
            'gaRecommendation.inboundOrder',
            'inboundOrderItem.item.unit',
            'inboundOrderItem.item.category',
            'cell.rack',
        ])
        ->whereHas('gaRecommendation', $activeOrderScope)
        ->whereHas('inboundOrderItem', fn($q) => $q->where('status', 'pending'))
        ->get()
        ->sortByDesc(fn($d) => optional($d->gaRecommendation->inboundOrder?->updated_at)->timestamp ?? 0)
        ->values();

        $activeDOs    = InboundOrder::where('status', 'put_away')
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'))
            ->count();

        $completedDOs = InboundOrder::where('status', 'completed')
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'))
            ->count();

        $totalOrders = $items->pluck('gaRecommendation.inbound_order_id')->unique()->count();

        return view('putaway.queue', compact('items', 'totalOrders', 'activeDOs', 'completedDOs'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show — Detail Order + Rekomendasi GA + Progress Put-Away
    // GET /putaway/{order}
    // ─────────────────────────────────────────────────────────────────────────

    public function show($orderId)
    {
        $order = InboundOrder::with([
            'warehouse',
            'receivedBy',
            'items.item.unit',
            'items.item.category',
            'items.putAwayConfirmations.cell.rack',
            'items.putAwayConfirmations.user',
        ])->findOrFail($orderId);

        if (!in_array($order->status, ['put_away', 'completed'])) {
            return redirect()->route('inbound.orders.show', $orderId)
                ->with('error', 'Order ini belum siap untuk put-away.');
        }

        // Ambil GA recommendation yang accepted
        $gaRecommendation = GaRecommendation::with([
            'details.cell.rack',
            'details.inboundOrderItem.item',
            'generatedBy',
        ])
            ->where('inbound_order_id', $orderId)
            ->where('status', 'accepted')
            ->latest()
            ->firstOrFail();

        // Buat map: inbound_detail_id → ga_detail (untuk tampil di view)
        $gaDetailMap = $gaRecommendation->details->groupBy('inbound_order_item_id');

        // Progress: berapa item sudah selesai
        $totalItems    = $order->items->count();
        $doneItems     = $order->items->where('status', 'put_away')->count();
        $progressPct   = $totalItems > 0 ? round($doneItems / $totalItems * 100) : 0;

        return view('putaway.show', compact(
            'order',
            'gaRecommendation',
            'gaDetailMap',
            'totalItems',
            'doneItems',
            'progressPct'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scan QR — Resolve cell dari QR code (AJAX)
    // POST /putaway/scan-qr
    // ─────────────────────────────────────────────────────────────────────────

    public function scanQr(Request $request)
    {
        $request->validate([
            'qr_code'     => 'required|string',
            'ga_cell_id'  => 'nullable|integer|exists:cells,id',
            'is_override' => 'nullable|boolean',
            'detail_id'   => 'nullable|integer|exists:inbound_details,id',
        ]);

        try {
            $cell = $this->putAwayService->resolveCellByQr(
                $request->qr_code,
                $request->filled('ga_cell_id') ? (int) $request->ga_cell_id : null,
                (bool) $request->input('is_override', false)
            );

            $itemStock = null;
            if ($request->filled('detail_id')) {
                $detail = InboundOrderItem::with('item.unit')->find((int) $request->detail_id);

                if ($detail) {
                    $currentQty = (int) Stock::where('item_id', $detail->item_id)
                        ->where('cell_id', $cell->id)
                        ->where('quantity', '>', 0)
                        ->where('status', 'available')
                        ->sum('quantity');

                    $itemStock = [
                        'will_merge'  => $currentQty > 0,
                        'current_qty' => $currentQty,
                        'max_stock'   => (int) ($detail->item?->max_stock ?? 0),
                        'unit'        => $detail->item?->unit?->code ?? $detail->item?->unit?->name ?? 'unit',
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'cell'   => [
                    'id'                 => $cell->id,
                    'code'               => $cell->physical_code,
                    'label'              => $cell->physical_label,
                    'raw_code'           => $cell->code,
                    'capacity_max'       => $cell->physical_capacity_max,
                    'capacity_used'      => $cell->physical_capacity_used,
                    'capacity_remaining' => $cell->physical_capacity_remaining,
                    'status'             => $cell->status,
                    'rack_code'          => $cell->rack->code ?? '-',
                    'item_stock'         => $itemStock,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Confirm — Operator konfirmasi satu item put-away (AJAX)
    // POST /putaway/{order}/items/{detail}/confirm
    // ─────────────────────────────────────────────────────────────────────────

    public function confirm(Request $request, $orderId, $detailId)
    {
        $request->validate([
            'cell_id'          => 'required|exists:cells,id',
            'quantity_stored'  => 'required|integer|min:1',
            'ga_detail_id'     => 'nullable|exists:ga_recommendation_details,id',
            'notes'            => 'nullable|string|max:255',
        ]);

        $order  = InboundOrder::findOrFail($orderId);
        $detail = InboundOrderItem::where('inbound_order_id', $orderId)
            ->findOrFail($detailId);
        $cell   = Cell::findOrFail($request->cell_id);

        // Cari GA detail yang jadi acuan (jika ada)
        $gaDetail = null;

        if ($request->boolean('fast_slow_mode')) {
            // Fast/Slow Moving mode — tidak terikat rekomendasi GA
            $gaDetail = null;
        } elseif ($request->filled('ga_detail_id')) {
            $gaDetail = GaRecommendationDetail::whereHas(
                'gaRecommendation',
                fn($q) => $q->where('inbound_order_id', $orderId)
                    ->where('status', 'accepted')
            )
                ->where('id', $request->ga_detail_id)
                ->where('inbound_order_item_id', $detailId)
                ->firstOrFail();
        } else {
            $gaDetail = GaRecommendationDetail::whereHas(
                'gaRecommendation',
                fn($q) => $q->where('inbound_order_id', $orderId)
                    ->where('status', 'accepted')
            )
                ->where('inbound_order_item_id', $detailId)
                ->first();
        }

        try {
            $confirmation = $this->putAwayService->confirmPlacement(
                detail: $detail,
                cell: $cell,
                quantityStored: $request->quantity_stored,
                userId: auth()->id(),
                gaDetail: $gaDetail,
                notes: $request->notes,
            );

            $order->refresh();
            $doneCount  = $order->items()->where('status', 'put_away')->count();
            $totalCount = $order->items()->count();

            return response()->json([
                'status'   => 'success',
                'message'  => "Item berhasil di-put-away ke lokasi {$cell->physical_label}.",
                'progress' => [
                    'done'        => $doneCount,
                    'total'       => $totalCount,
                    'percent'     => $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0,
                    'is_complete' => $order->status === 'completed',
                ],
                'follow_recommendation' => $confirmation->follow_recommendation,
                'cell_code'             => $cell->physical_code,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Alternative Cells — Rekomendasikan cell lain ketika cell GA penuh (AJAX)
    // GET /putaway/{order}/alternative-cells?for_cell_id=X&qty=Y
    // ─────────────────────────────────────────────────────────────────────────

    public function alternativeCells(Request $request, $orderId)
    {
        $request->validate([
            'for_cell_id' => 'required|integer|exists:cells,id',
            'qty'         => 'required|integer|min:1',
        ]);

        $order      = InboundOrder::findOrFail($orderId);
        $sourceCell = Cell::with('rack')->findOrFail($request->for_cell_id);
        $qty        = (int) $request->qty;

        // Cari cell alternatif di warehouse yang sama, masih ada kapasitas
        $alternatives = Cell::with('rack')
            ->where('is_active', true)
            ->where('id', '!=', $request->for_cell_id)
            ->whereRaw('(capacity_max - capacity_used) > 0')
            ->whereHas('rack', fn($q) => $q->where('warehouse_id', $order->warehouse_id))
            ->get()
            ->sortByDesc(function (Cell $cell) use ($qty) {
                $score = 0;
                // Prioritas 1: muat seluruh qty
                if ($cell->physical_capacity_remaining >= $qty) $score += 5000;
                // Prioritas 2: kapasitas tersisa terbesar
                $score += $cell->physical_capacity_remaining;
                return $score;
            })
            ->take(6)
            ->values();

        return response()->json([
            'source_cell' => [
                'id'                 => $sourceCell->id,
                'code'               => $sourceCell->physical_code,
                'capacity_remaining' => $sourceCell->physical_capacity_remaining,
                'capacity_max'       => $sourceCell->physical_capacity_max,
            ],
            'qty_needed'   => $qty,
            'alternatives' => $alternatives->map(fn(Cell $c) => [
                'id'                 => $c->id,
                'code'               => $c->physical_code,
                'rack_code'          => $c->rack?->code ?? '-',
                'capacity_remaining' => $c->physical_capacity_remaining,
                'capacity_max'       => $c->physical_capacity_max,
                'capacity_used'      => $c->physical_capacity_used,
                'status'             => $c->status,
                'fits_all'           => $c->physical_capacity_remaining >= $qty,
            ])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fast/Slow Moving Suggestions — Saran cell berdasarkan frekuensi outbound
    // GET /putaway/{order}/fast-slow-suggestions
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // Batch Scan — temukan semua item pending untuk satu cell (AJAX)
    // GET /putaway/batch-scan?qr_code=X
    // ─────────────────────────────────────────────────────────────────────────

    public function batchScan(Request $request)
    {
        $request->validate(['qr_code' => 'required|string']);

        $qrCode = $request->qr_code;

        // Extract cell code from URL QR (e.g. http://host/c/1-A → 1-A)
        if (str_contains($qrCode, '/c/')) {
            $qrCode = trim(last(explode('/c/', $qrCode)), '/ ');
        }

        // ── Try 1: exact cell match (qr_code, code, or label) ──────────────────
        $exactCell = Cell::where(function ($q) use ($qrCode) {
            $q->where('qr_code', $qrCode)
              ->orWhere('code', $qrCode)
              ->orWhere('label', $qrCode);
        })->where('is_active', true)->with('rack')->first();

        $cellIds     = collect();
        $displayCode = $qrCode;
        $displayRack = '-';

        if ($exactCell) {
            $cellIds     = collect([$exactCell->id]);
            $displayCode = $exactCell->physical_code;
            $displayRack = $exactCell->rack?->code ?? '-';
        } else {
            // ── Try 2: blok-grup rack QR (e.g. "1-A" → all cells blok=1, grup=A) ─
            $parts = explode('-', $qrCode);
            if (count($parts) === 2) {
                $blok = $parts[0];
                $grup = $parts[1];   // MySQL string compare is CI by default

                $cellIds = Cell::where('blok', $blok)
                    ->whereRaw('UPPER(CAST(grup AS CHAR)) = ?', [strtoupper($grup)])
                    ->where('is_active', true)
                    ->pluck('id');

                $displayCode = strtoupper($qrCode) . ' (Rak)';
                $displayRack = strtoupper($qrCode);
            }
        }

        if ($cellIds->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => "QR Code '{$qrCode}' tidak ditemukan. Pastikan sel/rak aktif di sistem.",
            ], 404);
        }

        $details = GaRecommendationDetail::with([
            'gaRecommendation.inboundOrder',
            'inboundOrderItem.item.unit',
            'cell',
        ])
        ->whereIn('cell_id', $cellIds)
        ->whereHas('gaRecommendation', fn($q) =>
            $q->where('status', 'accepted')
              ->whereHas('inboundOrder', fn($q2) => $q2->where('status', 'put_away'))
        )
        ->whereHas('inboundOrderItem', fn($q) => $q->where('status', 'pending'))
        ->get();

        return response()->json([
            'status'  => $details->isEmpty() ? 'empty' : 'found',
            'message' => $details->isEmpty() ? 'Tidak ada item pending untuk cell/rak ini.' : null,
            'display_code' => $displayCode,
            'display_rack' => $displayRack,
            'items' => $details->map(fn($d) => [
                'ga_detail_id' => $d->id,
                'order_id'     => $d->gaRecommendation->inbound_order_id,
                'detail_id'    => $d->inbound_order_item_id,
                'cell_id'      => $d->cell_id,
                'cell_code'    => $d->cell?->physical_code ?? $d->cell?->code ?? '-',
                'row_id'       => 'row-ga-' . $d->id,
                'item_name'    => $d->inboundOrderItem->item->name ?? '-',
                'item_sku'     => $d->inboundOrderItem->item->sku ?? '-',
                'unit'         => $d->inboundOrderItem->item->unit?->code ?? '-',
                'quantity'     => $d->quantity,
                'do_number'    => $d->gaRecommendation->inboundOrder->do_number,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch Confirm — simpan semua item sekaligus (AJAX)
    // POST /putaway/batch-confirm
    // ─────────────────────────────────────────────────────────────────────────

    public function batchConfirm(Request $request)
    {
        $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.cell_id'      => 'required|exists:cells,id',
            'items.*.order_id'     => 'required|exists:inbound_transactions,id',
            'items.*.detail_id'    => 'required|exists:inbound_details,id',
            'items.*.ga_detail_id' => 'nullable|integer',
            'items.*.quantity'     => 'required|integer|min:1',
            'notes'                => 'nullable|string|max:255',
        ]);

        $confirmed        = 0;
        $skipped          = 0;
        $errors           = [];
        $ordersCompleted  = 0;
        $touchedOrderIds  = [];

        foreach ($request->items as $item) {
            try {
                $cell   = Cell::findOrFail($item['cell_id']);
                $detail = InboundOrderItem::where('inbound_order_id', $item['order_id'])
                    ->findOrFail($item['detail_id']);

                if ($detail->status === 'put_away') {
                    $skipped++;
                    continue;
                }

                $gaDetail = !empty($item['ga_detail_id'])
                    ? GaRecommendationDetail::find($item['ga_detail_id'])
                    : null;

                $this->putAwayService->confirmPlacement(
                    detail: $detail,
                    cell: $cell,
                    quantityStored: $item['quantity'],
                    userId: auth()->id(),
                    gaDetail: $gaDetail,
                    notes: $request->notes ?? '',
                );
                $confirmed++;
                $touchedOrderIds[] = $item['order_id'];
            } catch (\Exception $e) {
                $errors[] = $item['detail_id'];
            }
        }

        // Hitung berapa DO yang baru saja selesai (completed) akibat batch ini
        foreach (array_unique($touchedOrderIds) as $orderId) {
            $o = InboundOrder::find($orderId);
            if ($o && $o->status === 'completed') {
                $ordersCompleted++;
            }
        }

        if ($confirmed === 0 && !empty($errors)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Semua item gagal disimpan. Periksa kapasitas cell.',
            ], 422);
        }

        return response()->json([
            'status'           => 'success',
            'confirmed_count'  => $confirmed,
            'skipped_count'    => $skipped,
            'error_count'      => count($errors),
            'orders_completed' => $ordersCompleted,
            'message'          => "{$confirmed} item berhasil di-put-away."
                . ($skipped > 0 ? " {$skipped} sudah selesai sebelumnya." : '')
                . (count($errors) > 0 ? ' ' . count($errors) . ' item gagal.' : ''),
        ]);
    }

    public function fastSlowSuggestions(InboundOrder $order)
    {
        $order->load(['items.item', 'items.putAwayConfirmations']);
        $warehouseId = $order->warehouse_id;

        $suggestions = [];

        foreach ($order->items as $detail) {
            if ($detail->status === 'put_away') continue;

            $storedQty = $detail->putAwayConfirmations->sum('quantity_stored');
            $remaining = max(0, $detail->quantity_received - $storedQty);
            if ($remaining <= 0) continue;

            $info = $this->fastSlowService->classify($detail->item_id, $warehouseId);
            $cell = $this->fastSlowService->suggestCell($detail->item_id, $warehouseId, $remaining);

            $suggestions[$detail->id] = [
                'classification' => $info['classification'],
                'label'          => $info['label'],
                'color'          => $info['color'],
                'count'          => $info['count'],
                'remaining_qty'  => $remaining,
                'cell'           => $cell,
            ];
        }

        return response()->json(['suggestions' => $suggestions]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Override — Supervisor ubah lokasi dari rekomendasi GA (AJAX)
    // POST /putaway/{order}/items/{detail}/override
    // ─────────────────────────────────────────────────────────────────────────

    public function override(Request $request, $orderId, $detailId)
    {
        $request->validate([
            'cell_id'         => 'required|exists:cells,id',
            'quantity_stored' => 'required|integer|min:1',
            'notes'           => 'nullable|string|max:255',
        ]);

        $detail = InboundOrderItem::where('inbound_order_id', $orderId)->findOrFail($detailId);
        $cell   = Cell::findOrFail($request->cell_id);

        $order = InboundOrder::findOrFail($orderId);

        try {
            // Override = confirm tanpa ga_detail (follow_recommendation akan false)
            $this->putAwayService->confirmPlacement(
                detail: $detail,
                cell: $cell,
                quantityStored: $request->quantity_stored,
                userId: auth()->id(),
                gaDetail: null,
                notes: '[OVERRIDE] ' . ($request->notes ?? ''),
            );

            $order->refresh();
            $doneCount  = $order->items()->where('status', 'put_away')->count();
            $totalCount = $order->items()->count();

            return response()->json([
                'status'  => 'success',
                'message' => "Override berhasil. Item ditempatkan ke lokasi {$cell->physical_label} (di luar rekomendasi GA).",
                'progress' => [
                    'done'        => $doneCount,
                    'total'       => $totalCount,
                    'percent'     => $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0,
                    'is_complete' => $order->status === 'completed',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
