<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\Stock;
use Illuminate\Http\Request;

class PublicCellController extends Controller
{
    public function show(string $code, Request $request)
    {
        // URL-encoded QR labels contain the full URL; extract just the code segment.
        if (str_contains($code, '/c/')) {
            $code = trim(last(explode('/c/', $code)), '/ ');
        }

        $cell = Cell::where('is_active', true)
            ->where(fn($q) => $q->where('code', $code)->orWhere('qr_code', $code)->orWhere('label', $code))
            ->with(['rack.zone.warehouse', 'dominantCategory'])
            ->first();

        if (!$cell) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => "Cell '{$code}' tidak ditemukan."], 404);
            }
            abort(404, 'Cell tidak ditemukan.');
        }

        $stocks = Stock::with(['item.category', 'item.unit'])
            ->where('cell_id', $cell->id)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('inbound_date', 'asc')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'cell'    => [
                    'id'               => $cell->id,
                    'code'             => $cell->code,
                    'label'            => $cell->label,
                    'status'           => $cell->status,
                    'zone_category'    => $cell->zone_category,
                    'capacity_max'     => $cell->capacity_max,
                    'capacity_used'    => $cell->capacity_used,
                    'warehouse'        => $cell->rack?->zone?->warehouse?->name ?? '—',
                    'zone'             => $cell->rack?->zone?->code ?? '—',
                    'rack'             => $cell->rack?->code ?? '—',
                    'level'            => $cell->level,
                    'dominant_category'=> $cell->dominantCategory?->name,
                ],
                'stocks' => $stocks->map(fn($s) => [
                    'item_name'      => $s->item?->name,
                    'item_sku'       => $s->item?->sku,
                    'item_merk'      => $s->item?->merk,
                    'category'       => $s->item?->category?->name,
                    'category_color' => $s->item?->category?->color_code ?? '#6c757d',
                    'unit'           => $s->item?->unit?->code,
                    'quantity'       => $s->quantity,
                    'inbound_date'   => $s->inbound_date?->format('d M Y') ?? '—',
                ]),
            ]);
        }

        return view('public.cell', compact('cell', 'stocks'));
    }
}
