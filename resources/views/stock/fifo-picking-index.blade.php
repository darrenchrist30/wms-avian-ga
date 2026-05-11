@extends('layouts.adminlte')
@section('title', 'FIFO Picking')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h3 class="mt-2">FIFO Picking</h3>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="font-weight-bold">
                            <i class="fas fa-sort-amount-up-alt mr-1"></i> Riwayat FIFO Picking
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                data-target=".filter">
                                <i class="fas fa-filter mr-2"></i>Filter
                            </button>
                            <button class="btn btn-sm btn-outline-dark btnRefresh">
                                <i class="fas fa-redo mr-2"></i>Refresh
                            </button>
                            <a href="{{ route('stock.fifo-picking.create') }}" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-plus mr-2"></i>Picking Baru
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form id="filter-container">
                        <div class="row m-2 filter collapse mb-3">
                            <div class="col-sm-12 col-md-4">
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label small font-weight-bold">Gudang</label>
                                    <div class="col-sm-8">
                                        <select class="form-control form-control-sm" id="filter-warehouse">
                                            <option value="">Semua Gudang</option>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-4">
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label small font-weight-bold">Dari</label>
                                    <div class="col-sm-8">
                                        <input type="date" class="form-control form-control-sm" id="filter-from">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-4">
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label small font-weight-bold">Sampai</label>
                                    <div class="col-sm-8">
                                        <input type="date" class="form-control form-control-sm" id="filter-to">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <table id="datatable" class="table table-bordered table-sm table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th width="50" class="text-center">#</th>
                                <th width="140">Tanggal</th>
                                <th>Item</th>
                                <th width="180">Gudang</th>
                                <th width="80" class="text-center">Cell</th>
                                <th width="120">Zona / Rak</th>
                                <th width="70" class="text-center">Qty</th>
                                <th>Catatan</th>
                                <th width="120">Petugas</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    var table = $('#datatable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[1, 'desc']],
        ajax: {
            url: '{{ route("stock.fifo-picking.datatable") }}',
            data: function (d) {
                d.warehouse_id = $('#filter-warehouse').val();
                d.date_from    = $('#filter-from').val();
                d.date_to      = $('#filter-to').val();
            },
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'moved_at',    name: 'moved_at' },
            { data: 'item',         name: 'item_name',     orderable: false },
            { data: 'warehouse',    name: 'warehouse_name',orderable: false },
            { data: 'from_cell',    name: 'from_cell',     orderable: false, className: 'text-center' },
            { data: 'zone_rack',    name: 'zone_rack',     orderable: false },
            { data: 'quantity',     name: 'quantity',      className: 'text-center font-weight-bold text-success' },
            { data: 'notes',        name: 'notes',         orderable: false },
            { data: 'performed_by', name: 'performed_by',  orderable: false },
        ]
    });

    $('.btnRefresh').on('click', function () {
        $('#filter-warehouse').val('');
        $('#filter-from, #filter-to').val('');
        table.ajax.reload();
    });

    $('#filter-warehouse, #filter-from, #filter-to').on('change', function () {
        table.ajax.reload();
    });
});
</script>
@endpush
