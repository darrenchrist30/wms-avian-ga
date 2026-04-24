@extends('layouts.adminlte')
@section('title', 'Supplier / Vendor')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Supplier / Vendor</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-industry mr-1"></i> Daftar Supplier
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                    data-target=".filter">
                                    <i class="fas fa-filter mr-2"></i>Filter
                                </button>
                                <button class="btn btn-sm btn-outline-dark btnRefresh">
                                    <i class="fas fa-redo mr-2"></i>Refresh
                                </button>
                                <a href="{{ route('master.suppliers.create') }}" class="btn btn-sm btn-outline-dark">
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
                                    <th width="60" class="text-center">Actions</th>
                                    <th width="50" class="text-center">#</th>
                                    <th width="90">Kode</th>
                                    <th>Nama Supplier</th>
                                    <th width="130">Kontak</th>
                                    <th width="120">Telepon</th>
                                    <th width="120">ERP Vendor ID</th>
                                    <th width="80" class="text-center">Pesanan</th>
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
            var baseURL = "{{ route('master.suppliers.datatable') }}";
            var routeDestroy = "{{ route('master.suppliers.destroy', ':id') }}";

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
                        data: 'nama_info',
                        name: 'name'
                    },
                    {
                        data: 'contact_person',
                        name: 'contact_person',
                        render: function(data) {
                            return data ? data : '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        data: 'phone',
                        name: 'phone',
                        render: function(data) {
                            return data ? data : '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        data: 'erp_vendor_id',
                        name: 'erp_vendor_id',
                        render: function(data) {
                            return data ? '<code>' + data + '</code>' : '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        data: 'inbound_orders_count',
                        name: 'inbound_orders_count',
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
                    title: 'Hapus Supplier?',
                    html: 'Supplier <strong>' + name + '</strong> akan dihapus permanen.',
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
