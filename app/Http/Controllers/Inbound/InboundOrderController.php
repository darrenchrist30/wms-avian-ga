<?php

namespace App\Http\Controllers\Inbound;

use App\Http\Controllers\Controller;
use App\Models\GaRecommendation;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\GaAcceptedNotification;
use App\Notifications\GaBatchAcceptedNotification;
use App\Services\GaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class InboundOrderController extends Controller
{
    public function __construct(
        private readonly GaService $gaService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD Standard
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $suppliers  = \App\Models\Supplier::where('is_active', true)->orderBy('name')->get();
        return view('inbound.orders.index', compact('warehouses', 'suppliers'));
    }

    public function datatable(Request $request)
    {
        $query = InboundOrder::with(['warehouse', 'receivedBy'])
            ->withCount('items')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('checkbox', function ($row) {
                $eligible = $row->status === 'inbound';
                if (!$eligible) return '';
                return '<input type="checkbox" class="order-check" value="' . $row->id . '"
                    data-do="' . e($row->do_number) . '">';
            })
            ->editColumn('do_date', function ($row) {
                $formatted = $row->do_date?->format('d M Y') ?? '-';
                if ($row->do_date && $row->do_date->isToday()) {
                    $formatted .= ' <span class="badge badge-danger" style="font-size:9px;vertical-align:middle">Hari Ini</span>';
                }
                return $formatted;
            })
            ->addColumn('status_badge', function ($row) {
                $map = [
                    'inbound'   => ['badge-warning',  'Inbound'],
                    'put_away'  => ['badge-primary',  'Put-Away'],
                    'completed' => ['badge-success',  'Completed'],
                ];
                [$cls, $label] = $map[$row->status] ?? ['badge-secondary', ucfirst($row->status)];
                return '<span class="badge ' . $cls . '">' . $label . '</span>';
            })
            ->addColumn('action', function ($row) {
                $showUrl = route('inbound.orders.show', $row->id);
                $html    = '<a href="' . $showUrl . '" class="btn btn-xs btn-info" title="Detail"><i class="fas fa-eye"></i></a> ';
                if ($row->status === 'inbound') {
                    $editUrl = route('inbound.orders.edit', $row->id);
                    $html   .= '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                    $html   .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->do_number) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
                return $html;
            })
            ->rawColumns(['checkbox', 'do_date', 'status_badge', 'action'])
            ->make(true);
    }

    public function create()
    {
        $items      = Item::where('is_active', true)->with('unit')->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $suppliers  = \App\Models\Supplier::where('is_active', true)->orderBy('name')->get();
        return view('inbound.orders.form', [
            'typeForm'   => 'create',
            'data'       => null,
            'items'      => $items,
            'warehouses' => $warehouses,
            'suppliers'  => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'              => 'required|exists:warehouses,id',
            'do_number'                 => 'required|string|max:100|unique:inbound_transactions,do_number',
            'erp_reference'             => 'nullable|string|max:100',
            'do_date'                   => 'required|date',
            'notes'                     => 'nullable|string',
            'items'                     => 'required|array|min:1',
            'items.*.item_id'           => 'required|exists:items,id',
            'items.*.quantity_ordered'  => 'required|integer|min:1',
            'items.*.lpn'               => 'nullable|string|max:100',
            'items.*.notes'             => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $order = InboundOrder::create([
                'warehouse_id'  => $request->warehouse_id,
                'received_by'   => auth()->id(),
                'do_number'     => strtoupper($request->do_number),
                'erp_reference' => $request->erp_reference,
                'do_date'       => $request->do_date,
                'received_at'   => now(),
                'status'        => 'inbound',
                'notes'         => $request->notes,
            ]);

            foreach ($request->items as $row) {
                InboundOrderItem::create([
                    'inbound_order_id'  => $order->id,
                    'item_id'           => $row['item_id'],
                    'lpn'               => $row['lpn'] ?? null,
                    'quantity_ordered'  => $row['quantity_ordered'],
                    'quantity_received' => 0,
                    'status'            => 'pending',
                    'notes'             => $row['notes'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('inbound.orders.show', $order->id)
                ->with('success', 'Inbound order ' . strtoupper($request->do_number) . ' berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan order. Silakan coba lagi.');
        }
    }

    public function show($id)
    {
        $order = InboundOrder::with([
            'warehouse',
            'supplier',
            'receivedBy',
            'items.item.unit',
            'items.item.category',
            'gaRecommendations' => fn($q) => $q->latest()->with([
                'details.cell.rack.zone',
                'details.inboundOrderItem.item',
                'generatedBy',
            ]),
        ])->findOrFail($id);

        $latestGa = $order->gaRecommendations->first();

        return view('inbound.orders.show', compact('order', 'latestGa'));
    }

    public function edit($id)
    {
        $order = InboundOrder::with('items')->findOrFail($id);
        if ($order->status !== 'inbound') {
            return redirect()->route('inbound.orders.show', $id)
                ->with('error', 'Hanya order berstatus Inbound yang dapat diedit.');
        }
        $items      = Item::where('is_active', true)->with('unit')->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $suppliers  = \App\Models\Supplier::where('is_active', true)->orderBy('name')->get();
        return view('inbound.orders.form', [
            'typeForm'   => 'edit',
            'data'       => $order,
            'items'      => $items,
            'warehouses' => $warehouses,
            'suppliers'  => $suppliers,
        ]);
    }

    public function update(Request $request, $id)
    {
        $order = InboundOrder::findOrFail($id);
        if ($order->status !== 'inbound') {
            return back()->with('error', 'Hanya order berstatus Inbound yang dapat diedit.');
        }

        $request->validate([
            'warehouse_id'              => 'required|exists:warehouses,id',
            'do_number'                 => 'required|string|max:100|unique:inbound_transactions,do_number,' . $id,
            'erp_reference'             => 'nullable|string|max:100',
            'do_date'                   => 'required|date',
            'notes'                     => 'nullable|string',
            'items'                     => 'required|array|min:1',
            'items.*.item_id'           => 'required|exists:items,id',
            'items.*.quantity_ordered'  => 'required|integer|min:1',
            'items.*.lpn'               => 'nullable|string|max:100',
            'items.*.notes'             => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'warehouse_id'  => $request->warehouse_id,
                'do_number'     => strtoupper($request->do_number),
                'erp_reference' => $request->erp_reference,
                'do_date'       => $request->do_date,
                'notes'         => $request->notes,
            ]);

            $order->items()->delete();
            foreach ($request->items as $row) {
                InboundOrderItem::create([
                    'inbound_order_id'  => $order->id,
                    'item_id'           => $row['item_id'],
                    'lpn'               => $row['lpn'] ?? null,
                    'quantity_ordered'  => $row['quantity_ordered'],
                    'quantity_received' => 0,
                    'status'            => 'pending',
                    'notes'             => $row['notes'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('inbound.orders.show', $order->id)
                ->with('success', 'Inbound order berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui order. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $order = InboundOrder::findOrFail($id);
        if ($order->status !== 'inbound') {
            return response()->json(['status' => 'error', 'message' => 'Hanya order berstatus Inbound yang dapat dihapus.'], 422);
        }
        DB::beginTransaction();
        try {
            $order->items()->delete();
            $order->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Inbound order berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 1 — Trigger GA
    // POST /inbound/orders/{order}/process-ga
    // qty_received = qty_ordered (otomatis), hasil GA selalu auto-accept → put_away
    // ─────────────────────────────────────────────────────────────────────────

    public function processGA(Request $request, $id)
    {
        $order = InboundOrder::with('items')->findOrFail($id);

        if ($order->status !== 'inbound') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya order berstatus Inbound yang dapat diproses GA.',
            ], 422);
        }

        // Auto-set quantity_received = quantity_ordered
        foreach ($order->items as $item) {
            if ($item->quantity_received == 0) {
                $item->update(['quantity_received' => $item->quantity_ordered, 'status' => 'pending']);
            }
        }
        $order->update(['received_by' => auth()->id(), 'received_at' => now()]);
        $order->load('items');

        try {
            $recommendation = $this->gaService->run($order, auth()->id());

            $recommendation->update([
                'status'      => 'accepted',
                'accepted_by' => auth()->id(),
                'accepted_at' => now(),
            ]);

            $order->update(['status' => 'put_away']);

            $notifUsers = User::whereHas('role', fn($q) => $q->whereIn('slug', ['admin', 'supervisor', 'operator']))->get();
            \Illuminate\Support\Facades\Notification::send(
                $notifUsers,
                new GaAcceptedNotification($order->fresh(), auth()->user()->name, $recommendation->fitness_score)
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'GA selesai. Fitness: ' . round($recommendation->fitness_score, 2) . '/100. Operator dapat langsung memulai put-away.',
                'data'    => [
                    'ga_recommendation_id' => $recommendation->id,
                    'fitness_score'        => $recommendation->fitness_score,
                    'redirect'             => route('putaway.show', $order->id),
                ],
            ]);
        } catch (\Exception $e) {
            $order->update(['status' => 'inbound']);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BATCH GA — Jalankan GA untuk banyak order sekaligus
    // POST /inbound/orders/batch-ga  { order_ids: [1,2,3,...] }
    // ─────────────────────────────────────────────────────────────────────────

    public function batchProcessGA(Request $request)
    {
        $request->validate([
            'order_ids'   => 'required|array|min:1|max:50',
            'order_ids.*' => 'required|integer|exists:inbound_transactions,id',
        ]);

        $results     = [];
        $validOrders = [];

        // ── Fase 1: Validasi & siapkan semua order (sekuensial) ──────────────
        foreach ($request->order_ids as $orderId) {
            $order = InboundOrder::with('items')->find($orderId);

            if (!$order) {
                $results[] = ['id' => $orderId, 'do_number' => '?', 'status' => 'error', 'message' => 'Order tidak ditemukan.'];
                continue;
            }

            if ($order->status !== 'inbound') {
                $results[] = ['id' => $orderId, 'do_number' => $order->do_number, 'status' => 'skip', 'message' => 'Status ' . $order->status . ' tidak dapat diproses GA.'];
                continue;
            }

            // Auto-set quantity_received = quantity_ordered
            foreach ($order->items as $item) {
                if ($item->quantity_received == 0) {
                    $item->update(['quantity_received' => $item->quantity_ordered, 'status' => 'pending']);
                }
            }
            $order->update(['received_by' => auth()->id(), 'received_at' => now()]);
            $order->load('items');

            $validOrders[] = $order;
        }

        // ── Fase 2: Jalankan GA untuk semua order secara paralel ─────────────
        if (!empty($validOrders)) {
            $batchResults = $this->gaService->runBatch($validOrders, auth()->id());

            foreach ($validOrders as $order) {
                $entry = $batchResults[$order->id];

                if ($entry['error'] !== null) {
                    $order->update(['status' => 'inbound']);
                    $results[] = [
                        'id'        => $order->id,
                        'do_number' => $order->do_number,
                        'status'    => 'error',
                        'message'   => $entry['error'],
                    ];
                    continue;
                }

                $recommendation = $entry['rec'];
                $recommendation->update([
                    'status'      => 'accepted',
                    'accepted_by' => auth()->id(),
                    'accepted_at' => now(),
                ]);
                $order->update(['status' => 'put_away']);

                $results[] = [
                    'id'            => $order->id,
                    'do_number'     => $order->do_number,
                    'status'        => 'accepted',
                    'message'       => 'GA selesai & otomatis diterima. Fitness: ' . round($recommendation->fitness_score, 1) . '/100.',
                    'fitness_score' => round($recommendation->fitness_score, 2),
                    'putaway_url'   => route('putaway.queue'),
                ];
            }
        }

        $total    = count($results);
        $accepted = collect($results)->where('status', 'accepted')->count();
        $errors   = collect($results)->whereIn('status', ['error', 'skip'])->count();

        // Send one batch notification for all accepted orders
        if ($accepted > 0) {
            $notifUsers = User::whereHas('role', fn($q) => $q->whereIn('slug', ['admin', 'supervisor', 'operator']))->get();
            \Illuminate\Support\Facades\Notification::send(
                $notifUsers,
                new GaBatchAcceptedNotification($accepted, auth()->user()->name)
            );
        }

        return response()->json([
            'success' => true,
            'summary' => compact('total', 'accepted', 'errors') + ['review' => 0],
            'results' => $results,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ERP Sync (placeholder, diimplementasi saat integrasi ERP)
    // ─────────────────────────────────────────────────────────────────────────

    public function syncFromErp(Request $request, $order)
    {
        return response()->json(['status' => 'info', 'message' => 'Fitur sync ERP belum diimplementasi.']);
    }
}
