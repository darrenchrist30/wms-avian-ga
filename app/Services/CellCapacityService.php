<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class CellCapacityService
{
    /**
     * Kept for backward compatibility with older callers.
     * Capacity is now direct quantity-based: 1 stored unit consumes 1 capacity unit.
     */
    public const SCALE = 1;

    /**
     * Default maximum quantity for a cell when capacity_max is not set.
     */
    public const DEFAULT_CAPACITY_MAX = 100;

    /** Legacy fallback retained for old UI/commands. */
    private const FALLBACK_MAX_STOCK = 100;

    /**
     * Legacy helper retained for old UI/commands.
     * Current capacity calculation does not use item.max_stock anymore.
     */
    public function itemMaxStock(Item|int|null $item): int
    {
        if (!($item instanceof Item)) {
            $item = $item
                ? Item::whereKey($item)->select('max_stock')->first()
                : null;
        }

        if (!$item || (int) $item->max_stock <= 0) {
            return self::FALLBACK_MAX_STOCK;
        }

        return (int) $item->max_stock;
    }

    /**
     * Return the effective direct capacity_max for a cell.
     * Admins can configure this from the Cells master data screen.
     */
    public function capacityMax(Cell $cell): int
    {
        return max(1, (int) ($cell->capacity_max ?: self::DEFAULT_CAPACITY_MAX));
    }

    /**
     * Convert quantity into direct cell-capacity demand.
     *
     * Client rule: capacity_max is set in master cell, and each stored quantity
     * consumes the same direct capacity unit. item.max_stock remains inventory
     * planning data, not a conversion factor for cell capacity.
     */
    public function pointsForQuantity(Item|int|null $item, int $quantity): int
    {
        return max(0, $quantity);
    }

    /**
     * Total direct capacity currently occupied in a cell.
     * Calculated live from stock_records so it is always accurate.
     */
    public function usedPoints(Cell $cell): int
    {
        return (int) DB::table('stock_records as sr')
            ->where('sr.cell_id', $cell->id)
            ->where('sr.quantity', '>', 0)
            ->whereIn('sr.status', ['available', 'reserved'])
            ->sum('sr.quantity');
    }

    public function remainingPoints(Cell $cell): int
    {
        return max(0, $this->capacityMax($cell) - $this->usedPoints($cell));
    }

    /**
     * Capacity demand for adding quantity to a cell.
     * Same-SKU consolidation is still preferred by GA fitness, but it does not
     * reduce capacity demand: adding 50 BJ consumes 50 capacity units.
     */
    public function demandForPlacement(Item|int $item, Cell $cell, int $quantity): int
    {
        return $this->pointsForQuantity($item, $quantity);
    }

    /** Resync the stored capacity_used field and update the cell status. */
    public function refresh(Cell $cell): void
    {
        $used = $this->usedPoints($cell);
        $cell->forceFill(['capacity_used' => $used])->save();
        $cell->updateStatus();
    }
}
