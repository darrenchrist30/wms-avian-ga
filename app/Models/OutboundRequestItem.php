<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundRequestItem extends Model
{
    protected $fillable = [
        'outbound_request_id',
        'item_id',
        'quantity_requested',
    ];

    public function outboundRequest(): BelongsTo
    {
        return $this->belongsTo(OutboundRequest::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
