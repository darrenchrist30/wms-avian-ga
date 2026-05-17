@extends('layouts.adminlte')
@section('title', 'Kategori Sparepart')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Kategori Sparepart</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-tags mr-1"></i> Daftar Kategori
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                    data-target=".filter">
                                    <i class="fas fa-filter mr-2"></i>Filter
                                </button>
                                <button class="btn btn-sm btn-outline-dark btnRefresh">
                                    <i class="fas fa-redo mr-2"></i>Refresh
                                </button>
                                <a href="{{ route('master.categories.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus mr-2"></i>Add
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="filter-container">
                            <div class="row m-2 filter collapse mb-3">
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Status</label>
                                        <div class="col-sm-8">
                                            <select name="status" class="form-control form-control-sm" id="filter-status">
                                                <option value="">Semua</option>
                                                <option value="1">Aktif</option>
                                                <option value="0">Nonaktif</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <table id="datatable"
                            class="table table-bordered table-sm table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th width="50" class="text-center">Actions</th>
                                    <th width="60" class="text-center">#</th>
                                    <th width="100">Kode</th>
                                    <th>Nama Kategori</th>
                                    <th width="100" class="text-center">Warna</th>
                                    <th>Deskripsi</th>
                                    <th width="80" class="text-center">Sparepart</th>
                                    <th width="80" class="text-center">Status</th>
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
        $(document).ready(function() {
            var baseURL = "{{ route('master.categories.datatable') }}";
            var routeDestroy = "{{ route('master.categories.destroy', ':id') }}";

            var table = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                order: [
                    [2, 'asc']
                ],
                ajax: {
                    url: baseURL,
                    data: function(d) {
                        d.status = $('#filter-status').val();
                    },
                    type: 'GET'
                },
                columns: [{
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'code',
                        name: 'code'
                    },
                    {
                        data: 'color_badge',
                        name: 'name',
                        orderable: false
                    },
                    {
                        data: 'color_code',
                        name: 'color_code',
                        className: 'text-center',
                        orderable: false,
                        render: function(data) {
                            return '<span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:' +
                                (data || '#6c757d') + ';border:1px solid #ccc;"></span>';
                        }
                    },
                    {
                        data: 'description',
                        name: 'description',
                        render: function(data) {
                            return data ? data : '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        data: 'items_count',
                        name: 'items_count',
                        className: 'text-center',
                        orderable: false
                    },
                    {
                        data: 'status_badge',
                        name: 'is_active',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                ]
            });

            $('.btnRefresh').on('click', function() {
                $('#filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-status').on('change', function() {
                table.ajax.reload();
            });

            $(document).on('click', '.btnDel', function(e) {
                e.preventDefault();
                let id = $(this).data('id');
                let name = $(this).data('name');
                let ajaxurl = routeDestroy.replace(':id', id);
                Swal.fire({
                    title: 'Hapus Kategori?',
                    html: 'Kategori <strong>' + name + '</strong> akan dihapus permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'DELETE',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire('Berhasil!', response.message, 'success');
                                } else {
                                    Swal.fire('Gagal!', response.message, 'error');
                                }
                                table.ajax.reload(null, false);
                            },
                            error: function(xhr) {
                                let msg = xhr?.responseJSON?.message || 'Terjadi kesalahan.';
                                Swal.fire('Gagal!', msg, 'error');
                                table.ajax.reload(null, false);
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
