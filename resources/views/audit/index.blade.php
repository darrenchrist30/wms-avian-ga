@extends('layouts.adminlte')

@section('title', 'Audit Log')

@section('content')
<div class="container-fluid">

    <div class="row mb-2">
        <div class="col-md-12">
            <h3 class="mt-2"><i class="fas fa-history mr-2 text-secondary"></i>Audit Log</h3>
            <p class="text-muted small">Rekam jejak semua perubahan data oleh pengguna.</p>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card card-outline card-secondary mb-3">
        <div class="card-header"><span class="font-weight-bold"><i class="fas fa-filter mr-1"></i> Filter</span></div>
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-2">
                    <select id="filterUser" class="form-control form-control-sm">
                        <option value="">Semua Pengguna</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterAction" class="form-control form-control-sm">
                        <option value="">Semua Aksi</option>
                        <option value="created">Dibuat</option>
                        <option value="updated">Diubah</option>
                        <option value="deleted">Dihapus</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterModel" class="form-control form-control-sm">
                        <option value="">Semua Modul</option>
                        @foreach ($modelTypes as $mt)
                            <option value="{{ $mt }}">{{ $mt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" id="filterDateFrom" class="form-control form-control-sm" placeholder="Dari tanggal">
                </div>
                <div class="col-md-2">
                    <input type="date" id="filterDateTo" class="form-control form-control-sm" placeholder="Sampai tanggal">
                </div>
                <div class="col-md-2">
                    <button id="btnReset" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fas fa-undo mr-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header">
            <span class="font-weight-bold"><i class="fas fa-list mr-1"></i> Riwayat Aktivitas</span>
        </div>
        <div class="card-body p-0">
            <table id="tblAudit" class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Modul</th>
                        <th>Data</th>
                        <th>IP</th>
                        <th style="width:50px">Detail</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>

{{-- Detail Modal --}}
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history mr-2"></i>Detail Perubahan</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const table = $('#tblAudit').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("audit.datatable") }}',
            data: d => {
                d.user_id    = $('#filterUser').val();
                d.action     = $('#filterAction').val();
                d.model_type = $('#filterModel').val();
                d.date_from  = $('#filterDateFrom').val();
                d.date_to    = $('#filterDateTo').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex',    orderable: false, searchable: false },
            { data: 'created_at' },
            { data: 'user_name',      defaultContent: '<span class="text-muted">System</span>' },
            { data: 'action_badge',   orderable: false },
            { data: 'model_type_label' },
            { data: 'model_label',    defaultContent: '<span class="text-muted">-</span>' },
            { data: 'ip_address',     defaultContent: '<span class="text-muted">-</span>' },
            { data: 'detail_btn',     orderable: false, searchable: false },
        ],
        language: { url: '/vendor/datatables/i18n/id.json' },
        order: [[1, 'desc']],
        pageLength: 25,
    });

    $('#filterUser, #filterAction, #filterModel').on('change', () => table.ajax.reload());
    $('#filterDateFrom, #filterDateTo').on('change', () => table.ajax.reload());
    $('#btnReset').on('click', function () {
        $('#filterUser, #filterAction, #filterModel').val('');
        $('#filterDateFrom, #filterDateTo').val('');
        table.ajax.reload();
    });

    $(document).on('click', '.btnDetail', function () {
        const action = $(this).data('action');
        const model  = $(this).data('model');
        const label  = $(this).data('label');
        const oldVal = $(this).data('old');
        const newVal = $(this).data('new');

        let html = `<p><strong>Modul:</strong> ${model} &nbsp;|&nbsp; <strong>Data:</strong> ${label || '-'}</p>`;

        const renderTable = (obj, title, themeClass) => {
            if (!obj || Object.keys(obj).length === 0) return '';
            let rows = Object.entries(obj).map(([k, v]) =>
                `<tr><td class="text-muted" style="width:35%">${k}</td><td>${v !== null && v !== undefined ? v : '<em class="text-muted">null</em>'}</td></tr>`
            ).join('');
            return `<div class="mb-3"><h6 class="font-weight-bold ${themeClass}">${title}</h6>
                <table class="table table-sm table-bordered mb-0"><tbody>${rows}</tbody></table></div>`;
        };

        if (action === 'created') {
            html += renderTable(newVal, 'Data Dibuat', 'text-success');
        } else if (action === 'updated') {
            html += renderTable(oldVal, 'Sebelum Diubah', 'text-warning');
            html += renderTable(newVal, 'Sesudah Diubah', 'text-success');
        } else if (action === 'deleted') {
            html += renderTable(oldVal, 'Data Dihapus', 'text-danger');
        }

        if (!oldVal && !newVal) {
            html += '<p class="text-muted text-center py-2">Tidak ada detail perubahan.</p>';
        }

        $('#modalBody').html(html);
        $('#modalDetail').modal('show');
    });
});
</script>
@endpush
