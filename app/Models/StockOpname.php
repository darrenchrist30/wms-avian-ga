<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $fillable = [
        'warehouse_id', 'opname_number', 'status', 'opname_date',
        'notes', 'created_by', 'completed_by', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'opname_date'  => 'date',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    public function getProgressPercentAttribute(): int
    {
        $total = $this->items()->count();
        if ($total === 0) return 0;
        $counted = $this->items()->where('status', 'counted')->count();
        return (int) round($counted / $total * 100);
    }

    // Generate nomor opname otomatis: SO-YYYY-NNNN
    public static function generateNumber(): string
    {
        $year = now()->year;
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->first();
        $seq  = $last ? ((int) substr($last->opname_number, -4)) + 1 : 1;
        return 'SO-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
