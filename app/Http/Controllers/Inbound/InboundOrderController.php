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
use App\Notifications\QtyConfirmedNotification;
use App\Services\GaService;
use App\Services\PutAwayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class InboundOrderController extends Controller
{
    public function __construct(
        private readonly GaService      $gaService,
        private readonly PutAwayService $putAwayService
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
            ->editColumn('do_date', fn($row) => $row->do_date?->format('d M Y') ?? '-')
            ->addColumn('status_badge', function ($row) {
                $map = [
                    'draft'       => ['badge-secondary', 'Draft'],
                    'processing'  => ['badge-warning',   'Processing'],
                    'recommended' => ['badge-info',      'Recommended'],
                    'put_away'    => ['badge-primary',   'Put Away'],
                    'completed'   => ['badge-success',   'Completed'],
                    'cancelled'   => ['badge-danger',    'Cancelled'],
                ];
                [$cls, $label] = $map[$row->status] ?? ['badge-secondary', ucfirst($row->status)];
                return '<span class="badge ' . $cls . '">' . $label . '</span>';
            })
            ->addColumn('action', function ($row) {
                $showUrl = route('inbound.orders.show', $row->id);
                $html    = '<a href="' . $showUrl . '" class="btn btn-xs btn-info" title="Detail"><i class="fas fa-eye"></i></a> ';
                if ($row->status === 'draft') {
                    $editUrl = route('inbound.orders.edit', $row->id);
                    $html   .= '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                    $html   .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->do_number) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
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
                'status'        => 'draft',
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
        if ($order->status !== 'draft') {
            return redirect()->route('inbound.orders.show', $id)
                ->with('error', 'Hanya order berstatus Draft yang dapat diedit.');
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
        if ($order->status !== 'draft') {
            return back()->with('error', 'Hanya order berstatus Draft yang dapat diedit.');
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
        if (!in_array($order->status, ['draft', 'cancelled'])) {
            return response()->json(['status' => 'error', 'message' => 'Hanya order Draft/Cancelled yang dapat dihapus.'], 422);
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
    // STEP 1 — Konfirmasi Qty Fisik di Dock (Operator)
    // POST /inbound/orders/{order}/confirm-qty
    // ─────────────────────────────────────────────────────────────────────────

    public function confirmQty(Request $request, $id)
    {
        $order = InboundOrder::with('items')->findOrFail($id);

        if ($order->status !== 'draft') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Konfirmasi qty hanya bisa dilakukan pada order berstatus Draft.',
            ], 422);
        }

        $request->validate([
            'quantities'   => 'required|array|min:1',
            'quantities.*' => 'required|integer|min:0',
        ]);

        try {
            // Tandai siapa yang menerima barang secara fisik (dock operator)
            $order->update([
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);

            $this->putAwayService->confirmQuantities($order, $request->quantities);

            if ($order->fresh()->status === 'cancelled') {
                return response()->json([
                    'status'  => 'warning',
                    'message' => 'Semua qty_received = 0. Order otomatis di-cancel.',
                    'redirect' => route('inbound.orders.index'),
                ]);
            }

            // Notifikasi ke Admin & Supervisor: qty sudah dikonfirmasi, siap GA
            $confirmedBy = auth()->user()->name;
            $notifUsers = User::whereHas('role', fn($q) => $q->whereIn('slug', ['admin', 'supervisor']))->get();
            \Illuminate\Support\Facades\Notification::send(
                $notifUsers,
                new QtyConfirmedNotification($order->fresh(), $confirmedBy)
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Konfirmasi penerimaan fisik berhasil disimpan. Order siap diproses GA.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 2 — Trigger GA (Supervisor)
    // POST /inbound/orders/{order}/process-ga
    // ─────────────────────────────────────────────────────────────────────────

    public function processGA(Request $request, $id)
    {
        $order = InboundOrder::with('items')->findOrFail($id);

        if (!in_array($order->status, ['draft', 'processing', 'recommended'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order tidak dapat diproses GA pada status saat ini.',
            ], 422);
        }

        // Validasi: ada item dengan qty_received > 0
        $hasQtyReceived = $order->items->where('quantity_received', '>', 0)->isNotEmpty();
        if (!$hasQtyReceived) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Konfirmasi penerimaan fisik (qty diterima) terlebih dahulu sebelum menjalankan GA.',
            ], 422);
        }

        $order->update(['status' => 'processing']);

        try {
            // Tombol "Jalankan GA" harus memanggil engine GA asli (Python FastAPI)
            $recommendation = $this->gaService->run($order, auth()->id());

            return response()->json([
                'status'  => 'success',
                'message' => 'GA selesai. Fitness score: ' . round($recommendation->fitness_score, 2) . '. Silakan review rekomendasi.',
                'data'    => [
                    'ga_recommendation_id' => $recommendation->id,
                    'fitness_score'        => $recommendation->fitness_score,
                    'generations_run'      => $recommendation->generations_run,
                    'execution_time_ms'    => $recommendation->execution_time_ms,
                ],
            ]);
        } catch (\Exception $e) {
            $order->update(['status' => 'draft']); // rollback status jika GA gagal
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 3a — Supervisor Accept Rekomendasi GA
    // POST /inbound/orders/{order}/ga/{recommendation}/accept
    // ─────────────────────────────────────────────────────────────────────────

    public function acceptGa(Request $request, $orderId, $recommendationId)
    {
        $order          = InboundOrder::findOrFail($orderId);
        $recommendation = GaRecommendation::where('inbound_order_id', $orderId)
            ->findOrFail($recommendationId);

        if ($order->status !== 'recommended') {
            return response()->json(['status' => 'error', 'message' => 'Order belum berstatus recommended.'], 422);
        }

        $recommendation->update(['status' => 'accepted']);

        // Notifikasi ke semua Operator: siap put-away
        $acceptedBy  = auth()->user()->name;
        $notifUsers  = User::whereHas('role', fn($q) => $q->whereIn('slug', ['admin', 'supervisor', 'operator']))->get();
        \Illuminate\Support\Facades\Notification::send(
            $notifUsers,
            new GaAcceptedNotification($order, $acceptedBy, $recommendation->fitness_score)
        );

        return response()->json([
            'status'   => 'success',
            'message'  => 'Rekomendasi GA diterima. Operator dapat memulai put-away.',
            'redirect' => route('putaway.show', $orderId),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 3b — Supervisor Reject Rekomendasi GA (Re-run atau Manual)
    // POST /inbound/orders/{order}/ga/{recommendation}/reject
    // ─────────────────────────────────────────────────────────────────────────

    public function rejectGa(Request $request, $orderId, $recommendationId)
    {
        $order          = InboundOrder::findOrFail($orderId);
        $recommendation = GaRecommendation::where('inbound_order_id', $orderId)
            ->findOrFail($recommendationId);

        if ($order->status !== 'recommended') {
            return response()->json(['status' => 'error', 'message' => 'Order belum berstatus recommended.'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:255']);

        $recommendation->update(['status' => 'rejected']);

        // Reset order ke draft agar GA bisa di-run ulang
        $order->update(['status' => 'draft']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Rekomendasi GA ditolak. Anda dapat menjalankan GA ulang atau melakukan manual assignment.',
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
