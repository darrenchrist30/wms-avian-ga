@extends('layouts.adminlte')
@section('title', 'Scan Transfer Stok')

@push('styles')
<style>
/* ── Inputs ──────────────────────────────────────────────── */
.scan-input {
    height: 58px;
    font-size: 22px;
    font-weight: 700;
}
.qty-input-lg {
    height: 70px;
    font-size: 32px;
    font-weight: 800;
    text-align: center;
}
#btnAllQty { font-size: 18px; font-weight: 700; }

/* ── Stock choices ─────────────────────────────────────── */
.stock-choice {
    cursor: pointer;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    background: #fff;
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    transition: border-color .12s, background .12s;
}
.stock-choice:last-child { margin-bottom: 0; }
.stock-choice:hover, .stock-choice.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.kb-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    border-radius: 6px;
    background: #e9ecef;
    color: #495057;
    font-size: 14px;
    font-weight: 800;
    margin-right: 14px;
    flex-shrink: 0;
}
.stock-choice.selected .kb-badge { background: #28a745; color: #fff; }
.stock-item-qty {
    font-size: 18px;
    font-weight: 800;
    color: #28a745;
    white-space: nowrap;
}

/* ── Keyboard hint ─────────────────────────────────────── */
.kb-hint {
    font-size: 11px;
    color: #6c757d;
    background: #f8f9fa;
    border-radius: 4px;
    padding: 4px 8px;
}
.kb-hint kbd {
    background: #dee2e6;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 11px;
}

/* ── Result / summary ──────────────────────────────────── */
#resultPanel .result-info {
    background: #f8f9fa;
    border-left: 4px solid #dee2e6;
    border-radius: 4px;
    padding: 10px 14px;
    font-size: 13px;
    color: #6c757d;
}
#resultPanel .result-success {
    background: #d4edda;
    border-left: 4px solid #28a745;
    border-radius: 4px;
    padding: 10px 14px;
}
#resultPanel .result-error {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    border-radius: 4px;
    padding: 10px 14px;
}
#resultPanel .result-loading {
    background: #e3f2fd;
    border-left: 4px solid #17a2b8;
    border-radius: 4px;
    padding: 10px 14px;
    color: #0c525d;
}

/* ── Transfer preview (step 4) ─────────────────────────── */
#transferPreview {
    background: linear-gradient(135deg, #e8f5e9 0%, #f0f8ff 100%);
    border: 1px solid #c3e6cb;
    border-radius: 10px;
    padding: 16px 20px;
}

/* ── History ───────────────────────────────────────────── */
.history-row {
    border-bottom: 1px solid #f0f0f0;
    padding: 6px 0;
    font-size: 12px;
}
.history-row:last-child { border-bottom: none; }

/* ── Camera ────────────────────────────────────────────── */
#cameraReader video  { width: 100% !important; border-radius: 6px; }
#cameraReader img    { display: none; }

/* ── Flash overlay ─────────────────────────────────────── */
@keyframes pageFlash {
    0%   { opacity: .6; }
    100% { opacity: 0; }
}
.flash-overlay {
    position: fixed; inset: 0;
    z-index: 9999; pointer-events: none;
    animation: pageFlash .35s ease-out forwards;
}

/* ── Baris picker ──────────────────────────────────────── */
.baris-option {
    cursor: pointer;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    transition: border-color .12s, background .12s;
}
.baris-option:last-child { margin-bottom: 0; }
.baris-option:hover { border-color: #28a745; background: #f0fff4; }
.baris-option .baris-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #e9ecef;
    font-size: 16px; font-weight: 800;
    margin-right: 14px; flex-shrink: 0;
}
.baris-option .cap-bar {
    height: 6px; border-radius: 3px;
    background: #e9ecef; margin-top: 4px;
    overflow: hidden;
}
.baris-option .cap-bar-fill {
    height: 100%; border-radius: 3px;
    background: #28a745; transition: width .3s;
}

</style>
@endpush

@section('content')
<div class="container-fluid pb-4" style="max-width:780px;">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-qrcode text-success mr-2"></i>Scan Transfer Stok
        </h5>
        <div class="d-flex" style="gap:6px;">
            <a href="{{ route('stock.movements') }}" class="btn btn-sm btn-outline-info">
                <i class="fas fa-history mr-1"></i>Mutasi Stok
            </a>
            <button type="button" id="btnResetFlow" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-redo mr-1"></i>Reset
            </button>
        </div>
    </div>

    {{-- Hidden state holders (JS still reads/writes these) --}}
    <div style="display:none">
        <div class="transfer-step active" id="stepSource">
            <span id="sourceCellText"></span><span id="sourceMeta"></span>
        </div>
        <div class="transfer-step" id="stepItem">
            <span id="itemText"></span><span id="itemMeta"></span>
        </div>
        <div class="transfer-step" id="stepQty"><span id="qtyText"></span></div>
        <div class="transfer-step" id="stepTarget">
            <span id="targetCellText"></span><span id="targetMeta"></span>
        </div>
    </div>

    {{-- Alert --}}
    <div id="scanAlert" class="alert d-none mb-3" role="alert"></div>

    {{-- Panduan alur — hanya tampil di state awal sebelum scan --}}
    <div id="flowGuide" class="mb-3">
        <div class="d-flex align-items-stretch" style="gap:8px;">
            <div class="flex-fill text-center p-3 rounded" style="background:#f0fff4;border:1px solid #c3e6cb;">
                <div style="width:32px;height:32px;border-radius:50%;background:#28a745;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">1</div>
                <div class="small font-weight-bold text-success">Scan Cell Asal</div>
                <div class="small text-muted mt-1">Scan QR cell<br>sumber barang</div>
            </div>
            <div class="d-flex align-items-center text-muted" style="font-size:18px;">›</div>
            <div class="flex-fill text-center p-3 rounded" style="background:#f8f9fa;border:1px solid #e9ecef;">
                <div style="width:32px;height:32px;border-radius:50%;background:#adb5bd;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">2</div>
                <div class="small font-weight-bold text-muted">Pilih Item</div>
                <div class="small text-muted mt-1">Pilih barang<br>yang dipindah</div>
            </div>
            <div class="d-flex align-items-center text-muted" style="font-size:18px;">›</div>
            <div class="flex-fill text-center p-3 rounded" style="background:#f8f9fa;border:1px solid #e9ecef;">
                <div style="width:32px;height:32px;border-radius:50%;background:#adb5bd;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">3</div>
                <div class="small font-weight-bold text-muted">Input Qty</div>
                <div class="small text-muted mt-1">Tentukan<br>jumlah pindah</div>
            </div>
            <div class="d-flex align-items-center text-muted" style="font-size:18px;">›</div>
            <div class="flex-fill text-center p-3 rounded" style="background:#f8f9fa;border:1px solid #e9ecef;">
                <div style="width:32px;height:32px;border-radius:50%;background:#adb5bd;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">4</div>
                <div class="small font-weight-bold text-muted">Scan Cell Tujuan</div>
                <div class="small text-muted mt-1">Scan QR cell<br>tujuan barang</div>
            </div>
        </div>
    </div>

    {{-- ② Main action card --}}
    <div class="card mb-3">
        <div class="card-header py-2">
            <h5 class="mb-0 font-weight-bold" id="scanLabel">Scan QR / barcode cell asal</h5>
            <span id="phaseBadge" class="d-none"></span>
        </div>
        <div class="card-body">

            {{-- Scan input section (hidden on qty step) --}}
            <div id="scanSection">
                <input type="text" id="scanInput" class="form-control scan-input mb-2"
                    autocomplete="off" inputmode="text" placeholder="Arahkan scanner lalu Enter">
                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                    <button type="button" id="btnCamera" class="btn btn-sm btn-outline-success ml-auto">
                        <i class="fas fa-camera mr-1"></i>Kamera
                    </button>
                </div>
                <div id="cameraWrap" class="mt-3 d-none">
                    <div id="cameraReader"></div>
                </div>
            </div>

            {{-- Qty section (shown only on step 3) --}}
            <div id="qtyCard" class="d-none">
                <div class="input-group mb-2">
                    <input type="text" id="qtyInput" class="form-control qty-input-lg"
                        inputmode="numeric" autocomplete="off" placeholder="—" disabled>
                    <div class="input-group-append">
                        <button type="button" id="btnAllQty" class="btn btn-outline-primary btn-lg px-4" disabled>
                            All
                        </button>
                    </div>
                </div>
                <div class="kb-hint">
                    <kbd>Enter</kbd> konfirmasi &nbsp;&nbsp; <kbd>*</kbd> atau <kbd>A</kbd> = semua qty
                </div>
            </div>

        </div>
    </div>

    {{-- ③ Stock list (step 2 — item selection) --}}
    <div class="card mb-3" id="stocksCard">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-hand-pointer mr-1"></i>Pilih item yang akan dipindah</strong>
            <small id="itemKbHint" style="display:none!important"></small>
        </div>
        <div class="card-body p-2" id="sourceStocks">
            <div class="text-muted text-center py-4">
                <i class="fas fa-search mr-1"></i>Scan cell asal untuk melihat stok.
            </div>
        </div>
    </div>

    {{-- QR Confirm (muncul setelah pilih baris, sebelum scan konfirmasi) --}}
    <div id="qrConfirmSection" class="card mb-3 d-none" style="border-left:4px solid #28a745;">
        <div class="card-header py-2 d-flex align-items-center">
            <i class="fas fa-qrcode text-success mr-2" style="font-size:18px;"></i>
            <strong id="qrConfirmLabel">Scan QR untuk konfirmasi</strong>
        </div>
        <div class="card-body text-center py-3">
            <div id="qrCanvas" class="d-inline-block" style="background:#fff;padding:12px;border-radius:8px;border:1px solid #dee2e6;"></div>
            <div class="text-muted small mt-2" id="qrCellCodeLabel"></div>
            <div class="text-muted small mt-1">Arahkan scanner ke QR di atas</div>
        </div>
    </div>

    {{-- Baris picker (muncul saat scan kolom sebagai target) --}}
    <div id="columnTargetPicker" class="card mb-3 d-none">
        <div class="card-header py-2">
            <strong><i class="fas fa-layer-group mr-1"></i>Pilih baris tujuan</strong>
            <small class="text-muted ml-2" id="pickerColumnCode"></small>
        </div>
        <div class="card-body p-2" id="pickerRows"></div>
    </div>

    {{-- Item terpilih (step 3 & 4) --}}
    <div id="selectedItemBanner" class="card mb-3 d-none" style="border-left:4px solid #28a745;">
        <div class="card-body py-2 px-3 d-flex align-items-center">
            <div class="flex-grow-1">
                <div class="small text-muted font-weight-bold text-uppercase mb-1" style="letter-spacing:.4px;">
                    <i class="fas fa-check-circle text-success mr-1"></i>Item Dipilih
                </div>
                <div class="font-weight-bold" id="bannerItemName" style="font-size:16px;">-</div>
                <div class="small text-muted" id="bannerItemSku">-</div>
            </div>
            <div class="ml-3 text-right">
                <div class="small text-muted">Tersedia</div>
                <div class="font-weight-bold text-success" style="font-size:20px;" id="bannerItemAvail">-</div>
            </div>
        </div>
    </div>

    {{-- ④ Transfer preview (step 4) --}}
    <div id="transferPreviewWrap" class="mb-3 d-none">
        <div id="transferPreview">
            <div class="text-muted small font-weight-bold mb-2 text-uppercase" style="letter-spacing:.5px;">
                <i class="fas fa-exchange-alt mr-1"></i>Ringkasan Transfer
            </div>
            <div class="row align-items-center">
                <div class="col-5 text-center">
                    <div class="small text-muted">Dari</div>
                    <div class="h6 mb-0 font-weight-bold text-dark" id="previewFrom">-</div>
                </div>
                <div class="col-2 text-center text-muted h4 mb-0">→</div>
                <div class="col-5 text-center">
                    <div class="small text-muted">Ke</div>
                    <div class="h6 mb-0 font-weight-bold text-success" id="previewTo">...</div>
                </div>
            </div>
            <hr class="my-2">
            <div class="text-center">
                <span class="font-weight-bold" id="previewItem">-</span>
                &nbsp;·&nbsp;
                <span class="text-success font-weight-bold" id="previewQty">-</span>
            </div>
        </div>
    </div>

    {{-- ⑤ Result panel --}}
    <div id="resultPanel" class="mb-3">
    </div>

    {{-- ⑥ History --}}
    <div class="card d-none" id="historyCard">
        <div class="card-header py-2">
            <strong><i class="fas fa-history mr-1 text-info"></i>Transfer Terakhir</strong>
        </div>
        <div class="card-body p-2" id="historyPanel"></div>
    </div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script src="{{ asset('js/qrious.min.js') }}"></script>
<script>
const cellLookupUrl = '{{ route("stock.transfer-scan.cell") }}';
const transferUrl   = '{{ route("stock.transfer") }}';
const csrfToken     = '{{ csrf_token() }}';
const cellBaseUrl   = '{{ url("/c") }}';

let phase            = 'source';
let sourceCell       = null;
let sourceStocks     = [];
let selectedStock    = null;
let pendingTargetCell = null;
let lastCameraCode   = '';
let cameraScanner    = null;
let recentTransfers  = (function() {
    try { return JSON.parse(localStorage.getItem('transfer_history') || '[]'); } catch(e) { return []; }
})();

/* ── Audio feedback ──────────────────────────────────────────── */
function beepSound(type) {
    try {
        const ctx  = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        if (type === 'success') {
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.28);
        } else {
            osc.frequency.setValueAtTime(220, ctx.currentTime);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.35);
        }
        ctx.resume();
    } catch (e) {}
}

function flashScreen(type) {
    const color = type === 'success' ? 'rgba(40,167,69,.35)' : 'rgba(220,53,69,.3)';
    $('<div class="flash-overlay">').css('background', color).appendTo('body');
    setTimeout(function () { $('.flash-overlay').remove(); }, 380);
}

/* ── Helpers ─────────────────────────────────────────────────── */
function cleanCode(code) {
    code = $.trim(code || '');
    if (code.indexOf('/c/') !== -1) {
        code = code.split('/c/').pop().replace(/^\/+|\/+$/g, '');
    }
    return code;
}

function setAlert(type, message) {
    $('#scanAlert')
        .removeClass('d-none alert-success alert-danger alert-warning alert-info')
        .addClass('alert-' + type)
        .html(message);
}
function clearAlert() { $('#scanAlert').addClass('d-none').html(''); }
function numberFmt(v) { return new Intl.NumberFormat('id-ID').format(v || 0); }
function escapeHtml(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
function timeAgo(date) {
    const s = Math.round((new Date() - new Date(date)) / 1000);
    if (s < 60)   return s + ' dtk lalu';
    if (s < 3600) return Math.floor(s / 60) + ' mnt lalu';
    return Math.floor(s / 3600) + ' jam lalu';
}

/* ── Phase management ────────────────────────────────────────── */
function setPhase(nextPhase) {
    phase = nextPhase;
    $('.transfer-step').removeClass('active');

    const labels = {
        source: 'Scan cell asal',
        item:   'Scan barcode item atau pilih di bawah',
        qty:    'Berapa qty yang akan dipindah?',
        target: 'Scan cell tujuan',
    };
    $('#phaseBadge').text(labels[nextPhase]);
    $('#scanLabel').text(labels[nextPhase]);

    // Panduan alur: tampil hanya di state awal
    if (nextPhase === 'source') {
        $('#flowGuide').removeClass('d-none');
    } else {
        $('#flowGuide').addClass('d-none');
    }

    if (nextPhase === 'source') $('#stepSource').addClass('active');
    if (nextPhase === 'item')   { $('#stepItem').addClass('active'); $('#itemKbHint').show(); }
    else                        { $('#itemKbHint').hide(); }
    if (nextPhase === 'qty')    $('#stepQty').addClass('active');
    if (nextPhase === 'target') {
        $('#stepTarget').addClass('active');
        if (selectedStock) {
            $('#previewFrom').text(selectedStock.cell_code || (sourceCell ? sourceCell.code : '-'));
            $('#previewItem').text(selectedStock.name);
            $('#previewQty').text(numberFmt(parseInt($('#qtyInput').val(), 10)) + ' ' + selectedStock.unit);
            $('#previewTo').text('Scan cell tujuan...');
            $('#transferPreviewWrap').removeClass('d-none');
        }
    } else {
        $('#transferPreviewWrap').addClass('d-none');
    }

    // Tampilkan/sembunyikan daftar item & banner item terpilih
    if (nextPhase === 'source' || nextPhase === 'item') {
        $('#stocksCard').removeClass('d-none');
        $('#selectedItemBanner').addClass('d-none');
    } else {
        // qty & target: sembunyikan list, tampilkan banner item terpilih
        $('#stocksCard').addClass('d-none');
        $('#selectedItemBanner').removeClass('d-none');
    }

    // Swap scan input vs qty input
    if (nextPhase === 'qty') {
        $('#scanSection').addClass('d-none');
        $('#qtyCard').removeClass('d-none');
        $('#qtyInput').prop('disabled', false).val('').focus().select();
        $('#btnAllQty').prop('disabled', false);
        $('#scanInput').prop('disabled', true);
    } else {
        $('#qtyCard').addClass('d-none');
        $('#scanSection').removeClass('d-none');
        $('#qtyInput').prop('disabled', true);
        $('#btnAllQty').prop('disabled', true);
        $('#scanInput').prop('disabled', false).val('').focus();
    }
}

/* ── Cell lookup ─────────────────────────────────────────────── */
function lookupCell(code, purpose) {
    const data = { code: cleanCode(code), purpose: purpose || 'source' };
    if (purpose === 'target' && selectedStock) {
        data.stock_id = selectedStock.stock_id;
    }
    return $.get(cellLookupUrl, data).then(function (res) {
        if (!res.found) throw new Error(res.message || 'Cell tidak ditemukan.');
        return res;
    });
}

/* ── Stock rendering ─────────────────────────────────────────── */
function renderSourceStocks() {
    if (!sourceStocks.length) {
        $('#sourceStocks').html('');
        return;
    }

    const html = sourceStocks.map(function (stock, idx) {
        const selected = selectedStock && selectedStock.stock_id === stock.stock_id ? ' selected' : '';
        const kbNum    = idx + 1;
        const barisTag = stock.baris
            ? '<span class="badge badge-secondary ml-1" style="font-size:11px;font-weight:600;">Baris ' + stock.baris + '</span>'
            : '';
        return '<div class="stock-choice' + selected + '" data-stock-id="' + stock.stock_id + '">' +
            '<span class="kb-badge">' + kbNum + '</span>' +
            '<div class="flex-grow-1">' +
                '<div class="font-weight-bold" style="font-size:15px">' + escapeHtml(stock.name) + barisTag + '</div>' +
                '<div class="small text-muted">' + escapeHtml(stock.sku) + (stock.barcode ? ' · ' + escapeHtml(stock.barcode) : '') + '</div>' +
            '</div>' +
            '<div class="stock-item-qty ml-3">' + numberFmt(stock.quantity) + ' <span style="font-size:13px;font-weight:600">' + escapeHtml(stock.unit) + '</span></div>' +
        '</div>';
    }).join('');

    $('#sourceStocks').html(html);
}

function selectStock(stock) {
    selectedStock = stock;
    $('#stepItem').addClass('done').removeClass('active');
    $('#itemText').text(stock.name);
    $('#itemMeta').text(stock.sku + ' · stok ' + numberFmt(stock.quantity) + ' ' + stock.unit);
    $('#qtyInput').attr('max', stock.quantity).val('');
    // Isi banner item terpilih
    $('#bannerItemName').text(stock.name);
    $('#bannerItemSku').text(stock.sku + (stock.barcode ? ' · ' + stock.barcode : ''));
    $('#bannerItemAvail').text(numberFmt(stock.quantity) + ' ' + stock.unit);
    renderSourceStocks();
    setPhase('qty');
}

function findStockByCode(code) {
    const needle = cleanCode(code).toUpperCase();
    return sourceStocks.find(function (stock) {
        return [stock.sku, stock.barcode, stock.erp_item_code, stock.lpn, String(stock.stock_id)]
            .filter(Boolean)
            .some(function (v) { return String(v).toUpperCase() === needle; });
    });
}

/* ── Qty confirm ─────────────────────────────────────────────── */
function confirmQty() {
    if (!selectedStock) {
        setAlert('warning', 'Pilih item terlebih dahulu.');
        setPhase('item');
        return;
    }
    const qty = parseInt($('#qtyInput').val(), 10);
    if (!qty || qty < 1) {
        setAlert('warning', 'Qty minimal 1.');
        $('#qtyInput').focus().select();
        return;
    }
    if (qty > selectedStock.quantity) {
        setAlert('warning', 'Qty melebihi stok tersedia: ' + selectedStock.quantity + ' ' + selectedStock.unit + '.');
        $('#qtyInput').focus().select();
        return;
    }
    clearAlert();
    $('#stepQty').addClass('done');
    $('#qtyText').text(numberFmt(qty) + ' ' + selectedStock.unit);
    setPhase('target');
}

/* ── Execute transfer ────────────────────────────────────────── */
function executeTransfer(target) {
    const qty = parseInt($('#qtyInput').val(), 10);
    if (!sourceCell || !selectedStock || !qty) {
        setAlert('danger', 'Data transfer belum lengkap.');
        return;
    }
    if (target.id === selectedStock.cell_id) {
        setAlert('warning', 'Cell tujuan sama dengan cell asal.');
        setPhase('target');
        return;
    }

    // Update preview with target cell
    $('#previewTo').text(target.code);
    const resolved = target.resolved_from_rack ? ' (dari rak ' + target.resolved_from_rack + ')' : '';
    $('#targetCellText').text(target.code);
    $('#targetMeta').text('Sisa: ' + target.capacity_remaining + '/' + target.capacity_max + ' ' + target.capacity_unit + resolved);

    $('#scanInput').prop('disabled', true);
    $('#resultPanel').html('<div class="result-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Menyimpan transfer...</div>');

    $.ajax({
        url: transferUrl,
        method: 'POST',
        data: {
            _token:     csrfToken,
            stock_id:   selectedStock.stock_id,
            to_cell_id: target.id,
            quantity:   qty,
            notes: 'Scan transfer: ' + (selectedStock.cell_code || sourceCell.code) + ' -> ' + target.code,
        },
        success: function (res) {
            $('#stepTarget').addClass('done');
            beepSound('success');
            flashScreen('success');
            const fromCode = selectedStock.cell_code || sourceCell.code;
            $('#resultPanel').html(
                '<div class="result-success">' +
                    '<div class="font-weight-bold text-success mb-1">' +
                        '<i class="fas fa-check-circle mr-1"></i>' + escapeHtml(res.message || 'Transfer berhasil.') +
                    '</div>' +
                    '<div class="small text-muted">' +
                        escapeHtml(selectedStock.sku) + ' · ' +
                        numberFmt(qty) + ' ' + escapeHtml(selectedStock.unit) + ' · ' +
                        escapeHtml(fromCode) + ' → ' + escapeHtml(target.code) +
                    '</div>' +
                '</div>'
            );
            addToHistory(selectedStock.sku, selectedStock.name, qty, selectedStock.unit, fromCode, target.code);
            setTimeout(resetFlow, 1200);
        },
        error: function (xhr) {
            const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Transfer gagal.';
            beepSound('error');
            flashScreen('error');
            $('#resultPanel').html('<div class="result-error"><i class="fas fa-times-circle mr-1"></i>' + escapeHtml(msg) + '</div>');
            setAlert('danger', escapeHtml(msg));
            setPhase('target');
        },
    });
}

/* ── Scan handler ────────────────────────────────────────────── */
function handleScan(code) {
    code = cleanCode(code);
    if (!code) return;
    clearAlert();

    if (phase === 'source') {
        lookupCell(code, 'source').done(function (res) {
            sourceCell    = res.cell;
            sourceStocks  = res.stocks || [];
            selectedStock = null;
            $('#stepSource').addClass('done').removeClass('active');
            $('#sourceCellText').text(sourceCell.code);
            $('#sourceMeta').text(sourceStocks.length + ' item · kap ' + sourceCell.capacity_used + '/' + sourceCell.capacity_max);
            renderSourceStocks();

            if (!sourceStocks.length) {
                setAlert('warning', 'Cell ' + sourceCell.code + ' tidak memiliki stok available.');
                setPhase('source');
                return;
            }
            if (sourceStocks.length === 1) { selectStock(sourceStocks[0]); return; }
            setPhase('item');
        }).fail(function (xhr) {
            beepSound('error');
            setAlert('danger', escapeHtml(xhr.responseJSON?.message || 'Cell asal tidak ditemukan.'));
            setPhase('source');
        });
        return;
    }

    if (phase === 'item') {
        const stock = findStockByCode(code);
        if (!stock) {
            beepSound('error');
            setAlert('warning', 'Item <strong>' + escapeHtml(code) + '</strong> tidak ada di cell asal ' + sourceCell.code + '.');
            setPhase('item');
            return;
        }
        selectStock(stock);
        return;
    }

    if (phase === 'target') {
        // Jika ada pending target (dari baris picker), verifikasi scan cocok
        if (pendingTargetCell) {
            if (cleanCode(code).toUpperCase() === pendingTargetCell.code.toUpperCase()) {
                const cell = pendingTargetCell;
                pendingTargetCell = null;
                executeTransfer(cell);
            } else {
                beepSound('error');
                setAlert('danger', 'Cell tidak cocok. Scan QR <strong>' + escapeHtml(pendingTargetCell.code) + '</strong> untuk konfirmasi.');
            }
            return;
        }
        lookupCell(code, 'target').done(function (res) {
            if (res.is_column_target) {
                showColumnTargetPicker(res);
            } else {
                executeTransfer(res.cell);
            }
        }).fail(function (xhr) {
            beepSound('error');
            setAlert('danger', escapeHtml(xhr.responseJSON?.message || 'Cell tujuan tidak ditemukan.'));
            setPhase('target');
        });
    }
}

/* ── Transfer history ────────────────────────────────────────── */
function addToHistory(sku, name, qty, unit, fromCode, toCode) {
    recentTransfers.unshift({ sku, name, qty, unit, from: fromCode, to: toCode, time: new Date().toISOString() });
    if (recentTransfers.length > 8) recentTransfers.pop();
    try { localStorage.setItem('transfer_history', JSON.stringify(recentTransfers)); } catch(e) {}
    renderHistory();
}

function renderHistory() {
    if (!recentTransfers.length) return;
    $('#historyCard').removeClass('d-none');
    const html = recentTransfers.map(function (t) {
        return '<div class="history-row d-flex justify-content-between align-items-center">' +
            '<div>' +
                '<span class="font-weight-bold">' + escapeHtml(t.sku) + '</span> ' +
                '<span class="text-muted">— ' + escapeHtml(t.name) + '</span> ' +
                '<span class="badge badge-light border">' + numberFmt(t.qty) + ' ' + escapeHtml(t.unit) + '</span> ' +
                '<span class="text-success ml-1">' + escapeHtml(t.from) + ' → ' + escapeHtml(t.to) + '</span>' +
            '</div>' +
            '<small class="text-muted ml-2 flex-shrink-0">' + timeAgo(t.time) + '</small>' +
        '</div>';
    }).join('');
    $('#historyPanel').html(html);
}

/* ── Baris picker (target column scan) ──────────────────────── */
function showColumnTargetPicker(res) {
    $('#pickerColumnCode').text(res.column_code);
    const html = res.child_cells.map(function(c) {
        const pct = c.capacity_max > 0 ? Math.round((c.capacity_remaining / c.capacity_max) * 100) : 0;
        const capColor = pct > 50 ? '#28a745' : pct > 20 ? '#ffc107' : '#dc3545';
        return '<div class="baris-option" data-cell-id="' + c.id + '" data-cell-code="' + escapeHtml(c.code) + '"' +
            ' data-qr-code="' + escapeHtml(c.qr_code || c.code) + '"' +
            ' data-cap-remaining="' + c.capacity_remaining + '" data-cap-max="' + c.capacity_max + '">' +
            '<div class="d-flex align-items-center flex-grow-1">' +
                '<span class="baris-num">' + c.baris + '</span>' +
                '<div>' +
                    '<div class="font-weight-bold">' + escapeHtml(c.code) + '</div>' +
                    '<div class="cap-bar" style="width:120px">' +
                        '<div class="cap-bar-fill" style="width:' + pct + '%;background:' + capColor + '"></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="text-right ml-3">' +
                '<div class="small text-muted">Sisa kapasitas</div>' +
                '<div class="font-weight-bold">' + c.capacity_remaining + ' / ' + c.capacity_max + '</div>' +
            '</div>' +
        '</div>';
    }).join('');
    $('#pickerRows').html(html || '<div class="text-muted text-center py-3">Tidak ada baris tersedia.</div>');
    $('#columnTargetPicker').removeClass('d-none');
    $('#scanSection').addClass('d-none');
}

$(document).on('click', '.baris-option', function() {
    pendingTargetCell = {
        id:                 parseInt($(this).data('cell-id')),
        code:               $(this).data('cell-code'),
        qr_code:            $(this).data('qr-code') || $(this).data('cell-code'),
        capacity_remaining: parseInt($(this).data('cap-remaining')),
        capacity_max:       parseInt($(this).data('cap-max')),
        capacity_unit:      'poin',
        resolved_from_rack: null,
    };
    $('#columnTargetPicker').addClass('d-none');
    $('#scanSection').removeClass('d-none');

    // Render QR code — encode URL sama persis seperti label cetak fisik
    const qrEl = document.getElementById('qrCanvas');
    qrEl.innerHTML = '<canvas id="qrCanvas_inner"></canvas>';
    new QRious({
        element: document.getElementById('qrCanvas_inner'),
        value: cellBaseUrl + '/' + pendingTargetCell.qr_code,
        size: 200,
        level: 'H',
        background: '#ffffff',
        foreground: '#1a2332',
        padding: 4,
    });
    $('#qrConfirmLabel').text('Scan QR cell ' + pendingTargetCell.code);
    $('#qrCellCodeLabel').text(pendingTargetCell.code);
    $('#qrConfirmSection').removeClass('d-none');

    $('#scanLabel').text('Scan QR cell ' + pendingTargetCell.code + ' untuk konfirmasi');
    $('#scanInput').val('').focus();
});

/* ── Reset ───────────────────────────────────────────────────── */
function resetFlow() {
    sourceCell    = null;
    sourceStocks  = [];
    selectedStock = null;

    $('#sourceCellText').text('Belum discan');
    $('#sourceMeta').text('-');
    $('#itemText').text('Belum dipilih');
    $('#itemMeta').text('-');
    $('#qtyText').text('Belum diisi');
    $('#targetCellText').text('Belum discan');
    $('#targetMeta').text('-');
    $('#qtyInput').val('').prop('disabled', true).removeAttr('max');
    $('#btnAllQty').prop('disabled', true);
    $('#sourceStocks').html('<div class="text-muted text-center py-4"><i class="fas fa-search mr-1"></i>Scan cell asal untuk melihat stok.</div>');
    $('#resultPanel').html('');
    $('#transferPreviewWrap').addClass('d-none');
    $('#scanSection').removeClass('d-none');
    $('#qtyCard').addClass('d-none');
    $('#stocksCard').removeClass('d-none');
    $('#selectedItemBanner').addClass('d-none');
    $('#columnTargetPicker').addClass('d-none');
    $('#qrConfirmSection').addClass('d-none');
    $('#qrCanvas').empty();
    pendingTargetCell = null;
    $('.transfer-step').removeClass('done active');
    clearAlert();
    setPhase('source');
    renderHistory();
}

/* ── Event handlers ──────────────────────────────────────────── */
$('#scanInput').on('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = $(this).val();
        $(this).val('');
        handleScan(code);
    }
});

$('#qtyInput').on('keydown', function (e) {
    if (e.key === '*' || e.key === 'a') {
        e.preventDefault();
        if (selectedStock) { $(this).val(selectedStock.quantity); confirmQty(); }
        return;
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        const raw = $(this).val().trim();
        if (raw && /[^0-9]/.test(raw)) {
            $(this).val('');
            handleScan(raw);
            return;
        }
        confirmQty();
    }
});

$('#btnAllQty').on('click', function () {
    if (!selectedStock) return;
    $('#qtyInput').val(selectedStock.quantity);
    confirmQty();
});

$(document).on('click', '.stock-choice', function () {
    const id    = parseInt($(this).data('stock-id'), 10);
    const stock = sourceStocks.find(function (s) { return s.stock_id === id; });
    if (stock) selectStock(stock);
});

$(document).on('keydown', function (e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        resetFlow();
        return;
    }
    if (phase === 'item' && e.key >= '1' && e.key <= '9' && !$(e.target).is('input, textarea')) {
        const idx = parseInt(e.key, 10) - 1;
        if (idx < sourceStocks.length) selectStock(sourceStocks[idx]);
        return;
    }
});

$('#scanInput').on('keyup', function (e) {
    if (phase !== 'item') return;
    const val = $(this).val().trim();
    if (/^[1-9]$/.test(val) && e.key >= '1' && e.key <= '9') {
        const idx = parseInt(val, 10) - 1;
        if (idx < sourceStocks.length) {
            $(this).val('');
            selectStock(sourceStocks[idx]);
        }
    }
});

$('#btnResetFlow').on('click', resetFlow);

$('#btnCamera').on('click', async function () {
    if (cameraScanner) {
        try { await cameraScanner.stop(); } catch (e) {}
        try { await cameraScanner.clear(); } catch (e) {}
        cameraScanner = null;
        $('#cameraWrap').addClass('d-none');
        $(this).html('<i class="fas fa-camera mr-1"></i>Kamera');
        $('#scanInput').focus();
        return;
    }
    $('#cameraWrap').removeClass('d-none');
    cameraScanner = new Html5Qrcode('cameraReader');
    $(this).html('<i class="fas fa-stop mr-1"></i>Stop Kamera');
    try {
        await cameraScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: function (w, h) { const s = Math.round(Math.min(w, h) * 0.72); return { width: s, height: s }; } },
            function (decodedText) {
                if (decodedText === lastCameraCode) return;
                lastCameraCode = decodedText;
                setTimeout(function () { lastCameraCode = ''; }, 1500);
                handleScan(decodedText);
            },
            function () {}
        );
    } catch (e) {
        setAlert('danger', 'Kamera tidak bisa dibuka. Gunakan scanner fisik atau input manual.');
        $('#cameraWrap').addClass('d-none');
        cameraScanner = null;
        $('#btnCamera').html('<i class="fas fa-camera mr-1"></i>Kamera');
    }
});

$(function () {
    setPhase('source');
});
</script>
@endpush
