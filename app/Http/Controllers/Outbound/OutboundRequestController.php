<?php

namespace App\Http\Controllers\Outbound;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OutboundRequest;
use App\Models\OutboundRequestItem;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OutboundRequestController extends Controller
{
    // ── LIST ─────────────────────────────────────────────────────────────────
    // GET /outbound/requests
    public function index()
    {
        return view('outbound.requests.index');
    }

    // ── DATATABLE ─────────────────────────────────────────────────────────────
    // GET /outbound/requests/datatable
    public function datatable(Request $request)
    {
        $user  = auth()->user();
        $query = OutboundRequest::with(['operator', 'warehouse'])
            ->withCount('items');

        if ($user->hasRole('operator')) {
            $query->where('operator_id', $user->id);
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search global
        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhereHas('operator', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('warehouse', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        $total = (clone $query)->count();

        // Order
        $orderCol   = $request->input('order.0.column', 0);
        $orderDir   = $request->input('order.0.dir', 'desc');
        $columnMap  = ['id', 'request_number', 'operator_name', 'warehouse_name', 'items_count', 'status', 'created_at'];
        $sortColumn = $columnMap[$orderCol] ?? 'id';
        if (in_array($sortColumn, ['id', 'request_number', 'status', 'created_at', 'items_count'])) {
            $query->orderBy($sortColumn, $orderDir);
        } else {
            $query->orderByDesc('id');
        }

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows   = $query->skip($start)->take($length)->get();

        $isSupervisor = $user->hasRole(['admin', 'supervisor']);

        $data = $rows->map(function ($req, $i) use ($start, $isSupervisor) {
            // Action button
            if ($req->isPending() && $isSupervisor) {
                $actionBtn = '<a href="' . route('outbound.requests.show', $req->id) . '" class="btn btn-xs btn-warning text-white"><i class="fas fa-edit mr-1"></i>Edit</a>';
            } else {
                $actionBtn = '<a href="' . route('outbound.requests.show', $req->id) . '" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>';
            }
            if ($req->isApproved() && $req->operator_id === auth()->id()) {
                $actionBtn .= ' <a href="' . route('outbound.create', ['request_id' => $req->id]) . '" class="btn btn-xs" style="background:#0d8564;color:#fff;border-color:#0d8564;" onmouseenter="this.style.background=\'#0a6e52\'" onmouseleave="this.style.background=\'#0d8564\'" title="Lanjutkan Outbound"><i class="fas fa-arrow-right"></i></a>';
            }

            return [
                'DT_RowIndex'    => $start + $i + 1,
                'request_number' => '<strong>' . e($req->request_number) . '</strong>',
                'operator'       => e($req->operator->name),
                'warehouse'      => e($req->warehouse->name),
                'items_count'    => $req->items_count . ' item',
                'status'         => '<span class="badge ' . $req->status_badge_class . '">' . e($req->status_label) . '</span>',
                'created_at'     => $req->created_at->format('d M Y, H:i'),
                'aksi'           => $actionBtn,
            ];
        });

        return response()->json([
            'draw'            => intval($request->draw),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    // ── CREATE FORM ───────────────────────────────────────────────────────────
    // GET /outbound/requests/create
    public function create()
    {
        $warehouses        = Warehouse::where('is_active', true)->orderBy('name')->get();
        $items             = Item::where('is_active', true)->with('unit')->orderBy('name')->get();
        $defaultWarehouseId = $warehouses->firstWhere('code', 'WH-001')?->id
            ?? $warehouses->first(fn($w) => stripos($w->name, 'sparepart') !== false)?->id;

        return view('outbound.requests.create', compact('warehouses', 'items', 'defaultWarehouseId'));
    }

    // ── CHECK STOCK (AJAX pre-submit validation) ──────────────────────────────
    // POST /outbound/requests/check-stock
    public function checkStock(Request $request)
    {
        $rows    = $request->input('items', []);
        $allOk   = true;
        $results = [];

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $qtyReq = (int) ($row['qty'] ?? 0);

            $item = Item::with('unit')->find($itemId);
            if (!$item) {
                continue;
            }

            $available = (int) Stock::where('item_id', $itemId)
                ->where('status', 'available')
                ->where('quantity', '>', 0)
                ->sum('quantity');

            $locations = Stock::where('item_id', $itemId)
                ->where('status', 'available')
                ->where('quantity', '>', 0)
                ->with('cell')
                ->get()
                ->pluck('cell.code')
                ->filter()
                ->unique()
                ->take(5)
                ->values()
                ->toArray();

            $sufficient = $available >= $qtyReq;
            if (!$sufficient) {
                $allOk = false;
            }

            $results[] = [
                'item_id'    => $itemId,
                'name'       => $item->name,
                'sku'        => $item->sku,
                'unit'       => $item->unit?->code ?? 'pcs',
                'qty_req'    => $qtyReq,
                'available'  => $available,
                'sufficient' => $sufficient,
                'locations'  => $locations,
            ];
        }

        return response()->json(['all_ok' => $allOk, 'items' => $results]);
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    // POST /outbound/requests
    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'    => 'required|exists:warehouses,id',
            'notes'           => 'nullable|string|max:500',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|integer|min:1',
        ], [
            'items.required' => 'Tambahkan minimal 1 item.',
            'items.min'      => 'Tambahkan minimal 1 item.',
        ]);

        DB::beginTransaction();
        try {
            $obr = OutboundRequest::create([
                'request_number' => OutboundRequest::generateRequestNumber(),
                'operator_id'    => auth()->id(),
                'warehouse_id'   => $request->warehouse_id,
                'status'         => 'pending',
                'notes'          => $request->notes,
            ]);

            foreach ($request->items as $row) {
                OutboundRequestItem::create([
                    'outbound_request_id' => $obr->id,
                    'item_id'             => $row['item_id'],
                    'quantity_requested'  => $row['qty'],
                ]);
            }

            DB::commit();

            // Kirim WA notifikasi ke supervisor
            $this->notifySupervisor($obr->load(['operator', 'warehouse', 'items.item']));

            return redirect()->route('outbound.requests.show', $obr->id)
                ->with('success', "Request {$obr->request_number} berhasil diajukan. Menunggu persetujuan supervisor.");

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Terjadi kesalahan. Silakan coba lagi.')->withInput();
        }
    }

    // ── SHOW ─────────────────────────────────────────────────────────────────
    // GET /outbound/requests/{id}
    public function show($id)
    {
        $obr = OutboundRequest::with([
            'operator', 'warehouse', 'items.item.unit',
            'approvedBy', 'rejectedBy',
        ])->findOrFail($id);

        $this->authorizeView($obr);

        $userSigPath = 'public/user_signatures/' . auth()->id() . '.png';
        $userSigUrl  = Storage::exists($userSigPath)
            ? asset('storage/user_signatures/' . auth()->id() . '.png')
            : null;

        return view('outbound.requests.show', compact('obr', 'userSigUrl'));
    }

    // ── APPROVE (supervisor) ──────────────────────────────────────────────────
    // POST /outbound/requests/{id}/approve
    public function approve(Request $request, $id)
    {
        $obr = OutboundRequest::with(['operator', 'warehouse', 'items.item'])->findOrFail($id);

        abort_if(!auth()->user()->hasRole(['admin', 'supervisor']), 403, 'Hanya supervisor yang dapat menyetujui.');
        abort_if(!$obr->isPending(), 422, 'Request ini tidak dalam status pending.');

        $request->validate([
            'signature' => 'required|string', // base64 PNG
        ], [
            'signature.required' => 'Tanda tangan supervisor wajib diisi.',
        ]);

        // Simpan signature PNG
        $signatureData = $request->signature;
        $signatureData = preg_replace('/^data:image\/\w+;base64,/', '', $signatureData);
        $decoded       = base64_decode($signatureData);

        $filename      = 'signature_' . $obr->request_number . '_' . now()->format('Ymd_His') . '.png';
        $storagePath   = 'public/outbound_signatures/' . $filename;
        Storage::put($storagePath, $decoded);

        // Simpan juga TTD per-user agar auto-load berikutnya
        Storage::put('public/user_signatures/' . auth()->id() . '.png', $decoded);

        $obr->update([
            'status'         => 'approved',
            'approved_by'    => auth()->id(),
            'approved_at'    => now(),
            'signature_path' => 'storage/outbound_signatures/' . $filename,
        ]);

        // Notifikasi ke operator
        $this->notifyOperator($obr, 'approved');

        return response()->json([
            'status'  => 'success',
            'message' => "Request {$obr->request_number} berhasil disetujui.",
        ]);
    }

    // ── REJECT (supervisor) ───────────────────────────────────────────────────
    // POST /outbound/requests/{id}/reject
    public function reject(Request $request, $id)
    {
        $obr = OutboundRequest::with(['operator', 'warehouse'])->findOrFail($id);

        abort_if(!auth()->user()->hasRole(['admin', 'supervisor']), 403, 'Hanya supervisor yang dapat menolak.');
        abort_if(!$obr->isPending(), 422, 'Request ini tidak dalam status pending.');

        $request->validate([
            'reject_reason' => 'required|string|max:500',
        ], [
            'reject_reason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $obr->update([
            'status'        => 'rejected',
            'rejected_by'   => auth()->id(),
            'rejected_at'   => now(),
            'reject_reason' => $request->reject_reason,
        ]);

        $this->notifyOperator($obr, 'rejected');

        return response()->json([
            'status'  => 'success',
            'message' => "Request {$obr->request_number} ditolak.",
        ]);
    }

    // ── CANCEL (operator) ────────────────────────────────────────────────────
    // POST /outbound/requests/{id}/cancel
    public function cancel($id)
    {
        $obr = OutboundRequest::findOrFail($id);

        abort_if($obr->operator_id !== auth()->id(), 403);
        abort_if(!$obr->isPending(), 422, 'Hanya request pending yang bisa dibatalkan.');

        $obr->update(['status' => 'cancelled']);

        return redirect()->route('outbound.requests.index')
            ->with('success', "Request {$obr->request_number} dibatalkan.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function authorizeView(OutboundRequest $obr): void
    {
        $user = auth()->user();
        if ($user->hasRole('operator') && $obr->operator_id !== $user->id) {
            abort(403);
        }
    }

    private function notifySupervisor(OutboundRequest $obr): void
    {
        $numbers = config('services.fonnte.supervisor_numbers', []);
        $token   = config('services.fonnte.token');

        if (empty($numbers) || empty($token)) {
            return;
        }

        $waktu   = now()->locale('id')->isoFormat('D MMM Y, HH:mm');
        $link    = route('outbound.requests.show', $obr->id);
        $caption = "*[WMS Avian] Permintaan Outbound - {$obr->request_number}*\n"
                 . "Operator : {$obr->operator->name}\n"
                 . "Waktu    : {$waktu}\n"
                 . "Item     : {$obr->items->count()} jenis\n\n"
                 . "Detail terlampir. Link: {$link}";

        // Generate PDF
        $pdfUrl  = null;
        $pdfPath = null;
        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('outbound.notif-pdf', [
                'obr'   => $obr,
                'waktu' => $waktu,
            ]);
            $filename = 'obr-' . $obr->request_number . '-' . time() . '.pdf';
            $pdfPath  = public_path('wa_pdfs/' . $filename);
            if (!file_exists(public_path('wa_pdfs'))) {
                mkdir(public_path('wa_pdfs'), 0755, true);
            }
            file_put_contents($pdfPath, $pdf->output());
            $pdfUrl = url('wa_pdfs/' . $filename);
        } catch (\Exception $e) {
            Log::error('[WA OBR] Gagal generate PDF: ' . $e->getMessage());
        }

        foreach ($numbers as $number) {
            $number = preg_replace('/[^0-9]/', '', $number);
            if (str_starts_with($number, '0')) {
                $number = '62' . substr($number, 1);
            }
            try {
                if ($pdfUrl) {
                    Http::withHeaders(['Authorization' => $token])
                        ->asForm()
                        ->post('https://api.fonnte.com/send', [
                            'target'   => $number,
                            'url'      => $pdfUrl,
                            'filename' => 'Outbound-' . $obr->request_number . '.pdf',
                            'message'  => $caption,
                        ]);
                } else {
                    // Fallback ke teks biasa jika PDF gagal
                    $itemLines = $obr->items->map(fn($it) =>
                        "• {$it->item->name} ({$it->item->sku}) — {$it->quantity_requested} {$it->item->unit?->code}"
                    )->join("\n");
                    Http::withHeaders(['Authorization' => $token])
                        ->asForm()
                        ->post('https://api.fonnte.com/send', [
                            'target'  => $number,
                            'message' => $caption . "\n\n*Item:*\n" . $itemLines,
                        ]);
                }
                Log::info("[WA OBR] Notif supervisor terkirim ke {$number}");
            } catch (\Exception $e) {
                Log::error("[WA OBR] Error ke {$number}: " . $e->getMessage());
            }
        }

        // Cleanup PDF lama (> 1 jam)
        if ($pdfPath) {
            try {
                foreach (glob(public_path('wa_pdfs/obr-*.pdf')) as $f) {
                    if (filemtime($f) < time() - 3600) {
                        @unlink($f);
                    }
                }
            } catch (\Exception) {}
        }
    }

    private function notifyOperator(OutboundRequest $obr, string $action): void
    {
        $token  = config('services.fonnte.token');
        $phone  = $obr->operator->phone ?? null;

        if (empty($token) || empty($phone)) {
            return;
        }

        $number = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($number, '0')) {
            $number = '62' . substr($number, 1);
        }

        $link = route('outbound.requests.show', $obr->id);

        if ($action === 'approved') {
            $waktu = $obr->approved_at?->locale('id')->isoFormat('D MMM Y, HH:mm');
            $message = "Yth. {$obr->operator->name},\n\n"
                . "Permintaan outbound Anda telah *disetujui* oleh supervisor:\n\n"
                . "No. Request  : *{$obr->request_number}*\n"
                . "Disetujui oleh: {$obr->approvedBy?->name}\n"
                . "Waktu        : {$waktu}\n\n"
                . "Silakan lanjutkan proses pengambilan barang melalui tautan berikut:\n"
                . "Link: {$link}\n\n"
                . "Terima Kasih.\n\n"
                . "Pesan ini dikirim otomatis oleh sistem.\n"
                . str_repeat('─', 28) . "\n"
                . "_[WMS Avian] Permintaan Outbound Disetujui — {$obr->request_number}_";
        } else {
            $waktu = $obr->rejected_at?->locale('id')->isoFormat('D MMM Y, HH:mm');
            $message = "Yth. {$obr->operator->name},\n\n"
                . "Permintaan outbound Anda *ditolak* oleh supervisor:\n\n"
                . "No. Request  : *{$obr->request_number}*\n"
                . "Ditolak oleh : {$obr->rejectedBy?->name}\n"
                . "Waktu        : {$waktu}\n"
                . "Alasan       : {$obr->reject_reason}\n\n"
                . "Silakan ajukan permintaan baru jika masih diperlukan.\n"
                . "Link: {$link}\n\n"
                . "Terima Kasih.\n\n"
                . "Pesan ini dikirim otomatis oleh sistem.\n"
                . str_repeat('─', 28) . "\n"
                . "_[WMS Avian] Permintaan Outbound Ditolak - {$obr->request_number}_";
        }

        try {
            Http::withHeaders(['Authorization' => $token])
                ->asForm()
                ->post('https://api.fonnte.com/send', [
                    'target'  => $number,
                    'message' => $message,
                ]);
            Log::info("[WA OBR] Notif operator terkirim ke {$number}");
        } catch (\Exception $e) {
            Log::error("[WA OBR] Error operator {$number}: " . $e->getMessage());
        }
    }
}
