@extends('layouts.adminlte')
@section('title', 'Penerimaan Barang')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Penerimaan Barang (Inbound)</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-truck-loading mr-1"></i> Daftar Inbound Order
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                    data-target=".filter">
                                    <i class="fas fa-filter mr-2"></i>Filter
                                </button>
                                <button class="btn btn-sm btn-outline-dark btnRefresh">
                                    <i class="fas fa-redo mr-2"></i>Refresh
                                </button>
                                <a href="{{ route('inbound.orders.create') }}" class="btn btn-sm btn-outline-dark">
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
                                            <select class="form-control form-control-sm" id="filter-status">
                                                <option value="">Semua Status</option>
                                                <option value="draft">Draft</option>
                                                <option value="processing">Processing</option>
                                                <option value="recommended">Recommended</option>
                                                <option value="put_away">Put Away</option>
                                                <option value="completed">Completed</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Supplier</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-supplier">
                                                <option value="">Semua Supplier</option>
                                                @foreach ($suppliers as $sup)
                                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Warehouse</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-warehouse">
                                                <option value="">Semua Warehouse</option>
                                                @foreach ($warehouses as $wh)
                                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                                @endforeach
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
                                    <th width="90" class="text-center">Actions</th>
                                    <th width="50" class="text-center">#</th>
                                    <th width="150">No. Surat Jalan</th>
                                    <th width="100" class="text-center">Tgl SJ</th>
                                    <th>Supplier</th>
                                    <th width="160">Warehouse</th>
                                    <th width="100">Ref. ERP</th>
                                    <th width="60" class="text-center">Items</th>
                                    <th width="110" class="text-center">Status</th>
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
            var baseURL     = "{{ route('inbound.orders.datatable') }}";
            var routeDestroy = "{{ route('inbound.orders.destroy', ':id') }}";

            var table = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                order: [[3, 'desc']],
                ajax: {
                    url: baseURL,
                    data: function(d) {
                        d.status       = $('#filter-status').val();
                        d.supplier_id  = $('#filter-supplier').val();
                        d.warehouse_id = $('#filter-warehouse').val();
                    },
                    type: 'GET'
                },
                columns: [
                    { data: 'action',       name: 'action',      orderable: false, searchable: false, className: 'text-center' },
                    { data: 'DT_RowIndex',  name: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'do_number',    name: 'do_number' },
                    { data: 'do_date',      name: 'do_date',     className: 'text-center' },
                    { data: 'supplier.name', name: 'supplier.name', defaultContent: '<span class="text-muted">-</span>' },
                    { data: 'warehouse.name', name: 'warehouse.name', defaultContent: '-' },
                    { data: 'erp_reference', name: 'erp_reference', defaultContent: '<span class="text-muted">-</span>' },
                    { data: 'items_count',  name: 'items_count', orderable: false, className: 'text-center' },
                    { data: 'status_badge', name: 'status',      orderable: false, searchable: false, className: 'text-center' },
                ]
            });

            $('.btnRefresh').on('click', function() {
                $('#filter-status, #filter-supplier, #filter-warehouse').val('');
                table.ajax.reload();
            });

            $('#filter-status, #filter-supplier, #filter-warehouse').on('change', function() {
                table.ajax.reload();
            });

            $(document).on('click', '.btnDel', function(e) {
                e.preventDefault();
                let id       = $(this).data('id');
                let name     = $(this).data('name');
                let ajaxurl  = routeDestroy.replace(':id', id);
                Swal.fire({
                    title: 'Hapus Order?',
                    html: 'Order <strong>' + name + '</strong> akan dihapus permanen.',
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
                            data: { _token: $('meta[name="csrf-token"]').attr('content') },
                            success: function(response) {
                                Swal.fire(
                                    response.status === 'success' ? 'Berhasil!' : 'Gagal!',
                                    response.message,
                                    response.status === 'success' ? 'success' : 'error'
                                );
                                table.ajax.reload(null, false);
                            },
                            error: function(xhr) {
                                Swal.fire('Gagal!', xhr?.responseJSON?.message || 'Terjadi kesalahan.', 'error');
                                table.ajax.reload(null, false);
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
