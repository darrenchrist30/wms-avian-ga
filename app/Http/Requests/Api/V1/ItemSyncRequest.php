<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validasi payload sinkronisasi item dari ERP.
 *
 * Contoh payload minimal:
 * {
 *   "items": [
 *     {
 *       "erp_item_code": "AVN-001",
 *       "name":          "Cat Tembok Putih 5kg",
 *       "category_code": "CAT-TEMBOK",
 *       "unit_code":     "KG"
 *     }
 *   ]
 * }
 *
 * Contoh payload lengkap:
 * {
 *   "items": [
 *     {
 *       "erp_item_code":  "AVN-001",
 *       "sku":            "AVN-001-WHT-5KG",
 *       "name":           "Cat Tembok Putih 5kg",
 *       "category_code":  "CAT-TEMBOK",
 *       "unit_code":      "KG",
 *       "barcode":        "8991234567890",
 *       "description":    "Cat tembok interior/eksterior, tahan cuaca",
 *       "weight_kg":      5.0,
 *       "volume_m3":      0.005,
 *       "min_stock":      10,
 *       "max_stock":      500,
 *       "reorder_point":  50,
 *       "movement_type":  "fast_moving",
 *       "item_size":      "medium",
 *       "is_active":      true
 *     }
 *   ]
 * }
 */
class ItemSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Wrapper array — max 500 item per request untuk cegah timeout
            'items'                   => ['required', 'array', 'min:1', 'max:500'],

            // Identifier wajib dari ERP — ini yang jadi kunci upsert
            'items.*.erp_item_code'   => ['required', 'string', 'max:100'],

            // Nama item wajib — ini yang ditampilkan di WMS
            'items.*.name'            => ['required', 'string', 'max:255'],

            // SKU opsional — jika tidak dikirim, pakai erp_item_code sebagai SKU
            'items.*.sku'             => ['nullable', 'string', 'max:100'],

            // Referensi ke master data WMS
            'items.*.category_code'   => ['nullable', 'string', 'max:50'],
            'items.*.unit_code'       => ['nullable', 'string', 'max:50'],

            // Detail item
            'items.*.barcode'         => ['nullable', 'string', 'max:100'],
            'items.*.description'     => ['nullable', 'string', 'max:1000'],
            'items.*.weight_kg'       => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items.*.volume_m3'       => ['nullable', 'numeric', 'min:0', 'max:99999'],

            // Stok & reorder
            'items.*.min_stock'       => ['nullable', 'integer', 'min:0'],
            'items.*.max_stock'       => ['nullable', 'integer', 'min:0'],
            'items.*.reorder_point'   => ['nullable', 'integer', 'min:0'],

            // Klasifikasi untuk algoritma GA
            'items.*.movement_type'   => ['nullable', Rule::in(['fast_moving', 'slow_moving', 'non_moving'])],
            'items.*.item_size'       => ['nullable', Rule::in(['small', 'medium', 'large', 'extra_large'])],

            // Deadstock threshold (hari tanpa pergerakan = deadstock)
            'items.*.deadstock_threshold_days' => ['nullable', 'integer', 'min:1', 'max:3650'],

            // Aktif/nonaktif — ERP kirim false untuk deaktivasi item di WMS
            'items.*.is_active'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                  => 'Array items wajib diisi.',
            'items.min'                       => 'Minimal 1 item harus dikirim.',
            'items.max'                       => 'Maksimal 500 item per request. Pisah menjadi beberapa batch.',
            'items.*.erp_item_code.required'  => 'Setiap item wajib memiliki erp_item_code.',
            'items.*.name.required'           => 'Setiap item wajib memiliki name.',
            'items.*.movement_type.in'        => "movement_type harus salah satu: fast_moving, slow_moving, non_moving.",
            'items.*.item_size.in'            => "item_size harus salah satu: small, medium, large, extra_large.",
            'items.*.weight_kg.numeric'       => 'weight_kg harus berupa angka desimal.',
            'items.*.volume_m3.numeric'       => 'volume_m3 harus berupa angka desimal.',
        ];
    }

    /**
     * Validasi tambahan: erp_item_code tidak boleh duplikat dalam satu batch.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $codes = array_column($this->input('items', []), 'erp_item_code');
            $duplicates = array_filter(
                array_count_values(array_filter($codes)),
                fn($count) => $count > 1
            );

            foreach (array_keys($duplicates) as $dupCode) {
                $v->errors()->add(
                    'items',
                    "erp_item_code '{$dupCode}' muncul lebih dari satu kali dalam batch ini."
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Payload tidak valid.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
