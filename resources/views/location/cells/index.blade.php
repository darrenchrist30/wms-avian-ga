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
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                            <div class="font-weight-bold">
                                <i class="fas fa-border-all mr-1"></i> Daftar Sel
                            </div>
                            <div class="d-flex flex-wrap" style="gap:6px;">
                                <button type="button" id="btnColumnCategory" class="btn btn-primary btn-sm">
                                    <i class="fas fa-tags mr-1"></i>Set Kategori Kolom
                                </button>
                                <a href="#" id="btnBulkQr" class="btn btn-primary btn-sm"
                                   title="Pilih rak di filter lalu klik untuk cetak semua label QR per sel">
                                    <i class="fas fa-layer-group mr-1"></i>Cetak Label Rak
                                </a>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                        data-target=".filter">
                                        <i class="fas fa-filter mr-2"></i>Filter
                                    </button>
                                    <button class="btn btn-sm btn-outline-dark btnRefresh">
                                        <i class="fas fa-redo mr-2"></i>Refresh
                                    </button>
                                    {{-- Add cell disembunyikan: cell dibuat otomatis saat generate rack --}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="filter-container">
                            <div class="row m-2 filter collapse mb-3">
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Gudang</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-warehouse">
                                                <option value="">Semua Gudang</option>
                                                @foreach ($warehouses as $warehouse)
                                                    <option value="{{ $warehouse->id }}" {{ $warehouse->id == $defaultWarehouseId ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Blok</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-rack">
                                                <option value="">Semua Blok</option>
                                                @foreach ($bloks as $blok)
                                                    <option value="{{ $blok }}">Blok {{ $blok }}</option>
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

    <div class="modal fade" id="columnCategoryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color:var(--avian-secondary)">
                    <h5 class="modal-title">
                        <i class="fas fa-tags mr-1"></i> Set Kategori Dominan per Kolom
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border mb-3">
                        Preview memakai data real <strong>mspart</strong>. Apply default hanya mengisi cell yang kategori dominannya masih kosong/netral.
                    </div>

                    <form id="columnCategoryForm" class="mb-3" data-no-loader="1">
                        <div class="row">
                            <div class="col-6 col-md-2">
                                <label class="small font-weight-bold">Blok</label>
                                <input type="number" min="1" class="form-control form-control-sm" id="cc-blok" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="small font-weight-bold">Grup</label>
                                <input type="text" maxlength="1" class="form-control form-control-sm text-uppercase" id="cc-grup" required>
                            </div>
                            <div class="col-6 col-md-2 mt-2 mt-md-0">
                                <label class="small font-weight-bold">Kolom</label>
                                <input type="number" min="1" class="form-control form-control-sm" id="cc-kolom" required>
                            </div>
                            <div class="col-6 col-md-2 mt-2 mt-md-0">
                                <label class="small font-weight-bold">Threshold</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" min="0" max="100" class="form-control" id="cc-threshold" value="80">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4 mt-3 mt-md-4">
                                <button type="submit" id="btnPreviewColumnCategory" class="btn btn-sm btn-primary btn-block">
                                    <i class="fas fa-search mr-1"></i> Preview Kolom
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="cc-empty" class="text-center text-muted py-4">
                        Pilih blok, grup, dan kolom untuk melihat kategori dominan sebelum apply.
                    </div>

                    <div id="cc-result" class="d-none">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <div class="border rounded p-3 h-100">
                                    <div class="small text-muted">Lokasi</div>
                                    <div class="h4 mb-0" id="cc-location">-</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="border rounded p-3 h-100">
                                    <div class="small text-muted">Kategori Dominan</div>
                                    <div class="font-weight-bold" id="cc-dominant">-</div>
                                    <div class="small" id="cc-dominance">-</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="border rounded p-3 h-100">
                                    <div class="small text-muted">SKU Real</div>
                                    <div class="h4 mb-0" id="cc-sku-count">0</div>
                                    <div class="small text-muted" id="cc-row-count">0 baris mspart</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="border rounded p-3 h-100">
                                    <div class="small text-muted">Status</div>
                                    <div id="cc-status">-</div>
                                    <div class="small text-muted" id="cc-cell-summary">-</div>
                                </div>
                            </div>
                        </div>

                        <div id="cc-notes" class="alert alert-warning py-2 d-none"></div>

                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <div class="card mb-0">
                                    <div class="card-header py-2 font-weight-bold">
                                        <i class="fas fa-chart-pie mr-1"></i> Komposisi Kategori Real
                                    </div>
                                    <div class="card-body p-2" id="cc-breakdown"></div>
                                </div>
                            </div>
                            <div class="col-md-7 mb-3">
                                <div class="card mb-3">
                                    <div class="card-header py-2 font-weight-bold">
                                        <i class="fas fa-database mr-1"></i> Contoh dari Data Real MSpart
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>SKU</th>
                                                    <th>Nama</th>
                                                    <th>Kategori</th>
                                                    <th class="text-center">Baris</th>
                                                </tr>
                                            </thead>
                                            <tbody id="cc-samples"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card mb-0">
                                    <div class="card-header py-2 font-weight-bold d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-warehouse mr-1"></i> Contoh dari Denah Aktual WMS</span>
                                        <small class="text-muted" id="cc-stock-summary">-</small>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>SKU</th>
                                                    <th>Nama</th>
                                                    <th>Kategori</th>
                                                    <th class="text-center">Cell</th>
                                                    <th class="text-right">Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody id="cc-stock-samples"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3">
                            <div class="font-weight-bold mb-2">Mode Apply</div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="cc-mode-neutral" name="cc-mode" value="neutral_only" class="custom-control-input" checked>
                                <label class="custom-control-label" for="cc-mode-neutral">Isi hanya cell kategori netral/kosong</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="cc-mode-overwrite" name="cc-mode" value="overwrite" class="custom-control-input">
                                <label class="custom-control-label" for="cc-mode-overwrite">Overwrite semua cell dalam kolom</label>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Default production: isi hanya cell netral/kosong agar kategori existing yang sudah spesifik tidak rusak.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="button" id="btnApplyColumnCategory" class="btn btn-primary" disabled>
                        <i class="fas fa-check mr-1"></i> Apply Kategori Kolom
                    </button>
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
            var columnCategoryPreviewURL = "{{ route('location.cells.column-category.preview') }}";
            var columnCategoryApplyURL = "{{ route('location.cells.column-category.apply') }}";
            var lastColumnCategoryPreview = null;

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
                        d.warehouse_id = $('#filter-warehouse').val();
                        d.blok         = $('#filter-rack').val();
                        d.status       = $('#filter-status').val();
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
                    { data: 'column_code', name: 'column_code', visible: false, searchable: false },
                    { data: 'is_column',   name: 'is_column',   visible: false, searchable: false },
                ],
                createdRow: function (row, data) {
                    if (data.is_column) {
                        $(row).css({ 'background': '#f0f9f4', 'border-left': '3px solid #28a745' });
                    }
                }
            });

            $('.btnRefresh').on('click', function() {
                $('#filter-warehouse').val('{{ $defaultWarehouseId }}');
                $('#filter-rack, #filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-warehouse, #filter-rack, #filter-status').on('change', function() {
                table.ajax.reload();
            });

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char];
                });
            }

            function openColumnCategoryModal(columnCode) {
                lastColumnCategoryPreview = null;
                $('#cc-empty').removeClass('d-none');
                $('#cc-result').addClass('d-none');
                $('#btnApplyColumnCategory').prop('disabled', true);
                $('#cc-notes').addClass('d-none').empty();
                $('#cc-mode-neutral').prop('checked', true);

                if (columnCode) {
                    const parts = String(columnCode).split('-');
                    if (parts.length >= 3) {
                        $('#cc-blok').val(parts[0]);
                        $('#cc-grup').val(parts[1]);
                        $('#cc-kolom').val(parts[2]);
                    }
                }

                $('#columnCategoryModal').modal('show');
            }

            function renderColumnCategoryPreview(data) {
                lastColumnCategoryPreview = data;
                $('#cc-empty').addClass('d-none');
                $('#cc-result').removeClass('d-none');

                const ready = data.status === 'ready';
                const applied = data.status === 'applied';
                const statusClass = ready ? 'badge badge-success' : (applied ? 'badge badge-info' : 'badge badge-warning');
                const cellSummary = data.cell_summary || {};

                $('#cc-location').text(data.location?.code || '-');
                $('#cc-dominant').text(data.dominant_category || '-');
                $('#cc-dominance').text((data.dominance_percent || 0).toFixed(2) + '% dari ' + (data.unique_sku_count || 0) + ' SKU');
                $('#cc-sku-count').text(data.unique_sku_count || 0);
                $('#cc-row-count').text((data.total_rows || 0) + ' baris mspart');
                $('#cc-status').html('<span class="' + statusClass + '">' + escapeHtml(data.status_label || '-') + '</span>');
                $('#cc-cell-summary').text(
                    (cellSummary.total_cells || 0) + ' cell, ' +
                    (cellSummary.neutral_cells || 0) + ' netral, ' +
                    (cellSummary.categorized_cells || 0) + ' sudah berkategori'
                );

                if (data.notes && data.notes.length) {
                    $('#cc-notes')
                        .removeClass('d-none')
                        .html(data.notes.map(note => '<div><i class="fas fa-exclamation-triangle mr-1"></i>' + escapeHtml(note) + '</div>').join(''));
                } else {
                    $('#cc-notes').addClass('d-none').empty();
                }

                const breakdown = data.category_breakdown || [];
                $('#cc-breakdown').html(breakdown.length
                    ? breakdown.map(row => {
                        return '<div class="mb-2">' +
                            '<div class="d-flex justify-content-between">' +
                                '<strong>' + escapeHtml(row.category) + '</strong>' +
                                '<span>' + row.count + ' SKU (' + Number(row.percent || 0).toFixed(2) + '%)</span>' +
                            '</div>' +
                            '<div class="progress" style="height:8px;">' +
                                '<div class="progress-bar bg-success" style="width:' + Math.min(100, Number(row.percent || 0)) + '%"></div>' +
                            '</div>' +
                        '</div>';
                    }).join('')
                    : '<div class="text-muted">Tidak ada kategori yang bisa dihitung.</div>'
                );

                const samples = data.samples || [];
                $('#cc-samples').html(samples.length
                    ? samples.map(row => {
                        return '<tr>' +
                            '<td><code>' + escapeHtml(row.sku) + '</code></td>' +
                            '<td>' + escapeHtml(row.name) + '</td>' +
                            '<td>' + escapeHtml(row.category) + '</td>' +
                            '<td class="text-center">' + escapeHtml(row.baris || '-') + '</td>' +
                        '</tr>';
                    }).join('')
                    : '<tr><td colspan="4" class="text-center text-muted">Tidak ada contoh item.</td></tr>'
                );

                const stockSummary = data.stock_summary || {};
                $('#cc-stock-summary').text(
                    (stockSummary.unique_sku_count || 0) + ' SKU, ' +
                    Number(stockSummary.total_quantity || 0).toLocaleString('id-ID') + ' qty'
                );
                const stockSamples = data.stock_samples || [];
                $('#cc-stock-samples').html(stockSamples.length
                    ? stockSamples.map(row => {
                        return '<tr>' +
                            '<td><code>' + escapeHtml(row.sku) + '</code></td>' +
                            '<td>' + escapeHtml(row.name) + '</td>' +
                            '<td>' + escapeHtml(row.category) + '</td>' +
                            '<td class="text-center">' + escapeHtml(row.cell || '-') + '</td>' +
                            '<td class="text-right">' + Number(row.quantity || 0).toLocaleString('id-ID') + '</td>' +
                        '</tr>';
                    }).join('')
                    : '<tr><td colspan="5" class="text-center text-muted">Tidak ada stok aktual di denah untuk kolom ini.</td></tr>'
                );

                const mode = $('input[name="cc-mode"]:checked').val() || 'neutral_only';
                const canApply = mode === 'overwrite'
                    ? Boolean(data.can_overwrite)
                    : Boolean(data.can_apply);
                $('#btnApplyColumnCategory').prop('disabled', !canApply);
            }

            $('input[name="cc-mode"]').on('change', function () {
                if (lastColumnCategoryPreview) {
                    renderColumnCategoryPreview(lastColumnCategoryPreview);
                }
            });

            $('#btnColumnCategory').on('click', function () {
                openColumnCategoryModal();
            });

            $(document).on('click', '.btnColumnCategory', function () {
                openColumnCategoryModal($(this).data('column'));
            });

            $('#columnCategoryForm').on('submit', function (e) {
                e.preventDefault();

                const $previewButton = $('#btnPreviewColumnCategory');
                $previewButton.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> Preview...');

                $.ajax({
                    url: columnCategoryPreviewURL,
                    type: 'GET',
                    data: {
                        blok: $('#cc-blok').val(),
                        grup: $('#cc-grup').val(),
                        kolom: $('#cc-kolom').val(),
                        threshold: $('#cc-threshold').val() || 80,
                    },
                    success: renderColumnCategoryPreview,
                    error: function (xhr) {
                        Swal.fire('Gagal Preview', xhr?.responseJSON?.message || 'Tidak bisa membaca preview kolom.', 'error');
                    },
                    complete: function () {
                        $previewButton.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Preview Kolom');
                        if (typeof window.__hideLoader === 'function') {
                            window.__hideLoader();
                        }
                    }
                });
            });

            $('#btnApplyColumnCategory').on('click', function () {
                if (!lastColumnCategoryPreview || !lastColumnCategoryPreview.can_overwrite) {
                    Swal.fire('Perlu Review', 'Kolom belum memenuhi threshold dominansi kategori.', 'warning');
                    return;
                }

                const mode = $('input[name="cc-mode"]:checked').val() || 'neutral_only';
                if (mode !== 'overwrite' && !lastColumnCategoryPreview.can_apply) {
                    Swal.fire(
                        'Sudah Apply',
                        'Semua cell aktif di kolom ini sudah memiliki kategori. Tidak perlu apply ulang dengan mode aman.',
                        'info'
                    );
                    return;
                }

                const location = lastColumnCategoryPreview.location?.code || '-';
                const category = lastColumnCategoryPreview.dominant_category || '-';

                Swal.fire({
                    title: 'Apply kategori kolom?',
                    html: 'Kolom <strong>' + escapeHtml(location) + '</strong> akan diset ke kategori <strong>' + escapeHtml(category) + '</strong>.<br><small>Mode: ' + (mode === 'overwrite' ? 'Overwrite semua cell' : 'Isi hanya cell netral/kosong') + '</small>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Apply',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: columnCategoryApplyURL,
                        type: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            blok: lastColumnCategoryPreview.location.blok,
                            grup: lastColumnCategoryPreview.location.grup,
                            kolom: lastColumnCategoryPreview.location.kolom,
                            dominant_category_id: lastColumnCategoryPreview.dominant_category_id,
                            threshold: lastColumnCategoryPreview.threshold || 80,
                            mode: mode,
                        },
                        success: function (response) {
                            const updatedCount = Number(response?.result?.updated_count || 0);
                            const successTitle = updatedCount > 0 ? 'Kategori Diterapkan' : 'Kategori Sudah Sesuai';
                            const successMessage = updatedCount > 0
                                ? updatedCount + ' cell pada kolom ' + location + ' berhasil diset ke kategori ' + category + '.'
                                : 'Tidak ada cell netral yang perlu diubah. Kolom ' + location + ' sudah memiliki kategori yang sesuai.';

                            if (typeof table !== 'undefined' && table?.ajax) {
                                table.ajax.reload(null, false);
                            }

                            Swal.fire({
                                title: successTitle,
                                text: successMessage,
                                icon: 'success',
                                confirmButtonColor: '#28a745',
                                confirmButtonText: 'OK',
                            }).then(() => {
                                $('#columnCategoryModal').modal('hide');
                                resetColumnCategoryPreview();
                            });
                        },
                        error: function (xhr) {
                            const errors = xhr?.responseJSON?.errors;
                            const firstError = errors ? Object.values(errors).flat()[0] : null;
                            Swal.fire('Gagal Apply', firstError || xhr?.responseJSON?.message || 'Tidak bisa apply kategori kolom.', 'error');
                        }
                    });
                });
            });

            // Cetak Label Rak
            const BULK_QR_URL = '{{ route("location.cells.bulk-qr") }}';

            $('#btnBulkQr').on('click', function (e) {
                e.preventDefault();
                const rackId = $('#filter-rack').val();
                if (!rackId) {
                    Swal.fire({
                        icon: 'info', title: 'Pilih Rak Terlebih Dahulu',
                        text: 'Gunakan filter Rak di atas, lalu klik "Cetak Label Rak" untuk mencetak semua label QR per sel.',
                        confirmButtonColor: '#28a745', confirmButtonText: 'Mengerti',
                    });
                    return;
                }
                window.open(BULK_QR_URL + '?rack_id=' + rackId, '_blank');
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
