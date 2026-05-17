<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validasi payload inbound dari ERP.
 *
 * Contoh payload minimal:
 * {
 *   "warehouse_code": "WH-001",
 *   "do_number":      "DO-2024-001234",
 *   "do_date":        "2024-04-15",
 *   "items": [
 *     { "erp_item_code": "AVN-001", "quantity": 50 }
 *   ]
 * }
 */
class InboundReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditangani oleh Sanctum middleware di routes
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Header Transaksi ────────────────────────────────────────────
            'warehouse_code'  => ['required', 'string', 'max:50', 'exists:warehouses,code'],
            'do_number'       => ['required', 'string', 'max:100'],
            'do_date'         => ['required', 'date_format:Y-m-d'],
            'notes'           => ['nullable', 'string', 'max:1000'],

            // ── Daftar Item ─────────────────────────────────────────────────
            'items'                 => ['required', 'array', 'min:1', 'max:500'],

            // Identifikasi item: kirim erp_item_code (kode item di ERP) atau sku
            'items.*.sku'           => ['required', 'string', 'max:100'],
            'items.*.quantity'      => ['required', 'integer', 'min:1', 'max:999999'],
            'items.*.notes'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            // Header
            'warehouse_code.required' => 'Kode gudang tujuan (warehouse_code) wajib diisi.',
            'warehouse_code.exists'   => 'Kode gudang tidak ditemukan. Pastikan gudang sudah terdaftar di WMS.',
            'do_number.required'      => 'Nomor Delivery Order (do_number) wajib diisi.',
            'do_number.max'           => 'Nomor DO maksimal 100 karakter.',
            'do_date.required'        => 'Tanggal DO (do_date) wajib diisi.',
            'do_date.date_format'     => 'Format tanggal DO harus YYYY-MM-DD. Contoh: 2024-04-15',

            // Items
            'items.required'          => 'Daftar item (items) tidak boleh kosong.',
            'items.array'             => 'Field items harus berupa array.',
            'items.min'               => 'Minimal harus ada 1 item dalam DO.',
            'items.max'               => 'Maksimal 500 item dalam satu DO.',

            'items.*.quantity.required' => 'Jumlah (quantity) wajib diisi untuk setiap item.',
            'items.*.quantity.integer'  => 'Jumlah harus berupa angka bulat.',
            'items.*.quantity.min'      => 'Jumlah minimal 1 untuk setiap item.',
            'items.*.quantity.max'      => 'Jumlah maksimal 999.999 per item.',
        ];
    }

    /**
     * Override: kembalikan JSON (bukan redirect HTML) saat validasi gagal.
     * Penting untuk API — client ERP mengharapkan JSON, bukan halaman HTML.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Data tidak valid. Periksa kembali payload yang dikirim.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
