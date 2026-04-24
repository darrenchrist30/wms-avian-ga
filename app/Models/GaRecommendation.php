<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GaRecommendation extends Model
{
    protected $fillable = [
        'inbound_order_id',
        'generated_by',
        'chromosome_json',
        'fitness_score',
        'generations_run',
        'execution_time_ms',
        'parameters_json',
        'generated_at',
        'status',
    ];

    protected $casts = [
        'chromosome_json'  => 'array',
        'parameters_json'  => 'array',
        'fitness_score'    => 'float',
        'generated_at'     => 'datetime',
        'generations_run'  => 'integer',
        'execution_time_ms'=> 'integer',
    ];

    public function inboundOrder()
    {
        return $this->belongsTo(InboundOrder::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function details()
    {
        return $this->hasMany(GaRecommendationDetail::class);
    }

    // Jumlah item yang sudah di-confirm put-away dari rekomendasi ini
    public function getConfirmedCountAttribute(): int
    {
        return $this->details()
            ->whereHas('putAwayConfirmations')
            ->count();
    }
}
