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
use App\Services\CellCapacityService;
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
        $filterStatus = $request->input('status', '');  // '' = semua | 'put_away' | 'completed'
        $warehouseId  = $request->input('warehouse_id', '');
        $today        = now()->format('Y-m-d');
        $startDate    = $request->input('start_date', $today);
        $endDate      = $request->input('end_date', $today);

        // Auto-redirect ke warehouse yang punya order aktif jika belum ada filter warehouse
        if (!$request->has('warehouse_id')) {
            $defaultWarehouse = Warehouse::where('is_active', true)
                ->whereHas('inboundOrders', fn($q) => $q->where('status', 'put_away'))
                ->orderBy('name')
                ->first();
            if ($defaultWarehouse) {
                return redirect()->route('putaway.index', array_merge($request->query(), ['warehouse_id' => $defaultWarehouse->id]));
            }
        }

        $base = InboundOrder::with(['warehouse'])
            ->withCount([
                'items',
                'items as put_away_count' => fn($q) => $q->where('status', 'put_away'),
            ])
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($startDate, fn($q) => $q->whereDate('do_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('do_date', '<=', $endDate))
;

        // Antrian aktif — tampil kalau status = '' (semua) atau 'put_away'
        $orders = $filterStatus === 'completed'
            ? collect()
            : (clone $base)->where('status', 'put_away')->orderByDesc('created_at')->orderByDesc('do_date')->get();

        // Riwayat completed — tampil kalau status = '' (semua) atau 'completed'
        $completedOrders = $filterStatus === 'put_away'
            ? collect()
            : (clone $base)->where('status', 'completed')->orderByDesc('processed_at')->orderByDesc('do_date')->get();

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        return view('putaway.index', compact('orders', 'completedOrders', 'warehouses'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queue — Consolidated put-away list across all pending orders
    // GET /putaway/queue
    // ─────────────────────────────────────────────────────────────────────────

    public function queue(Request $request)
    {
        $queueFilters = $this->queueFilters($request);

        $activeOrderScope = function ($q) use ($request) {
            $q->where('status', 'accepted')
              ->whereHas('inboundOrder', function ($q2) use ($request) {
                  $q2->where('status', 'put_away');
                  $this->applyQueueOrderFilters($q2, $request);
              });
        };

        $items = GaRecommendationDetail::with([
            'gaRecommendation.inboundOrder',
            'inboundOrderItem.item.unit',
            'inboundOrderItem.item.category',
            'inboundOrderItem.putAwayConfirmations',
            'cell.rack',
        ])
        ->whereHas('gaRecommendation', $activeOrderScope)
        ->whereHas('inboundOrderItem', fn($q) => $q->whereIn('status', ['pending', 'partial_put_away']))
        ->whereDoesntHave('putAwayConfirmations')
        ->get()
        ->sortBy(function ($d) {
            $cell = $d->cell;
            if (!$cell) return '99999-Z-999-999';
            return sprintf(
                '%05d-%s-%03d-%03d',
                (int) ($cell->blok  ?? 99999),
                strtoupper((string) ($cell->grup  ?? 'Z')),
                (int) ($cell->kolom ?? 999),
                (int) ($cell->baris ?? 999)
            );
        })
        ->values();

        $activeDoQuery = InboundOrder::where('status', 'put_away')
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'));
        $this->applyQueueOrderFilters($activeDoQuery, $request);
        $activeDOs = $activeDoQuery->count();

        $completedDoQuery = InboundOrder::where('status', 'completed')
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'));
        $this->applyQueueOrderFilters($completedDoQuery, $request);
        $completedDOs = $completedDoQuery->count();

        $totalOrders = $items->pluck('gaRecommendation.inbound_order_id')->unique()->count();

        return view('putaway.queue', compact('items', 'totalOrders', 'activeDOs', 'completedDOs', 'queueFilters'));
    }

    private function queueFilters(Request $request): array
    {
        $today = now()->toDateString();
        $allActive = $request->boolean('all_active');

        return [
            'all_active' => $allActive,
            'start_date' => $allActive ? '' : ($request->input('start_date') ?: $today),
            'end_date'   => $allActive ? '' : ($request->input('end_date') ?: $today),
            'do_number' => trim((string) $request->input('do_number', '')),
        ];
    }

    private function applyQueueOrderFilters($query, Request $request): void
    {
        $filters = $this->queueFilters($request);

        if ($filters['do_number'] !== '') {
            $query->where('do_number', 'like', '%' . $filters['do_number'] . '%');
        }

        if (!$filters['all_active'] && $filters['start_date'] !== '') {
            $query->whereDate('do_date', '>=', $filters['start_date']);
        }

        if (!$filters['all_active'] && $filters['end_date'] !== '') {
            $query->whereDate('do_date', '<=', $filters['end_date']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Operator Mode — Tampilan sederhana tablet untuk operator lapangan
    // GET /putaway/operator
    // ─────────────────────────────────────────────────────────────────────────

    public function operatorMode(Request $request)
    {
        $user          = auth()->user();
        $today         = now()->toDateString();
        $allActive     = $request->boolean('all_active', false);
        $startDate     = $request->input('start_date', '');
        $endDate       = $request->input('end_date', '');
        $hasDateFilter = !$allActive && ($startDate !== '' || $endDate !== '');

        // mode: 'today' | 'range' | 'all'
        $mode = $allActive ? 'all' : ($hasDateFilter ? 'range' : 'today');

        $buildBaseQuery = function (string $m, string $s = '', string $e = '') use ($user, $today) {
            return GaRecommendationDetail::with([
                'gaRecommendation.inboundOrder',
                'inboundOrderItem.item.unit',
                'cell.rack',
                'putAwayConfirmations',
            ])
            ->whereHas('gaRecommendation', function ($q) use ($user, $m, $today, $s, $e) {
                $q->where('status', 'accepted')
                  ->whereHas('inboundOrder', function ($q2) use ($user, $m, $today, $s, $e) {
                      $q2->where('status', 'put_away');
                      if ($user->warehouse_id) {
                          $q2->where('warehouse_id', $user->warehouse_id);
                      }
                      if ($m === 'today') {
                          $q2->whereDate('do_date', $today);
                      } elseif ($m === 'range') {
                          if ($s !== '') $q2->whereDate('do_date', '>=', $s);
                          if ($e !== '') $q2->whereDate('do_date', '<=', $e);
                      }
                      // mode 'all': tanpa filter tanggal
                  });
            });
        };

        $buildQuery = function (string $m, string $s = '', string $e = '') use ($buildBaseQuery) {
            return $buildBaseQuery($m, $s, $e)
            ->whereHas('inboundOrderItem', fn($q) => $q->whereIn('status', ['pending', 'partial_put_away']))
            ->whereDoesntHave('putAwayConfirmations');
        };

        $sortFn = function ($d) {
            $c = $d->cell;
            if (!$c) return '99999-Z-999-999';
            return sprintf('%05d-%s-%03d-%03d',
                (int)($c->blok ?? 99999),
                strtoupper((string)($c->grup ?? 'Z')),
                (int)($c->kolom ?? 999),
                (int)($c->baris ?? 999)
            );
        };

        $items = $buildQuery($mode, $startDate, $endDate)->get()->sortBy($sortFn)->values();
        $scopeDetails = $buildBaseQuery($mode, $startDate, $endDate)->get();
        $operatorSummary = [
            'sj_count'        => $scopeDetails
                ->pluck('gaRecommendation.inboundOrder.id')
                ->filter()
                ->unique()
                ->count(),
            'total_lines'     => $scopeDetails->count(),
            'total_qty'       => (int) $scopeDetails->sum('quantity'),
            'completed_lines' => $scopeDetails
                ->filter(fn($detail) => $detail->putAwayConfirmations->isNotEmpty())
                ->count(),
            'waiting_lines'   => $items->count(),
        ];

        $otherActiveCount = ($mode === 'today' && $items->isEmpty())
            ? $buildQuery('all')->count()
            : 0;

        return view('putaway.operator', compact(
            'items', 'allActive', 'today', 'otherActiveCount',
            'startDate', 'endDate', 'hasDateFilter', 'operatorSummary'
        ));
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
            'quantity'    => 'nullable|integer|min:1',
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
                        'capacity_demand' => app(CellCapacityService::class)->pointsForQuantity(
                            $detail->item,
                            (int) ($request->input('quantity') ?: $detail->quantity_received)
                        ),
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
            'split_cell_id'    => 'nullable|exists:cells,id|different:cell_id',
            'split_quantity_stored' => 'nullable|integer|min:1',
            'notes'            => 'nullable|string|max:255',
        ]);

        $order  = InboundOrder::findOrFail($orderId);
        $detail = InboundOrderItem::where('inbound_order_id', $orderId)
            ->findOrFail($detailId);
        $cell   = Cell::findOrFail($request->cell_id);
        $splitCell = $request->filled('split_cell_id')
            ? Cell::findOrFail($request->split_cell_id)
            : null;
        $splitQty = $request->filled('split_quantity_stored')
            ? (int) $request->split_quantity_stored
            : 0;

        if (($splitCell && $splitQty <= 0) || (!$splitCell && $splitQty > 0)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data split penempatan tidak lengkap.',
            ], 422);
        }

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
            $primaryQty = (int) $request->quantity_stored;
            $remainingQty = max(0, (int) $detail->quantity_received - (int) $detail->putAwayConfirmations()->sum('quantity_stored'));

            if ($primaryQty + $splitQty > $remainingQty) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Total qty penempatan ({$primaryQty} + {$splitQty}) melebihi sisa qty item ({$remainingQty}).",
                ], 422);
            }

            $confirmation = DB::transaction(function () use ($detail, $cell, $splitCell, $primaryQty, $splitQty, $gaDetail, $request) {
                $primaryConfirmation = $this->putAwayService->confirmPlacement(
                    detail: $detail,
                    cell: $cell,
                    quantityStored: $primaryQty,
                    userId: auth()->id(),
                    gaDetail: $gaDetail,
                    notes: $request->notes,
                );

                if ($splitCell && $splitQty > 0) {
                    $this->putAwayService->confirmPlacement(
                        detail: $detail->fresh(['item', 'inboundOrder']),
                        cell: $splitCell,
                        quantityStored: $splitQty,
                        userId: auth()->id(),
                        gaDetail: null,
                        notes: trim(($request->notes ? $request->notes . ' ' : '') . '[SPLIT_ALT] dari auto split put-away'),
                    );
                }

                return $primaryConfirmation;
            });

            $order->refresh();
            $doneCount  = $order->items()->where('status', 'put_away')->count();
            $totalCount = $order->items()->count();

            $message = $splitCell
                ? "Item berhasil di-put-away split ke {$cell->physical_code} dan {$splitCell->physical_code}."
                : "Item berhasil di-put-away ke lokasi {$cell->physical_label}.";

            return response()->json([
                'status'   => 'success',
                'message'  => $message,
                'progress' => [
                    'done'        => $doneCount,
                    'total'       => $totalCount,
                    'percent'     => $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0,
                    'is_complete' => $order->status === 'completed',
                ],
                'follow_recommendation' => $confirmation->follow_recommendation,
                'cell_code'             => $cell->physical_code,
                'split_cell_code'       => $splitCell?->physical_code,
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
            'detail_id'   => 'nullable|integer|exists:inbound_details,id',
        ]);

        $order      = InboundOrder::findOrFail($orderId);
        $sourceCell = Cell::with('rack')->findOrFail($request->for_cell_id);
        $qty        = (int) $request->qty;
        $detail     = $request->filled('detail_id')
            ? InboundOrderItem::with('item')->find((int) $request->detail_id)
            : null;
        $capacityService = app(CellCapacityService::class);
        $capacityDemand = $detail
            ? $capacityService->pointsForQuantity($detail->item, $qty)
            : $qty;

        // Cari cell alternatif di warehouse yang sama.
        // Do not subtract unsigned DB columns here: overfilled cells can make
        // MySQL underflow before PHP can clamp the remaining capacity to zero.
        $alternatives = $this->rankedAlternativeCells($sourceCell, $order, $capacityDemand, 6);

        return response()->json([
            'source_cell' => [
                'id'                 => $sourceCell->id,
                'code'               => $sourceCell->physical_code,
                'capacity_remaining' => $sourceCell->physical_capacity_remaining,
                'capacity_max'       => $sourceCell->physical_capacity_max,
            ],
            'qty_needed'   => $qty,
            'capacity_needed' => $capacityDemand,
            'alternatives' => $alternatives->map(fn(Cell $c) => [
                'id'                 => $c->id,
                'code'               => $c->physical_code,
                'rack_code'          => $c->rack?->code ?? '-',
                'capacity_remaining' => $c->physical_capacity_remaining,
                'capacity_max'       => $c->physical_capacity_max,
                'capacity_used'      => $c->physical_capacity_used,
                'status'             => $c->status,
                'fits_all'           => $c->physical_capacity_remaining >= $capacityDemand,
                'distance_score'     => $this->cellDistanceScore($sourceCell, $c),
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
        $request->validate([
            'qr_code' => 'required|string',
            'override' => 'nullable|boolean',
        ]);

        $qrCode = $request->qr_code;
        $isOverride = $request->boolean('override');

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
        $targetCell  = null;
        $displayCode = $qrCode;
        $displayRack = '-';

        if ($exactCell) {
            $cellIds     = collect([$exactCell->id]);
            $targetCell  = $exactCell;
            $displayCode = $exactCell->physical_code;
            $displayRack = $exactCell->rack?->code ?? '-';

            // Column-level cell (baris=null) → expand to all shelf cells in this column.
            // Do NOT filter by is_active — GA may have assigned items to cells that
            // were later deactivated; we still need to surface those pending details.
            if (!$isOverride
                && $exactCell->blok !== null
                && $exactCell->grup !== null
                && $exactCell->kolom !== null
                && $exactCell->baris === null
            ) {
                $shelfIds = Cell::where('blok', $exactCell->blok)
                    ->whereRaw('UPPER(CAST(grup AS CHAR)) = ?', [strtoupper((string) $exactCell->grup)])
                    ->where('kolom', $exactCell->kolom)
                    ->whereNotNull('baris')
                    ->pluck('id');
                if ($shelfIds->isNotEmpty()) {
                    $cellIds     = $shelfIds;
                    $displayCode = strtoupper($exactCell->blok . '-' . $exactCell->grup . '-' . $exactCell->kolom) . ' (Kolom)';
                }
            }

            if ($isOverride && $exactCell->blok !== null && $exactCell->grup !== null) {
                $cellIds = Cell::where('blok', $exactCell->blok)
                    ->whereRaw('UPPER(CAST(grup AS CHAR)) = ?', [strtoupper((string) $exactCell->grup)])
                    ->where('is_active', true)
                    ->pluck('id');
            }
        } else {
            // ── Try 2: blok-grup-kolom column QR (e.g. "1-A-1" → all cells blok=1,grup=A,kolom=1) ─
            $parts = explode('-', $qrCode);
            if (count($parts) === 3) {
                $blok  = $parts[0];
                $grup  = $parts[1];
                $kolom = $parts[2];

                $cellIds = Cell::where('blok', $blok)
                    ->whereRaw('UPPER(CAST(grup AS CHAR)) = ?', [strtoupper($grup)])
                    ->where('kolom', $kolom)
                    ->where('is_active', true)
                    ->pluck('id');

                $displayCode = strtoupper($blok . '-' . $grup . '-' . $kolom) . ' (Kolom)';
                $displayRack = strtoupper($blok . '-' . $grup);

                if ($isOverride) {
                    $targetCell = Cell::where('blok', $blok)
                        ->whereRaw('UPPER(CAST(grup AS CHAR)) = ?', [strtoupper($grup)])
                        ->where('kolom', $kolom)
                        ->where('is_active', true)
                        ->with('rack')
                        ->get()
                        ->filter(fn(Cell $cell) => $cell->physical_capacity_remaining > 0)
                        ->sortBy(fn(Cell $cell) => (int) $cell->baris)
                        ->first();

                    if ($targetCell) {
                        $displayCode = $targetCell->physical_code . ' (Override)';
                    }
                }
            }

            // ── Try 3: blok-grup rack QR (e.g. "1-A" → all cells blok=1, grup=A) ─
            if (count($parts) === 2) {
                $blok = $parts[0];
                $grup = $parts[1];   // MySQL string compare is CI by default

                $cellIds = Cell::where('blok', $blok)
                    ->whereRaw('UPPER(CAST(grup AS CHAR)) = ?', [strtoupper($grup)])
                    ->where('is_active', true)
                    ->pluck('id');

                $displayCode = strtoupper($qrCode) . ' (Rak)';
                $displayRack = strtoupper($qrCode);

                if ($isOverride) {
                    $targetCell = Cell::where('blok', $blok)
                        ->whereRaw('UPPER(CAST(grup AS CHAR)) = ?', [strtoupper($grup)])
                        ->where('is_active', true)
                        ->with('rack')
                        ->get()
                        ->filter(fn(Cell $cell) => $cell->physical_capacity_remaining > 0)
                        ->sortBy(function (Cell $cell) {
                            return sprintf(
                                '%d-%03d-%03d',
                                $cell->status === 'available' ? 0 : 1,
                                (int) $cell->kolom,
                                (int) $cell->baris
                            );
                        })
                        ->first();

                    if ($targetCell) {
                        $displayCode = $targetCell->physical_code . ' (Override)';
                    }
                }
            }
        }

        if ($cellIds->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => "QR Code '{$qrCode}' tidak ditemukan. Pastikan sel/rak aktif di sistem.",
            ], 404);
        }

        if ($isOverride && !$targetCell) {
            return response()->json([
                'status'  => 'error',
                'message' => "Tidak ada cell aktif yang masih punya kapasitas untuk override di '{$qrCode}'.",
            ], 422);
        }

        $details = GaRecommendationDetail::with([
            'gaRecommendation.inboundOrder',
            'inboundOrderItem.item.unit',
            'cell',
        ])
        ->whereIn('cell_id', $cellIds)
        ->whereHas('gaRecommendation', function ($q) use ($request) {
            $q->where('status', 'accepted')
              ->whereHas('inboundOrder', function ($q2) use ($request) {
                  $q2->where('status', 'put_away');
                  $this->applyQueueOrderFilters($q2, $request);
              });
        })
        ->whereHas('inboundOrderItem', fn($q) => $q->whereIn('status', ['pending', 'partial_put_away']))
        ->get()
        ->sortBy(fn($d) => (int) ($d->cell?->baris ?? 9999))
        ->values();

        $capacityService = app(CellCapacityService::class);
        $overrideRemaining = $targetCell ? $capacityService->remainingPoints($targetCell) : 0;
        $overrideAllocatedQty = [];
        $skippedCapacity = 0;

        $batchItems = $details->map(function ($d) use ($capacityService, $isOverride, $targetCell, &$overrideRemaining, &$overrideAllocatedQty, &$skippedCapacity) {
                $detail = $d->inboundOrderItem;
                $cell = $d->cell;
                $order = $d->gaRecommendation->inboundOrder;
                $qty = (int) $d->quantity;

                if ($isOverride && $targetCell) {
                    $itemId = (int) $detail->item_id;
                    $alreadyAllocated = (int) ($overrideAllocatedQty[$itemId] ?? 0);
                    $overrideDemand = $capacityService->pointsForQuantity($detail->item, $qty);

                    if ($overrideDemand > $overrideRemaining) {
                        $skippedCapacity++;
                        return null;
                    }

                    $overrideRemaining -= $overrideDemand;
                    $overrideAllocatedQty[$itemId] = $alreadyAllocated + $qty;

                    return [
                        'ga_detail_id'       => $d->id,
                        'order_id'           => $order->id,
                        'detail_id'          => $d->inbound_order_item_id,
                        'cell_id'            => $targetCell->id,
                        'cell_code'          => $targetCell->physical_code,
                        'ga_cell_code'       => $cell?->physical_code ?? $cell?->code ?? '-',
                        'row_id'             => 'row-ga-' . $d->id,
                        'item_name'          => $detail->item->name ?? '-',
                        'item_sku'           => $detail->item->sku ?? '-',
                        'unit'               => $detail->item->unit?->code ?? '-',
                        'quantity'           => $qty,
                        'primary_quantity'   => $qty,
                        'split_quantity'     => 0,
                        'requires_split'     => false,
                        'split_ready'        => false,
                        'capacity_remaining' => $overrideRemaining,
                        'capacity_needed'    => $overrideDemand,
                        'do_number'          => $order->do_number,
                        'alt_cell'           => null,
                        'is_override'        => true,
                    ];
                }

                $capacityDemand = $capacityService->pointsForQuantity($detail->item, $qty);
                $capacityRemaining = $cell?->physical_capacity_remaining ?? 0;
                $fitsAll = $capacityRemaining >= $capacityDemand;
                $maxFitQty = $fitsAll
                    ? $qty
                    : $this->maxFitQuantityForCell($cell, $detail, $qty);
                $splitQty = max(0, $qty - $maxFitQty);

                $altCell = null;
                if ($splitQty > 0 && $maxFitQty > 0 && $cell) {
                    $splitDemand = $capacityService->pointsForQuantity($detail->item, $splitQty);
                    $altCell = $this->rankedAlternativeCells($cell, $order, $splitDemand, 6)
                        ->first(fn(Cell $candidate) => $candidate->physical_capacity_remaining >= $splitDemand);
                }

                return [
                    'ga_detail_id'       => $d->id,
                    'order_id'           => $order->id,
                    'detail_id'          => $d->inbound_order_item_id,
                    'cell_id'            => $d->cell_id,
                    'cell_code'          => $cell?->physical_code ?? $cell?->code ?? '-',
                    'row_id'             => 'row-ga-' . $d->id,
                    'item_name'          => $detail->item->name ?? '-',
                    'item_sku'           => $detail->item->sku ?? '-',
                    'unit'               => $detail->item->unit?->code ?? '-',
                    'quantity'           => $qty,
                    'primary_quantity'   => $maxFitQty,
                    'split_quantity'     => $altCell ? $splitQty : 0,
                    'requires_split'     => $splitQty > 0,
                    'split_ready'        => (bool) $altCell,
                    'capacity_remaining' => $capacityRemaining,
                    'capacity_needed'    => $capacityDemand,
                    'do_number'          => $order->do_number,
                    'ga_cell_code'       => $cell?->physical_code ?? $cell?->code ?? '-',
                    'is_override'        => false,
                    'alt_cell'           => $altCell ? [
                        'id' => $altCell->id,
                        'code' => $altCell->physical_code,
                        'rack_code' => $altCell->rack?->code ?? '-',
                        'capacity_remaining' => $altCell->physical_capacity_remaining,
                    ] : null,
                ];
            })->filter()->values();

        return response()->json([
            'status'  => $batchItems->isEmpty() ? 'empty' : 'found',
            'message' => $batchItems->isEmpty()
                ? ($isOverride ? 'Tidak ada item yang bisa dioverride ke cell ini. Cek rak GA atau kapasitas cell tujuan.' : 'Tidak ada item pending untuk cell/rak ini.')
                : null,
            'is_override' => $isOverride,
            'display_code' => $displayCode,
            'display_rack' => $displayRack,
            'override_target_cell' => $targetCell ? [
                'id' => $targetCell->id,
                'code' => $targetCell->physical_code,
                'rack_code' => $targetCell->rack?->code ?? '-',
                'capacity_remaining' => $targetCell->physical_capacity_remaining,
            ] : null,
            'items' => $batchItems,
            'skipped_capacity' => $skippedCapacity,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch Confirm — simpan semua item sekaligus (AJAX)
    // POST /putaway/batch-confirm
    // ─────────────────────────────────────────────────────────────────────────

    public function batchConfirm(Request $request)
    {
        $items = collect($request->input('items', []))
            ->map(function ($item) {
                $hasSplitCell = !empty($item['split_cell_id']);
                $hasPositiveSplitQty = isset($item['split_quantity']) && (int) $item['split_quantity'] > 0;

                if (!$hasSplitCell && !$hasPositiveSplitQty) {
                    unset($item['split_cell_id'], $item['split_quantity']);
                }

                return $item;
            })
            ->all();

        $request->merge(['items' => $items]);

        $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.cell_id'      => 'required|exists:cells,id',
            'items.*.order_id'     => 'required|exists:inbound_transactions,id',
            'items.*.detail_id'    => 'required|exists:inbound_details,id',
            'items.*.ga_detail_id' => 'nullable|integer',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.split_cell_id' => 'nullable|exists:cells,id',
            'items.*.split_quantity' => 'nullable|integer|min:1',
            'items.*.is_override'  => 'nullable|boolean',
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
                $splitCell = !empty($item['split_cell_id'])
                    ? Cell::findOrFail($item['split_cell_id'])
                    : null;
                $splitQty = !empty($item['split_quantity'])
                    ? (int) $item['split_quantity']
                    : 0;

                if (($splitCell && $splitQty <= 0) || (!$splitCell && $splitQty > 0)) {
                    throw new \Exception('Data split batch tidak lengkap.');
                }
                if ($splitCell && $splitCell->id === $cell->id) {
                    throw new \Exception('Cell split batch tidak boleh sama dengan cell utama.');
                }

                $detail = InboundOrderItem::where('inbound_order_id', $item['order_id'])
                    ->findOrFail($item['detail_id']);

                if ($detail->status === 'put_away') {
                    $skipped++;
                    continue;
                }

                $isOverride = !empty($item['is_override']);
                $gaDetail = !$isOverride && !empty($item['ga_detail_id'])
                    ? GaRecommendationDetail::find($item['ga_detail_id'])
                    : null;

                $primaryQty = (int) $item['quantity'];
                $remainingQty = max(0, (int) $detail->quantity_received - (int) $detail->putAwayConfirmations()->sum('quantity_stored'));
                if ($primaryQty + $splitQty > $remainingQty) {
                    throw new \Exception('Total qty batch melebihi sisa qty item.');
                }

                DB::transaction(function () use ($detail, $cell, $splitCell, $primaryQty, $splitQty, $gaDetail, $request, $isOverride) {
                    $notes = $isOverride
                        ? trim('[OVERRIDE_BATCH] ' . ($request->notes ?? ''))
                        : ($request->notes ?? '');

                    $this->putAwayService->confirmPlacement(
                        detail: $detail,
                        cell: $cell,
                        quantityStored: $primaryQty,
                        userId: auth()->id(),
                        gaDetail: $gaDetail,
                        notes: $notes,
                    );

                    if ($splitCell && $splitQty > 0) {
                        $this->putAwayService->confirmPlacement(
                            detail: $detail->fresh(['item', 'inboundOrder']),
                            cell: $splitCell,
                            quantityStored: $splitQty,
                            userId: auth()->id(),
                            gaDetail: null,
                            notes: trim(($request->notes ? $request->notes . ' ' : '') . '[SPLIT_ALT] dari batch put-away'),
                        );
                    }
                });
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
            'split_cell_id'   => 'nullable|exists:cells,id|different:cell_id',
            'split_quantity_stored' => 'nullable|integer|min:1',
            'notes'           => 'nullable|string|max:255',
        ]);

        $detail = InboundOrderItem::where('inbound_order_id', $orderId)->findOrFail($detailId);
        $cell   = Cell::findOrFail($request->cell_id);
        $splitCell = $request->filled('split_cell_id')
            ? Cell::findOrFail($request->split_cell_id)
            : null;
        $splitQty = $request->filled('split_quantity_stored')
            ? (int) $request->split_quantity_stored
            : 0;

        if (($splitCell && $splitQty <= 0) || (!$splitCell && $splitQty > 0)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data split override tidak lengkap.',
            ], 422);
        }

        $order = InboundOrder::findOrFail($orderId);

        try {
            $primaryQty = (int) $request->quantity_stored;
            $remainingQty = max(0, (int) $detail->quantity_received - (int) $detail->putAwayConfirmations()->sum('quantity_stored'));

            if ($primaryQty + $splitQty > $remainingQty) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Total qty override ({$primaryQty} + {$splitQty}) melebihi sisa qty item ({$remainingQty}).",
                ], 422);
            }

            // Override = confirm tanpa ga_detail (follow_recommendation akan false)
            DB::transaction(function () use ($detail, $cell, $splitCell, $primaryQty, $splitQty, $request) {
                $this->putAwayService->confirmPlacement(
                    detail: $detail,
                    cell: $cell,
                    quantityStored: $primaryQty,
                    userId: auth()->id(),
                    gaDetail: null,
                    notes: '[OVERRIDE] ' . ($request->notes ?? ''),
                );

                if ($splitCell && $splitQty > 0) {
                    $this->putAwayService->confirmPlacement(
                        detail: $detail->fresh(['item', 'inboundOrder']),
                        cell: $splitCell,
                        quantityStored: $splitQty,
                        userId: auth()->id(),
                        gaDetail: null,
                        notes: trim('[OVERRIDE_SPLIT] ' . ($request->notes ?? '')),
                    );
                }
            });

            $order->refresh();
            $doneCount  = $order->items()->where('status', 'put_away')->count();
            $totalCount = $order->items()->count();

            $message = $splitCell
                ? "Override berhasil. Item ditempatkan split ke {$cell->physical_code} dan {$splitCell->physical_code}."
                : "Override berhasil. Item ditempatkan ke lokasi {$cell->physical_label} (di luar rekomendasi GA).";

            return response()->json([
                'status'  => 'success',
                'message' => $message,
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

    private function cellDistanceScore(Cell $from, Cell $to): int
    {
        if (
            $from->blok === null || $from->grup === null || $from->kolom === null || $from->baris === null
            || $to->blok === null || $to->grup === null || $to->kolom === null || $to->baris === null
        ) {
            return 9999;
        }

        $blokDistance = abs((int) $from->blok - (int) $to->blok);
        $grupDistance = abs($this->grupIndex((string) $from->grup) - $this->grupIndex((string) $to->grup));
        $kolomDistance = abs((int) $from->kolom - (int) $to->kolom);
        $barisDistance = abs((int) $from->baris - (int) $to->baris);

        return ($blokDistance * 100) + ($grupDistance * 25) + ($kolomDistance * 5) + $barisDistance;
    }

    private function rankedAlternativeCells(Cell $sourceCell, InboundOrder $order, int $capacityDemand, int $take = 6)
    {
        return Cell::with('rack')
            ->where('is_active', true)
            ->where('id', '!=', $sourceCell->id)
            ->whereColumn('capacity_max', '>', 'capacity_used')
            ->whereHas('rack', fn($q) => $q->where('warehouse_id', $order->warehouse_id))
            ->get()
            ->filter(fn(Cell $cell) => $cell->physical_capacity_remaining > 0)
            ->sortByDesc(function (Cell $cell) use ($sourceCell, $capacityDemand) {
                $remaining = $cell->physical_capacity_remaining;
                $distance = $this->cellDistanceScore($sourceCell, $cell);
                $sameRack = (int) $cell->blok === (int) $sourceCell->blok
                    && strtoupper((string) $cell->grup) === strtoupper((string) $sourceCell->grup);

                $score = 0;
                if ($remaining >= $capacityDemand) $score += 1_000_000;
                if ($sameRack) $score += 500_000;
                elseif ((int) $cell->blok === (int) $sourceCell->blok) $score += 150_000;

                if ((int) $cell->kolom === (int) $sourceCell->kolom) $score += 20_000;
                if ((int) $cell->baris === (int) $sourceCell->baris) $score += 10_000;

                $score += max(0, 100_000 - ($distance * 1_000));
                $score += min($remaining, 100);

                return $score;
            })
            ->take($take)
            ->values();
    }

    private function maxFitQuantityForCell(?Cell $cell, InboundOrderItem $detail, int $quantity): int
    {
        if (!$cell || $quantity <= 0) {
            return 0;
        }

        return max(0, min($quantity, (int) $cell->physical_capacity_remaining));
    }

    private function grupIndex(string $grup): int
    {
        $letter = strtoupper(trim($grup))[0] ?? 'Z';

        return max(0, ord($letter) - ord('A'));
    }
}
