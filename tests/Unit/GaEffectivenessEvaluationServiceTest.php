<?php

namespace Tests\Unit;

use App\Models\Cell;
use App\Services\CellCapacityService;
use App\Services\GaEffectivenessEvaluationService;
use PHPUnit\Framework\TestCase;

class GaEffectivenessEvaluationServiceTest extends TestCase
{
    private function service(): GaEffectivenessEvaluationService
    {
        return new GaEffectivenessEvaluationService(new CellCapacityService());
    }

    public function test_improvement_is_safe_when_baseline_zero(): void
    {
        $result = $this->service()->calculateImprovement(0, 0, true);

        $this->assertSame('Tidak berubah', $result['label']);
        $this->assertSame(0.0, $result['value']);
    }

    public function test_lower_is_better_improvement(): void
    {
        $result = $this->service()->calculateImprovement(100, 75, true);

        $this->assertSame(25.0, $result['value']);
        $this->assertSame('25.00%', $result['label']);
    }

    public function test_cell_distance_uses_mspart_coordinates(): void
    {
        $a = new Cell(['blok' => 1, 'grup' => 'A', 'kolom' => 1, 'baris' => 1]);
        $b = new Cell(['blok' => 2, 'grup' => 'C', 'kolom' => 3, 'baris' => 4]);

        $this->assertSame(27.0, $this->service()->distanceBetweenCells($a, $b));
    }
}
