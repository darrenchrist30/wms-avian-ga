<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rack extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'zone_id', 'code', 'name',
        'total_levels', 'total_columns',
        'pos_x', 'pos_z', 'rotation_y', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
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
