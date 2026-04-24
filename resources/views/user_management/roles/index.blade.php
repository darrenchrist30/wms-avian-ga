@extends('layouts.adminlte')

@section('title', 'Manajemen Role')

@section('content')
<div class="container-fluid">

    <div class="row mb-2">
        <div class="col-md-12 d-flex justify-content-between align-items-center">
            <h3 class="mt-2"><i class="fas fa-shield-alt mr-2 text-warning"></i>Manajemen Role</h3>
            <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Role
            </a>
        </div>
    </div>

    <div class="card card-outline card-warning">
        <div class="card-header">
            <span class="font-weight-bold"><i class="fas fa-list mr-1"></i> Daftar Role & Permission</span>
        </div>
        <div class="card-body p-0">
            <table id="tblRoles" class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Nama Role</th>
                        <th>Slug</th>
                        <th>Deskripsi</th>
                        <th class="text-center">Pengguna</th>
                        <th class="text-center">Permissions</th>
                        <th style="width:90px">Aksi</th>
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
    const table = $('#tblRoles').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("roles.datatable") }}',
        columns: [
            { data: 'DT_RowIndex',    orderable: false, searchable: false },
            { data: 'name' },
            { data: 'slug_badge',     orderable: false },
            { data: 'description',    defaultContent: '<span class="text-muted">-</span>' },
            { data: 'users_count',    className: 'text-center' },
            { data: 'permissions_count', className: 'text-center' },
            { data: 'action',         orderable: false, searchable: false },
        ],
        language: { url: '/vendor/datatables/i18n/id.json' },
        order: [[1, 'asc']],
    });

    $(document).on('click', '.btnDel', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Role?',
            text: name,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
        }).then(res => {
            if (!res.isConfirmed) return;
            $.ajax({
                url: '/roles/' + id,
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
