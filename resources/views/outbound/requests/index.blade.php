@extends('layouts.adminlte')
@section('title', 'Permintaan Outbound')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
        <h4 class="mb-0">
            <i class="fas fa-clipboard-list mr-2 text-primary"></i>Permintaan Outbound
        </h4>
        @if(auth()->user()->hasRole('operator'))
            <a href="{{ route('outbound.requests.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus mr-1"></i>Permintaan Baru
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                <span class="font-weight-bold" style="font-size:13px;">
                    <i class="fas fa-list mr-1"></i> Daftar Request
                </span>
                <div class="d-flex align-items-center" style="gap:6px;">
                    <select id="filterStatus" class="form-control form-control-sm" style="width:180px;">
                        <option value="">Semua Status</option>
                        <option value="pending">Menunggu Persetujuan</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                        <option value="completed">Selesai</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" id="btnRefresh">
                        <i class="fas fa-redo mr-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <table id="dtRequests" class="table table-bordered table-sm table-striped table-hover w-100 mb-0">
                <thead>
                    <tr>
                        <th width="44" class="text-center">#</th>
                        <th>No. Request</th>
                        <th>Operator</th>
                        <th>Gudang</th>
                        <th class="text-center" width="80">Item</th>
                        <th class="text-center" width="160">Status</th>
                        <th width="150">Waktu</th>
                        <th class="text-center" width="90">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
$(function () {
    var table = $('#dtRequests').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[0, 'desc']],
        language: {
            processing:    '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat data...',
            zeroRecords:   '<div class="text-center text-muted py-3"><i class="fas fa-inbox fa-2x d-block mb-2"></i>Belum ada permintaan outbound.</div>',
            emptyTable:    '<div class="text-center text-muted py-3"><i class="fas fa-inbox fa-2x d-block mb-2"></i>Belum ada permintaan outbound.</div>',
            lengthMenu:    'Tampil _MENU_ data',
            info:          'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty:     'Tidak ada data',
            search:        'Cari:',
            paginate:      { first: '«', last: '»', next: '›', previous: '‹' },
        },
        ajax: {
            url: '{{ route("outbound.requests.datatable") }}',
            type: 'GET',
            data: function (d) {
                d.status = $('#filterStatus').val();
            },
            error: function (xhr) {
                if (xhr.status === 401 || xhr.status === 419) { window.location.reload(); return; }
                Swal.fire('Error', 'Gagal memuat data.', 'error');
            }
        },
        columns: [
            { data: 'DT_RowIndex',    name: 'DT_RowIndex',    orderable: false, searchable: false, className: 'text-center text-muted', width: '40px', render: function(d) { return '<small>' + d + '</small>'; } },
            { data: 'request_number', name: 'request_number' },
            { data: 'operator',       name: 'operator_name',  orderable: false },
            { data: 'warehouse',      name: 'warehouse_name', orderable: false },
            { data: 'items_count',    name: 'items_count',    orderable: false, className: 'text-center' },
            { data: 'status',         name: 'status',         className: 'text-center', orderable: false },
            { data: 'created_at',     name: 'created_at' },
            { data: 'aksi',           name: 'aksi',           orderable: false, searchable: false, className: 'text-center' },
        ]
    });

    $('#filterStatus').on('change', function () { table.ajax.reload(); });
    $('#btnRefresh').on('click', function () { $('#filterStatus').val(''); table.ajax.reload(); });
});
</script>
@endpush
