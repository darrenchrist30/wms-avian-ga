<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InboundReceiveRequest;
use App\Http\Resources\Api\V1\InboundTransactionResource;
use App\Models\InboundOrder;
use App\Models\User;
use App\Notifications\NewInboundOrderNotification;
use App\Services\InboundReceiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Controller: InboundReceiveController
 *
 * Menangani HTTP request dari ERP untuk endpoint inbound.
 * Controller ini sengaja dibuat tipis — seluruh business logic
 * ada di InboundReceiveService.
 *
 * Endpoint:
 *   POST   /api/v1/inbound/receive       — terima DO baru dari ERP
 *   GET    /api/v1/inbound               — list DO (filter + paginasi)
 *   GET    /api/v1/inbound/{doNumber}    — cek status 1 DO
 */
class InboundReceiveController extends Controller
{
    public function __construct(
        private readonly InboundReceiveService $service
    ) {}

    // ───────────────────────────────────────────────────────────────────────
    // POST /api/v1/inbound/receive
    // ───────────────────────────────────────────────────────────────────────
    /**
     * Terima data Delivery Order dari ERP.
     *
     * Response:
     *   201 Created  — DO baru berhasil disimpan
     *   200 OK       — DO sudah ada sebelumnya (idempotent), data lama dikembalikan
     *   422          — Validasi gagal atau semua item tidak ditemukan
     *   500          — Error tidak terduga
     */
    public function receive(InboundReceiveRequest $request): JsonResponse
    {
        try {
            $result = $this->service->receive($request->validated());

            $isNew      = $result['is_new'];
            $statusCode = $isNew ? 201 : 200;
            $message    = $isNew
                ? 'Inbound transaction berhasil dibuat. Menunggu proses GA oleh supervisor.'
                : 'DO dengan nomor ini sudah pernah diterima sebelumnya. Data lama dikembalikan.';

            // Notifikasi ke Admin & Supervisor saat DO baru masuk dari ERP
            if ($isNew) {
                $notifUsers = User::whereHas('role', fn($q) => $q->whereIn('slug', ['admin', 'supervisor']))->get();
                Notification::send($notifUsers, new NewInboundOrderNotification($result['transaction']));
            }

            $response = [
                'success' => true,
                'message' => $message,
                'data'    => new InboundTransactionResource($result['transaction']),
            ];

            // Sertakan warning jika ada item yang tidak ditemukan di master data
            if (!empty($result['unmatched_items'])) {
                $count = count($result['unmatched_items']);
                $response['warnings'] = [
                    'message'         => "{$count} item tidak ditemukan di master data WMS dan diabaikan.",
                    'action'          => 'Daftarkan item tersebut di WMS, lalu kirim ulang DO jika diperlukan.',
                    'unmatched_items' => $result['unmatched_items'],
                ];
            }

            return response()->json($response, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ───────────────────────────────────────────────────────────────────────
    // GET /api/v1/inbound/{doNumber}
    // ───────────────────────────────────────────────────────────────────────
    /**
     * Cek status satu Delivery Order berdasarkan nomor DO.
     *
     * ERP bisa polling endpoint ini untuk memantau progres put-away.
     *
     * Response:
     *   200 OK  — data DO + status terkini + detail item
     *   404     — DO tidak ditemukan
     */
    public function show(string $doNumber): JsonResponse
    {
        $transaction = InboundOrder::where('do_number', $doNumber)
            ->with(['warehouse', 'supplier', 'items.item'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => "DO dengan nomor '{$doNumber}' tidak ditemukan di WMS.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new InboundTransactionResource($transaction),
        ]);
    }

    // ───────────────────────────────────────────────────────────────────────
    // GET /api/v1/inbound
    // ───────────────────────────────────────────────────────────────────────
    /**
     * List semua Delivery Order dengan filter dan paginasi.
     *
     * Query parameters (semua opsional):
     *   ?status=draft           — filter by status
     *   ?date_from=2024-01-01   — filter DO date dari tanggal ini
     *   ?date_to=2024-12-31     — filter DO date sampai tanggal ini
     *   ?per_page=20            — jumlah item per halaman (default: 20, max: 100)
     *   ?page=1                 — halaman ke berapa
     *
     * Response:
     *   200 OK — list DO + meta paginasi
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 20), 100);

        $transactions = InboundOrder::with(['warehouse', 'supplier'])
            ->when(
                $request->filled('status'),
                fn($q) => $q->where('status', $request->status)
            )
            ->when(
                $request->filled('date_from'),
                fn($q) => $q->whereDate('do_date', '>=', $request->date_from)
            )
            ->when(
                $request->filled('date_to'),
                fn($q) => $q->whereDate('do_date', '<=', $request->date_to)
            )
            ->orderByDesc('do_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => InboundTransactionResource::collection($transactions),
            'meta'    => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
                'from'         => $transactions->firstItem(),
                'to'           => $transactions->lastItem(),
            ],
        ]);
    }
}
