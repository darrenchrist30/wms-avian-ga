@extends('layouts.adminlte')
@section('title', 'Scan Transfer Stok')

@push('styles')
<style>
.scan-input   { height:58px; font-size:22px; font-weight:700; }
.qty-input-lg { height:70px; font-size:32px; font-weight:800; text-align:center; }
#btnAllQty    { font-size:18px; font-weight:700; }

/* ── Modal stock table ─────────────────────────────── */
#modalStocksTable tbody tr { cursor:pointer; transition:background .1s; }
#modalStocksTable tbody tr:hover { background:#f0fff4; }
#modalStocksTable tbody tr.in-cart { background:#d4edda; }
#modalStocksTable tbody tr.picking { background:#fff3cd; }

/* ── Cart items ────────────────────────────────────── */
.cart-item {
    display:flex; align-items:center; justify-content:space-between;
    padding:6px 10px; border-bottom:1px solid #e9ecef; font-size:13px;
}
.cart-item:last-child { border-bottom:none; }

/* ── Result ────────────────────────────────────────── */
.result-success { background:#d4edda; border-left:4px solid #28a745; border-radius:4px; padding:10px 14px; }
.result-error   { background:#f8d7da; border-left:4px solid #dc3545; border-radius:4px; padding:10px 14px; }
.result-loading { background:#e3f2fd; border-left:4px solid #17a2b8; border-radius:4px; padding:10px 14px; color:#0c525d; }

/* ── Transfer preview ──────────────────────────────── */
#transferPreview {
    background:linear-gradient(135deg,#e8f5e9 0%,#f0f8ff 100%);
    border:1px solid #c3e6cb; border-radius:10px; padding:16px 20px;
}

/* ── History ───────────────────────────────────────── */
.history-row { border-bottom:1px solid #f0f0f0; padding:6px 0; font-size:12px; }
.history-row:last-child { border-bottom:none; }

/* ── Camera ────────────────────────────────────────── */
#cameraReader video { width:100% !important; border-radius:6px; }
#cameraReader img   { display:none; }

/* ── Flash ─────────────────────────────────────────── */
@keyframes pageFlash { 0%{opacity:.6;} 100%{opacity:0;} }
.flash-overlay { position:fixed; inset:0; z-index:9999; pointer-events:none; animation:pageFlash .35s ease-out forwards; }

/* ── Baris picker ──────────────────────────────────── */
.baris-option {
    cursor:pointer; border:2px solid #e5e7eb; border-radius:8px;
    padding:12px 16px; margin-bottom:8px; display:flex; align-items:center;
    justify-content:space-between; background:#fff; transition:border-color .12s,background .12s;
}
.baris-option:last-child { margin-bottom:0; }
.baris-option:not(.baris-full):hover { border-color:#0d8564; background:#f0fff4; }
.baris-option.baris-full { border-color:#dee2e6 !important; }
.baris-option.baris-full:hover { background:#f8f9fa !important; border-color:#dee2e6 !important; }
.baris-option .baris-num {
    display:inline-flex; align-items:center; justify-content:center;
    width:36px; height:36px; border-radius:50%; background:#e9ecef;
    font-size:16px; font-weight:800; margin-right:14px; flex-shrink:0;
}
.baris-option .cap-bar { height:6px; border-radius:3px; background:#e9ecef; margin-top:4px; overflow:hidden; }
.baris-option .cap-bar-fill { height:100%; border-radius:3px; background:#28a745; transition:width .3s; }

/* ── Scan pulse (saat phase target) ───────────────── */
@keyframes scanPulse {
    0%,100% { border-color:#ced4da; box-shadow:none; }
    50%     { border-color:#0d8564; box-shadow:0 0 0 4px rgba(13,133,100,.18); }
}
.scan-pulse { animation:scanPulse 1.4s ease-in-out infinite !important; }

/* ── Riwayat button hover ──────────────────────────── */
#btnRiwayat { border-color:#0d8564; color:#0d8564; transition:all .18s; }
#btnRiwayat:hover { background:#0d8564; color:#fff; border-color:#0d8564; }

/* ── Modal close button ────────────────────────────── */
.modal .close:focus,
.modal .close:active { outline:none; box-shadow:none; }

/* ── Modal Tambah button ───────────────────────────── */
#modalBtnAdd { transition:background .15s, border-color .15s; }
#modalBtnAdd:hover  { background:#0a6e52 !important; border-color:#0a6e52 !important; }
#modalBtnAdd:active { background:#085c44 !important; border-color:#085c44 !important; }

/* ── Modal Lanjut button ───────────────────────────── */
#modalBtnDone {
    background:#0d8564; border-color:#0d8564; color:#fff;
    transition:background .18s, border-color .18s, transform .1s, box-shadow .18s;
}
#modalBtnDone:hover  { background:#0a6e52; border-color:#0a6e52; box-shadow:0 3px 8px rgba(13,133,100,.35); }
#modalBtnDone:active { background:#085c44; border-color:#085c44; transform:scale(.97); }
#modalBtnDone:focus  { box-shadow:0 0 0 3px rgba(13,133,100,.3); outline:none; }

/* ── Status strip ──────────────────────────────────── */
#statusStrip { background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:10px 16px; }
.strip-step  { display:flex; flex-direction:column; align-items:center; min-width:0; flex:1; }
.strip-lbl   { font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#ced4da; margin-bottom:2px; }
.strip-lbl.active { color:#28a745; }
.strip-lbl.done   { color:#6c757d; }
.strip-val   { font-size:13px; font-weight:700; color:#dee2e6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.strip-val.active { color:#28a745; }
.strip-val.done   { color:#212529; }
.strip-divider { color:#dee2e6; font-size:18px; flex-shrink:0; padding:0 6px; align-self:center; padding-top:14px; }
</style>
@endpush

@section('content')
<div class="container-fluid pb-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-qrcode text-success mr-2"></i>Scan Transfer Stok
        </h5>
        <div class="d-flex" style="gap:6px;">
            <button type="button" class="btn btn-sm btn-outline-info" id="btnRiwayat">
                <i class="fas fa-history mr-1"></i>Riwayat Transfer
            </button>
            <button type="button" id="btnResetFlow" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-redo mr-1"></i>Reset
            </button>
        </div>
    </div>

    {{-- Modal Riwayat Transfer --}}
    <div class="modal fade" id="modalRiwayat" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#f8f9fa;">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-history text-info mr-2"></i>Riwayat Transfer Stok
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-3">
                    <table id="tblRiwayat" class="table table-sm table-bordered table-hover w-100" style="font-size:13px;">
                        <thead class="thead-light">
                            <tr>
                                <th width="40" class="text-center">#</th>
                                <th width="140">Waktu</th>
                                <th>Item</th>
                                <th width="110" class="text-center">Dari</th>
                                <th width="110" class="text-center">Ke</th>
                                <th width="80" class="text-center">Qty</th>
                                <th width="110">Operator</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Status strip --}}
    <div id="statusStrip" class="mb-3">
        <div class="d-flex align-items-start" style="gap:4px;">
            <div class="strip-step">
                <div class="strip-lbl active" id="sLblSource">Asal</div>
                <div class="strip-val active" id="sValSource">scan cell</div>
            </div>
            <div class="strip-divider">›</div>
            <div class="strip-step">
                <div class="strip-lbl" id="sLblItem">Item</div>
                <div class="strip-val" id="sValItem">—</div>
            </div>
            <div class="strip-divider">›</div>
            <div class="strip-step">
                <div class="strip-lbl" id="sLblQty">Qty</div>
                <div class="strip-val" id="sValQty">—</div>
            </div>
            <div class="strip-divider">›</div>
            <div class="strip-step">
                <div class="strip-lbl" id="sLblTarget">Tujuan</div>
                <div class="strip-val" id="sValTarget">—</div>
            </div>
        </div>
    </div>

    {{-- Hidden state holders --}}
    <div style="display:none">
        <div class="transfer-step active" id="stepSource"><span id="sourceCellText"></span><span id="sourceMeta"></span></div>
        <div class="transfer-step" id="stepItem"><span id="itemText"></span><span id="itemMeta"></span></div>
        <div class="transfer-step" id="stepQty"><span id="qtyText"></span></div>
        <div class="transfer-step" id="stepTarget"><span id="targetCellText"></span><span id="targetMeta"></span></div>
    </div>

    {{-- ── Modal pilih item + cart ─────────────────────────── --}}
    <div class="modal fade" id="modalPickItem" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#f8f9fa;border-bottom:2px solid #28a745;">
                    <div>
                        <h5 class="modal-title mb-0 font-weight-bold">
                            <i class="fas fa-boxes text-success mr-2"></i>Pilih Item - Transfer Stok
                        </h5>
                        <small class="text-muted" id="modalCellLabel"></small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>

                <div class="modal-body p-0">

                    {{-- Summary bar --}}
                    <div class="d-flex justify-content-between align-items-center px-3 py-2"
                         style="background:#f0fff4;border-bottom:1px solid #c3e6cb;font-size:13px;">
                        <span id="modalStockSummaryText" class="text-muted"></span>
                        <span id="modalCartBadge" class="badge badge-success d-none"></span>
                    </div>

                    {{-- Items table --}}
                    <div style="max-height:300px;overflow-y:auto;padding:0 12px;">
                        <table id="modalStocksTable" class="table table-sm table-hover table-bordered mb-0 mt-2" style="font-size:12px;table-layout:fixed;width:100%;">
                            <colgroup>
                                <col style="width:36px;">
                                <col>
                                <col style="width:170px;">
                                <col style="width:80px;">
                                <col style="width:100px;">
                            </colgroup>
                            <thead class="thead-light" style="position:sticky;top:0;z-index:1;">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Nama / SKU</th>
                                    <th>Kategori</th>
                                    <th class="text-center">Lokasi</th>
                                    <th class="text-right">Tersedia</th>
                                </tr>
                            </thead>
                            <tbody id="modalStockRows"></tbody>
                        </table>
                    </div>

                    {{-- Qty picker (muncul saat baris diklik) --}}
                    <div id="modalQtyArea" class="px-3 py-2 d-none" style="background:#fffbea;border-top:2px solid #ffc107;">
                        <div class="font-weight-bold mb-1" id="modalQtyItemName" style="font-size:13px;"></div>
                        <div class="d-flex align-items-center" style="gap:6px;">
                            <input type="number" id="modalQtyInput" class="form-control form-control-sm"
                                style="width:80px;font-size:14px;font-weight:700;text-align:center;"
                                min="1" placeholder="Qty">
                            <button type="button" id="modalBtnAll" class="btn btn-sm btn-outline-secondary">All</button>
                            <button type="button" id="modalBtnAdd" class="btn btn-sm px-3" style="background:#0d8564;border-color:#0d8564;color:#fff;">
                                <i class="fas fa-plus mr-1"></i>Tambah
                            </button>
                        </div>
                    </div>

                    {{-- Cart (muncul saat ada item dipilih) --}}
                    <div id="modalCartWrap" class="d-none" style="border-top:2px solid #28a745;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2"
                             style="background:#f0fff4;">
                            <strong style="font-size:13px;color:#155724;">
                                <i class="fas fa-shopping-cart mr-1"></i>Item Dipilih
                            </strong>
                            <span id="modalCartSummary" class="text-muted" style="font-size:12px;"></span>
                        </div>
                        <div id="modalCartItems"></div>
                    </div>

                </div>

                <div class="modal-footer py-2">
                    <small class="text-muted mr-auto" id="modalFooterHint">Klik baris item untuk memilih</small>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal" id="modalBtnCancel">Batal</button>
                    <button type="button" class="btn btn-sm d-none" id="modalBtnDone">
                        <i class="fas fa-arrow-right mr-1"></i>Lanjut Scan Cell Tujuan
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Modal pilih baris tujuan ──────────────────────────────── --}}
    <div class="modal fade" id="modalPickBaris" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#f8f9fa;border-bottom:2px solid #0d8564;">
                    <div>
                        <h5 class="modal-title mb-0 font-weight-bold">
                            <i class="fas fa-layer-group mr-2" style="color:#0d8564;"></i>Pilih Baris Tujuan
                        </h5>
                        <small class="text-muted">Kolom: <span id="modalBarisColumnCode"></span></small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-2" id="modalBarisRows"></div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Batal</button>
                </div>
            </div>
        </div>
    </div>

    <div id="scanAlert" class="alert d-none mb-3" role="alert"></div>

    {{-- Scan / qty card --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 font-weight-bold" id="scanLabel">Scan QR / barcode cell asal</h5>
            <span id="phaseBadge" class="d-none"></span>
        </div>
        <div class="card-body">
            <div id="scanSection">
                <input type="text" id="scanInput" class="form-control scan-input mb-2"
                    autocomplete="off" inputmode="text" placeholder="Arahkan scanner lalu Enter">
                <div class="d-flex" style="gap:8px;">
                    <button type="button" id="btnCamera" class="btn btn-sm btn-outline-success ml-auto">
                        <i class="fas fa-camera mr-1"></i>Kamera
                    </button>
                </div>
                <div id="cameraWrap" class="mt-3 d-none"><div id="cameraReader"></div></div>
            </div>
            <div id="qtyCard" class="d-none">
                <div class="input-group mb-2">
                    <input type="text" id="qtyInput" class="form-control qty-input-lg"
                        inputmode="numeric" autocomplete="off" placeholder="—" disabled>
                    <div class="input-group-append">
                        <button type="button" id="btnAllQty" class="btn btn-outline-primary btn-lg px-4" disabled>All</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- QR Confirm --}}
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

    {{-- Transfer preview (full width, below scan) --}}
    <div id="transferPreviewWrap" class="card mb-3 d-none" style="border-left:4px solid #0d8564;">
        <div class="card-header py-2 d-flex align-items-center">
            <strong style="font-size:13px;"><i class="fas fa-exchange-alt mr-1" style="color:#0d8564;"></i>Ringkasan Transfer</strong>
            <button type="button" class="btn btn-xs btn-outline-secondary ml-auto" id="btnReEditCart">
                <i class="fas fa-edit mr-1"></i>Edit
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" style="font-size:12px;table-layout:fixed;width:100%;">
                <colgroup>
                    <col>
                    <col style="width:70px;">
                    <col style="width:90px;">
                    <col style="width:90px;">
                </colgroup>
                <thead class="thead-light">
                    <tr>
                        <th style="white-space:nowrap;">Item</th>
                        <th class="text-center" style="white-space:nowrap;">Qty</th>
                        <th class="text-center" style="white-space:nowrap;">Dari</th>
                        <th class="text-center" style="white-space:nowrap;" id="previewToHeader">Tujuan</th>
                    </tr>
                </thead>
                <tbody id="previewItems"></tbody>
            </table>
        </div>
    </div>

    <div id="resultPanel" class="mb-3"></div>
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

let phase             = 'source';
let sourceCell        = null;
let sourceStocks      = [];
let selectedCart      = [];   // [{stock, qty}, ...]
let pickingStock      = null; // stok yang sedang diinput qty di modal
let pendingTargetCell = null;
let lastCameraCode    = '';
let cameraScanner     = null;
let riwayatTable = null; // DataTable instance (lazy init)

/* ── Audio / visual ──────────────────────────────────── */
function beepSound(type) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator(), gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        if (type === 'success') {
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
            osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.28);
        } else {
            osc.frequency.setValueAtTime(220, ctx.currentTime);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.35);
        }
        ctx.resume();
    } catch(e) {}
}
function flashScreen(type) {
    const color = type === 'success' ? 'rgba(40,167,69,.35)' : 'rgba(220,53,69,.3)';
    $('<div class="flash-overlay">').css('background', color).appendTo('body');
    setTimeout(function() { $('.flash-overlay').remove(); }, 380);
}

/* ── Helpers ─────────────────────────────────────────── */
function cleanCode(code) {
    code = $.trim(code || '');
    if (code.indexOf('/c/') !== -1) code = code.split('/c/').pop().replace(/^\/+|\/+$/g, '');
    return code;
}
function setAlert(type, message) {
    $('#scanAlert').removeClass('d-none alert-success alert-danger alert-warning alert-info')
        .addClass('alert-' + type).html(message);
}
function clearAlert() { $('#scanAlert').addClass('d-none').html(''); }
function numberFmt(v) { return new Intl.NumberFormat('id-ID').format(v || 0); }
function escapeHtml(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
function timeAgo(date) {
    const s = Math.round((new Date() - new Date(date)) / 1000);
    if (s < 60) return s + ' dtk lalu';
    if (s < 3600) return Math.floor(s / 60) + ' mnt lalu';
    return Math.floor(s / 3600) + ' jam lalu';
}

/* ── Status strip ────────────────────────────────────── */
const STRIP_STEPS = ['Source','Item','Qty','Target'];
const PHASE_IDX   = { source:0, item:1, qty:2, target:3 };
function updateStrip(currentPhase) {
    const idx = PHASE_IDX[currentPhase] ?? 0;
    STRIP_STEPS.forEach(function(s, i) {
        $('#sLbl' + s).removeClass('active done');
        $('#sVal' + s).removeClass('active done');
        if (i < idx)       { $('#sLbl' + s).addClass('done'); $('#sVal' + s).addClass('done'); }
        else if (i === idx){ $('#sLbl' + s).addClass('active'); $('#sVal' + s).addClass('active'); }
    });
}

/* ── Phase ───────────────────────────────────────────── */
function setPhase(nextPhase) {
    phase = nextPhase;
    $('.transfer-step').removeClass('active');
    const labels = {
        source: 'Scan QR / barcode cell asal',
        target: 'Scan cell tujuan',
    };
    $('#scanLabel').text(labels[nextPhase] || 'Scan cell tujuan');
    updateStrip(nextPhase);

    if (nextPhase === 'source') { $('#stepSource').addClass('active'); $('#scanInput').removeClass('scan-pulse'); }
    if (nextPhase === 'target') {
        $('#stepTarget').addClass('active');
        $('#scanInput').addClass('scan-pulse');
        setAlert('info', '<i class="fas fa-map-marker-alt mr-2"></i><strong>Scan cell tujuan</strong> — arahkan scanner ke QR / barcode lokasi tujuan.');
        if (selectedCart.length) {
            const fromCode = sourceCell ? sourceCell.code : '—';
            $('#previewToHeader').text('Tujuan');
            const rows = selectedCart.map(function(c) {
                return '<tr>' +
                    '<td><div class="font-weight-bold" style="font-size:12px;">' + escapeHtml(c.stock.name) + '</div>' +
                        '<div class="text-muted" style="font-size:11px;">' + escapeHtml(c.stock.sku) + '</div></td>' +
                    '<td class="text-center font-weight-bold">' + numberFmt(c.qty) + ' <span class="text-muted" style="font-size:10px;">' + escapeHtml(c.stock.unit) + '</span></td>' +
                    '<td class="text-center"><span class="badge badge-secondary" style="font-size:10px;">' + escapeHtml(c.stock.cell_code || fromCode) + '</span></td>' +
                    '<td class="text-center text-success" id="previewTo_' + c.stock.stock_id + '">—</td>' +
                '</tr>';
            }).join('');
            $('#previewItems').html(rows);
            $('#transferPreviewWrap').removeClass('d-none');
        }
    } else {
        $('#transferPreviewWrap').addClass('d-none');
    }

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

/* ── Cell lookup ─────────────────────────────────────── */
function lookupCell(code, purpose) {
    const data = { code: cleanCode(code), purpose: purpose || 'source' };
    if (purpose === 'target' && selectedCart.length) {
        data.stock_id = selectedCart[0].stock.stock_id;
    }
    return $.get(cellLookupUrl, data).then(function(res) {
        if (!res.found) throw new Error(res.message || 'Cell tidak ditemukan.');
        return res;
    });
}

/* ── Modal: open & render ────────────────────────────── */
function openPickItemModal() {
    const totalQty   = sourceStocks.reduce(function(t, s) { return t + s.quantity; }, 0);
    const totalJenis = sourceStocks.length;

    $('#modalCellLabel').text('');
    $('#modalStockSummaryText').html(
        '<i class="fas fa-info-circle text-success mr-1"></i>' +
        '<strong>' + totalJenis + '</strong> jenis item tersedia di cell ini'
    );

    renderModalRows();
    renderModalCart();
    $('#modalQtyArea').addClass('d-none');
    pickingStock = null;
    $('#modalPickItem').modal('show');
    setTimeout(function() { $('#modalQtyInput').blur(); }, 300);
}

function renderModalRows() {
    const rows = sourceStocks.map(function(stock, idx) {
        const inCart    = selectedCart.find(function(c) { return c.stock.stock_id === stock.stock_id; });
        const isPicking = pickingStock && pickingStock.stock_id === stock.stock_id;
        let trClass = isPicking ? 'picking' : (inCart ? 'in-cart' : '');
        const cartQty    = inCart ? inCart.qty : null;
        const qtyDisplay = '<span style="font-weight:700;color:#212529;">' + numberFmt(stock.quantity) + '</span>' +
            ' <span class="text-muted" style="font-size:11px;font-weight:400;">' + escapeHtml(stock.unit) + '</span>' +
            (cartQty !== null
                ? '<br><span style="font-size:11px;font-weight:600;color:#0d8564;">Jumlah: ' + numberFmt(cartQty) + ' ' + escapeHtml(stock.unit) + '</span>'
                : '');
        const barisTag = stock.baris
            ? ' <span class="badge badge-light border" style="font-size:10px;">Baris ' + stock.baris + '</span>'
            : '';
        return '<tr class="' + trClass + '" data-stock-id="' + stock.stock_id + '">' +
            '<td class="text-center text-muted">' + (idx + 1) + '</td>' +
            '<td>' +
                '<div class="font-weight-bold" style="font-size:13px;">' + escapeHtml(stock.name) + barisTag + '</div>' +
                '<div class="text-muted" style="font-size:11px;">' + escapeHtml(stock.sku) +
                    (stock.barcode ? ' · ' + escapeHtml(stock.barcode) : '') + '</div>' +
            '</td>' +
            '<td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:170px;">' +
                '<span class="badge badge-light border" style="font-size:11px;max-width:165px;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:middle;">' +
                escapeHtml(stock.category || '—') + '</span></td>' +
            '<td class="text-center"><span class="badge badge-dark" style="font-size:11px;">' + escapeHtml(stock.cell_code || '—') + '</span></td>' +
            '<td class="text-right">' + qtyDisplay + '</td>' +
        '</tr>';
    }).join('');
    $('#modalStockRows').html(rows);
}

function renderModalCart() {
    const count    = selectedCart.length;
    const totalQty = selectedCart.reduce(function(t, c) { return t + c.qty; }, 0);

    if (count === 0) {
        $('#modalCartWrap').addClass('d-none');
        $('#modalCartBadge').addClass('d-none');
        $('#modalBtnDone').addClass('d-none');
        $('#modalFooterHint').text('Klik baris item untuk memilih');
        return;
    }

    $('#modalCartBadge').removeClass('d-none').text(count + ' item dipilih');
    $('#modalBtnDone').removeClass('d-none');
    $('#modalCartSummary').text(count + ' jenis · ' + numberFmt(totalQty) + ' total unit');
    $('#modalFooterHint').text('Tambah item lagi atau klik Lanjut');

    const html = selectedCart.map(function(c) {
        return '<div class="cart-item">' +
            '<div>' +
                '<span class="font-weight-bold" style="font-size:13px;">' + escapeHtml(c.stock.name) + '</span>' +
                '<span class="text-muted ml-1" style="font-size:11px;">' + escapeHtml(c.stock.sku) + '</span>' +
            '</div>' +
            '<div class="d-flex align-items-center" style="gap:8px;">' +
                '<span class="font-weight-bold" style="font-size:13px;color:#0d8564;">' + numberFmt(c.qty) + ' ' + escapeHtml(c.stock.unit) + '</span>' +
                '<button type="button" class="btn btn-xs btn-outline-danger cart-remove" data-stock-id="' + c.stock.stock_id + '" title="Hapus">' +
                    '<i class="fas fa-times"></i>' +
                '</button>' +
            '</div>' +
        '</div>';
    }).join('');

    $('#modalCartItems').html(html);
    $('#modalCartWrap').removeClass('d-none');
}

/* ── Modal events ────────────────────────────────────── */
$(document).on('click', '#modalStockRows tr', function() {
    const id    = parseInt($(this).data('stock-id'), 10);
    const stock = sourceStocks.find(function(s) { return s.stock_id === id; });
    if (!stock) return;

    // Jika sudah di cart, klik untuk remove
    const cartIdx = selectedCart.findIndex(function(c) { return c.stock.stock_id === id; });
    if (cartIdx !== -1) {
        selectedCart.splice(cartIdx, 1);
        pickingStock = null;
        $('#modalQtyArea').addClass('d-none');
        renderModalRows();
        renderModalCart();
        return;
    }

    // Set picking mode
    pickingStock = stock;
    $('#modalQtyItemName').html(
        '<div>' + escapeHtml(stock.name) + '</div>' +
        '<div class="text-muted" style="font-size:11px;font-weight:400;">' + escapeHtml(stock.sku) + '</div>'
    );
    $('#modalQtyMax').text(numberFmt(stock.quantity) + ' ' + stock.unit);
    $('#modalQtyInput').val('').attr('max', stock.quantity).attr('min', 1);
    $('#modalQtyArea').removeClass('d-none');
    renderModalRows();
    setTimeout(function() { $('#modalQtyInput').focus().select(); }, 100);
});

function addPickingToCart() {
    if (!pickingStock) return;
    const qty = parseInt($('#modalQtyInput').val(), 10);
    if (!qty || qty < 1) { $('#modalQtyInput').focus().select(); return; }
    if (qty > pickingStock.quantity) {
        $('#modalQtyInput').addClass('is-invalid').focus().select();
        setTimeout(function() { $('#modalQtyInput').removeClass('is-invalid'); }, 1000);
        return;
    }
    // Tambah atau update di cart
    const cartIdx = selectedCart.findIndex(function(c) { return c.stock.stock_id === pickingStock.stock_id; });
    if (cartIdx !== -1) selectedCart[cartIdx].qty = qty;
    else selectedCart.push({ stock: pickingStock, qty: qty });

    pickingStock = null;
    $('#modalQtyArea').addClass('d-none');
    $('#modalQtyInput').val('');
    renderModalRows();
    renderModalCart();
    beepSound('success');
}

$('#modalBtnAdd').on('click', addPickingToCart);

$('#modalQtyInput').on('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); addPickingToCart(); }
});

$('#modalBtnAll').on('click', function() {
    if (!pickingStock) return;
    $('#modalQtyInput').val(pickingStock.quantity);
    addPickingToCart();
});

$(document).on('click', '.cart-remove', function(e) {
    e.stopPropagation();
    const id = parseInt($(this).data('stock-id'), 10);
    selectedCart = selectedCart.filter(function(c) { return c.stock.stock_id !== id; });
    renderModalRows();
    renderModalCart();
});

$('#modalBtnDone').on('click', function() {
    if (!selectedCart.length) return;
    $('#modalPickItem').modal('hide');
    finalizeCartSelection();
});

$('#btnReEditCart').on('click', function() {
    openPickItemModal();
});


/* ── Finalize cart → scan target ─────────────────────── */
function finalizeCartSelection() {
    const totalQty   = selectedCart.reduce(function(t, c) { return t + c.qty; }, 0);
    const totalJenis = selectedCart.length;

    // Update strip
    $('#sValItem').text(totalJenis + ' item');
    $('#sValQty').text(numberFmt(totalQty) + ' unit');

    setPhase('target');
}

/* ── Execute transfer (batch) ────────────────────────── */
async function executeTransfer(target) {
    if (!sourceCell || !selectedCart.length) { setAlert('danger', 'Data transfer belum lengkap.'); return; }

    // Update kolom "Tujuan" di tabel ringkasan
    $('#previewToHeader').html('Tujuan <span class="badge badge-success" style="font-size:10px;">' + escapeHtml(target.code) + '</span>');
    selectedCart.forEach(function(c) {
        $('#previewTo_' + c.stock.stock_id).html('<span class="badge badge-success" style="font-size:10px;">' + escapeHtml(target.code) + '</span>');
    });
    $('#targetCellText').text(target.code);
    $('#scanInput').removeClass('scan-pulse').prop('disabled', true);
    $('#resultPanel').html('<div class="result-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Menyimpan ' + selectedCart.length + ' item...</div>');

    const errors = [];
    const done   = [];

    for (let i = 0; i < selectedCart.length; i++) {
        const item = selectedCart[i];
        try {
            await $.ajax({
                url: transferUrl, method: 'POST',
                data: {
                    _token:     csrfToken,
                    stock_id:   item.stock.stock_id,
                    to_cell_id: target.id,
                    quantity:   item.qty,
                    notes: 'Scan transfer: ' + (item.stock.cell_code || sourceCell.code) + ' -> ' + target.code,
                },
            });
            done.push(item);
        } catch(xhr) {
            const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Gagal';
            errors.push(escapeHtml(item.stock.name) + ': ' + escapeHtml(msg));
        }
    }

    if (errors.length === 0) {
        beepSound('success'); flashScreen('success');
        $('#sValTarget').text(target.code);
        const itemLines = done.map(function(c) {
            return '&nbsp;&nbsp;· ' + escapeHtml(c.stock.sku) + ' &nbsp;<strong>' + numberFmt(c.qty) + ' ' + escapeHtml(c.stock.unit) + '</strong>';
        }).join('<br>');
        Swal.fire({
            icon: 'success',
            title: 'Transfer Berhasil!',
            html: '<div style="font-size:14px;text-align:left;">' +
                '<div class="mb-2"><i class="fas fa-map-marker-alt mr-1"></i>' +
                escapeHtml(sourceCell ? sourceCell.code : '—') + ' <strong>→</strong> ' + escapeHtml(target.code) + '</div>' +
                '<div>' + itemLines + '</div>' +
                '</div>',
            confirmButtonColor: '#0d8564',
            confirmButtonText: '<i class="fas fa-check mr-1"></i>OK',
            customClass: { popup: 'swal2-sm' },
        }).then(function() { resetFlow(); });
    } else {
        beepSound('error'); flashScreen('error');
        $('#resultPanel').html(
            '<div class="result-error">' +
                (done.length ? '<div class="mb-1"><i class="fas fa-check-circle text-success mr-1"></i>' + done.length + ' item berhasil.</div>' : '') +
                '<div><i class="fas fa-times-circle mr-1"></i>' + errors.length + ' item gagal:</div>' +
                errors.map(function(e) { return '<div class="small mt-1">· ' + e + '</div>'; }).join('') +
            '</div>'
        );
        setPhase('target');
    }
}

/* ── Scan handler ────────────────────────────────────── */
function handleScan(code) {
    code = cleanCode(code);
    if (!code) return;
    clearAlert();

    if (phase === 'source') {
        lookupCell(code, 'source').done(function(res) {
            sourceCell   = res.cell;
            sourceStocks = res.stocks || [];
            selectedCart = [];
            $('#stepSource').addClass('done').removeClass('active');
            $('#sourceCellText').text(sourceCell.code);
            $('#sValSource').text(sourceCell.code);
            if (!sourceStocks.length) {
                setAlert('warning', 'Cell ' + sourceCell.code + ' tidak memiliki stok available.');
                setPhase('source'); return;
            }
            if (sourceStocks.length === 1) {
                // Auto-pick, langsung ke qty di modal
                selectedCart = [];
                openPickItemModal();
                // Auto-click baris pertama setelah modal terbuka
                $('#modalPickItem').one('shown.bs.modal', function() {
                    $('#modalStockRows tr:first').trigger('click');
                });
                return;
            }
            openPickItemModal();
        }).fail(function(xhr) {
            beepSound('error');
            setAlert('danger', escapeHtml(xhr.responseJSON?.message || 'Cell asal tidak ditemukan.'));
            setPhase('source');
        });
        return;
    }

    if (phase === 'target') {
        if (pendingTargetCell) {
            if (cleanCode(code).toUpperCase() === pendingTargetCell.code.toUpperCase()) {
                const cell = pendingTargetCell; pendingTargetCell = null; executeTransfer(cell);
            } else {
                beepSound('error');
                setAlert('danger', 'Cell tidak cocok. Scan QR <strong>' + escapeHtml(pendingTargetCell.code) + '</strong>.');
            }
            return;
        }
        lookupCell(code, 'target').done(function(res) {
            if (res.is_column_target) showColumnTargetPicker(res);
            else executeTransfer(res.cell);
        }).fail(function(xhr) {
            beepSound('error');
            setAlert('danger', escapeHtml(xhr.responseJSON?.message || 'Cell tujuan tidak ditemukan.'));
            setPhase('target');
        });
    }
}

/* ── Riwayat Transfer DataTable ──────────────────────── */
$('#btnRiwayat').on('click', function() {
    $('#modalRiwayat').modal('show');
});

$('#modalRiwayat').on('shown.bs.modal', function() {
    if (riwayatTable) { riwayatTable.ajax.reload(); return; }
    riwayatTable = $('#tblRiwayat').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("stock.movements") }}',
            data: function(d) { d.type = 'transfer'; },
            error: function(xhr) {
                if (xhr.status === 401 || xhr.status === 419) window.location.reload();
            }
        },
        columns: [
            { data: 'DT_RowIndex',   orderable: false, searchable: false, className: 'text-center text-muted' },
            { data: 'date_display',  name: 'moved_at' },
            { data: 'item_info',     name: 'item_info', orderable: false },
            { data: 'from_display',  name: 'from_display', orderable: false, className: 'text-center' },
            { data: 'to_display',    name: 'to_display', orderable: false, className: 'text-center' },
            { data: 'qty_display',   name: 'qty_display', orderable: false, className: 'text-center' },
            { data: 'by_display',    name: 'by_display', orderable: false },
        ],
        order: [[1, 'desc']],
        pageLength: 15,
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
    });
});

$('#modalRiwayat').on('hidden.bs.modal', function() {
    setTimeout(function() { $('#scanInput').focus(); }, 100);
});

/* ── Baris picker (modal) ────────────────────────────── */
function showColumnTargetPicker(res) {
    $('#modalBarisColumnCode').text(res.column_code);
    const html = res.child_cells.map(function(c) {
        const isFull     = c.capacity_remaining <= 0 || c.status === 'full' || c.status === 'blocked';
        const used       = c.capacity_max - c.capacity_remaining;
        // Bar menunjukkan % TERISI (bukan sisa) — semakin penuh semakin panjang
        const fillPct    = c.capacity_max > 0 ? Math.round((used / c.capacity_max) * 100) : 0;
        const capColor   = fillPct >= 100 ? '#dc3545' : fillPct >= 75 ? '#ffc107' : '#0d8564';
        const barisStyle = isFull
            ? 'opacity:.55;cursor:not-allowed;background:#f8f9fa;'
            : '';
        const penuhBadge = isFull
            ? '<span class="badge badge-danger ml-2" style="font-size:11px;">PENUH</span>'
            : '';
        const sisaText   = isFull
            ? '<span class="text-danger font-weight-bold">Penuh</span>'
            : '<span class="font-weight-bold">' + c.capacity_remaining + ' sisa</span>';

        return '<div class="baris-option ' + (isFull ? 'baris-full' : '') + '"' +
            ' data-cell-id="' + c.id + '" data-cell-code="' + escapeHtml(c.code) + '"' +
            ' data-qr-code="' + escapeHtml(c.qr_code || c.code) + '"' +
            ' data-cap-remaining="' + c.capacity_remaining + '" data-cap-max="' + c.capacity_max + '"' +
            ' style="' + barisStyle + '">' +
            '<div class="d-flex align-items-center flex-grow-1">' +
                '<span class="baris-num">' + c.baris + '</span>' +
                '<div>' +
                    '<div class="font-weight-bold">' + escapeHtml(c.code) + penuhBadge + '</div>' +
                    '<div class="cap-bar" style="width:160px">' +
                        '<div class="cap-bar-fill" style="width:' + Math.min(fillPct, 100) + '%;background:' + capColor + '"></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="text-right ml-3">' +
                '<div class="small text-muted">Terisi ' + fillPct + '%</div>' +
                '<div>' + sisaText + ' <span class="text-muted" style="font-size:12px;">/ ' + c.capacity_max + '</span></div>' +
            '</div>' +
        '</div>';
    }).join('');
    $('#modalBarisRows').html(html || '<div class="text-muted text-center py-3">Tidak ada baris tersedia.</div>');
    $('#modalPickBaris').modal('show');
}

$(document).on('click', '.baris-option', function() {
    if ($(this).hasClass('baris-full')) return; // blokir klik cell penuh
    pendingTargetCell = {
        id:                 parseInt($(this).data('cell-id')),
        code:               $(this).data('cell-code'),
        qr_code:            $(this).data('qr-code') || $(this).data('cell-code'),
        capacity_remaining: parseInt($(this).data('cap-remaining')),
        capacity_max:       parseInt($(this).data('cap-max')),
    };
    $('#modalPickBaris').modal('hide');
    const qrEl = document.getElementById('qrCanvas');
    qrEl.innerHTML = '<canvas id="qrCanvas_inner"></canvas>';
    new QRious({
        element: document.getElementById('qrCanvas_inner'),
        value: cellBaseUrl + '/' + pendingTargetCell.qr_code,
        size: 180, level: 'H', background: '#ffffff', foreground: '#1a2332', padding: 4,
    });
    $('#qrConfirmLabel').text('Scan QR cell ' + pendingTargetCell.code);
    $('#qrCellCodeLabel').text(pendingTargetCell.code);
    $('#qrConfirmSection').removeClass('d-none');
    $('#scanLabel').text('Scan QR cell ' + pendingTargetCell.code + ' untuk konfirmasi');
    $('#scanInput').val('').focus();
});

$('#modalPickBaris').on('hidden.bs.modal', function() {
    setTimeout(function() { $('#scanInput').focus(); }, 100);
});

/* ── Reset ───────────────────────────────────────────── */
function resetFlow() {
    sourceCell = null; sourceStocks = []; selectedCart = []; pickingStock = null;
    $('#sourceCellText, #itemText, #qtyText, #targetCellText').text('');
    $('#resultPanel').html('');
    $('#transferPreviewWrap').addClass('d-none');
    $('#scanSection').removeClass('d-none');
    $('#qtyCard').addClass('d-none');
    $('#columnTargetPicker, #qrConfirmSection').addClass('d-none');
    $('#qrCanvas').empty();
    pendingTargetCell = null;
    $('.transfer-step').removeClass('done active');
    $('#sValSource').text('scan cell');
    $('#sValItem, #sValQty, #sValTarget').text('—');
    clearAlert();
    setPhase('source');
}

/* ── Event handlers ──────────────────────────────────── */
$('#scanInput').on('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); const code = $(this).val(); $(this).val(''); handleScan(code); }
});

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') { e.preventDefault(); resetFlow(); }
});

$('#btnResetFlow').on('click', resetFlow);

$('#btnCamera').on('click', async function() {
    if (cameraScanner) {
        try { await cameraScanner.stop(); } catch(e) {}
        try { await cameraScanner.clear(); } catch(e) {}
        cameraScanner = null;
        $('#cameraWrap').addClass('d-none');
        $(this).html('<i class="fas fa-camera mr-1"></i>Kamera');
        $('#scanInput').focus(); return;
    }
    $('#cameraWrap').removeClass('d-none');
    cameraScanner = new Html5Qrcode('cameraReader');
    $(this).html('<i class="fas fa-stop mr-1"></i>Stop Kamera');
    try {
        await cameraScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: function(w, h) { const s = Math.round(Math.min(w, h) * 0.72); return { width: s, height: s }; } },
            function(decodedText) {
                if (decodedText === lastCameraCode) return;
                lastCameraCode = decodedText;
                setTimeout(function() { lastCameraCode = ''; }, 1500);
                handleScan(decodedText);
            },
            function() {}
        );
    } catch(e) {
        setAlert('danger', 'Kamera tidak bisa dibuka.');
        $('#cameraWrap').addClass('d-none');
        cameraScanner = null;
        $('#btnCamera').html('<i class="fas fa-camera mr-1"></i>Kamera');
    }
});

// Auto-focus scan input saat modal ditutup (tombol X / Batal)
$('#modalPickItem').on('hidden.bs.modal', function() {
    setTimeout(function() { $('#scanInput').focus(); }, 100);
});

$(function() { setPhase('source'); });
</script>
@endpush
