@extends('layouts.adminlte')

@section('title', 'Manajemen Pengguna')

@section('content')
<div class="container-fluid">

    <div class="row mb-2">
        <div class="col-md-12 d-flex justify-content-between align-items-center">
            <h3 class="mt-2"><i class="fas fa-users mr-2 text-danger"></i>Manajemen Pengguna</h3>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Pengguna
            </a>
        </div>
    </div>

    <div class="card card-outline card-danger">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <span class="font-weight-bold"><i class="fas fa-list mr-1"></i> Daftar Pengguna</span>
                <div class="d-flex gap-2">
                    <select id="filterRole" class="form-control form-control-sm mr-2" style="width:160px">
                        <option value="">Semua Role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <select id="filterStatus" class="form-control form-control-sm" style="width:130px">
                        <option value="">Semua Status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <table id="tblUsers" class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>ID Karyawan</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th style="width:150px">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
$(function () {
    const table = $('#tblUsers').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("users.datatable") }}',
            data: d => {
                d.role_id = $('#filterRole').val();
                d.status  = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex',  orderable: false, searchable: false },
            { data: 'name' },
            { data: 'email' },
            { data: 'employee_id', defaultContent: '<span class="text-muted">-</span>' },
            { data: 'role_badge',  orderable: false },
            { data: 'status_badge', orderable: false },
            { data: 'action',      orderable: false, searchable: false },
        ],
        language: { url: '/vendor/datatables/i18n/id.json' },
        order: [[1, 'asc']],
    });

    $('#filterRole, #filterStatus').on('change', () => table.ajax.reload());

    $(document).on('click', '.btnDel', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Pengguna?',
            text: name,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
        }).then(res => {
            if (!res.isConfirmed) return;
            $.ajax({
                url: '/users/' + id,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: r => {
                    if (r.status === 'success') {
                        Swal.fire({ title: 'Terhapus!', icon: 'success', timer: 1500, showConfirmButton: false });
                        table.ajax.reload();
                    } else {
                        Swal.fire('Gagal', r.message, 'error');
                    }
                },
                error: xhr => Swal.fire('Gagal', xhr.responseJSON?.message ?? 'Terjadi kesalahan.', 'error'),
            });
        });
    });
});
</script>
@endpush
