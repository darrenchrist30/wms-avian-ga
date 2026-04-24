<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeadstockNotification extends Model
{
    protected $fillable = [
        'item_id',
        'cell_id',
        'warehouse_id',
        'days_no_movement',
        'last_movement_at',
        'status',
    ];

    protected $casts = [
        'last_movement_at'  => 'datetime',
        'days_no_movement'  => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}
