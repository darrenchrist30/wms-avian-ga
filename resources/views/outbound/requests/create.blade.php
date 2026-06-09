@extends('layouts.adminlte')
@section('title', 'Permintaan Outbound')

@push('styles')
<style>
.page-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e9ecef;
}
.page-header h4 { margin: 0; font-size: 20px; }
.section-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.section-card .section-title {
    background: #f8f9fa;
    padding: 12px 18px;
    font-size: 13px;
    font-weight: 700;
    color: #344054;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.section-card .section-body { padding: 18px; }
.section-card .section-body-flush { padding: 0; }
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}
.empty-state i { font-size: 32px; margin-bottom: 8px; display: block; opacity: .4; }
.item-row-name { font-size: 13px; font-weight: 600; color: #1a1a1a; }
.item-row-sku  { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.form-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 4px;
}

/* ── Scan Bar ── */
.scan-bar {
    background: #f0fdf4;
    border-bottom: 1px solid #d1fae5;
    padding: 12px 18px;
}
.scan-input-wrap { position: relative; }
.scan-input-wrap .scan-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    color: #6c757d; font-size: 14px; pointer-events: none; z-index: 4;
}
#scanInput {
    padding-left: 34px; font-size: 14px; font-weight: 600;
    border: 2px solid #a7f3d0; border-radius: 6px; height: 40px;
    transition: border-color .15s; background: #fff;
}
#scanInput:focus { border-color: #0d8564; box-shadow: 0 0 0 3px rgba(13,133,100,.12); outline: none; }
.scan-feedback {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 600; padding: 4px 10px;
    border-radius: 20px; transition: opacity .3s; white-space: nowrap;
}
.scan-feedback.sf-success { background: #f0fdf4; color: #065f46; border: 1px solid #6ee7b7; }
.scan-feedback.sf-error   { background: #fff5f5; color: #991b1b; border: 1px solid #fecaca; }
.scan-feedback.sf-hidden  { opacity: 0; pointer-events: none; }
#readerReq { width: 100%; max-width: 360px; border-radius: 8px; overflow: hidden; margin-top: 10px; }
#readerReq video { border-radius: 8px; }
tr.row-flash { background: #d1fae5 !important; transition: background .5s; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12">

            <div class="page-header" style="justify-content:space-between;">
                <h4 class="font-weight-bold mb-0">
                    <i class="fas fa-clipboard-list mr-2" style="color:#0d8564;"></i>Permintaan Outbound
                </h4>
                <a href="{{ route('outbound.requests.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left mr-1"></i>Kembali
                </a>
            </div>

            <form id="formRequest" action="{{ route('outbound.requests.store') }}" method="POST">
                @csrf

                {{-- Informasi Request --}}
                <div class="section-card">
                    <div class="section-title">
                        <span><i class="fas fa-building mr-2 text-muted"></i>Informasi Request</span>
                    </div>
                    <div class="section-body">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group mb-md-0">
                                    <label class="small font-weight-bold mb-1">Gudang <span class="text-danger">*</span></label>
                                    <select name="warehouse_id" class="form-control form-control-sm @error('warehouse_id') is-invalid @enderror" required>
                                        <option value="">-- Pilih Gudang --</option>
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ old('warehouse_id', $defaultWarehouseId) == $wh->id ? 'selected' : '' }}>
                                                {{ $wh->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold mb-1">Catatan <span class="text-muted font-weight-normal">(opsional)</span></label>
                                    <input type="text" name="notes" class="form-control form-control-sm" maxlength="500"
                                        placeholder="Tujuan pengambilan, keperluan, nama penerima, dll."
                                        value="{{ old('notes') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Daftar Item --}}
                <div class="section-card">
                    <div class="section-title">
                        <span><i class="fas fa-boxes mr-2 text-muted"></i>Daftar Item Outbound</span>
                        <button type="button" id="btnAddItem" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list mr-1"></i>Pilih Manual
                        </button>
                    </div>

                    {{-- Scan Bar --}}
                    <div class="scan-bar">
                        <div class="d-flex align-items-center flex-wrap" style="gap:10px;">
                            <div class="scan-input-wrap" style="flex:1;max-width:460px;">
                                <i class="fas fa-barcode scan-icon"></i>
                                <input type="text" id="scanInput" class="form-control"
                                       placeholder="Scan barcode / ketik SKU, tekan Enter…"
                                       autocomplete="off" autofocus>
                            </div>
                            <button type="button" id="btnCameraReq" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-camera mr-1"></i>Kamera
                            </button>
                            <span id="scanFeedback" class="scan-feedback sf-hidden"></span>
                        </div>
                        <div id="readerReq" style="display:none;"></div>
                    </div>

                    <div class="section-body-flush">
                        <table class="table table-sm table-hover mb-0" id="tblItems" style="border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f8f9fa;">
                                    <th width="44" class="text-center pl-3" style="border-bottom:1px solid #e9ecef;border-right:1px solid #e9ecef;">#</th>
                                    <th style="border-bottom:1px solid #e9ecef;border-right:1px solid #e9ecef;">Item / SKU</th>
                                    <th style="border-bottom:1px solid #e9ecef;border-right:1px solid #e9ecef;">Lokasi</th>
                                    <th width="150" class="text-center" style="border-bottom:1px solid #e9ecef;border-right:1px solid #e9ecef;">Qty</th>
                                    <th width="60" class="text-center pr-3" style="border-bottom:1px solid #e9ecef;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemRows">
                                <tr id="emptyRow">
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-barcode"></i>
                                            <div>Belum ada item ditambahkan.</div>
                                            <div style="font-size:12px;margin-top:4px;">Scan barcode atau klik <strong>Pilih Manual</strong>.</div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                @error('items')
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                    </div>
                @enderror

                <div class="form-footer">
                    <a href="{{ route('outbound.requests.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times mr-1"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-primary px-4" id="btnSubmit">
                        <i class="fas fa-paper-plane mr-1"></i> Kirim
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

{{-- Modal: Pilih Item Manual --}}
<div class="modal fade" id="modalAddItem" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:10px;overflow:hidden;">
            <div class="modal-header" style="background:#0d8564;color:#fff;padding:12px 16px;">
                <h6 class="modal-title mb-0 font-weight-bold">
                    <i class="fas fa-search mr-1"></i>Pilih Item
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;">&times;</button>
            </div>
            <div class="modal-body pt-3">
                <div class="input-group input-group-sm mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                    <input type="text" id="itemSearch" class="form-control" placeholder="Cari nama item atau SKU...">
                </div>
                <div style="max-height:380px;overflow-y:auto;border:1px solid #e9ecef;border-radius:6px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama Item</th>
                                <th width="140">SKU</th>
                                <th width="70" class="text-center">Satuan</th>
                                <th width="70" class="text-center">Pilih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                            <tr class="item-option" data-id="{{ $item->id }}" data-name="{{ $item->name }}"
                                data-sku="{{ $item->sku }}" data-unit="{{ $item->unit?->code }}">
                                <td>{{ $item->name }}</td>
                                <td><code style="font-size:11px;">{{ $item->sku }}</code></td>
                                <td class="text-center">{{ $item->unit?->code }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-primary btnPickItem">Pilih</button>
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
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
$(function () {

    var rowCount       = 0;
    var selectedItems  = {};   // { item_id: rowCount }
    var feedbackTimer  = null;
    var cameraActive   = false;
    var qrScanner      = null;

    /* ── Add new row ────────────────────────────────────────────── */
    function addItemRow(id, name, sku, unit, locations) {
        selectedItems[id] = ++rowCount;
        $('#emptyRow').hide();
        var displayNum = $('#itemRows tr[id^="item-row-"]').length + 1;

        var locHtml = '';
        if (locations && locations.length) {
            locHtml = locations.map(function (l) {
                return '<span class="badge badge-light border" style="font-size:11px;font-weight:600;margin:1px;">' + escHtml(l) + '</span>';
            }).join(' ');
        } else {
            locHtml = '<span class="text-muted" style="font-size:11px;">–</span>';
        }

        var row = '<tr id="item-row-' + rowCount + '" data-item-id="' + id + '" style="border-bottom:1px solid #f3f4f6;">'
            + '<td class="text-center text-muted pl-3" style="border-right:1px solid #e9ecef;">' + displayNum + '</td>'
            + '<td style="border-right:1px solid #e9ecef;">'
            +   '<input type="hidden" name="items[' + rowCount + '][item_id]" value="' + id + '">'
            +   '<div class="item-row-name">' + escHtml(name) + '</div>'
            +   '<div class="item-row-sku">' + escHtml(sku) + '</div>'
            + '</td>'
            + '<td style="vertical-align:middle;border-right:1px solid #e9ecef;">' + locHtml + '</td>'
            + '<td class="text-center" style="border-right:1px solid #e9ecef;">'
            +   '<div class="input-group input-group-sm" style="max-width:130px;margin:auto;">'
            +     '<input type="number" name="items[' + rowCount + '][qty]" class="form-control text-center" min="1" value="1" required>'
            +     '<div class="input-group-append"><span class="input-group-text" style="font-size:11px;">' + escHtml(unit || 'pcs') + '</span></div>'
            +   '</div>'
            + '</td>'
            + '<td class="text-center pr-3">'
            +   '<button type="button" class="btn btn-xs btn-outline-danger btnRemoveItem"'
            +   ' data-item-id="' + id + '" data-row="' + rowCount + '" title="Hapus">'
            +   '<i class="fas fa-trash"></i></button>'
            + '</td>'
            + '</tr>';

        $('#itemRows').append(row);
    }

    /* ── Increment qty of existing row (scan duplicate) ─────────── */
    function incrementItemQty(id) {
        var rowNum   = selectedItems[id];
        var qtyInput = $('#item-row-' + rowNum + ' input[type="number"]');
        qtyInput.val(parseInt(qtyInput.val() || 1) + 1);
        var $row = $('#item-row-' + rowNum);
        $row.addClass('row-flash');
        setTimeout(function () { $row.removeClass('row-flash'); }, 600);
    }

    /* ── Scan feedback pill ──────────────────────────────────────── */
    function showScanFeedback(success, text) {
        var el = $('#scanFeedback');
        el.removeClass('sf-hidden sf-success sf-error').addClass(success ? 'sf-success' : 'sf-error');
        el.html((success ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + escHtml(text));
        clearTimeout(feedbackTimer);
        feedbackTimer = setTimeout(function () { el.addClass('sf-hidden'); }, 3000);
    }

    /* ── Process barcode via AJAX ────────────────────────────────── */
    function processScan(barcode) {
        $.ajax({
            url:  '{{ route("outbound.find-item") }}',
            type: 'GET',
            data: { barcode: barcode },
            success: function (res) {
                if (!res.success) {
                    showScanFeedback(false, res.message);
                    return;
                }
                var item = res.item;
                if (selectedItems[item.id] !== undefined) {
                    incrementItemQty(item.id);
                    showScanFeedback(true, item.name + ' — qty +1');
                } else {
                    addItemRow(item.id, item.name, item.sku, item.unit, item.locations);
                    showScanFeedback(true, item.name + ' ditambahkan');
                }
            },
            error: function (xhr) {
                showScanFeedback(false, xhr.responseJSON?.message ?? 'Item tidak ditemukan');
            }
        });
    }

    /* ── Scan input: Enter key ───────────────────────────────────── */
    $('#scanInput').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var barcode = $(this).val().trim();
            if (barcode) {
                processScan(barcode);
                $(this).val('');
            }
        }
    });

    /* ── Camera toggle ───────────────────────────────────────────── */
    $('#btnCameraReq').on('click', function () {
        if (cameraActive) stopCamera(); else startCamera();
    });

    function startCamera() {
        $('#readerReq').show();
        $('#btnCameraReq').html('<i class="fas fa-stop mr-1 text-danger"></i>Matikan Kamera');
        cameraActive = true;
        qrScanner = new Html5Qrcode('readerReq');
        qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 150 } },
            function (decoded) {
                processScan(decoded);
                qrScanner.pause();
                setTimeout(function () { if (qrScanner) qrScanner.resume(); }, 1500);
            },
            function () {}
        ).catch(function (err) {
            Swal.fire('Kamera Error', err, 'error');
            stopCamera();
        });
    }

    function stopCamera() {
        if (qrScanner) qrScanner.stop().catch(function(){}).finally(function () { qrScanner = null; });
        $('#readerReq').hide();
        $('#btnCameraReq').html('<i class="fas fa-camera mr-1"></i>Kamera');
        cameraActive = false;
        $('#scanInput').focus();
    }

    /* ── Global keydown → redirect to scan input (gun scanner) ──── */
    $(document).on('keydown', function (e) {
        if ($('.modal.show').length) return;
        var active = document.activeElement;
        var otherField = active && active !== document.body
            && active.id !== 'scanInput'
            && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT');
        if (otherField) return;
        if (['Shift','Control','Alt','Meta','Tab','Escape',
             'F1','F2','F3','F4','F5','F6','F7','F8','F9','F10','F11','F12'].includes(e.key)) return;
        if (!$('#scanInput').is(':focus')) $('#scanInput').focus();
    });

    /* ── Remove row ──────────────────────────────────────────────── */
    $(document).on('click', '.btnRemoveItem', function () {
        var itemId = $(this).data('item-id');
        var rowId  = $(this).data('row');
        delete selectedItems[itemId];
        $('#item-row-' + rowId).remove();
        if ($('#itemRows tr:visible').length === 0) $('#emptyRow').show();
        renumberRows();
        $('#scanInput').focus();
    });

    function renumberRows() {
        $('#itemRows tr[id^="item-row-"]').each(function (i) {
            $(this).find('td:first').text(i + 1);
        });
    }

    /* ── Manual search & pick ────────────────────────────────────── */
    $('#itemSearch').on('input', function () {
        var q = $(this).val().toLowerCase();
        $('.item-option').each(function () {
            var text = $(this).data('name').toLowerCase() + ' ' + $(this).data('sku').toLowerCase();
            $(this).toggle(text.indexOf(q) !== -1);
        });
    });

    $(document).on('click', '.btnPickItem', function () {
        var row = $(this).closest('.item-option');
        var id  = row.data('id');
        if (selectedItems[id] !== undefined) {
            Swal.fire('Item sudah ada', 'Item ini sudah ada di daftar.', 'warning');
            return;
        }
        addItemRow(id, row.data('name'), row.data('sku'), row.data('unit'), []);
        $('#modalAddItem').modal('hide');
    });

    $('#btnAddItem').on('click', function () {
        $('#itemSearch').val('');
        $('.item-option').show();
        $('#modalAddItem').modal('show');
    });

    /* ── Refocus scan input when modal closes ────────────────────── */
    $('#modalAddItem').on('hidden.bs.modal', function () {
        $('#scanInput').focus();
    });

    /* ── Form submit guard ───────────────────────────────────────── */
    var _forceSubmit = false;

    $('#formRequest').on('submit', function (e) {
        if (Object.keys(selectedItems).length === 0) {
            e.preventDefault();
            Swal.fire('Item kosong', 'Tambahkan minimal 1 item sebelum mengajukan request.', 'warning');
            return false;
        }

        if (_forceSubmit) {
            $('#btnSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Mengirim...');
            return true;
        }

        e.preventDefault();

        // Kumpulkan items dari form
        var items = [];
        $('#itemRows tr[id^="item-row-"]').each(function () {
            var itemId = $(this).data('item-id');
            var qty    = parseInt($(this).find('input[type="number"]').val()) || 1;
            items.push({ item_id: itemId, qty: qty });
        });

        $('#btnSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Memeriksa stok...');

        $.ajax({
            url:  '{{ route("outbound.requests.check-stock") }}',
            type: 'POST',
            data: JSON.stringify({ items: items, _token: '{{ csrf_token() }}' }),
            contentType: 'application/json',
            success: function (res) {
                $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Kirim');

                if (res.all_ok) {
                    _forceSubmit = true;
                    $('#formRequest').submit();
                    return;
                }

                // Ada item yang stoknya kurang — bangun tabel detail
                var insufficient = res.items.filter(function (i) { return !i.sufficient; });
                var tableRows = insufficient.map(function (i) {
                    var locText = i.locations.length
                        ? i.locations.map(function (l) { return '<code style="font-size:11px;margin-right:4px;">' + escHtml(l) + '</code>'; }).join('')
                        : '<span class="text-muted">–</span>';
                    return '<tr>'
                        + '<td style="text-align:left;padding:6px 8px;">'
                        +   '<div style="font-weight:600;font-size:13px;">' + escHtml(i.name) + '</div>'
                        +   '<div style="font-size:11px;color:#9ca3af;">' + escHtml(i.sku) + '</div>'
                        + '</td>'
                        + '<td style="text-align:center;padding:6px 8px;color:#dc2626;font-weight:700;">' + i.qty_req + ' ' + escHtml(i.unit) + '</td>'
                        + '<td style="text-align:center;padding:6px 8px;color:#059669;font-weight:700;">' + i.available + ' ' + escHtml(i.unit) + '</td>'
                        + '<td style="text-align:left;padding:6px 8px;">' + locText + '</td>'
                        + '</tr>';
                }).join('');

                var html = '<div style="font-size:13px;text-align:left;">'
                    + '<p style="margin-bottom:10px;color:#374151;">Item berikut <strong>stoknya tidak mencukupi</strong> permintaan:</p>'
                    + '<div style="overflow-x:auto;">'
                    + '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
                    + '<thead><tr style="background:#f3f4f6;">'
                    +   '<th style="padding:6px 8px;text-align:left;border-bottom:1px solid #e5e7eb;">Item</th>'
                    +   '<th style="padding:6px 8px;text-align:center;border-bottom:1px solid #e5e7eb;">Diminta</th>'
                    +   '<th style="padding:6px 8px;text-align:center;border-bottom:1px solid #e5e7eb;">Tersedia</th>'
                    +   '<th style="padding:6px 8px;text-align:left;border-bottom:1px solid #e5e7eb;">Lokasi Stok</th>'
                    + '</tr></thead>'
                    + '<tbody>' + tableRows + '</tbody>'
                    + '</table></div>'
                    + '</div>';

                Swal.fire({
                    title: '<span style="font-size:18px;">⚠️ Stok Tidak Cukup</span>',
                    html:  html,
                    icon:  'warning',
                    showCancelButton:   false,
                    confirmButtonText:  '<i class="fas fa-times mr-1"></i> Tutup',
                    confirmButtonColor: '#6b7280',
                    width: 600,
                }).then(function () {
                    $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Kirim');
                });
            },
            error: function () {
                $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Kirim');
                // Gagal cek stok → tetap lanjut submit (jangan blok user)
                _forceSubmit = true;
                $('#formRequest').submit();
            }
        });

        return false;
    });

    /* ── Helpers ─────────────────────────────────────────────────── */
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

});
</script>
@endpush
