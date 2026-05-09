<?php

namespace App\Http\Controllers\Reports;

use App\Exports\GaEffectivenessExport;
use App\Http\Controllers\Controller;
use App\Models\GaRecommendation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class GaEffectivenessExportController extends Controller
{
    public function pdf(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->buildReportData($year, 100);

        $pdf = Pdf::loadView('reports.exports.ga-effectiveness-pdf', $data)
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-efektivitas-ga-' . $year . '.pdf');
    }

    public function excel(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->buildReportData($year, 1000);
        $exportData = $this->buildExcelData($data);

        return Excel::download(
            new GaEffectivenessExport($exportData),
            'laporan-efektivitas-ga-' . $year . '.xlsx'
        );
    }

    private function buildReportData(int $year, int $limit = 100): array
    {
        $monthlyFitness = DB::table('ga_recommendations')
            ->selectRaw('
                MONTH(created_at) as month,
                COUNT(*) as total,
                AVG(fitness_score) as avg_fitness,
                MAX(fitness_score) as max_fitness,
                MIN(fitness_score) as min_fitness,
                AVG(execution_time_ms) as avg_exec_ms,
                AVG(generations_run) as avg_generations
            ')
            ->whereYear('created_at', $year)
            ->whereNotNull('fitness_score')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        $compliance = DB::table('put_away_confirmations as pac')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN pac.follow_recommendation = 1 THEN 1 ELSE 0 END) as followed,
                SUM(CASE WHEN pac.follow_recommendation = 0 THEN 1 ELSE 0 END) as overridden
            ')
            ->whereYear('pac.created_at', $year)
            ->first();

        $stockLocations = DB::table('stock_records')
            ->where('status', 'available')
            ->groupBy('item_id')
            ->select('item_id', DB::raw('COUNT(DISTINCT cell_id) as cell_count'))
            ->get();

        $splitLocationCount = $stockLocations->where('cell_count', '>', 1)->count();
        $avgLocationsPerSku = $stockLocations->count() > 0
            ? round($stockLocations->avg('cell_count'), 2)
            : 0;

        $capStats = DB::table('cells')
            ->where('is_active', true)
            ->selectRaw('SUM(capacity_used) as used, SUM(capacity_max) as total_cap')
            ->first();

        $rackUtilization = ($capStats->total_cap ?? 0) > 0
            ? round(($capStats->used / $capStats->total_cap) * 100, 1)
            : 0;

        $avgPutAwayMinutes = (int) round(
            DB::table('inbound_transactions')
                ->where('status', 'completed')
                ->whereYear('created_at', $year)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_min')
                ->value('avg_min') ?? 0
        );

        $totalActiveCells = max(DB::table('cells')->where('is_active', true)->count(), 1);
        $itemPutCounts = DB::table('stock_records')
            ->where('status', 'available')
            ->groupBy('item_id')
            ->select('item_id', DB::raw('COUNT(*) as put_count'))
            ->get();

        $randomSplitSum = 0.0;
        $randomLocSum = 0.0;
        $C = $totalActiveCells;
        foreach ($itemPutCounts as $row) {
            $T = max((int) $row->put_count, 1);
            $randomSplitSum += $T > 1 ? (1 - pow(1 / $C, $T - 1)) : 0;
            $randomLocSum += $C * (1 - pow(1 - 1 / $C, $T));
        }

        $randomSplitCount = (int) round($randomSplitSum);
        $randomAvgLocPerSku = $itemPutCounts->count() > 0
            ? round($randomLocSum / $itemPutCounts->count(), 2)
            : 0;
        $randomPutAwayMinutes = $avgPutAwayMinutes > 0
            ? (int) round($avgPutAwayMinutes * 1.20)
            : 0;

        $gaFollowedCount = DB::table('put_away_confirmations')
            ->where('follow_recommendation', 1)
            ->whereYear('created_at', $year)
            ->count();

        $gaFollowedLocs = DB::table('put_away_confirmations as pac')
            ->join('inbound_details as id2', 'id2.id', '=', 'pac.inbound_order_item_id')
            ->where('pac.follow_recommendation', 1)
            ->groupBy('id2.item_id')
            ->select('id2.item_id', DB::raw('COUNT(DISTINCT pac.cell_id) as cell_count'))
            ->get();

        $gaScenarioSplit = $gaFollowedLocs->where('cell_count', '>', 1)->count();
        $gaScenarioAvgLoc = $gaFollowedLocs->count() > 0
            ? round($gaFollowedLocs->avg('cell_count'), 2)
            : 0;

        $gaScenarioMinutes = (int) round(
            DB::table('inbound_transactions as it')
                ->join('ga_recommendations as gar', 'gar.inbound_order_id', '=', 'it.id')
                ->where('it.status', 'completed')
                ->whereIn('gar.status', ['accepted', 'pending_review'])
                ->whereYear('it.created_at', $year)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, it.created_at, it.updated_at)) as avg_min')
                ->value('avg_min') ?? 0
        );

        $scenarioComparison = [
            [
                'label' => 'Kondisi Aktual',
                'desc' => 'Snapshot stok gudang saat ini',
                'split_count' => $splitLocationCount,
                'avg_loc' => $avgLocationsPerSku,
                'utilization' => $rackUtilization,
                'putaway_min' => $avgPutAwayMinutes,
                'is_simulated' => false,
                'is_ga' => false,
            ],
            [
                'label' => 'Penempatan Acak',
                'desc' => 'Simulasi tanpa optimasi berbasis occupancy model',
                'split_count' => $randomSplitCount,
                'avg_loc' => $randomAvgLocPerSku,
                'utilization' => $rackUtilization,
                'putaway_min' => $randomPutAwayMinutes,
                'is_simulated' => true,
                'is_ga' => false,
            ],
            [
                'label' => 'Rekomendasi GA',
                'desc' => 'Konfirmasi put-away yang mengikuti saran GA',
                'split_count' => $gaScenarioSplit,
                'avg_loc' => $gaScenarioAvgLoc,
                'utilization' => $rackUtilization,
                'putaway_min' => $gaScenarioMinutes,
                'is_simulated' => false,
                'is_ga' => true,
            ],
        ];

        $overallStats = DB::table('ga_recommendations')
            ->whereYear('created_at', $year)
            ->selectRaw('
                COUNT(*) as total,
                AVG(fitness_score) as avg_fitness,
                MAX(fitness_score) as best_fitness,
                MIN(fitness_score) as worst_fitness,
                AVG(execution_time_ms) as avg_exec_ms,
                AVG(generations_run) as avg_gen
            ')
            ->first();

        $compliancePct = ($compliance->total ?? 0) > 0
            ? round(($compliance->followed / $compliance->total) * 100, 1)
            : 0;

        $summary = [
            'total_ga' => $overallStats->total ?? 0,
            'avg_fitness' => round($overallStats->avg_fitness ?? 0, 4),
            'best_fitness' => round($overallStats->best_fitness ?? 0, 4),
            'worst_fitness' => round($overallStats->worst_fitness ?? 0, 4),
            'avg_exec_ms' => round($overallStats->avg_exec_ms ?? 0),
            'avg_generations' => round($overallStats->avg_gen ?? 0),
            'compliance_pct' => $compliancePct,
            'split_location_count' => $splitLocationCount,
            'avg_locations_per_sku' => $avgLocationsPerSku,
            'rack_utilization' => $rackUtilization,
            'avg_putaway_minutes' => $avgPutAwayMinutes,
        ];

        $gaRecords = GaRecommendation::with(['inboundOrder', 'generatedBy'])
            ->whereYear('created_at', $year)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $details = DB::table('ga_recommendation_details as grd')
            ->join('ga_recommendations as gr', 'gr.id', '=', 'grd.ga_recommendation_id')
            ->join('inbound_transactions as it', 'it.id', '=', 'gr.inbound_order_id')
            ->join('inbound_details as iod', 'iod.id', '=', 'grd.inbound_order_item_id')
            ->join('items as i', 'i.id', '=', 'iod.item_id')
            ->join('cells as c', 'c.id', '=', 'grd.cell_id')
            ->select([
                'gr.id as ga_id',
                'it.do_number',
                'gr.status as ga_status',
                'gr.fitness_score',
                'i.sku',
                'i.name as item_name',
                'c.code as cell_code',
                'grd.quantity',
                'grd.gene_fitness',
                'grd.fc_cap_score',
                'grd.fc_cat_score',
                'grd.fc_aff_score',
                'grd.fc_split_score',
                'gr.created_at',
            ])
            ->whereYear('gr.created_at', $year)
            ->orderByDesc('gr.created_at')
            ->orderBy('grd.id')
            ->limit($limit * 10)
            ->get();

        return compact(
            'year',
            'summary',
            'compliance',
            'compliancePct',
            'scenarioComparison',
            'gaRecords',
            'details',
            'monthlyFitness',
            'totalActiveCells',
            'itemPutCounts',
            'gaFollowedCount'
        );
    }

    private function buildExcelData(array $data): array
    {
        $summary = $data['summary'];
        $compliance = $data['compliance'];

        $summaryRows = [
            ['Tahun Laporan', $data['year']],
            ['Tanggal Export', now()->format('Y-m-d H:i:s')],
            ['Total GA Run', $summary['total_ga']],
            ['Average Fitness', $summary['avg_fitness']],
            ['Best Fitness', $summary['best_fitness']],
            ['Worst Fitness', $summary['worst_fitness']],
            ['Average Execution Time (ms)', $summary['avg_exec_ms']],
            ['Average Generations', $summary['avg_generations']],
            ['Follow GA Rate (%)', $summary['compliance_pct']],
            ['Followed Confirmation', (int) ($compliance->followed ?? 0)],
            ['Overridden Confirmation', (int) ($compliance->overridden ?? 0)],
            ['Split Location Count', $summary['split_location_count']],
            ['Average Locations per SKU', $summary['avg_locations_per_sku']],
            ['Rack Utilization (%)', $summary['rack_utilization']],
            ['Average Put-Away Time (minutes)', $summary['avg_putaway_minutes']],
        ];

        $scenarioRows = collect($data['scenarioComparison'])->map(fn($sc) => [
            $sc['label'],
            $sc['desc'],
            $sc['split_count'],
            $sc['avg_loc'],
            $sc['utilization'],
            $sc['putaway_min'],
            $sc['is_simulated'] ? 'Ya' : 'Tidak',
            $sc['is_ga'] ? 'Ya' : 'Tidak',
        ])->values()->all();

        $gaRunRows = $data['gaRecords']->map(fn($ga) => [
            $ga->id,
            $ga->inboundOrder->do_number ?? '-',
            $ga->status,
            $ga->fitness_score,
            $ga->generations_run,
            $ga->execution_time_ms,
            $ga->generatedBy->name ?? '-',
            optional($ga->generated_at)->format('Y-m-d H:i:s') ?: optional($ga->created_at)->format('Y-m-d H:i:s'),
            $ga->review_required ? 'Ya' : 'Tidak',
            $ga->review_reason,
        ])->values()->all();

        $detailRows = $data['details']->map(fn($d) => [
            $d->ga_id,
            $d->do_number,
            $d->ga_status,
            $d->fitness_score,
            $d->sku,
            $d->item_name,
            $d->cell_code,
            $d->quantity,
            $d->gene_fitness,
            $d->fc_cap_score,
            $d->fc_cat_score,
            $d->fc_aff_score,
            $d->fc_split_score,
            $d->created_at,
        ])->values()->all();

        return [
            'summary_headings' => ['Metrik', 'Nilai'],
            'summary_rows' => $summaryRows,
            'scenario_headings' => ['Skenario', 'Deskripsi', 'Split Location', 'Avg Lokasi/SKU', 'Utilisasi Rak (%)', 'Est. Waktu Put-Away (menit)', 'Simulasi', 'Skenario GA'],
            'scenario_rows' => $scenarioRows,
            'ga_run_headings' => ['GA ID', 'DO Number', 'Status', 'Fitness Score', 'Generations Run', 'Execution Time (ms)', 'Generated By', 'Generated At', 'Review Required', 'Review Reason'],
            'ga_run_rows' => $gaRunRows,
            'detail_headings' => ['GA ID', 'DO Number', 'GA Status', 'Overall Fitness', 'SKU', 'Item Name', 'Recommended Cell', 'Quantity', 'Gene Fitness', 'FC_CAP', 'FC_CAT', 'FC_AFF', 'FC_SPLIT', 'Run Created At'],
            'detail_rows' => $detailRows,
        ];
    }
}
