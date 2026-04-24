<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ItemAffinity;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ItemAffinityController extends Controller
{
    public function index()
    {
        $totalPairs   = ItemAffinity::count();
        $avgScore     = $totalPairs > 0 ? round(ItemAffinity::avg('affinity_score'), 4) : 0;
        $maxCount     = $totalPairs > 0 ? ItemAffinity::max('co_occurrence_count') : 0;
        $categories   = ItemCategory::orderBy('name')->get();

        // Top 10 pasangan untuk bar chart
        $top10 = ItemAffinity::with(['item:id,name,sku', 'relatedItem:id,name,sku'])
            ->orderByDesc('co_occurrence_count')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'label'   => ($a->item->sku ?? '?') . ' – ' . ($a->relatedItem->sku ?? '?'),
                'count'   => $a->co_occurrence_count,
                'score'   => round((float) $a->affinity_score, 4),
            ])
            ->values();

        return view('master.affinities.index', compact(
            'totalPairs', 'avgScore', 'maxCount', 'categories', 'top10'
        ));
    }

    public function datatable(Request $request)
    {
        $query = ItemAffinity::with(['item.category', 'relatedItem.category'])
            ->orderByDesc('co_occurrence_count');

        if ($request->filled('category_id')) {
            $cat = (int) $request->category_id;
            $query->where(function ($q) use ($cat) {
                $q->whereHas('item',        fn($q2) => $q2->where('category_id', $cat))
                  ->orWhereHas('relatedItem', fn($q2) => $q2->where('category_id', $cat));
            });
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('item_a', function ($row) {
                $cat = $row->item->category;
                $badge = $cat
                    ? '<span class="badge px-1" style="background:' . ($cat->color_code ?? '#6c757d') . ';color:#fff;font-size:10px;">' . $cat->name . '</span>'
                    : '';
                return '<div class="font-weight-bold" style="font-size:13px;">' . e($row->item->name ?? '—') . '</div>'
                     . '<small class="text-muted">' . e($row->item->sku ?? '') . '</small> ' . $badge;
            })
            ->addColumn('item_b', function ($row) {
                $cat = $row->relatedItem->category;
                $badge = $cat
                    ? '<span class="badge px-1" style="background:' . ($cat->color_code ?? '#6c757d') . ';color:#fff;font-size:10px;">' . $cat->name . '</span>'
                    : '';
                return '<div class="font-weight-bold" style="font-size:13px;">' . e($row->relatedItem->name ?? '—') . '</div>'
                     . '<small class="text-muted">' . e($row->relatedItem->sku ?? '') . '</small> ' . $badge;
            })
            ->addColumn('score_bar', function ($row) {
                $pct   = round((float) $row->affinity_score * 100);
                $color = $pct >= 70 ? '#28a745' : ($pct >= 40 ? '#fd7e14' : '#6c757d');
                return '<div class="d-flex align-items-center" style="gap:6px;">'
                     . '<div style="flex:1;background:#e9ecef;border-radius:4px;height:8px;">'
                     . '<div style="width:' . $pct . '%;background:' . $color . ';height:100%;border-radius:4px;"></div></div>'
                     . '<small class="font-weight-bold" style="min-width:36px;">' . number_format((float) $row->affinity_score, 4) . '</small>'
                     . '</div>';
            })
            ->rawColumns(['item_a', 'item_b', 'score_bar'])
            ->make(true);
    }
}
