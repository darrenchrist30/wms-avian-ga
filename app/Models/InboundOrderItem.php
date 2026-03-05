<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundOrderItem extends Model
{
    protected $fillable = [
        'inbound_order_id', 'item_id', 'lpn',
        'quantity_ordered', 'quantity_received', 'status', 'notes',
    ];

    public function inboundOrder()
    {
        return $this->belongsTo(InboundOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function putAwayRecommendation()
    {
        return $this->hasOne(PutAwayRecommendation::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class);
    }
}
