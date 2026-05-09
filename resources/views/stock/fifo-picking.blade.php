@extends('layouts.adminlte')
@section('title', 'FIFO Picking — Rekomendasi Pengambilan Barang')

@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold">
                <i class="fas fa-sort-amount-up-alt mr-2 text-primary"></i>FIFO Picking
            </h4>
            <small class="text-muted">
                Rekomendasi pengambilan barang berdasarkan urutan inbound paling lama (First In, First Out).
                Fitur ini tidak mencakup delivery order atau shipment — hanya membantu operator menentukan cell mana yang diambil terlebih dahulu.
            </small>
        </div>
    </div>

    <div class="row">

        {{-- ── Form Input ────────────────────────────────────────── --}}
        <div class="col-md-4">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h6 class="card-title mb-0"><i class="fas fa-search mr-1"></i> Cari Stok FIFO</h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:13px">Item / SKU <span class="text-danger">*</span></label>
                        <select id="itemSelect" class="form-control select2">
                            <option value="">-- Pilih Item --</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-sku="{{ $item->sku }}">
                                    {{ $item->name }} ({{ $item->sku }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:13px">Gudang <span class="text-danger">*</span></label>
                        <select id="warehouseSelect" class="form-control">
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:13px">Qty Dibutuhkan <span class="text-danger">*</span></label>
                        <input type="number" id="qtyInput" class="form-control" min="1" placeholder="Masukkan jumlah..." />
                    </div>
                    <button id="btnPreview" class="btn btn-primary btn-block">
                        <i class="fas fa-list-ol mr-1"></i> Preview Rekomendasi FIFO
                    </button>
                </div>
            </div>

            {{-- Info Box --}}
            <div class="callout callout-info" style="font-size:12px">
                <h6><i class="fas fa-info-circle mr-1"></i>Cara Kerja FIFO</h6>
                <p class="mb-0">
                    Sistem mencari stok item yang tersedia, lalu mengurutkan dari
                    <strong>inbound_date paling lama</strong> terlebih dahulu.
                    Operator mengikuti urutan ini agar barang yang masuk pertama juga keluar pertama.
                </p>
            </div>
        </div>

        {{-- ── Hasil Preview ─────────────────────────────────────── --}}
        <div class="col-md-8">

            {{-- Loading state --}}
            <div id="loadingState" class="text-center py-5" style="display:none!important">
                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                <p class="text-muted mt-2">Menghitung rekomendasi FIFO...</p>
            </div>

            {{-- Empty state --}}
            <div id="emptyState" class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-sort-amount-up-alt fa-3x text-muted mb-3" style="opacity:.3"></i>
                    <p class="text-muted mb-0">Pilih item dan masukkan qty, lalu klik <strong>Preview Rekomendasi FIFO</strong>.</p>
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
                    <i class="fas fa-plus mr-1"></i> Pengambilan Baru
                </button>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    // Select2 for item dropdown
    if ($.fn.select2) {
        $('#itemSelect').select2({ placeholder: '-- Pilih Item --', allowClear: true });
    }

    let currentPreview = null;

    // ── Preview ─────────────────────────────────────────────────
    $('#btnPreview').on('click', function () {
        const itemId      = $('#itemSelect').val();
        const warehouseId = $('#warehouseSelect').val();
        const qty         = parseInt($('#qtyInput').val());

        if (!itemId)   { toastWarn('Pilih item terlebih dahulu.'); return; }
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
                currentPreview = res;
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
        if (!confirm('Konfirmasi pengambilan FIFO?\nStok akan dikurangi dan movement outbound akan dicatat.')) return;

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');

        $.ajax({
            url:  '{{ route("stock.fifo-picking.confirm") }}',
            type: 'POST',
            data: {
                _token:       '{{ csrf_token() }}',
                item_id:      $('#itemSelect').val(),
                warehouse_id: $('#warehouseSelect').val(),
                quantity:     parseInt($('#qtyInput').val()),
                notes:        $('#notesInput').val(),
            },
            success: function (res) {
                if (!res.success) { showError(res.message); $('#btnConfirm').prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Konfirmasi Pengambilan'); return; }
                renderSuccess(res);
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message ?? 'Terjadi kesalahan.';
                showError(msg);
                $('#btnConfirm').prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Konfirmasi Pengambilan');
            }
        });
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
