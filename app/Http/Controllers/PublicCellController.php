<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\Item;
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
            ->with(['rack.warehouse', 'dominantCategory'])
            ->first();

        if (!$cell) {
            // Check if the code belongs to an item (operator scanned item QR instead of rack QR)
            $item = Item::with('category', 'unit')
                ->where('is_active', true)
                ->where(fn($q) => $q->where('sku', $code)->orWhere('barcode', $code))
                ->first();

            if ($item) {
                $itemStocks = Stock::with(['cell.rack.warehouse'])
                    ->where('item_id', $item->id)
                    ->where('status', 'available')
                    ->where('quantity', '>', 0)
                    ->orderBy('inbound_date', 'asc')
                    ->get();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'type'    => 'item',
                        'item'    => [
                            'name'           => $item->name,
                            'sku'            => $item->sku,
                            'category'       => $item->category?->name,
                            'category_color' => $item->category?->color_code ?? '#6c757d',
                            'unit'           => $item->unit?->code ?? $item->unit?->name,
                            'merk'           => $item->merk,
                            'total_qty'      => $itemStocks->sum('quantity'),
                        ],
                        'stocks'  => $itemStocks->map(fn($s) => [
                            'cell_code'    => $s->cell?->physical_code ?? $s->cell?->code ?? '—',
                            'rack_code'    => ($s->cell && $s->cell->blok !== null && $s->cell->grup !== null)
                                ? $s->cell->blok . '-' . strtoupper((string) $s->cell->grup)
                                : ($s->cell?->rack?->code ?? '—'),
                            'quantity'     => $s->quantity,
                            'unit'         => $item->unit?->code ?? $item->unit?->name ?? '',
                            'inbound_date' => $s->inbound_date?->format('d M Y') ?? '—',
                        ])->values(),
                    ]);
                }
                return view('public.item', compact('item', 'itemStocks'));
            }

            // Check if it's a rack-prefix "blok-grup" format (e.g. "1-A")
            $parts = explode('-', $code);
            if (count($parts) === 2) {
                $rackBlok = $parts[0];
                $rackGrup = strtoupper($parts[1]);

                $rackCells = Cell::with(['dominantCategory', 'rack.warehouse'])
                    ->where('is_active', true)
                    ->where('blok', $rackBlok)
                    ->whereRaw('UPPER(grup) = ?', [$rackGrup])
                    ->orderBy('kolom')
                    ->orderBy('baris')
                    ->get();

                if ($rackCells->isNotEmpty()) {
                    $rackCode      = $rackBlok . '-' . $rackGrup;
                    $warehouseName = $rackCells->first()->rack?->warehouse?->name ?? '—';

                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => true,
                            'type'    => 'rack',
                            'rack'    => [
                                'code'      => $rackCode,
                                'warehouse' => $warehouseName,
                                'total'     => $rackCells->count(),
                                'available' => $rackCells->where('status', 'available')->count(),
                                'partial'   => $rackCells->where('status', 'partial')->count(),
                                'full'      => $rackCells->where('status', 'full')->count(),
                            ],
                            'cells' => $rackCells->map(fn($c) => [
                                'code'              => $c->physical_code ?? $c->code,
                                'status'            => $c->status,
                                'capacity_used'     => $c->physical_capacity_used ?? $c->capacity_used,
                                'capacity_max'      => $c->physical_capacity_max ?? $c->capacity_max,
                                'dominant_category' => $c->dominantCategory?->name,
                                'category_color'    => $c->dominantCategory?->color_code ?? '#dee2e6',
                            ])->values(),
                        ]);
                    }
                    return view('public.rack', compact('rackCells', 'rackCode', 'warehouseName'));
                }
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => "Kode '{$code}' tidak ditemukan."], 404);
            }
            return view('public.not-found', compact('code'));
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
                    'warehouse'        => $cell->rack?->warehouse?->name ?? '—',
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
