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
                                <i class="fas fa-tags mr-1"></i> Kategori Sparepart
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-dark" data-toggle="collapse" data-target="#filter"
                                    aria-expanded="false">
                                    <i class="fas fa-filter mr-2"></i>Filter
                                </button>
                                <button class="btn btn-sm btn-outline-dark btnRefresh">
                                    <i class="fas fa-redo mr-2"></i>Refresh
                                </button>
                                <a href="{{ route('master.categories.create') }}" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-plus mr-2"></i>Add
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        @endif

                        <div id="filter" class="row m-2 collapse mb-3">
                            <div class="col-sm-12 col-md-4">
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Status</label>
                                    <div class="col-sm-8">
                                        <select id="status_filter" class="form-control">
                                            <option value="">Semua</option>
                                            <option value="Aktif">Aktif</option>
                                            <option value="Nonaktif">Nonaktif</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <table id="datatable" class="table table-bordered table-sm table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="12%">Kode</th>
                                    <th>Nama Kategori</th>
                                    <th>Deskripsi</th>
                                    <th width="8%">Warna</th>
                                    <th width="10%">Sparepart</th>
                                    <th width="9%">Status</th>
                                    <th width="8%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $i => $cat)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td><span class="badge badge-secondary">{{ $cat->code }}</span></td>
                                        <td class="font-weight-bold">{{ $cat->name }}</td>
                                        <td class="text-muted">{{ $cat->description ?? '-' }}</td>
                                        <td class="text-center">
                                            <span
                                                style="display:inline-block;width:24px;height:24px;border-radius:50%;background:{{ $cat->color_code ?? '#6c757d' }};border:1px solid #ddd;"
                                                title="{{ $cat->color_code }}"></span>
                                        </td>
                                        <td class="text-center"><span
                                                class="badge badge-info">{{ $cat->items_count }}</span></td>
                                        <td class="text-center">
                                            @if ($cat->is_active)
                                                <span class="badge badge-success">Aktif</span>
                                            @else
                                                <span class="badge badge-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('master.categories.edit', $cat->id) }}"
                                                    class="btn btn-warning btn-xs" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-danger btn-xs btnDel" data-id="{{ $cat->id }}"
                                                    data-name="{{ $cat->name }}" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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

    <form id="delete-form" method="POST" style="display:none;">
        @csrf @method('DELETE')
    </form>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var table = $('#datatable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [
                    [1, 'asc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: [4, 7]
                }]
            });

            $('#status_filter').on('change', function() {
                table.column(6).search($(this).val()).draw();
            });

            $('.btnRefresh').on('click', function() {
                $('#status_filter').val('');
                table.search('').columns().search('').draw();
            });

            $(document).on('click', '.btnDel', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                Swal.fire({
                    title: 'Hapus Kategori?',
                    text: '"' + name + '" akan dihapus permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        var form = $('#delete-form');
                        form.attr('action', '/master/categories/' + id);
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
