@extends('layouts.adminlte')
@section('title', 'Master Sparepart')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Master Sparepart</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-cogs mr-1"></i> Daftar Sparepart
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                    data-target=".filter">
                                    <i class="fas fa-filter mr-2"></i>Filter
                                </button>
                                <button class="btn btn-sm btn-outline-dark btnRefresh">
                                    <i class="fas fa-redo mr-2"></i>Refresh
                                </button>
                                <a href="{{ route('master.items.scan') }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-barcode mr-2"></i>Scan Barcode
                                </a>
                                <button class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#modalImport">
                                    <i class="fas fa-file-excel mr-2"></i>Import Excel
                                </button>
                                <a href="{{ route('master.items.create') }}" class="btn btn-primary btn-sm">
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
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Kategori</label>
                                        <div class="col-sm-8">
                                            <select name="category_id" class="form-control form-control-sm"
                                                id="filter-category">
                                                <option value="">Semua Kategori</option>
                                                @foreach ($categories as $cat)
                                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
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
                                    <th width="80" class="text-center">Actions</th>
                                    <th width="50" class="text-center">#</th>
                                    <th>SKU / Nama Sparepart</th>
                                    <th width="120">Kategori</th>
                                    <th width="80">Satuan</th>
                                    <th width="80" class="text-center">Min Order</th>
                                    <th width="80" class="text-center">Max Order</th>
                                    <th width="80" class="text-center">Status</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Import Excel --}}
    <div class="modal fade" id="modalImport" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-file-excel mr-2"></i>Import Sparepart dari Excel</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form action="{{ route('master.items.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Format kolom:</strong> SKU, Nama Sparepart, Kategori, Kode Satuan,
                            ERP Item Code, Min Stok, Maks Stok, Reorder Point, Berat KG, Volume M3, Barcode,
                            Deadstock Threshold Hari, Deskripsi.
                            <br>SKU yang sudah ada akan <strong>dilewati</strong> (tidak diupdate).
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">File Excel / CSV <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file" name="file" class="custom-file-input" id="importFile"
                                        accept=".xlsx,.xls,.csv" required>
                                    <label class="custom-file-label" for="importFile">Pilih file...</label>
                                </div>
                            </div>
                            <small class="text-muted">Format: .xlsx, .xls, .csv — Maks 5 MB</small>
                            @error('file')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <a href="{{ route('master.items.template') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download mr-1"></i>Download Template
                        </a>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload mr-1"></i>Upload & Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Error Import --}}
    @if (session('import_errors'))
        <div class="modal fade" id="modalImportErrors" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Baris yang Gagal Diimport</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Baris berikut gagal diimport. Perbaiki data di file Excel lalu upload ulang baris tersebut saja.</p>
                        <ul class="list-group list-group-flush small">
                            @foreach (session('import_errors') as $err)
                                <li class="list-group-item py-1 text-danger"><i class="fas fa-times-circle mr-1"></i>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function () {
                $('#modalImportErrors').modal('show');
            });
        </script>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var baseURL = "{{ route('master.items.datatable') }}";
            var routeDestroy = "{{ route('master.items.destroy', ':id') }}";

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
                        d.category_id = $('#filter-category').val();
                        d.status      = $('#filter-status').val();
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
                        data: 'nama_info',
                        name: 'name'
                    },
                    {
                        data: 'category_badge',
                        name: 'category.name',
                        orderable: false
                    },
                    {
                        data: 'unit',
                        name: 'unit.code',
                        render: function(data) {
                            return data ? (data.code || '-') : '-';
                        },
                        orderable: false
                    },
                    {
                        data: 'min_stock',
                        name: 'min_stock',
                        orderable: true,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'max_stock',
                        name: 'max_stock',
                        orderable: true,
                        searchable: false,
                        className: 'text-center'
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

            // Custom file input label
        $('#importFile').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).siblings('.custom-file-label').text(fileName || 'Pilih file...');
        });

        $('.btnRefresh').on('click', function() {
                $('#filter-category, #filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-category, #filter-status').on('change', function() {
                table.ajax.reload();
            });

            $(document).on('click', '.btnDel', function(e) {
                e.preventDefault();
                let id = $(this).data('id');
                let name = $(this).data('name');
                let ajaxurl = routeDestroy.replace(':id', id);
                Swal.fire({
                    title: 'Hapus Sparepart?',
                    html: 'Sparepart <strong>' + name + '</strong> akan dihapus permanen.',
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
