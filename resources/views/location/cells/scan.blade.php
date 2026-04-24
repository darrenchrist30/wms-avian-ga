@extends('layouts.adminlte')
@section('title', 'Scan QR Cell')

@section('content')
<div class="container-fluid pb-4">

{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-qrcode text-success mr-2"></i>Scan QR Cell Gudang
        </h5>
        <small class="text-muted">Scan QR pada cell rak untuk melihat isi dan status stok</small>
    </div>
    <a href="{{ route('location.cells.index') }}" class="btn btn-sm btn-light border">
        <i class="fas fa-arrow-left mr-1"></i>Daftar Cell
    </a>
</div>

{{-- Petunjuk singkat --}}
<div class="row justify-content-center mb-2">
    <div class="col-md-7 col-lg-6">
        <div class="alert alert-success py-2 mb-0 d-flex align-items-center" style="gap:10px; border-radius:8px;">
            <i class="fas fa-info-circle fa-lg"></i>
            <div style="font-size:13px;">
                <strong>Cara scan:</strong> Arahkan scanner ke <strong>label QR/barcode yang tertempel di cell rak fisik</strong>.
                Data stok otomatis tampil dari database terkini.
            </div>
        </div>
    </div>
</div>

{{-- Input Scanner --}}
<div class="row justify-content-center mb-3">
    <div class="col-md-7 col-lg-6">
        <div class="card border-success shadow-sm">
            <div class="card-body py-3">

                {{-- Toggle Mode --}}
                <div class="d-flex justify-content-center mb-3" style="gap:8px;">
                    <button id="btnModeManual" class="btn btn-success btn-sm px-3" onclick="setMode('manual')">
                        <i class="fas fa-barcode mr-1"></i>Pistol Scanner / Ketik
                        <small class="d-block" style="font-size:10px; opacity:.85; font-weight:400;">
                            Scan ke label di rak → otomatis masuk
                        </small>
                    </button>
                    <button id="btnModeCamera" class="btn btn-outline-success btn-sm px-3" onclick="setMode('camera')">
                        <i class="fas fa-camera mr-1"></i>Kamera Tablet/HP
                        <small class="d-block" style="font-size:10px; opacity:.85; font-weight:400;">
                            Arahkan kamera ke QR di rak
                        </small>
                    </button>
                </div>

                {{-- Mode Manual --}}
                <div id="modeManual">
                    <div class="text-center mb-3 p-2 rounded" style="background:#f8fff8; border:1px dashed #28a745;">
                        <i class="fas fa-barcode fa-2x text-success mb-1 d-block"></i>
                        <div class="font-weight-bold text-success" style="font-size:13px;">
                            Arahkan pistol scanner ke label di cell rak
                        </div>
                        <small class="text-muted">Kode otomatis masuk ke bawah ini & langsung dicari</small>
                    </div>
                    <div class="input-group input-group-lg">
                        <input type="text" id="cellInput"
                            class="form-control text-center font-weight-bold"
                            placeholder="Contoh: 1-A  atau  2-C"
                            autofocus autocomplete="off"
                            style="letter-spacing:3px; font-size:22px;">
                        <div class="input-group-append">
                            <button class="btn btn-success px-4" id="btnLookup" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            <i class="fas fa-keyboard mr-1"></i>Atau ketik manual, tekan <kbd>Enter</kbd> / klik tombol cari
                        </small>
                    </div>
                </div>

                {{-- Mode Kamera --}}
                <div id="modeCamera" style="display:none;">
                    <div class="text-center mb-2 p-2 rounded" style="background:#f8fff8; border:1px dashed #28a745;">
                        <i class="fas fa-camera fa-lg text-success mr-1"></i>
                        <span class="font-weight-bold text-success" style="font-size:13px;">Arahkan kamera ke QR code pada label cell di rak</span>
                        <br><small class="text-muted">Otomatis terdeteksi saat QR code masuk ke kotak bidik</small>
                    </div>
                    <div id="cameraContainer" style="position:relative; max-width:480px; margin:0 auto;">
                        <div id="reader" style="border-radius:8px; overflow:hidden;"></div>
                        <div id="scanLine" style="
                            display:none; position:absolute; top:50%; left:10%; right:10%;
                            height:2px; background:rgba(40,167,69,.85);
                            box-shadow:0 0 8px rgba(40,167,69,.9);
                            animation: scanAnim 1.5s ease-in-out infinite alternate;
                        "></div>
                    </div>
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
        <div class="col-md-10 col-lg-9">

            {{-- Card Info Cell --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <strong><i class="fas fa-map-marker-alt mr-1 text-primary"></i>Lokasi Cell</strong>
                        <span id="cellCode" class="ml-2 font-weight-bold" style="font-size:17px;"></span>
                    </div>
                    <span id="cellStatusBadge" class="badge px-3 py-1" style="font-size:13px;"></span>
                </div>
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td class="text-muted py-1" style="width:90px;">Rak</td>
                                    <td class="font-weight-bold py-1" id="cellRack">—</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-1">Level</td>
                                    <td class="font-weight-bold py-1" id="cellLevel">—</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-1">Zona</td>
                                    <td class="py-1" id="cellZone">—</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-1">Gudang</td>
                                    <td class="py-1" id="cellWarehouse">—</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-4 text-center border-left border-right">
                            {{-- Capacity Ring --}}
                            <div class="mb-1">
                                <div style="position:relative; display:inline-block; width:110px; height:110px;">
                                    <canvas id="capacityCanvas" width="110" height="110"></canvas>
                                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                                        <div id="utilPct" style="font-size:22px;font-weight:900;line-height:1;"></div>
                                        <div style="font-size:10px;color:#6c757d;">terisi</div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span id="totalQtyBadge" class="font-weight-bold" style="font-size:15px;"></span>
                                <br><small class="text-muted" id="capacityText"></small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-2">
                                <div class="text-muted small">Total SKU</div>
                                <div class="font-weight-bold" style="font-size:22px;" id="totalSkus">0</div>
                            </div>
                            <a href="#" id="btnStockDetail" class="btn btn-sm btn-outline-info btn-block">
                                <i class="fas fa-search-location mr-1"></i>Detail Stok
                            </a>
                            <a href="#" id="btnQrLabel" class="btn btn-sm btn-outline-secondary btn-block mt-1">
                                <i class="fas fa-qrcode mr-1"></i>Cetak Label
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Daftar Isi Cell (FIFO) --}}
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>
                        <i class="fas fa-layer-group mr-1 text-warning"></i>
                        Isi Cell — Urutan FIFO
                        <small class="text-muted font-weight-normal ml-1">(barang terlama = ambil pertama)</small>
                    </strong>
                    <span id="fifoCount" class="badge badge-warning"></span>
                </div>
                <div class="card-body p-0" id="stocksBody">
                    {{-- filled by JS --}}
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Cell Kosong --}}
<div id="emptyArea" style="display:none;" class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-secondary">
            <div class="card-body text-center py-4">
                <i class="fas fa-inbox fa-3x text-secondary mb-3 d-block" style="opacity:.4;"></i>
                <h5 class="font-weight-bold" id="emptyCellCode">Cell Kosong</h5>
                <p class="text-muted mb-1">Tidak ada stok di cell ini saat ini.</p>
                <small class="text-muted" id="emptyCellLocation"></small>
                <div class="mt-3">
                    <a href="#" id="btnEmptyQrLabel" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-qrcode mr-1"></i>Cetak Label QR
                    </a>
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
                <h5 class="font-weight-bold text-danger" id="notFoundMsg">Cell tidak ditemukan</h5>
                <p class="text-muted mb-0">Pastikan kode QR benar atau cell sudah terdaftar di sistem.</p>
            </div>
        </div>
    </div>
</div>

{{-- Riwayat Scan --}}
<div id="historySection" style="display:none;" class="row justify-content-center mt-3">
    <div class="col-md-10 col-lg-9">
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
                            <th width="90">Kode Cell</th>
                            <th>Lokasi</th>
                            <th class="text-center" width="80">SKU</th>
                            <th class="text-center" width="90">Qty Terisi</th>
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
@keyframes scanAnim { from { top: 35%; } to { top: 65%; } }
#reader { width: 100%; }
#reader video { width: 100% !important; border-radius: 8px; }
#reader img { display: none; }

.fifo-item { border-left: 4px solid #dee2e6; transition: background .15s; }
.fifo-item:hover { background: #f8f9fa; }
.fifo-item.fifo-first { border-left-color: #dc3545; background: #fff5f5; }
.fifo-item.fifo-second { border-left-color: #fd7e14; }
.fifo-item.fifo-normal { border-left-color: #dee2e6; }

.days-badge { font-size: 11px; border-radius: 20px; }
.days-old   { background: #fff3cd; color: #856404; }
.days-medium{ background: #fde8d8; color: #a0522d; }
.days-new   { background: #d4edda; color: #155724; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script>
const LOOKUP_URL = '{{ route("location.cells.lookup") }}';
let scanHistory  = [];
let html5QrCode  = null;
let cameraActive = false;
let lastScanned  = '';
let scanCooldown = false;

// ─── MODE TOGGLE ─────────────────────────────────────────────────────────────
function setMode(mode) {
    if (mode === 'manual') {
        stopCamera();
        $('#modeManual').show(); $('#modeCamera').hide();
        $('#btnModeManual').removeClass('btn-outline-success').addClass('btn-success active');
        $('#btnModeCamera').removeClass('btn-success active').addClass('btn-outline-success');
        setTimeout(() => $('#cellInput').focus(), 100);
    } else {
        $('#modeManual').hide(); $('#modeCamera').show();
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
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 },
          formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
          aspectRatio: 1.0 },
        function onScanSuccess(decodedText) {
            if (scanCooldown || decodedText === lastScanned) return;
            lastScanned = decodedText; scanCooldown = true;
            setTimeout(() => { scanCooldown = false; }, 3000);
            navigator.vibrate && navigator.vibrate([100, 50, 100]);
            $('#cameraStatus').html(
                `<small class="text-success font-weight-bold">
                    <i class="fas fa-check-circle mr-1"></i>Terdeteksi: <strong>${decodedText}</strong>
                </small>`);
            $('#cellInput').val(decodedText);
            doLookup(decodedText);
        },
        function () {}
    ).then(() => {
        cameraActive = true;
        $('#scanLine').show(); $('#btnStopCamera').show();
        $('#cameraStatus').html('<small class="text-success"><i class="fas fa-video mr-1"></i>Kamera aktif — arahkan ke QR code pada cell</small>');
    }).catch(err => {
        cameraActive = false;
        let msg = 'Kamera tidak bisa diakses.';
        if (err.toString().includes('NotAllowed') || err.toString().includes('Permission'))
            msg = 'Akses kamera ditolak. Izinkan akses kamera di browser.';
        else if (err.toString().includes('NotFound'))
            msg = 'Tidak ada kamera ditemukan di perangkat ini.';
        $('#cameraStatus').html(`<small class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>${msg}</small>`);
    });
}

function stopCamera() {
    if (html5QrCode && cameraActive) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear(); cameraActive = false;
            $('#scanLine').hide(); $('#btnStopCamera').hide();
            $('#reader').html('');
            $('#cameraStatus').html('<small class="text-muted">Kamera dimatikan.</small>');
        }).catch(() => {});
    }
}
window.addEventListener('beforeunload', stopCamera);

// ─── LOOKUP ───────────────────────────────────────────────────────────────────
function doLookup(code) {
    if (!code.trim()) return;
    $('#resultArea, #emptyArea, #notFoundArea').hide();
    $('#btnLookup').html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    $.getJSON(LOOKUP_URL, { code: code.trim() }, function (res) {
        if (!res.found) {
            $('#notFoundMsg').text(res.message || 'Cell tidak ditemukan.');
            $('#notFoundArea').show();
            addHistory(code, null);
        } else if (res.cell.total_qty === 0) {
            showEmpty(res.cell);
            addHistory(code, res.cell);
        } else {
            showResult(res.cell, res.stocks);
            addHistory(code, res.cell);
        }
    }).fail(function () {
        $('#notFoundMsg').text('Terjadi kesalahan koneksi. Coba lagi.');
        $('#notFoundArea').show();
    }).always(function () {
        $('#btnLookup').html('<i class="fas fa-search"></i>').prop('disabled', false);
        if ($('#modeManual').is(':visible')) $('#cellInput').select();
    });
}

// ─── TAMPILKAN CELL KOSONG ────────────────────────────────────────────────────
function showEmpty(cell) {
    const statusMap = { available:'Tersedia',partial:'Sebagian',full:'Penuh',blocked:'Terblokir',reserved:'Reservasi' };
    $('#emptyCellCode').text('Cell ' + cell.code + ' — Kosong');
    $('#emptyCellLocation').text('Rak ' + cell.rack + ' | Level ' + cell.level + ' | ' + cell.zone);
    $('#btnEmptyQrLabel').attr('href', cell.qr_label_url);
    $('#emptyArea').show();
    $('html,body').animate({ scrollTop: $('#emptyArea').offset().top - 80 }, 300);
}

// ─── TAMPILKAN HASIL ──────────────────────────────────────────────────────────
function showResult(cell, stocks) {
    // Status cell badge
    const statusMap = {
        available: { text: 'Tersedia',   cls: 'badge-success' },
        partial:   { text: 'Sebagian',   cls: 'badge-warning' },
        full:      { text: 'Penuh',       cls: 'badge-danger'  },
        blocked:   { text: 'Terblokir',  cls: 'badge-dark'    },
        reserved:  { text: 'Reservasi',  cls: 'badge-info'    },
    };
    const st = statusMap[cell.status] || { text: cell.status, cls: 'badge-secondary' };

    $('#cellCode').text(cell.code);
    $('#cellRack').text(cell.rack);
    $('#cellLevel').text('Level ' + cell.level);
    $('#cellZone').text(cell.zone);
    $('#cellWarehouse').text(cell.warehouse);
    $('#cellStatusBadge').text(st.text).attr('class', 'badge px-3 py-1 ' + st.cls);
    $('#totalQtyBadge').text(cell.total_qty.toLocaleString('id') + ' unit');
    $('#capacityText').text('dari ' + cell.capacity_max + ' unit');
    $('#totalSkus').text(cell.total_skus);
    $('#btnStockDetail').attr('href', cell.stock_url);
    $('#btnQrLabel').attr('href', cell.qr_label_url);
    $('#fifoCount').text(stocks.length + ' baris');

    // Capacity donut canvas
    drawCapacityRing(cell.utilization, cell.status);
    $('#utilPct').text(cell.utilization + '%');

    // Build stocks table
    let html = '';
    if (!stocks || !stocks.length) {
        html = '<div class="text-center text-muted py-4">Tidak ada stok.</div>';
    } else {
        stocks.forEach((s, i) => {
            const daysClass = i === 0 ? 'fifo-first' : (i === 1 ? 'fifo-second' : 'fifo-normal');
            const ageClass  = s.days_in_cell > 90 ? 'days-old' : (s.days_in_cell > 30 ? 'days-medium' : 'days-new');
            const ageTxt    = s.days_in_cell !== null ? s.days_in_cell + ' hari' : '—';
            const fifoLabel = i === 0
                ? `<span class="badge badge-danger ml-1" style="font-size:10px;">AMBIL PERTAMA</span>` : '';
            const expiryRow = s.expiry_date
                ? `<small class="text-danger"><i class="fas fa-calendar-times mr-1"></i>Exp: ${s.expiry_date}</small> &nbsp;` : '';
            const lpnRow    = s.lpn
                ? `<small class="text-muted"><i class="fas fa-tag mr-1"></i>LPN: ${s.lpn}</small>` : '';

            html += `
            <div class="fifo-item ${daysClass} p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div style="flex:1;">
                        <div class="font-weight-bold mb-1" style="font-size:15px;">
                            ${i + 1}. ${s.name}${fifoLabel}
                        </div>
                        <div class="mb-1">
                            <span class="badge px-2 mr-1" style="background:${s.category_color};color:#fff;font-size:10px;">${s.category}</span>
                            <code class="text-muted" style="font-size:11px;">${s.sku}</code>
                        </div>
                        <div>
                            ${expiryRow}${lpnRow}
                        </div>
                    </div>
                    <div class="text-right ml-3">
                        <div class="font-weight-bold" style="font-size:20px;">${s.quantity.toLocaleString('id')}</div>
                        <div class="text-muted small">${s.unit}</div>
                        <div class="mt-1">
                            <span class="days-badge px-2 py-1 ${ageClass}">
                                <i class="fas fa-clock mr-1"></i>${ageTxt}
                            </span>
                        </div>
                        <div class="text-muted mt-1" style="font-size:10px;">
                            Masuk: ${s.inbound_date ?? '—'}
                        </div>
                    </div>
                </div>
            </div>`;
        });
    }
    $('#stocksBody').html(html);
    $('#resultArea').show();
    $('html,body').animate({ scrollTop: $('#resultArea').offset().top - 80 }, 300);
}

// ─── CAPACITY RING (canvas) ───────────────────────────────────────────────────
function drawCapacityRing(pct, status) {
    const canvas = document.getElementById('capacityCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const cx = 55, cy = 55, r = 44, lw = 10;
    ctx.clearRect(0, 0, 110, 110);

    // Background ring
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, 2 * Math.PI);
    ctx.strokeStyle = '#e9ecef'; ctx.lineWidth = lw; ctx.stroke();

    // Fill ring
    const colors = { available:'#28a745', partial:'#fd7e14', full:'#dc3545', blocked:'#343a40', reserved:'#17a2b8' };
    const fillColor = colors[status] || '#28a745';
    const startAngle = -Math.PI / 2;
    const endAngle   = startAngle + (pct / 100) * 2 * Math.PI;
    ctx.beginPath(); ctx.arc(cx, cy, r, startAngle, endAngle);
    ctx.strokeStyle = fillColor; ctx.lineWidth = lw;
    ctx.lineCap = 'round'; ctx.stroke();
}

// ─── RIWAYAT ──────────────────────────────────────────────────────────────────
function addHistory(code, cell) {
    const now = new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
    scanHistory.unshift({ code, cell, time: now });

    const tbody = $('#historyBody');
    tbody.empty();
    scanHistory.slice(0, 10).forEach((h, i) => {
        const loc  = h.cell ? `Rak ${h.cell.rack} | Level ${h.cell.level} | ${h.cell.zone}` : '<span class="text-danger">—</span>';
        const skus = h.cell ? h.cell.total_skus : '—';
        const qty  = h.cell ? h.cell.total_qty.toLocaleString('id') + ' unit' : '<span class="text-danger">tidak ditemukan</span>';
        tbody.append(`<tr>
            <td class="text-center text-muted">${i + 1}</td>
            <td><code class="font-weight-bold">${h.code}</code></td>
            <td><small>${loc}</small></td>
            <td class="text-center">${skus}</td>
            <td class="text-center">${qty}</td>
            <td class="text-center"><small>${h.time}</small></td>
        </tr>`);
    });
    $('#historySection').show();
}

// ─── EVENT HANDLERS ───────────────────────────────────────────────────────────
$('#btnLookup').on('click', () => doLookup($('#cellInput').val()));
$('#cellInput').on('keydown', function (e) {
    if (e.key === 'Enter') { doLookup($(this).val()); e.preventDefault(); }
});
$('#btnClearHistory').on('click', function () {
    scanHistory = [];
    $('#historyBody').empty();
    $('#historySection').hide();
});
$(function () { $('#cellInput').focus(); });
</script>
@endpush
