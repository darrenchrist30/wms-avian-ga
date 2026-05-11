<?php

namespace App\Http\Controllers\PutAway;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Models\GaRecommendationDetail;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\Warehouse;
use App\Services\FastSlowMovingService;
use App\Services\PutAwayService;
use Illuminate\Http\Request;

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
        $filterStatus = $request->input('status', '');  // '' | 'recommended' | 'put_away' | 'completed'
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
            $activeStatuses = in_array($filterStatus, ['recommended', 'put_away'])
                ? [$filterStatus]
                : ['recommended', 'put_away'];

            $orders = (clone $base)
                ->whereIn('status', $activeStatuses)
                ->orderByDesc('updated_at')
                ->paginate(15)
                ->withQueryString();
        }

        // Riwayat completed — disembunyikan kalau filter aktif saja
        if (in_array($filterStatus, ['recommended', 'put_away'])) {
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
    // Show — Detail Order + Rekomendasi GA + Progress Put-Away
    // GET /putaway/{order}
    // ─────────────────────────────────────────────────────────────────────────

    public function show($orderId)
    {
        $order = InboundOrder::with([
            'warehouse',
            'supplier',
            'receivedBy',
            'items.item.unit',
            'items.item.category',
            'items.putAwayConfirmations.cell.rack.zone',
            'items.putAwayConfirmations.user',
        ])->findOrFail($orderId);

        if (!in_array($order->status, ['recommended', 'put_away', 'completed'])) {
            return redirect()->route('inbound.orders.show', $orderId)
                ->with('error', 'Order ini belum siap untuk put-away.');
        }

        // Ambil GA recommendation yang accepted
        $gaRecommendation = GaRecommendation::with([
            'details.cell.rack.zone',
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
        $request->validate(['qr_code' => 'required|string']);

        try {
            $cell = $this->putAwayService->resolveCellByQr($request->qr_code);

            return response()->json([
                'status' => 'success',
                'cell'   => [
                    'id'                 => $cell->id,
                    'code'               => $cell->code,
                    'label'              => $cell->label ?? $cell->code,
                    'zone_category'      => $cell->zone_category,
                    'capacity_max'       => $cell->capacity_max,
                    'capacity_used'      => $cell->capacity_used,
                    'capacity_remaining' => $cell->capacity_max - $cell->capacity_used,
                    'status'             => $cell->status,
                    'rack_code'          => $cell->rack->code ?? '-',
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
                'message'  => "Item berhasil di-put-away ke sel {$cell->code}.",
                'progress' => [
                    'done'        => $doneCount,
                    'total'       => $totalCount,
                    'percent'     => $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0,
                    'is_complete' => $order->status === 'completed',
                ],
                'follow_recommendation' => $confirmation->follow_recommendation,
                'cell_code'             => $cell->code,
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
        $sourceCell = Cell::with('rack.zone')->findOrFail($request->for_cell_id);
        $zoneId     = $sourceCell->rack?->zone_id;
        $qty        = (int) $request->qty;

        // Cari cell alternatif di warehouse yang sama, masih ada kapasitas
        $alternatives = Cell::with('rack.zone')
            ->where('is_active', true)
            ->where('id', '!=', $request->for_cell_id)
            ->whereRaw('(capacity_max - capacity_used) > 0')
            ->where(function ($q) use ($order) {
                $q->whereHas('rack', fn($q2) => $q2->where('warehouse_id', $order->warehouse_id))
                    ->orWhereHas('rack.zone', fn($q2) => $q2->where('warehouse_id', $order->warehouse_id));
            })
            ->get()
            ->sortByDesc(function (Cell $cell) use ($zoneId, $qty) {
                $score = 0;
                // Prioritas 1: zona yang sama dengan cell GA
                if ($cell->rack?->zone_id === $zoneId) $score += 10000;
                // Prioritas 2: muat seluruh qty
                if ($cell->capacity_remaining >= $qty) $score += 5000;
                // Prioritas 3: kapasitas tersisa terbesar
                $score += $cell->capacity_remaining;
                return $score;
            })
            ->take(6)
            ->values();

        return response()->json([
            'source_cell' => [
                'id'                 => $sourceCell->id,
                'code'               => $sourceCell->code,
                'zone'               => $sourceCell->rack?->zone?->name ?? '-',
                'zone_category'      => $sourceCell->zone_category,
                'capacity_remaining' => $sourceCell->capacity_remaining,
                'capacity_max'       => $sourceCell->capacity_max,
            ],
            'qty_needed'   => $qty,
            'alternatives' => $alternatives->map(fn(Cell $c) => [
                'id'                 => $c->id,
                'code'               => $c->code,
                'zone'               => $c->rack?->zone?->name ?? '-',
                'zone_category'      => $c->zone_category ?? $c->rack?->zone?->code ?? '-',
                'rack_code'          => $c->rack?->code ?? '-',
                'capacity_remaining' => $c->capacity_remaining,
                'capacity_max'       => $c->capacity_max,
                'capacity_used'      => $c->capacity_used,
                'status'             => $c->status,
                'fits_all'           => $c->capacity_remaining >= $qty,
            ])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fast/Slow Moving Suggestions — Saran cell berdasarkan frekuensi outbound
    // GET /putaway/{order}/fast-slow-suggestions
    // ─────────────────────────────────────────────────────────────────────────

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
                'message' => "Override berhasil. Item ditempatkan ke sel {$cell->code} (di luar rekomendasi GA).",
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
