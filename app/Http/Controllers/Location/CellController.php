<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\ItemCategory;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class CellController extends Controller
{
    public function index()
    {
        $zones = Zone::with('warehouse')->where('is_active', true)->orderBy('code')->get();
        $racks = Rack::with('zone')->where('is_active', true)->orderBy('code')->get();
        return view('location.cells.index', compact('zones', 'racks'));
    }

    public function create()
    {
        $racks      = Rack::with('zone.warehouse')->where('is_active', true)->orderBy('code')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.cells.form', [
            'typeForm'   => 'create',
            'data'       => null,
            'racks'      => $racks,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'rack_id'              => 'required|exists:racks,id',
            'level'                => 'required|integer|min:1|max:26',
            'capacity_max'         => 'required|integer|min:1',
            'dominant_category_id' => 'nullable|exists:item_categories,id',
            'status'               => 'required|in:available,partial,full,blocked,reserved',
            'is_active'            => 'boolean',
        ]);
        $exists = Cell::where('rack_id', $request->rack_id)
            ->where('level', $request->level)->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['level' => 'Sel dengan level tersebut sudah ada di rak ini.']);
        }
        $rack   = Rack::findOrFail($request->rack_id);
        $letter = chr(64 + $request->level); // 1=A, 2=B, ..., 7=G
        DB::beginTransaction();
        try {
            Cell::create([
                'rack_id'              => $request->rack_id,
                'dominant_category_id' => $request->dominant_category_id,
                'code'                 => $rack->code . '-' . $letter,
                'level'                => $request->level,
                'column'               => 1,
                'capacity_max'         => $request->capacity_max,
                'capacity_used'        => 0,
                'status'               => $request->status,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.cells.index')->with('success', 'Sel berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan sel. Silakan coba lagi.');
        }
    }

    public function show($id) { return redirect()->route('location.cells.index'); }

    public function edit($id)
    {
        $data       = Cell::with('rack.zone.warehouse')->findOrFail($id);
        $racks      = Rack::with('zone.warehouse')->where('is_active', true)->orderBy('code')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.cells.form', [
            'typeForm'   => 'edit',
            'data'       => $data,
            'racks'      => $racks,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, $id)
    {
        $cell = Cell::findOrFail($id);
        $request->validate([
            'capacity_max'         => 'required|integer|min:1',
            'dominant_category_id' => 'nullable|exists:item_categories,id',
            'status'               => 'required|in:available,partial,full,blocked,reserved',
            'is_active'            => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            $cell->update([
                'dominant_category_id' => $request->dominant_category_id,
                'capacity_max'         => $request->capacity_max,
                'status'               => $request->status,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.cells.index')->with('success', 'Sel berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui sel. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $cell = Cell::withCount('stocks')->findOrFail($id);
        if ($cell->stocks_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Sel tidak dapat dihapus karena masih memiliki ' . $cell->stocks_count . ' stok aktif.'], 422);
        }
        DB::beginTransaction();
        try {
            $cell->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Sel berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    // ─── Halaman Scan QR Cell (untuk operator tablet) ───────────────────────────
    public function scanPage()
    {
        return view('location.cells.scan');
    }

    // ─── AJAX lookup cell berdasarkan QR / kode ──────────────────────────────────
    public function lookup(Request $request)
    {
        $code = trim($request->input('code', ''));
        if (!$code) {
            return response()->json(['found' => false, 'message' => 'Kode tidak boleh kosong.']);
        }

        $cell = Cell::with(['rack.zone.warehouse', 'dominantCategory'])
            ->where(function ($q) use ($code) {
                $q->where('code', $code)
                  ->orWhere('qr_code', $code)
                  ->orWhere('label', $code);
            })
            ->where('is_active', true)
            ->first();

        if (!$cell) {
            return response()->json(['found' => false, 'message' => "Cell \"$code\" tidak ditemukan."]);
        }

        // Stok aktual dari stock_records (FIFO)
        $stocks = Stock::with(['item.unit', 'item.category'])
            ->where('cell_id', $cell->id)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('inbound_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $totalQty  = $stocks->sum('quantity');
        $totalSkus = $stocks->unique('item_id')->count();
        $utilPct   = $cell->capacity_max > 0
            ? min(100, round($totalQty / $cell->capacity_max * 100))
            : 0;

        return response()->json([
            'found' => true,
            'cell'  => [
                'id'            => $cell->id,
                'code'          => $cell->code,
                'label'         => $cell->label ?? $cell->code,
                'rack'          => $cell->rack?->code ?? '—',
                'zone'          => $cell->rack?->zone?->name ?? '—',
                'warehouse'     => $cell->rack?->zone?->warehouse?->name ?? '—',
                'level'         => chr(64 + $cell->level),
                'status'        => $cell->status,
                'capacity_max'  => $cell->capacity_max,
                'capacity_used' => $totalQty,
                'utilization'   => $utilPct,
                'total_qty'     => (int) $totalQty,
                'total_skus'    => $totalSkus,
                'qr_label_url'  => route('location.cells.qr-label', $cell->id),
                'stock_url'     => route('location.cells.stock', $cell->id),
            ],
            'stocks' => $stocks->values()->map(fn ($s) => [
                'item_id'        => $s->item_id,
                'sku'            => $s->item?->sku ?? '—',
                'name'           => $s->item?->name ?? '—',
                'category'       => $s->item?->category?->name ?? '—',
                'category_color' => $s->item?->category?->color_code ?? '#6c757d',
                'unit'           => $s->item?->unit?->code ?? '',
                'quantity'       => (int) $s->quantity,
                'inbound_date'   => $s->inbound_date?->format('d M Y'),
                'days_in_cell'   => $s->inbound_date
                    ? (int) $s->inbound_date->diffInDays(now()) : null,
                'expiry_date'    => $s->expiry_date?->format('d M Y'),
                'lpn'            => $s->lpn,
            ]),
        ]);
    }

    // ─── Print QR Label untuk Cell ───────────────────────────────────────────────
    public function qrLabel(Cell $cell)
    {
        $cell->load(['rack.zone.warehouse', 'dominantCategory']);

        // Generate & simpan qr_code jika belum ada
        if (!$cell->qr_code) {
            $cell->qr_code = $cell->code;
            $cell->saveQuietly();
        }

        // Hitung stok aktual
        $totalQty = Stock::where('cell_id', $cell->id)
            ->where('status', 'available')
            ->sum('quantity');

        return view('location.cells.qr-label', compact('cell', 'totalQty'));
    }

    public function stockDetail($id)
    {
        $cell = Cell::with([
            'rack.zone.warehouse',
            'dominantCategory',
            'stocks.item.category',
            'stocks.item.unit',
        ])->findOrFail($id);
        return view('location.cells.stock', compact('cell'));
    }

    public function datatable(Request $request)
    {
        $query = Cell::with(['rack.zone.warehouse', 'dominantCategory'])->withCount('stocks');

        if ($request->filled('zone_id')) {
            $query->whereHas('rack.zone', fn($q) => $q->where('id', $request->zone_id));
        }
        if ($request->filled('rack_id')) {
            $query->where('rack_id', $request->rack_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('level_label', fn($row) => chr(64 + $row->level))
            ->addColumn('lokasi', function ($row) {
                $wh   = $row->rack->zone->warehouse->name ?? '-';
                $zone = $row->rack->zone->code ?? '-';
                $rack = $row->rack->code ?? '-';
                return '<small class="text-muted">' . e($wh) . ' / <span class="text-primary">' . e($zone) . '</span> / <strong>' . e($rack) . '</strong></small>';
            })
            ->addColumn('kapasitas', function ($row) {
                $pct = $row->capacity_max > 0 ? round(($row->capacity_used / $row->capacity_max) * 100) : 0;
                $color = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                $html  = '<div class="progress" style="height:14px;"><div class="progress-bar ' . $color . '" style="width:' . $pct . '%"><small>' . $pct . '%</small></div></div>';
                $html .= '<small class="text-muted">' . $row->capacity_used . '/' . $row->capacity_max . '</small>';
                return $html;
            })
            ->addColumn('status_badge', function ($row) {
                $map = [
                    'available' => 'badge-success',
                    'partial'   => 'badge-warning',
                    'full'      => 'badge-danger',
                    'blocked'   => 'badge-dark',
                    'reserved'  => 'badge-info',
                ];
                $cls = $map[$row->status] ?? 'badge-secondary';
                return '<span class="badge ' . $cls . '">' . ucfirst($row->status) . '</span>';
            })
            ->addColumn('action', function ($row) {
                $stockUrl = route('location.cells.stock', $row->id);
                $qrUrl    = route('location.cells.qr-label', $row->id);
                $editUrl  = route('location.cells.edit', $row->id);
                $html  = '<div style="display:flex;gap:3px;flex-wrap:nowrap;">';
                $html .= '<a href="' . $stockUrl . '" class="btn btn-xs btn-info" title="Lihat Stok"><i class="fas fa-box"></i></a>';
                $html .= '<a href="' . $qrUrl . '" class="btn btn-xs btn-success" title="Print QR Label"><i class="fas fa-qrcode"></i></a>';
                $html .= '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a>';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->code) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['level_label', 'lokasi', 'kapasitas', 'status_badge', 'action'])
            ->make(true);
    }
}
