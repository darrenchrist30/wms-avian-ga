<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = ['code', 'name', 'address', 'pic', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function racks()
    {
        return $this->hasMany(Rack::class);
    }

    public function inboundOrders()
    {
        return $this->hasMany(InboundOrder::class);
    }

    public function getTotalCapacityAttribute(): int
    {
        return $this->racks()->with('cells')->get()
            ->flatMap(fn($r) => $r->cells)
            ->sum('capacity_max');
    }

    public function getUsedCapacityAttribute(): int
    {
        return $this->racks()->with('cells')->get()
            ->flatMap(fn($r) => $r->cells)
            ->sum('capacity_used');
    }
}
