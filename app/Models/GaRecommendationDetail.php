<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GaRecommendationDetail extends Model
{
    protected $fillable = [
        'ga_recommendation_id',
        'inbound_order_item_id',
        'cell_id',
        'quantity',
        'gene_fitness',
        'fc_cap_score',
        'fc_cat_score',
        'fc_aff_score',
        'fc_split_score',
    ];

    protected $casts = [
        'gene_fitness'   => 'float',
        'fc_cap_score'   => 'float',
        'fc_cat_score'   => 'float',
        'fc_aff_score'   => 'float',
        'fc_split_score' => 'float',
    ];

    public function gaRecommendation()
    {
        return $this->belongsTo(GaRecommendation::class);
    }

    public function inboundOrderItem()
    {
        return $this->belongsTo(InboundOrderItem::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function putAwayConfirmations()
    {
        return $this->hasMany(PutAwayConfirmation::class, 'ga_recommendation_detail_id');
    }

    // Fitness dalam format persentase (0-100)
    public function getFitnessPercentAttribute(): float
    {
        return round(($this->gene_fitness ?? 0) * 100, 1);
    }
}
