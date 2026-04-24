<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PutAwayConfirmation extends Model
{
    protected $fillable = [
        'inbound_order_item_id',
        'cell_id',
        'ga_recommendation_detail_id',
        'user_id',
        'quantity_stored',
        'follow_recommendation',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'follow_recommendation' => 'boolean',
        'confirmed_at'          => 'datetime',
    ];

    public function inboundOrderItem()
    {
        return $this->belongsTo(InboundOrderItem::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function gaRecommendationDetail()
    {
        return $this->belongsTo(GaRecommendationDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
