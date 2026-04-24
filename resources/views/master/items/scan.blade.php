@extends('layouts.adminlte')
@section('title', 'Scan Barcode Sparepart')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-barcode text-primary mr-2"></i>Scan Barcode Sparepart
        </h5>
        <small class="text-muted">Scan dengan kamera, alat scanner, atau ketik kode SKU</small>
    </div>
    <a href="{{ route('master.items.index') }}" class="btn btn-sm btn-light border">
        <i class="fas fa-arrow-left mr-1"></i>Master Item
    </a>
</div>

{{-- Input + Pilihan Mode Scan --}}
<div class="row justify-content-center mb-3">
    <div class="col-md-7">
        <div class="card border-success shadow-sm">
            <div class="card-body py-3">

                {{-- Toggle Mode --}}
                <div class="d-flex justify-content-center mb-3" style="gap:8px;">
                    <button id="btnModeManual" class="btn btn-success btn-sm px-4" onclick="setMode('manual')">
                        <i class="fas fa-keyboard mr-1"></i>Ketik / Scanner Fisik
                    </button>
                    <button id="btnModeCamera" class="btn btn-outline-success btn-sm px-4" onclick="setMode('camera')">
                        <i class="fas fa-camera mr-1"></i>Scan Kamera
                    </button>
                </div>

                {{-- Mode Manual / Scanner Fisik --}}
                <div id="modeManual">
                    <div class="text-center mb-3">
                        <i class="fas fa-barcode fa-2x text-primary mb-1 d-block"></i>
                        <small class="text-muted">Arahkan scanner ke barcode, atau ketik kode secara manual</small>
                    </div>
                    <div class="input-group input-group-lg">
                        <input type="text" id="barcodeInput"
                            class="form-control text-center font-weight-bold"
                            placeholder="Scan atau ketik kode di sini..."
                            autofocus autocomplete="off"
                            style="letter-spacing:2px; font-size:18px;">
                        <div class="input-group-append">
                            <button class="btn btn-success px-4" id="btnLookup" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            <i class="fas fa-keyboard mr-1"></i>Tekan <kbd>Enter</kbd> setelah scan, atau klik tombol cari
                        </small>
                    </div>
                </div>

                {{-- Mode Kamera --}}
                <div id="modeCamera" style="display:none;">
                    <div class="text-center mb-2">
                        <small class="text-muted">Arahkan kamera ke barcode item — otomatis terdeteksi</small>
                    </div>

                    {{-- Viewfinder --}}
                    <div id="cameraContainer" style="position:relative; max-width:480px; margin:0 auto;">
                        <div id="reader" style="border-radius:8px; overflow:hidden;"></div>

                        {{-- Overlay garis tengah --}}
                        <div id="scanLine" style="
                            display:none;
                            position:absolute; top:50%; left:10%; right:10%;
                            height:2px; background:rgba(40,167,69,.8);
                            box-shadow:0 0 8px rgba(40,167,69,.9);
                            animation: scanAnim 1.5s ease-in-out infinite alternate;
                        "></div>
                    </div>

                    {{-- Status kamera --}}
                    <div id="cameraStatus" class="text-center mt-2">
                        <small class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Memulai kamera...</small>
                    </div>

                    <div class="text-center mt-2">
                        <button class="btn btn-sm btn-outline-danger" id="btnStopCamera" style="display:none;" onclick="stopCamera()">
                            <i class="fas fa-stop mr-1"></i>Stop Kamera
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Hasil Scan --}}
<div id="resultArea" style="display:none;">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-box-open mr-1"></i>Detail Sparepart</strong>
                    <span id="stockStatusBadge" class="badge px-3 py-1" style="font-size:13px;"></span>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Info Item --}}
                        <div class="col-md-6 mb-3">
                            <div class="mb-3">
                                <div class="text-muted small mb-1">Nama Barang</div>
                                <div class="font-weight-bold" style="font-size:17px;" id="itemName">—</div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <div class="text-muted small">SKU</div>
                                    <div class="font-weight-bold" id="itemSku">—</div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="text-muted small">ERP Code</div>
                                    <div id="itemErp">—</div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="text-muted small">Kategori</div>
                                    <span id="itemCategory" class="badge px-2" style="font-size:12px;">—</span>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="text-muted small">Satuan</div>
                                    <div class="font-weight-bold" id="itemUnit">—</div>
                                </div>
                                <div class="col-12 mb-2">
                                    <div class="text-muted small">Tipe Pergerakan</div>
                                    <div id="itemMovement">—</div>
                                </div>
                            </div>
                        </div>

                        {{-- Stok --}}
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3 text-center mb-3" id="stockBox">
                                <div class="text-muted small mb-1">Stok Saat Ini</div>
                                <h2 class="mb-0 font-weight-bold" id="itemStock">0</h2>
                                <div class="text-muted small" id="itemStockUnit"></div>
                            </div>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="text-muted small">Min Stock</div>
                                        <div class="font-weight-bold text-danger" id="itemMinStock">—</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="text-muted small">Reorder Point</div>
                                        <div class="font-weight-bold text-warning" id="itemReorder">—</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 d-flex" style="gap:8px;">
                                <a href="#" id="btnDetail" class="btn btn-sm btn-outline-primary flex-fill">
                                    <i class="fas fa-search mr-1"></i>Detail Stok
                                </a>
                                <a href="#" id="btnBarcode" class="btn btn-sm btn-outline-secondary flex-fill">
                                    <i class="fas fa-barcode mr-1"></i>Cetak Label
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Lokasi Cell --}}
                    <hr class="my-2">
                    <div class="font-weight-bold mb-2">
                        <i class="fas fa-map-marker-alt text-primary mr-1"></i>Lokasi di Gudang
                        <small class="text-muted font-weight-normal">(urutan FIFO)</small>
                    </div>
                    <div id="locationsBody"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Not Found --}}
<div id="notFoundArea" style="display:none;" class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-body text-center py-4">
                <i class="fas fa-times-circle fa-3x text-danger mb-3 d-block"></i>
                <h5 class="font-weight-bold text-danger" id="notFoundMsg">Barang tidak ditemukan</h5>
                <p class="text-muted mb-0">Pastikan kode barcode benar atau item sudah terdaftar.</p>
            </div>
        </div>
    </div>
</div>

{{-- Riwayat Scan --}}
<div id="historySection" style="display:none;" class="row justify-content-center mt-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-history mr-1"></i>Riwayat Scan Sesi Ini</strong>
                <button class="btn btn-xs btn-light border" id="btnClearHistory">Hapus</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Kode</th>
                            <th>Item</th>
                            <th class="text-center" width="100">Stok</th>
                            <th class="text-center" width="80">Waktu</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('styles')
<style>
@keyframes scanAnim {
    from { top: 35%; }
    to   { top: 65%; }
}
#reader { width: 100%; }
#reader video { width: 100% !important; border-radius: 8px; }
#reader img { display:none; } /* sembunyikan tombol bawaan html5-qrcode */
</style>
@endpush

@push('scripts')
{{-- Library html5-qrcode untuk scan via kamera --}}
<script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script>
const LOOKUP_URL = '{{ route("master.items.lookup") }}';
let scanHistory  = [];
let html5QrCode  = null;
let cameraActive = false;
let lastScanned  = '';       // debounce: hindari scan ganda
let scanCooldown = false;

// ─── MODE TOGGLE ─────────────────────────────────────────────────────────────
function setMode(mode) {
    if (mode === 'manual') {
        stopCamera();
        $('#modeManual').show();
        $('#modeCamera').hide();
        $('#btnModeManual').removeClass('btn-outline-success').addClass('btn-success active');
        $('#btnModeCamera').removeClass('btn-success active').addClass('btn-outline-success');
        setTimeout(() => $('#barcodeInput').focus(), 100);
    } else {
        $('#modeManual').hide();
        $('#modeCamera').show();
        $('#btnModeCamera').removeClass('btn-outline-success').addClass('btn-success active');
        $('#btnModeManual').removeClass('btn-success active').addClass('btn-outline-success');
        startCamera();
    }
}

// ─── KAMERA ───────────────────────────────────────────────────────────────────
function startCamera() {
    if (cameraActive) return;

    $('#cameraStatus').html('<small class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Memulai kamera...</small>');

    html5QrCode = new Html5Qrcode('reader');

    const config = {
        fps: 10,
        qrbox: { width: 280, height: 140 },
        formatsToSupport: [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.QR_CODE,
        ],
        aspectRatio: 1.7,
    };

    html5QrCode.start(
        { facingMode: 'environment' },   // kamera belakang (tablet/HP)
        config,
        function onScanSuccess(decodedText) {
            // Debounce: jangan proses kode yang sama dalam 3 detik
            if (scanCooldown || decodedText === lastScanned) return;
            lastScanned  = decodedText;
            scanCooldown = true;
            setTimeout(() => { scanCooldown = false; }, 3000);

            // Feedback visual + audio
            navigator.vibrate && navigator.vibrate(100);
            $('#cameraStatus').html(
                `<small class="text-success font-weight-bold">
                    <i class="fas fa-check-circle mr-1"></i>Terdeteksi: <strong>${decodedText}</strong>
                </small>`
            );

            // Isi input dan lookup
            $('#barcodeInput').val(decodedText);
            doLookup(decodedText);
        },
        function onScanError() { /* diabaikan, terus scan */ }
    ).then(() => {
        cameraActive = true;
        $('#scanLine').show();
        $('#btnStopCamera').show();
        $('#cameraStatus').html('<small class="text-success"><i class="fas fa-video mr-1"></i>Kamera aktif — arahkan ke barcode</small>');
    }).catch(err => {
        cameraActive = false;
        let msg = 'Kamera tidak bisa diakses.';
        if (err.toString().includes('NotAllowed') || err.toString().includes('Permission')) {
            msg = 'Akses kamera ditolak. Izinkan akses kamera di browser.';
        } else if (err.toString().includes('NotFound')) {
            msg = 'Tidak ada kamera ditemukan di perangkat ini.';
        }
        $('#cameraStatus').html(`<small class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>${msg}</small>`);
        $('#reader').html('');
    });
}

function stopCamera() {
    if (html5QrCode && cameraActive) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            cameraActive = false;
            $('#scanLine').hide();
            $('#btnStopCamera').hide();
            $('#reader').html('');
            $('#cameraStatus').html('<small class="text-muted">Kamera dimatikan.</small>');
        }).catch(() => {});
    }
}

// Matikan kamera saat navigasi keluar
window.addEventListener('beforeunload', stopCamera);

// ─── LOOKUP ───────────────────────────────────────────────────────────────────
function doLookup(code) {
    if (!code.trim()) return;
    $('#resultArea, #notFoundArea').hide();
    $('#btnLookup').html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    $.getJSON(LOOKUP_URL, { barcode: code.trim() }, function (res) {
        if (!res.found) {
            $('#notFoundMsg').text(res.message || 'Barang tidak ditemukan.');
            $('#notFoundArea').show();
            addHistory(code, null);
        } else {
            showResult(res.item, res.locations);
            addHistory(code, res.item);
        }
    }).fail(function () {
        $('#notFoundMsg').text('Terjadi kesalahan koneksi. Coba lagi.');
        $('#notFoundArea').show();
    }).always(function () {
        $('#btnLookup').html('<i class="fas fa-search"></i>').prop('disabled', false);
        if ($('#modeManual').is(':visible')) $('#barcodeInput').select();
    });
}

// ─── TAMPILKAN HASIL ──────────────────────────────────────────────────────────
function showResult(item, locations) {
    const statusMap = {
        empty:    { text: 'Stok Habis',    cls: 'badge-dark',    bg: '#343a40', color: '#fff' },
        critical: { text: 'Stok Kritis',   cls: 'badge-danger',  bg: '#f8d7da', color: '#721c24' },
        reorder:  { text: 'Perlu Reorder', cls: 'badge-warning', bg: '#fff3cd', color: '#856404' },
        ok:       { text: 'Stok Aman',     cls: 'badge-success', bg: '#d4edda', color: '#155724' },
    };
    const st  = statusMap[item.stock_status] || statusMap.ok;
    const mov = { fast_moving:'Fast Moving', slow_moving:'Slow Moving', non_moving:'Non Moving' };

    $('#itemName').text(item.name);
    $('#itemSku').text(item.sku);
    $('#itemErp').text(item.erp_code || '—');
    $('#itemCategory').text(item.category).css({ background: item.category_color, color:'#fff' });
    $('#itemUnit').text(item.unit);
    $('#itemMovement').html(`<span class="badge badge-secondary">${mov[item.movement_type] || item.movement_type}</span>`);
    $('#itemStock').text(item.total_stock.toLocaleString('id'));
    $('#itemStockUnit').text(item.unit);
    $('#itemMinStock').text(item.min_stock);
    $('#itemReorder').text(item.reorder_point);
    $('#stockBox').css({ background: st.bg, color: st.color, borderColor: st.color });
    $('#stockStatusBadge').text(st.text).attr('class', 'badge px-3 py-1 ' + st.cls);
    $('#btnDetail').attr('href', item.detail_url);
    $('#btnBarcode').attr('href', item.barcode_url);

    // Lokasi
    if (!locations || !locations.length) {
        $('#locationsBody').html(`
            <div class="text-center text-muted py-3">
                <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                Belum ada stok tersimpan di gudang.
            </div>`);
    } else {
        let rows = '';
        locations.forEach((loc, i) => {
            const fifo = i === 0 ? ' <span class="badge badge-success" style="font-size:9px;">FIFO</span>' : '';
            rows += `<tr class="${i === 0 ? 'table-success' : ''}">
                <td class="text-center font-weight-bold">${loc.cell_code}${fifo}</td>
                <td>${loc.rack_code}</td>
                <td>${loc.zone_name}</td>
                <td class="text-center font-weight-bold">${Number(loc.quantity).toLocaleString('id')} ${item.unit}</td>
                <td class="text-center"><small>${loc.inbound_date ?? '—'}</small></td>
                <td class="text-center"><small>${loc.expiry_date ?? '—'}</small></td>
            </tr>`;
        });
        $('#locationsBody').html(`
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="thead-light"><tr>
                        <th class="text-center" width="110">Cell</th>
                        <th width="80">Rak</th>
                        <th>Zona</th>
                        <th class="text-center" width="100">Qty</th>
                        <th class="text-center" width="110">Tgl Masuk</th>
                        <th class="text-center" width="110">Expired</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`);
    }

    $('#resultArea').show();
    $('html,body').animate({ scrollTop: $('#resultArea').offset().top - 80 }, 300);
}

// ─── RIWAYAT ──────────────────────────────────────────────────────────────────
function addHistory(code, item) {
    const now = new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
    scanHistory.unshift({ code, item, time: now });

    const tbody = $('#historyBody');
    tbody.empty();
    scanHistory.slice(0, 10).forEach((h, i) => {
        tbody.append(`<tr>
            <td class="text-center text-muted">${i + 1}</td>
            <td><code>${h.code}</code></td>
            <td>${h.item ? h.item.name : '<span class="text-danger">Tidak ditemukan</span>'}</td>
            <td class="text-center">${h.item ? h.item.total_stock.toLocaleString('id') + ' ' + h.item.unit : '—'}</td>
            <td class="text-center"><small>${h.time}</small></td>
        </tr>`);
    });
    $('#historySection').show();
}

// ─── EVENT HANDLERS ───────────────────────────────────────────────────────────
$('#btnLookup').on('click', () => doLookup($('#barcodeInput').val()));
$('#barcodeInput').on('keydown', function (e) {
    if (e.key === 'Enter') { doLookup($(this).val()); e.preventDefault(); }
});
$('#btnClearHistory').on('click', function () {
    scanHistory = [];
    $('#historyBody').empty();
    $('#historySection').hide();
});

$(function () { $('#barcodeInput').focus(); });
</script>
@endpush
