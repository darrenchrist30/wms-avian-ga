@extends('layouts.adminlte')
@section('title', 'Rak (Rack)')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Rak (Rack)</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-th-large mr-1"></i> Daftar Rak
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                    data-target=".filter">
                                    <i class="fas fa-filter mr-2"></i>Filter
                                </button>
                                <button class="btn btn-sm btn-outline-dark btnRefresh">
                                    <i class="fas fa-redo mr-2"></i>Refresh
                                </button>
                                <a href="{{ route('location.racks.create') }}" class="btn btn-sm btn-outline-dark">
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
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Zona</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-zone">
                                                <option value="">Semua Zona</option>
                                                @foreach ($zones as $zone)
                                                    <option value="{{ $zone->id }}">{{ $zone->code }} - {{ $zone->name }}
                                                        ({{ $zone->warehouse->name ?? '-' }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Status</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-status">
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
                                    <th width="100">Kode Rak</th>
                                    <th width="120">Nama</th>
                                    <th>Lokasi (Warehouse / Zona)</th>
                                    <th width="140">Kategori Dominan</th>
                                    <th width="70" class="text-center">Level</th>
                                    <th width="60" class="text-center">Sel</th>
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
            var baseURL = "{{ route('location.racks.datatable') }}";
            var routeDestroy = "{{ route('location.racks.destroy', ':id') }}";

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
                        d.zone_id = $('#filter-zone').val();
                        d.status  = $('#filter-status').val();
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
                    { data: 'code', name: 'code' },
                    {
                        data: 'name',
                        name: 'name',
                        render: function(data) { return data || '<span class="text-muted">-</span>'; }
                    },
                    { data: 'lokasi',   name: 'zone.code', orderable: false },
                    { data: 'kategori', name: 'dominantCategory.name', orderable: false, searchable: false },
                    { data: 'total_levels', name: 'total_levels', className: 'text-center' },
                    { data: 'cells_count',  name: 'cells_count',  className: 'text-center', orderable: false },
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
                $('#filter-zone, #filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-zone, #filter-status').on('change', function() {
                table.ajax.reload();
            });

            $(document).on('click', '.btnDel', function(e) {
                e.preventDefault();
                let id = $(this).data('id');
                let name = $(this).data('name');
                let ajaxurl = routeDestroy.replace(':id', id);
                Swal.fire({
                    title: 'Hapus Rak?',
                    html: 'Rak <strong>' + name + '</strong> beserta seluruh selnya akan dihapus permanen.',
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
                                Swal.fire(response.status === 'success' ? 'Berhasil!' : 'Gagal!', response.message, response.status === 'success' ? 'success' : 'error');
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
