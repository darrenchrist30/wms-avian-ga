<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id',
        'warehouse_id',
        'from_cell_id',
        'to_cell_id',
        'performed_by',
        'lpn',
        'batch_no',
        'quantity',
        'movement_type',
        'reference_type',
        'reference_id',
        'notes',
        'moved_at',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function fromCell()
    {
        return $this->belongsTo(Cell::class, 'from_cell_id');
    }

    public function toCell()
    {
        return $this->belongsTo(Cell::class, 'to_cell_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // Scope: filter by tipe gerakan
    public function scopeOfType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    // Scope: hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
