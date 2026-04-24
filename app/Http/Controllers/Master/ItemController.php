<?php

namespace App\Http\Controllers\Master;

use App\Exports\ItemsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ItemsImport;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;

class ItemController extends Controller
{
    public function index()
    {
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('master.items.index', compact('categories'));
    }

    public function create()
    {
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        $units = Unit::where('is_active', true)->orderBy('name')->get();
        return view('master.items.form', [
            'typeForm'   => 'create',
            'data'       => null,
            'categories' => $categories,
            'units'      => $units,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sku'                      => 'required|string|max:50|unique:items,sku',
            'name'                     => 'required|string|max:200',
            'category_id'              => 'required|exists:item_categories,id',
            'unit_id'                  => 'required|exists:units,id',
            'erp_item_code'            => 'nullable|string|max:50',
            'barcode'                  => 'nullable|string|max:100|unique:items,barcode',
            'description'              => 'nullable|string',
            'min_stock'                => 'required|integer|min:0',
            'max_stock'                => 'required|integer|min:0|gte:min_stock',
            'reorder_point'            => 'required|integer|min:0',
            'movement_type'            => 'required|in:fast_moving,slow_moving,dead',
            'weight_kg'                => 'nullable|numeric|min:0',
            'volume_m3'                => 'nullable|numeric|min:0',
            'deadstock_threshold_days' => 'required|integer|min:1',
            'is_active'                => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            Item::create([
                'sku'                      => strtoupper($request->sku),
                'name'                     => $request->name,
                'category_id'              => $request->category_id,
                'unit_id'                  => $request->unit_id,
                'erp_item_code'            => $request->erp_item_code,
                'barcode'                  => $request->barcode,
                'description'              => $request->description,
                'min_stock'                => $request->min_stock,
                'max_stock'                => $request->max_stock,
                'reorder_point'            => $request->reorder_point,
                'movement_type'            => $request->movement_type,
                'weight_kg'                => $request->weight_kg,
                'volume_m3'                => $request->volume_m3,
                'deadstock_threshold_days' => $request->deadstock_threshold_days,
                'is_active'                => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.items.index')->with('success', 'Sparepart berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan sparepart. Silakan coba lagi.');
        }
    }

    public function show($id) { return redirect()->route('master.items.index'); }

    public function edit($id)
    {
        $data = Item::findOrFail($id);
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        $units = Unit::where('is_active', true)->orderBy('name')->get();
        return view('master.items.form', [
            'typeForm'   => 'edit',
            'data'       => $data,
            'categories' => $categories,
            'units'      => $units,
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);
        $request->validate([
            'sku'                      => 'required|string|max:50|unique:items,sku,' . $id,
            'name'                     => 'required|string|max:200',
            'category_id'              => 'required|exists:item_categories,id',
            'unit_id'                  => 'required|exists:units,id',
            'erp_item_code'            => 'nullable|string|max:50',
            'barcode'                  => 'nullable|string|max:100|unique:items,barcode,' . $id,
            'description'              => 'nullable|string',
            'min_stock'                => 'required|integer|min:0',
            'max_stock'                => 'required|integer|min:0|gte:min_stock',
            'reorder_point'            => 'required|integer|min:0',
            'movement_type'            => 'required|in:fast_moving,slow_moving,dead',
            'weight_kg'                => 'nullable|numeric|min:0',
            'volume_m3'                => 'nullable|numeric|min:0',
            'deadstock_threshold_days' => 'required|integer|min:1',
            'is_active'                => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            $item->update([
                'sku'                      => strtoupper($request->sku),
                'name'                     => $request->name,
                'category_id'              => $request->category_id,
                'unit_id'                  => $request->unit_id,
                'erp_item_code'            => $request->erp_item_code,
                'barcode'                  => $request->barcode,
                'description'              => $request->description,
                'min_stock'                => $request->min_stock,
                'max_stock'                => $request->max_stock,
                'reorder_point'            => $request->reorder_point,
                'movement_type'            => $request->movement_type,
                'weight_kg'                => $request->weight_kg,
                'volume_m3'                => $request->volume_m3,
                'deadstock_threshold_days' => $request->deadstock_threshold_days,
                'is_active'                => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.items.index')->with('success', 'Sparepart berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui sparepart. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $item = Item::withCount('stocks')->findOrFail($id);
        if ($item->stocks_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Sparepart tidak bisa dihapus karena masih memiliki ' . $item->stocks_count . ' record stok aktif.'], 422);
        }
        DB::beginTransaction();
        try {
            $item->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Sparepart berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new ItemsTemplateExport, 'template_sparepart.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.mimes'    => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max'      => 'Ukuran file maksimal 5 MB.',
        ]);

        try {
            $import = new ItemsImport;
            Excel::import($import, $request->file('file'));

            $msg = "Import selesai: {$import->imported} sparepart berhasil ditambahkan.";
            if ($import->skipped > 0) {
                $msg .= " {$import->skipped} baris dilewati (SKU sudah ada).";
            }

            if (!empty($import->errors)) {
                session()->flash('import_errors', $import->errors);
            }

            return redirect()->route('master.items.index')->with('success', $msg);
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('master.items.index')->with('error', 'Gagal memproses file import. Pastikan format file sesuai template.');
        }
    }

    public function barcode($id)
    {
        $item = Item::with(['category', 'unit'])->findOrFail($id);
        return view('master.items.barcode', compact('item'));
    }

    // ─── Halaman Scan Barcode ────────────────────────────────────────────────
    public function scanPage()
    {
        return view('master.items.scan');
    }

    // ─── AJAX Lookup item berdasarkan barcode / SKU ──────────────────────────
    public function lookup(Request $request)
    {
        $code = trim($request->input('barcode', ''));
        if (!$code) {
            return response()->json(['found' => false, 'message' => 'Kode tidak boleh kosong.']);
        }

        // Cari by barcode dulu, fallback ke SKU, lalu ERP code
        $item = Item::with(['category', 'unit'])
            ->where(function ($q) use ($code) {
                $q->where('barcode', $code)
                  ->orWhere('sku', $code)
                  ->orWhere('erp_item_code', $code);
            })
            ->where('is_active', true)
            ->first();

        if (!$item) {
            return response()->json(['found' => false, 'message' => "Barang dengan kode \"$code\" tidak ditemukan."]);
        }

        // Ambil stok saat ini
        $totalStock = DB::table('stock_records')
            ->where('item_id', $item->id)
            ->where('status', 'available')
            ->sum('quantity');

        // Lokasi cell tempat item berada (FIFO — yang masuk pertama)
        $locations = DB::table('stock_records as sr')
            ->join('cells as c', 'c.id', '=', 'sr.cell_id')
            ->join('racks as r', 'r.id', '=', 'c.rack_id')
            ->join('zones as z', 'z.id', '=', 'r.zone_id')
            ->where('sr.item_id', $item->id)
            ->where('sr.status', 'available')
            ->where('sr.quantity', '>', 0)
            ->select('c.code as cell_code', 'r.code as rack_code', 'z.name as zone_name', 'sr.quantity', 'sr.inbound_date', 'sr.expiry_date')
            ->orderBy('sr.inbound_date', 'asc')
            ->limit(5)
            ->get();

        return response()->json([
            'found' => true,
            'item'  => [
                'id'            => $item->id,
                'sku'           => $item->sku,
                'barcode'       => $item->barcode ?? $item->sku,
                'erp_code'      => $item->erp_item_code,
                'name'          => $item->name,
                'category'      => $item->category?->name ?? '—',
                'category_color'=> $item->category?->color_code ?? '#6c757d',
                'unit'          => $item->unit?->code ?? '—',
                'movement_type' => $item->movement_type,
                'min_stock'     => $item->min_stock,
                'reorder_point' => $item->reorder_point,
                'total_stock'   => (int) $totalStock,
                'stock_status'  => $totalStock == 0 ? 'empty'
                    : ($totalStock <= $item->min_stock ? 'critical'
                    : ($totalStock <= $item->reorder_point ? 'reorder' : 'ok')),
                'barcode_url'   => route('master.items.barcode', $item->id),
                'detail_url'    => route('stock.show', $item->id),
            ],
            'locations' => $locations,
        ]);
    }

    public function datatable(Request $request)
    {
        $query = Item::with(['category', 'unit'])->withCount('stocks');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('nama_info', function ($row) {
                $html = '<div class="font-weight-bold">' . e($row->name) . '</div>';
                $html .= '<small class="text-muted">' . e($row->sku) . '</small>';
                if ($row->barcode) $html .= '<br><small class="text-info"><i class="fas fa-barcode"></i> ' . e($row->barcode) . '</small>';
                return $html;
            })
            ->addColumn('category_badge', function ($row) {
                if ($row->category) {
                    return '<span class="badge" style="background:' . ($row->category->color_code ?? '#6c757d') . ';color:#fff;">' . e($row->category->name) . '</span>';
                }
                return '<span class="text-muted">-</span>';
            })
            ->addColumn('movement_badge', function ($row) {
                $map = [
                    'fast_moving'  => ['Fast Moving', 'badge-warning'],
                    'slow_moving'  => ['Slow Moving', 'badge-info'],
                    'dead'         => ['Dead Stock', 'badge-danger'],
                ];
                [$label, $cls] = $map[$row->movement_type] ?? [$row->movement_type, 'badge-secondary'];
                return '<span class="badge ' . $cls . '">' . $label . '</span>';
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                $barcodeUrl = route('master.items.barcode', $row->id);
                $editUrl    = route('master.items.edit', $row->id);
                $html  = '<a href="' . $barcodeUrl . '" class="btn btn-xs btn-info" title="Barcode"><i class="fas fa-barcode"></i></a> ';
                $html .= '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->name) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['nama_info', 'category_badge', 'movement_badge', 'status_badge', 'action'])
            ->make(true);
    }
}
