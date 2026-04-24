@extends('layouts.adminlte')
@section('title', 'Sel Penyimpanan')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Sel Penyimpanan</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-border-all mr-1"></i> Daftar Sel
                            </div>
                            <div class="d-flex" style="gap:6px;">
                                <a href="{{ route('location.cells.scan') }}" class="btn btn-sm btn-avian-secondary">
                                    <i class="fas fa-qrcode mr-1"></i>Scan QR Cell
                                </a>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                        data-target=".filter">
                                        <i class="fas fa-filter mr-2"></i>Filter
                                    </button>
                                    <button class="btn btn-sm btn-outline-dark btnRefresh">
                                        <i class="fas fa-redo mr-2"></i>Refresh
                                    </button>
                                    <a href="{{ route('location.cells.create') }}" class="btn btn-sm btn-outline-dark">
                                        <i class="fas fa-plus mr-2"></i>Add
                                    </a>
                                </div>
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
                                                    <option value="{{ $zone->id }}">{{ $zone->code }} - {{ $zone->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Rak</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-rack">
                                                <option value="">Semua Rak</option>
                                                @foreach ($racks as $rack)
                                                    <option value="{{ $rack->id }}">{{ $rack->code }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Status Sel</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-status">
                                                <option value="">Semua</option>
                                                <option value="available">Available</option>
                                                <option value="partial">Partial</option>
                                                <option value="full">Full</option>
                                                <option value="blocked">Blocked</option>
                                                <option value="reserved">Reserved</option>
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
                                    <th width="80" class="text-center">Actions</th>
                                    <th width="50" class="text-center">#</th>
                                    <th width="140">Kode Sel</th>
                                    <th>Lokasi</th>
                                    <th width="70" class="text-center">Level</th>
                                    <th width="150">Kapasitas</th>
                                    <th width="90" class="text-center">Status</th>
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
            var baseURL = "{{ route('location.cells.datatable') }}";
            var routeDestroy = "{{ route('location.cells.destroy', ':id') }}";

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
                        d.rack_id = $('#filter-rack').val();
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
                    { data: 'code',        name: 'code' },
                    { data: 'lokasi',      name: 'rack.code', orderable: false },
                    { data: 'level_label', name: 'level', className: 'text-center font-weight-bold' },
                    { data: 'kapasitas',   name: 'capacity_used', orderable: false },
                    {
                        data: 'status_badge',
                        name: 'status',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                ]
            });

            $('.btnRefresh').on('click', function() {
                $('#filter-zone, #filter-rack, #filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-zone, #filter-rack, #filter-status').on('change', function() {
                table.ajax.reload();
            });

            $(document).on('click', '.btnDel', function(e) {
                e.preventDefault();
                let id = $(this).data('id');
                let name = $(this).data('name');
                let ajaxurl = routeDestroy.replace(':id', id);
                Swal.fire({
                    title: 'Hapus Sel?',
                    html: 'Sel <strong>' + name + '</strong> akan dihapus permanen.',
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
