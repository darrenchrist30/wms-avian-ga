@extends('layouts.adminlte')

@section('title', 'Zona Penyimpanan')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Zona Penyimpanan</h3>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-th-large mr-1"></i> Zona Penyimpanan
                            </div>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-dark" data-toggle="collapse"
                                    data-target="#filterBox">
                                    <i class="fas fa-filter mr-1"></i>Filter
                                </button>
                                <a href="{{ route('location.zones.index') }}" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-sync-alt mr-1"></i>Refresh
                                </a>
                                <a href="{{ route('location.zones.create') }}" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-plus mr-1"></i>Add
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Filter --}}
                    <div class="collapse" id="filterBox">
                        <div class="card-body border-bottom bg-light">
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-4 mb-0">
                                    <label class="small font-weight-bold">Warehouse</label>
                                    <select id="filterWarehouse" class="form-control form-control-sm">
                                        <option value="">Semua Warehouse</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3 mb-0">
                                    <label class="small font-weight-bold">Status</label>
                                    <select id="filterStatus" class="form-control form-control-sm">
                                        <option value="">Semua Status</option>
                                        <option value="Aktif">Aktif</option>
                                        <option value="Nonaktif">Nonaktif</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <button type="button" id="btnResetFilter" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-times mr-1"></i>Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ session('error') }}
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table id="tbl-zones" class="table table-bordered table-sm table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th width="40">#</th>
                                        <th>Kode</th>
                                        <th>Nama Zona</th>
                                        <th>Warehouse</th>
                                        <th>Deskripsi</th>
                                        <th width="70" class="text-center">Rak</th>
                                        <th width="90" class="text-center">Status</th>
                                        <th width="90" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($zones as $i => $zone)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <span class="badge badge-secondary">{{ $zone->code }}</span>
                                            </td>
                                            <td class="font-weight-bold">{{ $zone->name }}</td>
                                            <td>{{ $zone->warehouse->name ?? '-' }}</td>
                                            <td class="text-muted">{{ $zone->description ?? '-' }}</td>
                                            <td class="text-center">
                                                <span class="badge badge-info">{{ $zone->racks_count }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if ($zone->is_active)
                                                    <span class="badge badge-success">Aktif</span>
                                                @else
                                                    <span class="badge badge-secondary">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('location.zones.edit', $zone->id) }}"
                                                    class="btn btn-xs btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-xs btn-danger btn-delete"
                                                    data-id="{{ $zone->id }}" data-name="{{ $zone->name }}"
                                                    title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden delete form --}}
    <form id="delete-form" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>
@endsection

@push('scripts')
    <script>
        $(function() {
            var table = $('#tbl-zones').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                pageLength: 10,
                order: [
                    [1, 'asc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: [7]
                }]
            });

            // Populate warehouse filter dropdown from rendered data
            var warehouseSet = {};
            $('#tbl-zones tbody tr').each(function() {
                var wh = $(this).find('td').eq(3).text().trim();
                if (wh && wh !== '-' && !warehouseSet[wh]) {
                    warehouseSet[wh] = true;
                    $('#filterWarehouse').append('<option value="' + wh + '">' + wh + '</option>');
                }
            });

            $('#filterWarehouse').on('change', function() {
                table.column(3).search($(this).val()).draw();
            });

            $('#filterStatus').on('change', function() {
                table.column(6).search($(this).val()).draw();
            });

            $('#btnResetFilter').on('click', function() {
                $('#filterWarehouse').val('');
                $('#filterStatus').val('');
                table.columns().search('').draw();
            });

            // Delete confirmation
            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');

                Swal.fire({
                    title: 'Hapus Zona?',
                    html: 'Zona <strong>' + name +
                        '</strong> akan dihapus.<br>Pastikan tidak ada rak di zona ini.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        var form = $('#delete-form');
                        form.attr('action', '/location/zones/' + id);
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
