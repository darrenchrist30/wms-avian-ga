<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InboundOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'supplier_id',
        'received_by',
        'do_number',
        'erp_reference',
        'do_date',
        'received_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'do_date'     => 'date',
        'received_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(InboundOrderItem::class);
    }

    public function putAwayRecommendations()
    {
        return $this->hasMany(PutAwayRecommendation::class);
    }

    // Cek apakah semua item sudah put-away
    public function isFullyPutAway(): bool
    {
        return $this->items()->where('status', '!=', 'put_away')->count() === 0;
    }
}
