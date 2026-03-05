<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'address', 'pic', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function inboundOrders()
    {
        return $this->hasMany(InboundOrder::class);
    }

    // Total kapasitas seluruh cell dalam gudang
    public function getTotalCapacityAttribute(): int
    {
        return $this->zones()->with('racks.cells')->get()
            ->flatMap(fn($z) => $z->racks)
            ->flatMap(fn($r) => $r->cells)
            ->sum('capacity_max');
    }

    // Total kapasitas terpakai
    public function getUsedCapacityAttribute(): int
    {
        return $this->zones()->with('racks.cells')->get()
            ->flatMap(fn($z) => $z->racks)
            ->flatMap(fn($r) => $r->cells)
            ->sum('capacity_used');
    }
}
