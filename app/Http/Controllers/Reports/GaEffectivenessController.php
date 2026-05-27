<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InboundOrder;
use App\Services\GaEffectivenessEvaluationService;
use Illuminate\Http\Request;

class GaEffectivenessController extends Controller
{
    public function index(Request $request, GaEffectivenessEvaluationService $service)
    {
        $selectedOrderIds = collect($request->input('order_ids', []))
            ->when(is_string($request->input('order_ids')), fn($items) => collect(explode(',', (string) $request->input('order_ids'))))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $randomSeed = max(1, (int) $request->input('random_seed', 42));

        $orders = InboundOrder::with(['warehouse'])
            ->whereHas('items')
            ->orderByDesc('do_date')
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        $result = null;
        if ($selectedOrderIds) {
            $result = $service->evaluate($selectedOrderIds, $randomSeed);
        }

        return view('reports.ga-effectiveness', [
            'orders' => $orders,
            'selectedOrderIds' => $selectedOrderIds,
            'randomSeed' => $randomSeed,
            'result' => $result,
        ]);
    }
}
