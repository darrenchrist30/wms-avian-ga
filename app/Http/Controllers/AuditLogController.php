<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class AuditLogController extends Controller
{
    public function index()
    {
        $users      = User::orderBy('name')->get(['id', 'name']);
        $modelTypes = AuditLog::select('model_type')->distinct()->orderBy('model_type')->pluck('model_type');
        return view('audit.index', compact('users', 'modelTypes'));
    }

    public function datatable(Request $request)
    {
        $query = AuditLog::query();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return DataTables::of($query->latest('created_at'))
            ->addIndexColumn()
            ->addColumn('action_badge', fn($row) => $row->action_badge)
            ->addColumn('model_type_label', fn($row) => e($row->model_type_label))
            ->addColumn('detail_btn', function ($row) {
                return '<button class="btn btn-xs btn-info btnDetail"
                    data-id="' . $row->id . '"
                    data-action="' . e($row->action) . '"
                    data-model="' . e($row->model_type_label) . '"
                    data-label="' . e($row->model_label) . '"
                    data-old=\'' . e(json_encode($row->old_values)) . '\'
                    data-new=\'' . e(json_encode($row->new_values)) . '\'
                    title="Lihat Detail"><i class="fas fa-eye"></i></button>';
            })
            ->rawColumns(['action_badge', 'detail_btn'])
            ->make(true);
    }
}
