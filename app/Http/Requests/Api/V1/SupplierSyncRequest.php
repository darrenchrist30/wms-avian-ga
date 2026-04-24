<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validasi payload sinkronisasi supplier dari ERP.
 *
 * Contoh payload:
 * {
 *   "suppliers": [
 *     {
 *       "erp_vendor_id":  "V-001",
 *       "code":           "SUP-001",
 *       "name":           "PT Maju Jaya Sentosa",
 *       "contact_person": "Budi Santoso",
 *       "phone":          "031-1234567",
 *       "email":          "budi@majujaya.com",
 *       "address":        "Jl. Industri No. 1, Surabaya",
 *       "is_active":      true
 *     }
 *   ]
 * }
 */
class SupplierSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suppliers'                   => ['required', 'array', 'min:1', 'max:200'],
            'suppliers.*.erp_vendor_id'   => ['required', 'string', 'max:100'],
            'suppliers.*.name'            => ['required', 'string', 'max:255'],
            'suppliers.*.code'            => ['nullable', 'string', 'max:50'],
            'suppliers.*.contact_person'  => ['nullable', 'string', 'max:255'],
            'suppliers.*.phone'           => ['nullable', 'string', 'max:50'],
            'suppliers.*.email'           => ['nullable', 'email', 'max:255'],
            'suppliers.*.address'         => ['nullable', 'string', 'max:500'],
            'suppliers.*.is_active'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'suppliers.required'                => 'Array suppliers wajib diisi.',
            'suppliers.min'                     => 'Minimal 1 supplier harus dikirim.',
            'suppliers.max'                     => 'Maksimal 200 supplier per request.',
            'suppliers.*.erp_vendor_id.required'=> 'Setiap supplier wajib memiliki erp_vendor_id.',
            'suppliers.*.name.required'         => 'Setiap supplier wajib memiliki name.',
            'suppliers.*.email.email'           => 'Format email supplier tidak valid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ids = array_column($this->input('suppliers', []), 'erp_vendor_id');
            $duplicates = array_filter(
                array_count_values(array_filter($ids)),
                fn($count) => $count > 1
            );

            foreach (array_keys($duplicates) as $dupId) {
                $v->errors()->add(
                    'suppliers',
                    "erp_vendor_id '{$dupId}' muncul lebih dari satu kali dalam batch ini."
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
