<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemAffinity extends Model
{
    protected $fillable = [
        'item_id',
        'related_item_id',
        'affinity_score',
        'co_occurrence_count',
    ];

    protected $casts = [
        'affinity_score'      => 'float',
        'co_occurrence_count' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function relatedItem()
    {
        return $this->belongsTo(Item::class, 'related_item_id');
    }

    /**
     * Ambil skor afinitas antara dua item (A→B atau B→A, keduanya sama).
     * Dipakai GA Python via API untuk menghitung FC_AFF.
     */
    public static function getScore(int $itemId, int $relatedItemId): float
    {
        $record = static::where(function ($q) use ($itemId, $relatedItemId) {
            $q->where('item_id', $itemId)->where('related_item_id', $relatedItemId);
        })->orWhere(function ($q) use ($itemId, $relatedItemId) {
            $q->where('item_id', $relatedItemId)->where('related_item_id', $itemId);
        })->first();

        return $record ? (float) $record->affinity_score : 0.0;
    }
}
