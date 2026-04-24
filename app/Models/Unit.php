<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
