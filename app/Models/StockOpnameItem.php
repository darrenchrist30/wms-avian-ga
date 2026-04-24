<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id', 'item_id', 'cell_id',
        'system_qty', 'physical_qty', 'difference',
        'status', 'scanned_by', 'scanned_at', 'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function opname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    // Label selisih: lebih / kurang / sesuai
    public function getDifferenceStatusAttribute(): string
    {
        if ($this->difference === null) return 'pending';
        if ($this->difference > 0)  return 'surplus';
        if ($this->difference < 0)  return 'shortage';
        return 'match';
    }
}
