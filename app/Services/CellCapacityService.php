<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\Item;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class CellCapacityService
{
    /**
     * Normalisation scale.
     * An item whose max_stock equals SCALE occupies exactly 1 point per unit,
     * so a DEFAULT_CAPACITY_MAX cell holds exactly max_stock units of such an item.
     */
    public const SCALE = 100;

    /**
     * Default capacity for a cell when no capacity_max is set in the database.
     * Think of it as "100 normalised slots".
     * - An item with max_stock = 100 fills 1 slot per unit → cell holds 100 units.
     * - An item with max_stock =  10 fills 10 slots per unit → cell holds 10 units.
     * - An item with max_stock = 200 fills 0.5 → ceil(1) slot per unit → cell holds ~200 units.
     */
    public const DEFAULT_CAPACITY_MAX = 100;

    /** Fallback max_stock when an item record has none set. */
    private const FALLBACK_MAX_STOCK = 100;

    // ── Item helpers ──────────────────────────────────────────────────────────

    /**
     * Return the effective max_stock for an item.
     * This is the warehouse policy ceiling — how many units belong in one cell.
     * Higher max_stock  → small/frequent item (takes less capacity per unit).
     * Lower  max_stock  → large/rare item    (takes more capacity per unit).
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

    // ── Cell capacity_max ─────────────────────────────────────────────────────

    /**
     * Return the effective capacity_max (in points) for a cell.
     * Uses the stored value; falls back to DEFAULT_CAPACITY_MAX.
     * Admins can override per-cell via the Cells master data screen.
     */
    public function capacityMax(Cell $cell): int
    {
        return max(1, (int) ($cell->capacity_max ?: self::DEFAULT_CAPACITY_MAX));
    }

    // ── Core capacity calculations ────────────────────────────────────────────

    /**
     * Convert a quantity of an item into capacity points.
     *
     * Formula: ceil(qty × SCALE / max_stock)
     *
     * Examples (SCALE = 100):
     *   50 units, max_stock=200 → ceil(50×100/200) = 25  points (small item)
     *   50 units, max_stock= 10 → ceil(50×100/10)  = 500 points (large item)
     *    1 unit,  max_stock=  2 → ceil(1×100/2)    =  50 points (big, rare item)
     */
    public function pointsForQuantity(Item|int|null $item, int $quantity): int
    {
        $quantity = max(0, $quantity);
        if ($quantity === 0) return 0;

        $maxStock = $this->itemMaxStock($item);

        return max(1, (int) ceil($quantity * self::SCALE / $maxStock));
    }

    /**
     * Total capacity points currently occupied in a cell.
     * Calculated live from stock_records so it is always accurate.
     */
    public function usedPoints(Cell $cell): int
    {
        $rows = DB::table('stock_records as sr')
            ->join('items as i', 'i.id', '=', 'sr.item_id')
            ->where('sr.cell_id', $cell->id)
            ->where('sr.quantity', '>', 0)
            ->whereIn('sr.status', ['available', 'reserved'])
            ->groupBy('sr.item_id', 'i.max_stock')
            ->selectRaw('i.max_stock, SUM(sr.quantity) as qty')
            ->get();

        return (int) $rows->sum(function ($row) {
            $maxStock = max(1, (int) ($row->max_stock ?: self::FALLBACK_MAX_STOCK));
            return max(1, (int) ceil((int) $row->qty * self::SCALE / $maxStock));
        });
    }

    public function remainingPoints(Cell $cell): int
    {
        return max(0, $this->capacityMax($cell) - $this->usedPoints($cell));
    }

    /**
     * Marginal capacity demand for adding $quantity more of $item to $cell.
     * Uses before/after ceiling so upserts into an already-stocked cell
     * are not double-charged for points already consumed.
     */
    public function demandForPlacement(Item|int $item, Cell $cell, int $quantity): int
    {
        $currentQty = (int) Stock::where('cell_id', $cell->id)
            ->where('item_id', $item instanceof Item ? $item->id : $item)
            ->where('quantity', '>', 0)
            ->whereIn('status', ['available', 'reserved'])
            ->sum('quantity');

        $before = $this->pointsForQuantity($item, $currentQty);
        $after  = $this->pointsForQuantity($item, $currentQty + max(0, $quantity));

        return max(0, $after - $before);
    }

    /** Resync the stored capacity_used field and update the cell status. */
    public function refresh(Cell $cell): void
    {
        $used = $this->usedPoints($cell);
        $cell->forceFill(['capacity_used' => $used])->save();
        $cell->updateStatus();
    }
}
