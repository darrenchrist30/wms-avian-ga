<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InboundOrder extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'inbound_transactions';

    public function getAuditLabel(): string
    {
        return 'DO ' . $this->do_number;
    }

    protected $fillable = [
        'warehouse_id',
        'supplier_id',
        'received_by',
        'do_number',
        'no_bukti_manual',
        'erp_reference',
        'ref_doc_spk',
        'batch_header',
        'do_date',
        'received_at',
        'processed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'do_date'      => 'date',
        'received_at'  => 'datetime',
        'processed_at' => 'datetime',
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

    public function gaRecommendations()
    {
        return $this->hasMany(GaRecommendation::class);
    }

    public function latestGaRecommendation()
    {
        return $this->hasOne(GaRecommendation::class)->latestOfMany();
    }

    // Cek apakah semua item sudah put-away
    public function isFullyPutAway(): bool
    {
        return $this->items()->where('status', '!=', 'put_away')->count() === 0;
    }
}
