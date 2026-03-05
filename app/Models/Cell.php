<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cell extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'rack_id', 'dominant_category_id', 'code',
        'level', 'column', 'capacity_max', 'capacity_used',
        'status', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function dominantCategory()
    {
        return $this->belongsTo(ItemCategory::class, 'dominant_category_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function putAwayRecommendations()
    {
        return $this->hasMany(PutAwayRecommendation::class);
    }

    // Sisa kapasitas
    public function getRemainingCapacityAttribute(): int
    {
        return $this->capacity_max - $this->capacity_used;
    }

    // Persentase utilisasi
    public function getUtilizationPercentAttribute(): float
    {
        if ($this->capacity_max === 0) return 0;
        return round($this->capacity_used / $this->capacity_max * 100, 1);
    }

    // Otomatis update status berdasarkan kapasitas
    public function updateStatus(): void
    {
        if ($this->status === 'blocked' || $this->status === 'reserved') return;

        if ($this->capacity_used === 0) {
            $this->status = 'available';
        } elseif ($this->capacity_used >= $this->capacity_max) {
            $this->status = 'full';
        } else {
            $this->status = 'partial';
        }
        $this->save();
    }
}
