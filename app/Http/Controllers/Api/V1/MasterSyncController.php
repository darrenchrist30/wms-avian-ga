<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ItemSyncRequest;
use App\Services\MasterSyncService;
use Illuminate\Http\JsonResponse;

/**
 * Controller: MasterSyncController
 *
 * Endpoint sinkronisasi master data dari ERP ke WMS.
 * Dipanggil oleh ERP saat ada item/supplier baru atau perubahan data.
 *
 * Endpoint:
 *   POST /api/v1/master/items/sync      — sync item dari ERP
 *   POST /api/v1/master/suppliers/sync  — sync supplier dari ERP
 */
class MasterSyncController extends Controller
{
    public function __construct(
        private readonly MasterSyncService $service
    ) {}

    // ───────────────────────────────────────────────────────────────────────
    // POST /api/v1/master/items/sync
    // ───────────────────────────────────────────────────────────────────────
    /**
     * Sinkronisasi master data item dari ERP.
     *
     * Behavior:
     *   - Item baru (erp_item_code belum ada) → CREATE
     *   - Item ada, data berubah → UPDATE, catat field yang berubah
     *   - Item ada, data sama persis → SKIP (tidak sentuh DB)
     *   - category_code / unit_code tidak ditemukan → FAILED (item tsb diabaikan)
     *   - is_active: false → nonaktifkan item di WMS
     *
     * Response selalu 200 (partial success).
     * Cek 'report.failed' untuk item yang gagal diproses.
     */
    public function syncItems(ItemSyncRequest $request): JsonResponse
    {
        try {
            $report  = $this->service->syncItems($request->validated()['items']);
            $hasFail = $report['failed'] > 0;

            return response()->json([
                'success' => true,
                'message' => $this->buildItemMessage($report),
                'report'  => [
                    'total_received' => $report['total_received'],
                    'created'        => $report['created'],
                    'updated'        => $report['updated'],
                    'skipped'        => $report['skipped'],
                    'failed'         => $report['failed'],
                ],
                // Detail per item — berguna untuk ERP mendeteksi yang gagal
                'results' => $report['results'],
                // Flag peringatan jika ada yang gagal
                'has_failures' => $hasFail,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function buildItemMessage(array $r): string
    {
        $parts = [];
        if ($r['created'] > 0) $parts[] = "{$r['created']} item baru ditambahkan";
        if ($r['updated'] > 0) $parts[] = "{$r['updated']} item diperbarui";
        if ($r['skipped'] > 0) $parts[] = "{$r['skipped']} item tidak berubah";
        if ($r['failed']  > 0) $parts[] = "{$r['failed']} item gagal (periksa 'results')";

        return empty($parts)
            ? 'Tidak ada item yang diproses.'
            : 'Sync selesai: ' . implode(', ', $parts) . '.';
    }

}
