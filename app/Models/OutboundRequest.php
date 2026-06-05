<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundRequest extends Model
{
    protected $fillable = [
        'request_number',
        'operator_id',
        'warehouse_id',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'signature_path',
        'rejected_by',
        'rejected_at',
        'reject_reason',
        'executed_at',
    ];

    protected $casts = [
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'executed_at'  => 'datetime',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OutboundRequestItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'Menunggu Persetujuan',
            'approved'  => 'Disetujui',
            'rejected'  => 'Ditolak',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default     => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'badge-warning',
            'approved'  => 'badge-success',
            'rejected'  => 'badge-danger',
            'completed' => 'badge-primary',
            'cancelled' => 'badge-secondary',
            default     => 'badge-secondary',
        };
    }

    // Generate nomor request otomatis: OBR-2026-001
    public static function generateRequestNumber(): string
    {
        $year = now()->year;
        $prefix = "OBR-{$year}-";
        $last = self::where('request_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('request_number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
