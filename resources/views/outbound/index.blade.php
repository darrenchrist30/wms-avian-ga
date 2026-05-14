@extends('layouts.adminlte')
@section('title', 'Outbound — Riwayat Pengambilan Barang')

@section('content')
<div class="container-fluid">

    <div class="row mb-2">
        <div class="col-md-12">
            <h4 class="mt-2">
                <i class="fas fa-sign-out-alt mr-2 text-danger"></i>
                Outbound — Pengambilan Barang
            </h4>
            <p class="text-muted mb-0">Riwayat pengambilan barang dari gudang menggunakan metode FIFO.</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="font-weight-bold">
                    <i class="fas fa-history mr-1"></i> Riwayat Outbound
                </div>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-secondary btnFilter" data-toggle="collapse"
                        data-target=".filter-section">
                        <i class="fas fa-filter mr-1"></i>Filter
                    </button>
                    <button class="btn btn-sm btn-outline-secondary btnRefresh">
                        <i class="fas fa-redo mr-1"></i>Refresh
                    </button>
                    <a href="{{ route('outbound.create') }}" class="btn btn-sm btn-danger">
                        <i class="fas fa-plus mr-1"></i>Outbound Baru
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row filter-section collapse mb-3">
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

            <table id="datatable" class="table table-bordered table-sm table-striped table-hover w-100">
                <thead>
                    <tr>
                        <th width="50" class="text-center">#</th>
                        <th width="140">Tanggal</th>
                        <th>Item</th>
                        <th width="180">Gudang</th>
                        <th width="100" class="text-center">Rak - Cell</th>
                        <th width="70" class="text-center">Qty</th>
                        <th>Catatan</th>
                        <th width="120">Petugas</th>
                    </tr>
                </thead>
            </table>
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
            url: '{{ route("outbound.datatable") }}',
            data: function (d) {
                d.warehouse_id = $('#filter-warehouse').val();
                d.date_from    = $('#filter-from').val();
                d.date_to      = $('#filter-to').val();
            },
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex',  name: 'DT_RowIndex',   orderable: false, searchable: false, className: 'text-center' },
            { data: 'moved_at',     name: 'moved_at' },
            { data: 'item',         name: 'item_name',      orderable: false },
            { data: 'warehouse',    name: 'warehouse_name', orderable: false },
            { data: 'from_cell',    name: 'from_cell',      orderable: false, className: 'text-center' },
            { data: 'quantity',     name: 'quantity',       className: 'text-center font-weight-bold text-danger' },
            { data: 'notes',        name: 'notes',          orderable: false },
            { data: 'performed_by', name: 'performed_by',   orderable: false },
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
