<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'action',
        'model_type', 'model_id', 'model_label',
        'old_values', 'new_values',
        'ip_address', 'url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, Model $model, ?array $old, ?array $new): void
    {
        try {
            $user = auth()->user();
            static::create([
                'user_id'     => $user?->id,
                'user_name'   => $user?->name ?? 'System',
                'action'      => $action,
                'model_type'  => class_basename($model),
                'model_id'    => (string) $model->getKey(),
                'model_label' => method_exists($model, 'getAuditLabel') ? $model->getAuditLabel() : null,
                'old_values'  => $old ?: null,
                'new_values'  => $new ?: null,
                'ip_address'  => request()?->ip(),
                'url'         => request()?->fullUrl(),
            ]);
        } catch (\Exception $e) {
            // Audit log failure must never break the main operation
            report($e);
        }
    }

    public function getActionBadgeAttribute(): string
    {
        return match($this->action) {
            'created' => '<span class="badge badge-success">Dibuat</span>',
            'updated' => '<span class="badge badge-warning text-dark">Diubah</span>',
            'deleted' => '<span class="badge badge-danger">Dihapus</span>',
            default   => '<span class="badge badge-secondary">' . e($this->action) . '</span>',
        };
    }

    public function getModelTypeLabelAttribute(): string
    {
        $map = [
            'User'          => 'Pengguna',
            'Role'          => 'Role',
            'Item'          => 'Sparepart',
            'ItemCategory'  => 'Kategori',
            'Unit'          => 'Satuan',
            'Warehouse'     => 'Warehouse',
            'Rack'          => 'Rak',
            'Cell'          => 'Sel',
            'InboundOrder'  => 'Inbound Order',
        ];
        return $map[$this->model_type] ?? $this->model_type;
    }
}
