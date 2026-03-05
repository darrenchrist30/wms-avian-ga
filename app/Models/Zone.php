<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'code',
        'name',
        'description',
        'pos_x',
        'pos_z',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function racks()
    {
        return $this->hasMany(Rack::class);
    }

    public function cells()
    {
        return $this->hasManyThrough(Cell::class, Rack::class);
    }

    // Hitung utilisasi zona (%)
    public function getUtilizationPercentAttribute(): float
    {
        $cells = $this->cells;
        $max   = $cells->sum('capacity_max');
        if ($max === 0) return 0;
        return round($cells->sum('capacity_used') / $max * 100, 1);
    }
}
