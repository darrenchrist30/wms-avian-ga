@extends('layouts.adminlte')
@section('title', 'Outbound — Kasir')

@push('styles')
<style>
/* ── Layout ── */
.pos-wrapper { display:flex; gap:16px; align-items:flex-start; }
.pos-scanner { flex:0 0 380px; }
.pos-cart    { flex:1; min-width:0; }

@media (max-width: 900px) {
    .pos-wrapper { flex-direction: column; }
    .pos-scanner { flex:none; width:100%; }
}

/* ── Scanner panel ── */
.scan-input-wrap { position:relative; }
.scan-input-wrap .scan-icon {
    position:absolute; left:12px; top:50%; transform:translateY(-50%);
    color:#6c757d; font-size:16px; pointer-events:none;
}
#barcodeInput {
    padding-left:38px; font-size:15px; font-weight:600;
    border:2px solid #dee2e6; border-radius:8px; height:48px;
    transition: border-color .15s;
}
#barcodeInput:focus { border-color:#dc3545; box-shadow:0 0 0 3px rgba(220,53,69,.15); }

.last-scan-card {
    border-radius:10px; border:1.5px solid #dee2e6;
    padding:14px 16px; background:#fff; min-height:90px;
    transition: border-color .2s, background .2s;
}
.last-scan-card.success { border-color:#28a745; background:#f0fff4; }
.last-scan-card.error   { border-color:#dc3545; background:#fff5f5; }

/* ── Camera ── */
#reader { width:100%; border-radius:8px; overflow:hidden; }
#reader video { border-radius:8px; }

/* ── Cart ── */
.cart-card {
    border-radius:10px; border:1.5px solid #dee2e6;
    overflow:hidden; background:#fff;
}
.cart-header {
    background:#fff; border-bottom:1.5px solid #dee2e6;
    padding:12px 16px; display:flex; align-items:center; justify-content:space-between;
}
.cart-body { padding:0; max-height:420px; overflow-y:auto; }
.cart-item {
    display:flex; align-items:center; padding:12px 16px;
    border-bottom:1px solid #f0f0f0; gap:12px;
}
.cart-item:last-child { border-bottom:none; }
.cart-item-info { flex:1; min-width:0; }
.cart-item-name { font-weight:700; font-size:13px; line-height:1.3; }
.cart-item-sku  { font-size:11px; color:#6c757d; margin-top:1px; }
.cart-item-qty  {
    display:flex; align-items:center; gap:4px; flex-shrink:0;
}
.qty-btn {
    width:30px; height:30px; border-radius:6px; border:1.5px solid #dee2e6;
    background:#f8f9fa; font-size:14px; font-weight:700; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    transition: background .1s, border-color .1s;
    color:#495057;
}
.qty-btn:hover { background:#e9ecef; border-color:#adb5bd; }
.qty-display {
    width:46px; text-align:center; font-weight:700; font-size:14px;
    border:1.5px solid #dee2e6; border-radius:6px; height:30px;
    padding:0 4px;
}
.qty-display:focus { outline:none; border-color:#dc3545; }
.btn-remove-item {
    width:28px; height:28px; border-radius:6px; border:none;
    background:transparent; color:#adb5bd; cursor:pointer; font-size:14px;
    display:flex; align-items:center; justify-content:center;
    transition: color .1s, background .1s;
}
.btn-remove-item:hover { color:#dc3545; background:#fff5f5; }

.cart-empty {
    padding:40px 20px; text-align:center; color:#adb5bd;
}
.cart-footer {
    border-top:1.5px solid #dee2e6; padding:14px 16px; background:#f8f9fa;
}
.cart-total-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.cart-total-label { font-size:12px; color:#6c757d; font-weight:600; }
.cart-total-val   { font-size:18px; font-weight:800; color:#212529; }

#btnProcess {
    width:100%; padding:14px; font-size:15px; font-weight:700;
    border-radius:8px; border:none; background:#dc3545; color:#fff;
    cursor:pointer; transition: background .15s, transform .1s;
    display:flex; align-items:center; justify-content:center; gap:8px;
}
#btnProcess:hover:not(:disabled) { background:#c82333; }
#btnProcess:disabled { background:#adb5bd; cursor:not-allowed; }
#btnProcess:active:not(:disabled) { transform:scale(.98); }

/* ── Warehouse select ── */
#warehouseSelect { font-weight:600; }

/* ── Preview Modal ── */
.preview-item-section { margin-bottom:16px; }
.preview-item-title {
    font-weight:700; font-size:13px; padding:8px 12px;
    background:#fff3cd; border-radius:6px; margin-bottom:6px;
    display:flex; justify-content:space-between;
}
.preview-table th { font-size:11px; }
.preview-table td { font-size:12px; vertical-align:middle; }
</style>
@endpush

@section('content')
<div class="container-fluid pb-4">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold">
                <i class="fas fa-sign-out-alt mr-2 text-danger"></i>Outbound — Kasir
            </h4>
            <small class="text-muted">
                <a href="{{ route('outbound.index') }}">Outbound</a>
                <i class="fas fa-chevron-right mx-1" style="font-size:9px;"></i>
                Scan &amp; Keluarkan Barang
            </small>
        </div>
        <a href="{{ route('outbound.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-history mr-1"></i> Riwayat
        </a>
    </div>

    <div class="pos-wrapper">

        {{-- ══════════════════════════════════
             LEFT — SCANNER PANEL
        ══════════════════════════════════ --}}
        <div class="pos-scanner">

            <div class="card shadow-sm mb-3" style="border-radius:10px;">
                <div class="card-header py-2 px-3" style="border-radius:10px 10px 0 0;">
                    <span class="font-weight-bold" style="font-size:13px;">
                        <i class="fas fa-barcode mr-1 text-danger"></i> Scan Barcode Item
                    </span>
                </div>
                <div class="card-body p-3">

                    {{-- Barcode text input (auto-focus for gun scanner) --}}
                    <div class="scan-input-wrap mb-3">
                        <i class="fas fa-barcode scan-icon"></i>
                        <input type="text" id="barcodeInput" class="form-control"
                               placeholder="Scan barcode / ketik SKU…" autocomplete="off" autofocus>
                    </div>

                    {{-- Camera toggle --}}
                    <div class="mb-3">
                        <button id="btnCamera" class="btn btn-sm btn-outline-secondary btn-block" type="button">
                            <i class="fas fa-camera mr-1"></i> Aktifkan Kamera
                        </button>
                        <div id="reader" class="mt-2" style="display:none;"></div>
                    </div>

                    {{-- Last scanned feedback --}}
                    <div class="last-scan-card" id="lastScanCard">
                        <div class="text-center text-muted" id="lastScanEmpty" style="padding:10px 0;">
                            <i class="fas fa-qrcode fa-2x mb-1" style="opacity:.25;"></i>
                            <div style="font-size:12px;">Menunggu scan…</div>
                        </div>
                        <div id="lastScanResult" style="display:none;">
                            <div style="font-size:10px;font-weight:600;color:#6c757d;letter-spacing:.5px;margin-bottom:4px;">TERAKHIR DISCAN</div>
                            <div class="font-weight-bold" id="lsName" style="font-size:13px;line-height:1.3;"></div>
                            <div style="font-size:11px;color:#6c757d;" id="lsSku"></div>
                            <div style="font-size:11px;margin-top:4px;" id="lsStock"></div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Gudang selector --}}
            <div class="card shadow-sm" style="border-radius:10px;">
                <div class="card-body p-3">
                    <label class="mb-1" style="font-size:12px;font-weight:700;color:#555;">
                        <i class="fas fa-warehouse mr-1"></i> Gudang Asal
                    </label>
                    @if ($warehouses->count() === 1)
                        <input type="text" class="form-control" readonly
                               value="{{ $warehouses->first()->name }} ({{ $warehouses->first()->code }})">
                        <input type="hidden" id="warehouseSelect" value="{{ $warehouses->first()->id }}">
                    @else
                        <select id="warehouseSelect" class="form-control">
                            <option value="">-- Pilih Gudang --</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

        </div>

        {{-- ══════════════════════════════════
             RIGHT — CART PANEL
        ══════════════════════════════════ --}}
        <div class="pos-cart">
            <div class="cart-card shadow-sm">

                <div class="cart-header">
                    <span class="font-weight-bold" style="font-size:13px;">
                        <i class="fas fa-shopping-cart mr-1 text-danger"></i>
                        Keranjang Outbound
                    </span>
                    <span class="badge badge-danger" id="cartCountBadge" style="display:none;font-size:11px;"></span>
                </div>

                <div class="cart-body" id="cartBody">
                    <div class="cart-empty" id="cartEmpty">
                        <i class="fas fa-sign-out-alt fa-3x mb-2" style="opacity:.15;"></i>
                        <div style="font-size:13px;">Keranjang kosong</div>
                        <div style="font-size:11px;margin-top:4px;">Scan barcode item untuk menambahkan</div>
                    </div>
                </div>

                <div class="cart-footer" id="cartFooter" style="display:none;">
                    <div class="cart-total-row">
                        <div>
                            <div class="cart-total-label">TOTAL ITEM JENIS</div>
                            <div class="cart-total-val" id="totalKind">0</div>
                        </div>
                        <div class="text-right">
                            <div class="cart-total-label">TOTAL QTY</div>
                            <div class="cart-total-val text-danger" id="totalQty">0</div>
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <input type="text" id="outboundNotes" class="form-control form-control-sm"
                               placeholder="Catatan outbound (opsional)…" maxlength="500">
                    </div>

                    <button id="btnProcess" disabled>
                        <i class="fas fa-sign-out-alt"></i>
                        <span id="btnProcessLabel">PROSES OUTBOUND</span>
                    </button>
                </div>

            </div>
        </div>

    </div>

</div>

{{-- ══════════════════════════════════════════════════
     MODAL — FIFO PREVIEW
══════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalPreview" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#fff3cd;border-bottom:1.5px solid #ffc107;">
                <h6 class="modal-title font-weight-bold mb-0">
                    <i class="fas fa-clipboard-list mr-1 text-warning"></i>
                    Preview FIFO — Rencana Pengambilan
                </h6>
            </div>
            <div class="modal-body py-3" id="previewBody">
                {{-- filled by JS --}}
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali Edit
                </button>
                <button type="button" id="btnConfirmFinal" class="btn btn-danger btn-sm">
                    <i class="fas fa-check mr-1"></i> Konfirmasi Outbound
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════
     MODAL — SUCCESS
══════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalSuccess" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h5 class="font-weight-bold">Outbound Berhasil!</h5>
                <p class="text-muted" id="successSummary"></p>
                <div id="successDetail" class="text-left mb-3"></div>
            </div>
            <div class="modal-footer py-2 justify-content-center">
                <button type="button" class="btn btn-danger" id="btnNewOutbound">
                    <i class="fas fa-plus mr-1"></i> Outbound Baru
                </button>
                <a href="{{ route('outbound.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-history mr-1"></i> Lihat Riwayat
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
$(function () {

    /* ─────────────────────────────────────────────────────────────────
       STATE
    ───────────────────────────────────────────────────────────────── */
    const cart = {};   // { item_id: { id, name, sku, merk, available_stock, qty } }
    let cameraActive = false;
    let qrScanner    = null;

    /* ─────────────────────────────────────────────────────────────────
       BARCODE SCANNER — keyboard / gun
    ───────────────────────────────────────────────────────────────── */
    $('#barcodeInput').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const barcode = $(this).val().trim();
            if (barcode) processBarcode(barcode);
            $(this).val('');
        }
    });

    /* ─────────────────────────────────────────────────────────────────
       CAMERA SCANNER — html5-qrcode
    ───────────────────────────────────────────────────────────────── */
    $('#btnCamera').on('click', function () {
        if (cameraActive) {
            stopCamera();
        } else {
            startCamera();
        }
    });

    function startCamera() {
        $('#reader').show();
        $('#btnCamera').html('<i class="fas fa-stop mr-1 text-danger"></i> Matikan Kamera');
        cameraActive = true;

        qrScanner = new Html5Qrcode('reader');
        qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 150 } },
            function (decoded) {
                processBarcode(decoded);
                // brief pause so same code isn't double-scanned
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
        if (qrScanner) {
            qrScanner.stop().catch(function(){}).finally(function () { qrScanner = null; });
        }
        $('#reader').hide();
        $('#btnCamera').html('<i class="fas fa-camera mr-1"></i> Aktifkan Kamera');
        cameraActive = false;
        $('#barcodeInput').focus();
    }

    /* ─────────────────────────────────────────────────────────────────
       PROCESS BARCODE — lookup item then add to cart
    ───────────────────────────────────────────────────────────────── */
    function processBarcode(barcode) {
        $.ajax({
            url:      '{{ route("outbound.find-item") }}',
            type:     'GET',
            data:     { barcode: barcode },
            success:  function (res) {
                if (!res.success) {
                    showScanFeedback(null, res.message);
                    return;
                }
                showScanFeedback(res.item);
                addToCart(res.item);
            },
            error: function (xhr) {
                showScanFeedback(null, xhr.responseJSON?.message ?? 'Item tidak ditemukan.');
            }
        });
    }

    function showScanFeedback(item, errMsg) {
        const card = $('#lastScanCard');
        $('#lastScanEmpty').hide();
        $('#lastScanResult').show();

        if (item) {
            card.removeClass('error').addClass('success');
            $('#lsName').text(item.name);
            $('#lsSku').text('SKU: ' + item.sku + (item.merk ? ' · ' + item.merk : ''));
            $('#lsStock').html(
                item.available_stock > 0
                    ? '<i class="fas fa-check-circle text-success mr-1"></i><span class="text-success font-weight-bold">Stok: ' + item.available_stock + ' unit</span>'
                    : '<i class="fas fa-exclamation-triangle text-warning mr-1"></i><span class="text-warning">Stok habis</span>'
            );
        } else {
            card.removeClass('success').addClass('error');
            $('#lsName').html('<span class="text-danger"><i class="fas fa-times-circle mr-1"></i>' + errMsg + '</span>');
            $('#lsSku').text('');
            $('#lsStock').text('');
        }
    }

    /* ─────────────────────────────────────────────────────────────────
       CART MANAGEMENT
    ───────────────────────────────────────────────────────────────── */
    function addToCart(item) {
        if (cart[item.id]) {
            cart[item.id].qty += 1;
        } else {
            cart[item.id] = { ...item, qty: 1 };
        }
        renderCart();
        $('#barcodeInput').focus();
    }

    function removeFromCart(itemId) {
        delete cart[itemId];
        renderCart();
    }

    function setQty(itemId, qty) {
        qty = parseInt(qty);
        if (!qty || qty < 1) qty = 1;
        if (cart[itemId]) {
            cart[itemId].qty = qty;
            renderCart();
        }
    }

    function renderCart() {
        const ids = Object.keys(cart);
        if (ids.length === 0) {
            $('#cartEmpty').show();
            $('#cartFooter').hide();
            $('#cartCountBadge').hide();
            $('#btnProcess').prop('disabled', true);
            updateTotals();
            return;
        }

        $('#cartEmpty').hide();
        $('#cartFooter').show();

        // Render items
        let html = '';
        ids.forEach(function (id) {
            const item = cart[id];
            html += `
            <div class="cart-item" data-id="${item.id}">
                <div class="cart-item-info">
                    <div class="cart-item-name">${escHtml(item.name)}</div>
                    <div class="cart-item-sku">${escHtml(item.sku)}${item.merk ? ' · ' + escHtml(item.merk) : ''}</div>
                </div>
                <div class="cart-item-qty">
                    <button class="qty-btn btn-minus" data-id="${item.id}">−</button>
                    <input type="number" class="qty-display qty-input" data-id="${item.id}"
                           value="${item.qty}" min="1" max="${item.available_stock}">
                    <button class="qty-btn btn-plus" data-id="${item.id}">+</button>
                </div>
                <button class="btn-remove-item" data-id="${item.id}" title="Hapus dari keranjang">
                    <i class="fas fa-times"></i>
                </button>
            </div>`;
        });

        // Replace only item rows (keep empty/footer intact)
        // We rebuild the body html
        const bodyHtml = '<div id="cartEmpty" style="display:none;" class="cart-empty"></div>' + html;
        // Actually simpler: just clear & refill
        $('#cartBody').html('<div id="cartEmpty" style="display:none;"></div>' + html);

        updateTotals();
        bindCartEvents();
    }

    function updateTotals() {
        const ids   = Object.keys(cart);
        const kinds = ids.length;
        const qty   = ids.reduce(function (sum, id) { return sum + cart[id].qty; }, 0);

        $('#totalKind').text(kinds);
        $('#totalQty').text(qty);
        $('#cartCountBadge').text(kinds + ' item').toggle(kinds > 0);
        $('#btnProcessLabel').text('PROSES OUTBOUND (' + kinds + ' item, ' + qty + ' unit)');
        $('#btnProcess').prop('disabled', kinds === 0);
    }

    function bindCartEvents() {
        // +/- buttons
        $('#cartBody').off('click', '.btn-minus').on('click', '.btn-minus', function () {
            const id = $(this).data('id');
            cart[id].qty = Math.max(1, cart[id].qty - 1);
            renderCart();
        });
        $('#cartBody').off('click', '.btn-plus').on('click', '.btn-plus', function () {
            const id = $(this).data('id');
            cart[id].qty += 1;
            renderCart();
        });
        // qty direct input
        $('#cartBody').off('change', '.qty-input').on('change', '.qty-input', function () {
            setQty($(this).data('id'), $(this).val());
        });
        // remove
        $('#cartBody').off('click', '.btn-remove-item').on('click', '.btn-remove-item', function () {
            removeFromCart($(this).data('id'));
        });
    }

    /* ─────────────────────────────────────────────────────────────────
       PROCESS — batch preview then confirm
    ───────────────────────────────────────────────────────────────── */
    $('#btnProcess').on('click', function () {
        const warehouseId = $('#warehouseSelect').val();
        if (!warehouseId) {
            Swal.fire('Pilih Gudang', 'Tentukan gudang asal pengambilan barang.', 'warning');
            return;
        }

        const cartArr = buildCartArray();
        if (!cartArr.length) return;

        Swal.fire({ title: 'Menghitung FIFO...', allowOutsideClick: false, allowEscapeKey: false, didOpen: function () { Swal.showLoading(); } });

        $.ajax({
            url:  '{{ route("outbound.batch-preview") }}',
            type: 'POST',
            data: JSON.stringify({ warehouse_id: warehouseId, cart: cartArr }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function (res) {
                Swal.close();
                if (!res.success) {
                    Swal.fire('Stok Tidak Cukup', res.message, 'error');
                    return;
                }
                renderPreviewModal(res.previews);
                $('#modalPreview').modal('show');
            },
            error: function (xhr) {
                Swal.close();
                Swal.fire('Error', xhr.responseJSON?.message ?? 'Terjadi kesalahan.', 'error');
            }
        });
    });

    /* ─────────────────────────────────────────────────────────────────
       PREVIEW MODAL
    ───────────────────────────────────────────────────────────────── */
    function renderPreviewModal(previews) {
        let html = '';
        previews.forEach(function (p) {
            let rows = '';
            p.picks.forEach(function (pick, i) {
                rows += `<tr>
                    <td class="text-center">${i + 1}</td>
                    <td><strong>${escHtml(pick.cell_code)}</strong></td>
                    <td><span class="text-muted">${escHtml(pick.zone_code)} / Rak ${escHtml(pick.rack_code)}</span></td>
                    <td><span class="badge badge-light border">${escHtml(pick.inbound_date)}</span></td>
                    <td class="text-center">${pick.available_qty}</td>
                    <td class="text-center text-danger font-weight-bold">${pick.take_qty}</td>
                </tr>`;
            });
            html += `
            <div class="preview-item-section">
                <div class="preview-item-title">
                    <span><i class="fas fa-box mr-1"></i>${escHtml(p.item_name)}</span>
                    <span class="badge badge-danger">${p.total_qty} unit</span>
                </div>
                <table class="table table-sm table-bordered preview-table mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="36" class="text-center">#</th>
                            <th>Cell</th>
                            <th>Zona / Rak</th>
                            <th>Inbound Date</th>
                            <th class="text-center" width="70">Tersedia</th>
                            <th class="text-center text-danger" width="60">Ambil</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        });

        const totalItems = previews.length;
        const totalQty   = previews.reduce(function (s, p) { return s + p.total_qty; }, 0);
        html = `<div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Periksa rencana pengambilan berikut. Tekan <strong>Konfirmasi Outbound</strong> untuk menyimpan.
                    <span class="float-right font-weight-bold">${totalItems} item · ${totalQty} unit</span>
                </div>` + html;

        $('#previewBody').html(html);
    }

    /* ─────────────────────────────────────────────────────────────────
       FINAL CONFIRM
    ───────────────────────────────────────────────────────────────── */
    $('#btnConfirmFinal').on('click', function () {
        const warehouseId = $('#warehouseSelect').val();
        const cartArr     = buildCartArray();
        const notes       = $('#outboundNotes').val();

        $('#btnConfirmFinal').prop('disabled', true);
        Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, allowEscapeKey: false, didOpen: function () { Swal.showLoading(); } });

        $.ajax({
            url:  '{{ route("outbound.batch-confirm") }}',
            type: 'POST',
            data: JSON.stringify({ warehouse_id: warehouseId, cart: cartArr, notes: notes }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function (res) {
                Swal.close();
                $('#modalPreview').modal('hide');
                if (!res.success) {
                    Swal.fire('Gagal', res.message, 'error');
                    $('#btnConfirmFinal').prop('disabled', false);
                    return;
                }
                renderSuccessModal(res);
                $('#modalSuccess').modal('show');
            },
            error: function (xhr) {
                Swal.close();
                Swal.fire('Gagal', xhr.responseJSON?.message ?? 'Terjadi kesalahan.', 'error');
                $('#btnConfirmFinal').prop('disabled', false);
            }
        });
    });

    /* ─────────────────────────────────────────────────────────────────
       SUCCESS MODAL
    ───────────────────────────────────────────────────────────────── */
    function renderSuccessModal(res) {
        $('#successSummary').text(res.total_items + ' jenis item · ' + res.total_qty + ' unit berhasil dikeluarkan. Movement outbound telah dicatat.');

        let html = '<ul class="list-unstyled mb-0">';
        res.results.forEach(function (r) {
            html += `<li class="mb-1">
                <i class="fas fa-check-circle text-success mr-1"></i>
                <strong>${escHtml(r.item_name)}</strong>
                <span class="text-muted"> — ${r.quantity} unit</span>
            </li>`;
        });
        html += '</ul>';
        $('#successDetail').html(html);
    }

    $('#btnNewOutbound').on('click', function () {
        $('#modalSuccess').modal('hide');
        resetAll();
    });

    /* ─────────────────────────────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────────────────────────────── */
    function buildCartArray() {
        return Object.values(cart).map(function (item) {
            return { item_id: item.id, quantity: item.qty };
        });
    }

    function resetAll() {
        // Clear cart
        Object.keys(cart).forEach(function (k) { delete cart[k]; });
        renderCart();
        // Reset feedback
        $('#lastScanCard').removeClass('success error');
        $('#lastScanEmpty').show();
        $('#lastScanResult').hide();
        // Reset confirm button
        $('#btnConfirmFinal').prop('disabled', false);
        // Focus scanner
        $('#barcodeInput').val('').focus();
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Auto-focus on scanner input when modal closes
    $('#modalPreview, #modalSuccess').on('hidden.bs.modal', function () {
        $('#barcodeInput').focus();
    });

    // ── Global keydown: redirect any keystroke to barcode input ────────────
    // When a scanner gun fires, it sends keystrokes to whatever has focus.
    // This ensures those keystrokes always land in the barcode field, even
    // if the operator accidentally clicked elsewhere on the page.
    $(document).on('keydown', function (e) {
        // Don't steal focus while a modal is open
        if ($('.modal.show').length) return;

        const active = document.activeElement;
        // Don't intercept if operator is actively typing in another field
        // (qty inputs, notes, warehouse select, buttons)
        const otherInteractive = active && active !== document.body
            && active.id !== 'barcodeInput'
            && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT');
        if (otherInteractive) return;

        // Ignore modifier-only keys and function keys
        if (['Shift','Control','Alt','Meta','Tab','Escape','F1','F2','F3','F4',
             'F5','F6','F7','F8','F9','F10','F11','F12'].includes(e.key)) return;

        // Redirect — focus input so the character lands there
        if (!$('#barcodeInput').is(':focus')) {
            $('#barcodeInput').focus();
        }
    });

    // Initial render
    renderCart();

});
</script>
@endpush
