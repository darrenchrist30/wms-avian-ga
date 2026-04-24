<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemCategory extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = ['code', 'name', 'description', 'color_code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function dominantCells()
    {
        return $this->hasMany(Cell::class, 'dominant_category_id');
    }
}
