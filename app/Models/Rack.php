<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rack extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'zone_id', 'warehouse_id', 'dominant_category_id',
        'code', 'name', 'rack_number',
        'total_levels', 'total_columns',
        'pos_x', 'pos_y', 'pos_z', 'rotation_y',
        'width_3d', 'height_3d', 'depth_3d',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function dominantCategory()
    {
        return $this->belongsTo(\App\Models\ItemCategory::class, 'dominant_category_id');
    }

    public function cells()
    {
        return $this->hasMany(Cell::class);
    }

    // Ambil cell berdasarkan level & kolom
    public function getCell(int $level, int $column): ?Cell
    {
        return $this->cells->where('level', $level)->where('column', $column)->first();
    }
}
