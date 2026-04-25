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
        'review_required',
        'review_reason',
        'accepted_by',
        'accepted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'chromosome_json'   => 'array',
        'parameters_json'   => 'array',
        'fitness_score'     => 'float',
        'generated_at'      => 'datetime',
        'generations_run'   => 'integer',
        'execution_time_ms' => 'integer',
        'review_required'   => 'boolean',
        'accepted_at'       => 'datetime',
        'rejected_at'       => 'datetime',
    ];

    public function inboundOrder()
    {
        return $this->belongsTo(InboundOrder::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function details()
    {
        return $this->hasMany(GaRecommendationDetail::class);
    }

    public function getConfirmedCountAttribute(): int
    {
        return $this->details()
            ->whereHas('putAwayConfirmations')
            ->count();
    }
}
