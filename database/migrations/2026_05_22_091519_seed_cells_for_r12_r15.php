<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['12', '13', '14', '15'] as $rackCode) {
            $rack = DB::table('racks')->where('code', $rackCode)->first();
            if (!$rack) continue;

            // Skip if cells already exist for this rack
            if (DB::table('cells')->where('rack_id', $rack->id)->exists()) continue;

            $levels = (int) ($rack->total_levels ?? 7);
            for ($lv = 1; $lv <= $levels; $lv++) {
                DB::table('cells')->insert([
                    'rack_id'       => $rack->id,
                    'code'          => "R{$rackCode}-L{$lv}",
                    'label'         => "Rak {$rackCode} Level {$lv}",
                    'level'         => $lv,
                    'column'        => 1,
                    'capacity_max'  => 20,
                    'capacity_used' => 0,
                    'status'        => 'available',
                    'is_active'     => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['12', '13', '14', '15'] as $rackCode) {
            $rack = DB::table('racks')->where('code', $rackCode)->first();
            if (!$rack) continue;
            DB::table('cells')
                ->where('rack_id', $rack->id)
                ->whereNull('blok')
                ->delete();
        }
    }
};
