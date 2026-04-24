<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class SupplierController extends Controller
{
    public function index()
    {
        return view('master.suppliers.index');
    }

    public function create()
    {
        return view('master.suppliers.form', ['typeForm' => 'create', 'data' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'           => 'required|string|max:20|unique:suppliers,code',
            'name'           => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:100',
            'address'        => 'nullable|string|max:500',
            'erp_vendor_id'  => 'nullable|string|max:50',
            'is_active'      => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            Supplier::create([
                'code'           => strtoupper($request->code),
                'name'           => $request->name,
                'contact_person' => $request->contact_person,
                'phone'          => $request->phone,
                'email'          => $request->email,
                'address'        => $request->address,
                'erp_vendor_id'  => $request->erp_vendor_id,
                'is_active'      => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan supplier. Silakan coba lagi.');
        }
    }

    public function show($id) { return redirect()->route('master.suppliers.index'); }

    public function edit($id)
    {
        $data = Supplier::withCount('inboundOrders')->findOrFail($id);
        return view('master.suppliers.form', ['typeForm' => 'edit', 'data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $request->validate([
            'code'           => 'required|string|max:20|unique:suppliers,code,' . $id,
            'name'           => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:100',
            'address'        => 'nullable|string|max:500',
            'erp_vendor_id'  => 'nullable|string|max:50',
            'is_active'      => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            $supplier->update([
                'code'           => strtoupper($request->code),
                'name'           => $request->name,
                'contact_person' => $request->contact_person,
                'phone'          => $request->phone,
                'email'          => $request->email,
                'address'        => $request->address,
                'erp_vendor_id'  => $request->erp_vendor_id,
                'is_active'      => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.suppliers.index')->with('success', 'Supplier berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui supplier. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $supplier = Supplier::withCount('inboundOrders')->findOrFail($id);
        if ($supplier->inbound_orders_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Supplier tidak bisa dihapus karena memiliki ' . $supplier->inbound_orders_count . ' riwayat pesanan masuk.'], 422);
        }
        DB::beginTransaction();
        try {
            $supplier->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Supplier berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function datatable(Request $request)
    {
        $query = Supplier::withCount('inboundOrders');
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('nama_info', function ($row) {
                $html = '<div class="font-weight-bold">' . e($row->name) . '</div>';
                if ($row->email) $html .= '<small class="text-muted">' . e($row->email) . '</small>';
                return $html;
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                $editUrl = route('master.suppliers.edit', $row->id);
                $html  = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . $row->name . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['nama_info', 'status_badge', 'action'])
            ->make(true);
    }
}
