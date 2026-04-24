<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundOrderItem extends Model
{
    protected $table = 'inbound_details';

    protected $fillable = [
        'inbound_order_id', 'item_id', 'lpn', 'lpn_timestamp',
        'quantity_ordered', 'quantity_received', 'status', 'notes',
    ];

    protected $casts = [
        'lpn_timestamp' => 'datetime',
    ];

    public function inboundOrder()
    {
        return $this->belongsTo(InboundOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function gaRecommendationDetails()
    {
        return $this->hasMany(GaRecommendationDetail::class);
    }

    public function putAwayConfirmations()
    {
        return $this->hasMany(PutAwayConfirmation::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class);
    }
}
