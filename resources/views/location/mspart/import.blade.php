@extends('layouts.adminlte')
@section('title', 'Import Layout MSpart')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h3 class="mt-2">Import Layout MSpart</h3>
            <p class="text-muted mb-3">
                Upload file <code>MSpart.sql</code> dan <code>StockSpart.sql</code> untuk menginisialisasi
                sub-rak dan sel berdasarkan data lokasi fisik MSpart (blok/grup/kolom/baris).
            </p>
        </div>
    </div>

    <div class="row">
        {{-- Upload Form --}}
        <div class="col-md-5">
            <div class="card">
                <div class="card-header font-weight-bold">
                    <i class="fas fa-file-import mr-2"></i>Upload File SQL
                </div>
                <div class="card-body">
                    <form id="importForm" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm" name="warehouse_id" id="warehouseSelect" required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">
                                File MSpart.sql <span class="text-danger">*</span>
                            </label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="mspartFile"
                                    name="mspart_file" accept=".sql" required>
                                <label class="custom-file-label" for="mspartFile">Pilih file...</label>
                            </div>
                            <small class="text-muted">Tabel: <code>mspart</code> (max 20 MB)</small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">
                                File StockSpart.sql
                                <span class="text-muted font-weight-normal">(opsional)</span>
                            </label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="stockFile"
                                    name="stock_file" accept=".sql">
                                <label class="custom-file-label" for="stockFile">Pilih file...</label>
                            </div>
                            <small class="text-muted">Tabel: <code>Qspart</code> — untuk mengisi data stok awal</small>
                        </div>

                        <div class="form-group mb-0">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="dryRun" name="dry_run" value="1">
                                <label class="custom-control-label" for="dryRun">
                                    Dry Run (preview tanpa menyimpan)
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer d-flex" style="gap:8px;">
                    <button type="button" class="btn btn-success btn-sm" id="btnImport">
                        <i class="fas fa-file-import mr-1"></i>Jalankan Import
                    </button>
                    <a href="{{ route('warehouse3d.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-cube mr-1"></i>Lihat 3D View
                    </a>
                </div>
            </div>

            {{-- Info card --}}
            <div class="card border-info">
                <div class="card-header bg-info text-white py-2 font-weight-bold" style="font-size:13px;">
                    <i class="fas fa-info-circle mr-1"></i>Cara Kerja
                </div>
                <div class="card-body py-2" style="font-size:12px;">
                    <ol class="pl-3 mb-0">
                        <li>Baca MSpart.sql → temukan item dengan <code>blok</code>, <code>grup</code>, <code>kolom</code>, <code>baris</code> valid</li>
                        <li>Buat <strong>sub-rak</strong> per pasangan (blok, grup), misal "1A", "4G"</li>
                        <li>Buat <strong>sel</strong> per kombinasi (kolom, baris) di dalam sub-rak</li>
                        <li>Jika ada StockSpart.sql, hubungkan item ke sel dengan data stok awal</li>
                    </ol>
                    <hr class="my-2">
                    <p class="mb-1"><strong>Format kode sub-rak:</strong> <code>[blok][grup]</code> → <code>1A</code>, <code>2B</code></p>
                    <p class="mb-0"><strong>Format kode sel:</strong> <code>[blok]-[grup]-[kolom]-[baris]</code> → <code>1-A-3-2</code></p>
                </div>
            </div>
        </div>

        {{-- Results Panel --}}
        <div class="col-md-7">
            {{-- Loading --}}
            <div id="importLoading" class="text-center py-5" style="display:none;">
                <div class="spinner-border text-success mb-3" style="width:3rem;height:3rem;"></div>
                <div class="font-weight-bold">Memproses file SQL...</div>
                <small class="text-muted">Harap tunggu, sedang parsing dan menyimpan data</small>
            </div>

            {{-- Empty state --}}
            <div id="importEmpty" class="card text-center py-5 text-muted">
                <div><i class="fas fa-file-upload fa-3x mb-3 text-secondary"></i></div>
                <div>Upload file SQL dan klik <strong>Jalankan Import</strong> untuk memulai.</div>
            </div>

            {{-- Results --}}
            <div id="importResults" style="display:none;">

                {{-- Summary cards --}}
                <div class="row mb-3" id="summaryCards"></div>

                {{-- Log --}}
                <div class="card">
                    <div class="card-header py-2 font-weight-bold" style="font-size:13px;">
                        <i class="fas fa-list mr-1"></i>Log Proses
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="importLog"></ul>
                    </div>
                    <div class="card-footer py-2 text-right" id="importFooter" style="display:none;">
                        <a href="{{ route('warehouse3d.index') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-cube mr-1"></i>Buka Visualisasi 3D
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    var importUrl = '{{ route("location.mspart.import.post") }}';
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Update custom file labels
    $(document).on('change', '.custom-file-input', function () {
        var fileName = $(this).val().split('\\').pop() || $(this).val().split('/').pop();
        $(this).next('.custom-file-label').text(fileName || 'Pilih file...');
    });

    $('#btnImport').on('click', function () {
        var mspartFile = $('#mspartFile')[0].files[0];
        if (!mspartFile) {
            Swal.fire('File Wajib', 'Silakan pilih file MSpart.sql terlebih dahulu.', 'warning');
            return;
        }

        var isDryRun = $('#dryRun').is(':checked');
        var action   = isDryRun ? 'Preview (Dry Run)' : 'Import Permanen';

        Swal.fire({
            title: action + '?',
            html: isDryRun
                ? 'Data <strong>tidak akan disimpan</strong> ke database. Hanya menampilkan preview.'
                : 'Sub-rak dan sel akan dibuat/diperbarui di database. Proses ini tidak dapat diurungkan.',
            icon: isDryRun ? 'info' : 'warning',
            showCancelButton: true,
            confirmButtonColor: isDryRun ? '#17a2b8' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-play mr-1"></i>' + action,
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var formData = new FormData($('#importForm')[0]);
            if (!isDryRun) formData.delete('dry_run');

            $('#importEmpty').hide();
            $('#importResults').hide();
            $('#importLoading').show();
            $('#btnImport').prop('disabled', true);

            $.ajax({
                url: importUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 120000,
                success: function (res) {
                    renderResults(res);
                },
                error: function (xhr) {
                    $('#importLoading').hide();
                    $('#importEmpty').hide();
                    $('#importResults').show();

                    var msg = xhr?.responseJSON?.error || xhr?.responseJSON?.message || 'Terjadi kesalahan pada server.';
                    $('#summaryCards').html('');
                    $('#importLog').html(
                        '<li class="list-group-item list-group-item-danger">' +
                        '<i class="fas fa-exclamation-circle mr-2"></i>' + msg + '</li>'
                    );
                    $('#importFooter').hide();
                    $('#btnImport').prop('disabled', false);
                }
            });
        });
    });

    function renderResults(res) {
        $('#importLoading').hide();
        $('#importResults').show();
        $('#btnImport').prop('disabled', false);

        var s = res.summary;
        var dryLabel = res.dry_run ? ' <span class="badge badge-info">DRY RUN</span>' : '';

        var skuColor = s.unmatched_skus > 0 ? 'warning' : 'success';
        $('#summaryCards').html(
            summCard('Baris MSpart',    s.mspart_rows,    'secondary', 'fas fa-file-code') +
            summCard('Item Berlokasi',  s.located_items,  'primary',   'fas fa-map-marker-alt') +
            summCard('SKU Cocok',       s.matched_skus,   skuColor,    'fas fa-check-circle') +
            summCard('SKU Tdk Cocok',   s.unmatched_skus, s.unmatched_skus > 0 ? 'danger' : 'secondary', 'fas fa-unlink') +
            summCard('Sub-Rak Baru',    s.racks_created,  'success',   'fas fa-th-large') +
            summCard('Sel Baru',        s.cells_created,  'success',   'fas fa-border-all') +
            summCard('Stok Dihubungkan',s.stock_assigned, 'info',      'fas fa-link') +
            summCard('Stok Dilewati',   s.stock_skipped,  'warning',   'fas fa-forward')
        );

        var logHtml = '';
        $.each(res.log, function (i, entry) {
            var cls = entry.type === 'warn' ? 'warning' : (entry.type === 'success' ? 'success' : 'secondary');
            var icon = entry.type === 'warn' ? 'fa-exclamation-triangle' : (entry.type === 'success' ? 'fa-check-circle' : 'fa-info-circle');
            logHtml += '<li class="list-group-item list-group-item-' + cls + ' py-2" style="font-size:13px;">' +
                '<i class="fas ' + icon + ' mr-2"></i>' + entry.msg + '</li>';
        });

        if (res.dry_run) {
            logHtml += '<li class="list-group-item list-group-item-info py-2" style="font-size:13px;">' +
                '<i class="fas fa-info-circle mr-2"></i><strong>DRY RUN selesai — tidak ada data yang disimpan.</strong></li>';
        } else {
            logHtml += '<li class="list-group-item list-group-item-success py-2 font-weight-bold" style="font-size:13px;">' +
                '<i class="fas fa-check-double mr-2"></i>Import selesai! Buka visualisasi 3D untuk melihat hasilnya.</li>';
        }

        $('#importLog').html(logHtml);

        if (!res.dry_run && s.cells_created > 0) {
            $('#importFooter').show();
        } else {
            $('#importFooter').hide();
        }
    }

    function summCard(label, value, color, icon) {
        return '<div class="col-6 col-md-3 mb-2">' +
            '<div class="card border-' + color + ' text-center h-100">' +
            '<div class="card-body py-2 px-1">' +
            '<i class="' + icon + ' text-' + color + ' mb-1 d-block"></i>' +
            '<div class="font-weight-bold" style="font-size:18px;">' + value + '</div>' +
            '<small class="text-muted" style="font-size:10px;">' + label + '</small>' +
            '</div></div></div>';
    }
});
</script>
@endpush
