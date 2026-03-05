<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'unit_id', 'sku', 'erp_item_code', 'name', 'barcode',
        'description', 'min_stock', 'max_stock', 'reorder_point', 'movement_type',
        'weight_kg', 'volume_m3', 'image', 'is_active', 'deadstock_threshold_days',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'weight_kg'                => 'float',
        'volume_m3'                => 'float',
        'deadstock_threshold_days' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function putAwayRecommendations()
    {
        return $this->hasMany(PutAwayRecommendation::class);
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stocks()->where('status', 'available')->sum('quantity');
    }

    public function getIsBelowMinStockAttribute(): bool
    {
        return $this->total_stock < $this->min_stock;
    }

    public function getFifoStocksAttribute()
    {
        return $this->stocks()
            ->where('status', 'available')
            ->orderBy('inbound_date', 'asc')
            ->get();
    }
}