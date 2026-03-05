<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PutAwayRecommendation extends Model
{
    protected $fillable = [
        'inbound_order_id',
        'inbound_order_item_id',
        'item_id',
        'cell_id',
        'confirmed_by',
        'fitness_score',
        'generation',
        'quantity',
        'chromosome_index',
        'status',
        'override_cell_id',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'confirmed_at'  => 'datetime',
        'fitness_score' => 'float',
    ];

    public function inboundOrder()
    {
        return $this->belongsTo(InboundOrder::class);
    }

    public function inboundOrderItem()
    {
        return $this->belongsTo(InboundOrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function overrideCell()
    {
        return $this->belongsTo(Cell::class, 'override_cell_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // Cell aktual (override jika ada, jika tidak gunakan rekomendasi GA)
    public function getActualCellAttribute(): Cell
    {
        return $this->override_cell_id ? $this->overrideCell : $this->cell;
    }
}
