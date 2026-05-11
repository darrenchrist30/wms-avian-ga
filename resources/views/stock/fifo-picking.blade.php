@extends('layouts.adminlte')
@section('title', 'FIFO Picking — Rekomendasi Pengambilan Barang')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css">
<style>
    .select2 { width: 100% !important; }
    .select2-container--bootstrap4 .select2-selection--single {
        display: flex !important;
        align-items: center;
        padding: 0 2rem 0 0;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        flex: 1;
        padding: 0 0.75rem !important;
        line-height: normal !important;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold">
                <i class="fas fa-sort-amount-up-alt mr-2 text-primary"></i>FIFO Picking Baru
            </h4>
            {{-- <small class="text-muted">
                Rekomendasi pengambilan barang berdasarkan urutan inbound paling lama (First In, First Out).
                Fitur ini tidak mencakup delivery order atau shipment — hanya membantu operator menentukan cell mana yang diambil terlebih dahulu.
            </small> --}}
        </div>
        <a href="{{ route('stock.fifo-picking.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Riwayat
        </a>
    </div>

    {{-- ── Form Input (full width, horizontal) ─────────────────── --}}
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h6 class="card-title mb-0"><i class="fas fa-search mr-1"></i> Cari Stok FIFO</h6>
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-5 mb-0">
                    <label class="font-weight-bold" style="font-size:13px">Item / SKU <span class="text-danger">*</span></label>
                    <select id="itemSelect" class="form-control select2">
                        <option value="">-- Pilih Item --</option>
                    </select>
                </div>
                <div class="form-group col-md-4 mb-0">
                    <label class="font-weight-bold" style="font-size:13px">Gudang <span class="text-danger">*</span></label>
                    @if($warehouses->count() === 1)
                        <input type="text" class="form-control"
                               value="{{ $warehouses->first()->name }} ({{ $warehouses->first()->code }})"
                               title="{{ $warehouses->first()->name }} ({{ $warehouses->first()->code }})"
                               style="text-overflow:ellipsis" readonly>
                        <input type="hidden" id="warehouseSelect" value="{{ $warehouses->first()->id }}">
                    @else
                        <select id="warehouseSelect" class="form-control select2">
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="form-group col-md-1 mb-0">
                    <label class="font-weight-bold" style="font-size:13px">Qty <span class="text-danger">*</span></label>
                    <input type="number" id="qtyInput" class="form-control" min="1" placeholder="Qty" />
                </div>
                <div class="form-group col-md-2 mb-0">
                    <label class="d-block" style="font-size:13px;">&nbsp;</label>
                    <button id="btnPreview" class="btn btn-primary btn-block">
                        <i class="fas fa-list-ol mr-1"></i> Preview FIFO
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Hasil Preview (full width, di bawah form) ─────────────── --}}

    {{-- Loading state --}}
    <div id="loadingState" class="text-center py-5" style="display:none!important">
        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
        <p class="text-muted mt-2">Menghitung rekomendasi FIFO...</p>
    </div>

    {{-- Empty state --}}
    <div id="emptyState" class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-sort-amount-up-alt fa-3x text-muted mb-3" style="opacity:.3"></i>
            <p class="text-muted mb-0">
                Pilih item, gudang, dan masukkan qty, lalu klik <strong>Preview FIFO</strong>.
            </p>
        </div>
    </div>

    {{-- Error state --}}
    <div id="errorState" class="alert alert-danger" style="display:none">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <span id="errorMsg"></span>
    </div>

    {{-- Preview Result --}}
    <div id="previewResult" style="display:none">
        <div class="card card-outline card-success">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="card-title mb-0">
                    <i class="fas fa-clipboard-list mr-1 text-success"></i>
                    Rekomendasi FIFO — <span id="previewItemName"></span>
                </h6>
                <span class="badge badge-success" id="previewTotalBadge"></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Cell</th>
                            <th>Zona / Rak</th>
                            <th>Inbound Date</th>
                            <th class="text-right">Tersedia</th>
                            <th class="text-right text-success font-weight-bold">Ambil</th>
                        </tr>
                    </thead>
                    <tbody id="picksTableBody"></tbody>
                    <tfoot>
                        <tr class="table-success font-weight-bold">
                            <td colspan="5" class="text-right">Total Pengambilan</td>
                            <td class="text-right" id="totalQtyFoot"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Confirm Form --}}
        <div class="card">
            <div class="card-body">
                <div class="form-group mb-2">
                    <label style="font-size:13px;font-weight:600">Catatan (opsional)</label>
                    <input type="text" id="notesInput" class="form-control form-control-sm"
                           placeholder="Contoh: Pengambilan untuk produksi shift 1" maxlength="500" />
                </div>
                <button id="btnConfirm" class="btn btn-success">
                    <i class="fas fa-check mr-1"></i> Konfirmasi Pengambilan
                </button>
                <button id="btnReset" class="btn btn-outline-secondary ml-2">
                    <i class="fas fa-redo mr-1"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Sukses State --}}
    <div id="successState" style="display:none">
        <div class="alert alert-success">
            <i class="fas fa-check-circle mr-1"></i>
            <strong>Pengambilan berhasil!</strong> <span id="successMsg"></span>
        </div>
        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-receipt mr-1"></i> Rekap Pengambilan</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th><th>Cell</th><th>Inbound Date</th><th class="text-right">Qty Diambil</th>
                        </tr>
                    </thead>
                    <tbody id="successTableBody"></tbody>
                </table>
            </div>
        </div>
        <button id="btnPickAgain" class="btn btn-primary mt-2">
            <i class="fas fa-plus mr-1"></i> Picking Baru
        </button>
        <a href="{{ route('stock.fifo-picking.index') }}" class="btn btn-outline-secondary mt-2 ml-2">
            <i class="fas fa-history mr-1"></i> Lihat Semua Riwayat
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    if ($.fn.select2) {
        $('#itemSelect').select2({
            placeholder: '-- Pilih Item --',
            theme: 'bootstrap4',
            allowClear: true,
            // minimumInputLength: 2,
            language: {
                inputTooShort: function () { return 'Ketik minimal 2 karakter...'; },
                searching:     function () { return 'Mencari...'; },
                noResults:     function () { return 'Item tidak ditemukan.'; },
            },
            ajax: {
                url: '{{ route("stock.fifo-picking.search-items") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true
            }
        });
        if ($('#warehouseSelect').is('select')) {
            $('#warehouseSelect').select2({ placeholder: '-- Pilih Gudang --', theme: 'bootstrap4', allowClear: true });
        }
    }

    let currentPreview = null;

    // ── Preview ─────────────────────────────────────────────────
    $('#btnPreview').on('click', function () {
        const itemId      = $('#itemSelect').val();
        const warehouseId = $('#warehouseSelect').val();
        const qty         = parseInt($('#qtyInput').val());

        if (!itemId) { toastWarn('Pilih item terlebih dahulu.'); return; }
        if (!warehouseId) { toastWarn('Pilih gudang terlebih dahulu.'); return; }
        if (!qty || qty < 1) { toastWarn('Masukkan qty yang valid.'); return; }

        showLoading();

        $.ajax({
            url:  '{{ route("stock.fifo-picking.preview") }}',
            type: 'POST',
            data: {
                _token:       '{{ csrf_token() }}',
                item_id:      itemId,
                warehouse_id: warehouseId,
                quantity:     qty,
            },
            success: function (res) {
                hideLoading();
                if (!res.success) { showError(res.message); return; }
                currentPreview = { ...res, request: { item_id: itemId, warehouse_id: warehouseId, quantity: qty } };
                renderPreview(res);
            },
            error: function (xhr) {
                hideLoading();
                const msg = xhr.responseJSON?.message ?? 'Terjadi kesalahan.';
                showError(msg);
            }
        });
    });

    // ── Confirm ─────────────────────────────────────────────────
    $('#btnConfirm').on('click', function () {
        if (!currentPreview) return;

        Swal.fire({
            icon: 'question',
            title: 'Konfirmasi Pengambilan FIFO?',
            text: 'Stok akan dikurangi dan movement outbound akan dicatat.',
            showCancelButton: true,
            confirmButtonText: 'Ya, Konfirmasi',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#28a745',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $('#btnConfirm').prop('disabled', true);

            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang menyimpan pengambilan FIFO.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () { Swal.showLoading(); }
            });

            $.ajax({
                url:  '{{ route("stock.fifo-picking.confirm") }}',
                type: 'POST',
                data: {
                    _token:       '{{ csrf_token() }}',
                    item_id:      currentPreview.request.item_id,
                    warehouse_id: currentPreview.request.warehouse_id,
                    quantity:     currentPreview.request.quantity,
                    notes:        $('#notesInput').val(),
                },
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Gagal', res.message, 'error');
                        $('#btnConfirm').prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Konfirmasi Pengambilan');
                        return;
                    }
                    Swal.close();
                    renderSuccess(res);
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.message ?? 'Terjadi kesalahan.';
                    Swal.fire('Gagal', msg, 'error');
                    $('#btnConfirm').prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Konfirmasi Pengambilan');
                }
            });
        }); // end Swal.then
    });

    // ── Reset ────────────────────────────────────────────────────
    $('#btnReset').on('click', resetForm);
    $('#btnPickAgain').on('click', resetForm);

    // ── Helpers ──────────────────────────────────────────────────
    function renderPreview(res) {
        $('#previewItemName').text(res.item.name + ' (' + res.item.sku + ')');
        $('#previewTotalBadge').text('Total: ' + res.total_qty + ' unit');
        $('#totalQtyFoot').text(res.total_qty + ' unit');

        let rows = '';
        res.picks.forEach(function (p, i) {
            rows += `<tr>
                <td>${i + 1}</td>
                <td><strong>${p.cell_code}</strong></td>
                <td><span class="text-muted">${p.zone_code} / Rak ${p.rack_code}</span></td>
                <td><span class="badge badge-light border">${p.inbound_date}</span></td>
                <td class="text-right">${p.available_qty}</td>
                <td class="text-right text-success font-weight-bold">${p.take_qty}</td>
            </tr>`;
        });
        $('#picksTableBody').html(rows);

        $('#emptyState, #errorState, #successState').hide();
        $('#previewResult').show();
        $('#btnConfirm').prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Konfirmasi Pengambilan');
        // $('html, body').animate({ scrollTop: $('#previewResult').offset().top - 20 }, 400);
    }

    function renderSuccess(res) {
        let rows = '';
        res.picks.forEach(function (p, i) {
            rows += `<tr><td>${i + 1}</td><td><strong>${p.cell_code}</strong></td><td>${p.inbound_date}</td><td class="text-right font-weight-bold">${p.take_qty}</td></tr>`;
        });
        $('#successTableBody').html(rows);
        $('#successMsg').text(res.item_name + ' sebanyak ' + res.total_qty + ' unit berhasil diambil. Movement outbound telah dicatat.');

        $('#previewResult').hide();
        $('#errorState').hide();
        $('#successState').show();
        currentPreview = null;
    }

    function showLoading() {
        $('#emptyState, #previewResult, #errorState, #successState').hide();
        $('#loadingState').css('display', 'block');
    }

    function hideLoading() {
        $('#loadingState').css('display', 'none');
    }

    function showError(msg) {
        $('#errorMsg').text(msg);
        $('#emptyState, #previewResult').hide();
        $('#errorState').show();
    }

    function resetForm() {
        $('#itemSelect').val('').trigger('change');
        $('#qtyInput').val('');
        $('#notesInput').val('');
        currentPreview = null;
        $('#emptyState').show();
        $('#previewResult, #errorState, #successState').hide();
    }

    function toastWarn(msg) {
        if (window.toastr) {
            toastr.warning(msg);
        } else {
            alert(msg);
        }
    }
});
</script>
@endpush
