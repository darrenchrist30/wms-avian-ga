<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $table = 'stock_records';

    protected $fillable = [
        'item_id', 'cell_id', 'warehouse_id', 'inbound_order_item_id',
        'lpn', 'batch_no', 'quantity',
        'inbound_date', 'expiry_date', 'last_moved_at', 'status',
    ];

    protected $casts = [
        'inbound_date'  => 'date',
        'expiry_date'   => 'date',
        'last_moved_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function inboundOrderItem()
    {
        return $this->belongsTo(InboundOrderItem::class);
    }

    public function getIsDeadstockAttribute(): bool
    {
        if ($this->status !== 'available' || $this->quantity <= 0) {
            return false;
        }
        $threshold    = $this->item?->deadstock_threshold_days ?? 90;
        $lastActivity = $this->last_moved_at ?? $this->inbound_date;
        if (!$lastActivity) {
            return false;
        }
        return $lastActivity->diffInDays(now()) >= $threshold;
    }

    public function getDaysSinceLastMovementAttribute(): ?int
    {
        $lastActivity = $this->last_moved_at ?? $this->inbound_date;
        if (!$lastActivity) {
            return null;
        }
        return (int) $lastActivity->diffInDays(now());
    }

    public function getIsNearExpiryAttribute(): bool
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->diffInDays(now()) <= 30;
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeNearExpiry($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
                     ->whereDate('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeDeadstock($query, int $days = 90)
    {
        $cutoff = now()->subDays($days);
        return $query
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($q2) use ($cutoff) {
                    $q2->whereNotNull('last_moved_at')
                       ->where('last_moved_at', '<', $cutoff);
                })->orWhere(function ($q2) use ($cutoff) {
                    $q2->whereNull('last_moved_at')
                       ->where('inbound_date', '<', $cutoff);
                });
            });
    }
}