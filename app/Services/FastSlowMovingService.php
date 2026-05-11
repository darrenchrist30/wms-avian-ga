<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\StockMovement;

class FastSlowMovingService
{
    private const FAST_THRESHOLD   = 5;
    private const WINDOW_DAYS      = 30;

    public function classify(int $itemId, int $warehouseId): array
    {
        $count = StockMovement::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('movement_type', 'outbound')
            ->where('moved_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->count();

        return match (true) {
            $count >= self::FAST_THRESHOLD => [
                'classification' => 'fast',
                'label'          => 'Fast Moving',
                'color'          => 'success',
                'count'          => $count,
            ],
            $count >= 1 => [
                'classification' => 'medium',
                'label'          => 'Medium Moving',
                'color'          => 'warning',
                'count'          => $count,
            ],
            default => [
                'classification' => 'slow',
                'label'          => 'Slow Moving',
                'color'          => 'secondary',
                'count'          => $count,
            ],
        };
    }

    /**
     * Suggest a cell based on fast/slow classification.
     * Fast  → rak dengan kode terkecil (dekat pintu masuk)
     * Slow  → rak dengan kode terbesar (belakang gudang)
     * Medium → tengah (urutan ascending, mulai dari tengah)
     */
    public function suggestCell(int $itemId, int $warehouseId, int $quantity): ?array
    {
        $info = $this->classify($itemId, $warehouseId);

        $cells = Cell::with('rack')
            ->whereHas('rack.zone', fn($q) => $q->where('warehouse_id', $warehouseId))
            ->whereIn('status', ['available', 'partial'])
            ->get()
            ->filter(fn($c) => ($c->capacity_max - $c->capacity_used) >= $quantity);

        if ($cells->isEmpty()) return null;

        $sorted = $info['classification'] === 'fast'
            ? $cells->sortBy(fn($c) => $c->rack?->code)->values()
            : $cells->sortByDesc(fn($c) => $c->rack?->code)->values();

        $cell = $sorted->first();

        return [
            'cell_id'            => $cell->id,
            'cell_code'          => $cell->code,
            'rack_code'          => $cell->rack?->code ?? '-',
            'capacity_remaining' => $cell->capacity_max - $cell->capacity_used,
            'capacity_max'       => $cell->capacity_max,
        ];
    }
}
