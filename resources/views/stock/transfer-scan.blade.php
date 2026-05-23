@extends('layouts.adminlte')
@section('title', 'Scan Transfer Stok')

@push('styles')
<style>
.transfer-step {
    border-left: 4px solid #d1d5db;
    background: #fff;
    border-radius: 6px;
    padding: 12px 14px;
    min-height: 82px;
}
.transfer-step.active { border-left-color: #28a745; box-shadow: 0 0 0 1px rgba(40,167,69,.12); }
.transfer-step.done   { border-left-color: #17a2b8; background: #f8fffb; }
.scan-input {
    height: 58px;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0;
}
.qty-input-lg {
    height: 52px;
    font-size: 24px;
    font-weight: 700;
    text-align: center;
}
.stock-choice {
    cursor: pointer;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 14px;
    background: #fff;
    min-height: 60px;
    transition: border-color .12s, background .12s;
}
.stock-choice:hover,
.stock-choice.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.kb-badge {
    display: inline-block;
    min-width: 28px;
    height: 28px;
    line-height: 28px;
    text-align: center;
    border-radius: 6px;
    background: #e9ecef;
    color: #495057;
    font-size: 13px;
    font-weight: 700;
    margin-right: 8px;
    flex-shrink: 0;
}
.stock-choice.selected .kb-badge {
    background: #28a745;
    color: #fff;
}
.result-panel {
    min-height: 72px;
}
.kb-hint {
    font-size: 11px;
    color: #6c757d;
    background: #f8f9fa;
    border-radius: 4px;
    padding: 4px 8px;
    margin-top: 6px;
}
.kb-hint kbd {
    background: #dee2e6;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 11px;
}
.history-row {
    border-bottom: 1px solid #f0f0f0;
    padding: 5px 0;
    font-size: 12px;
}
.history-row:last-child { border-bottom: none; }
#cameraReader video  { width: 100% !important; border-radius: 6px; }
#cameraReader img    { display: none; }

@keyframes pageFlash {
    0%   { opacity: .6; }
    100% { opacity: 0; }
}
.flash-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    pointer-events: none;
    animation: pageFlash .35s ease-out forwards;
}
</style>
@endpush

@section('content')
<div class="container-fluid pb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-qrcode text-success mr-2"></i>Scan Transfer Stok
            </h5>
            <small class="text-muted">Scan cell asal → scan item → qty → scan cell tujuan. Scanner otomatis setelah Enter.</small>
        </div>
        <a href="{{ route('stock.movements') }}" class="btn btn-sm btn-outline-info">
            <i class="fas fa-history mr-1"></i>Mutasi Stok
        </a>
    </div>

    <div id="scanAlert" class="alert d-none" role="alert"></div>

    <div class="row">
        {{-- LEFT: Scanner input --}}
        <div class="col-lg-5 mb-3">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-barcode mr-1"></i>Scanner</strong>
                    <span class="badge badge-success" id="phaseBadge">1. Scan Cell Asal</span>
                </div>
                <div class="card-body">
                    <label class="small font-weight-bold" id="scanLabel">Scan QR / barcode cell asal</label>
                    <input type="text" id="scanInput" class="form-control scan-input"
                        autocomplete="off" inputmode="text" placeholder="Arahkan scanner lalu Enter">
                    <div class="kb-hint mt-1" id="kbHintScan">
                        <kbd>Enter</kbd> kirim scan &nbsp;&nbsp; <kbd>Esc</kbd> reset
                    </div>
                    <div class="d-flex flex-wrap mt-2" style="gap:6px;">
                        <button type="button" id="btnResetFlow" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-redo mr-1"></i>Reset <kbd class="ml-1" style="font-size:10px">Esc</kbd>
                        </button>
                        <button type="button" id="btnCamera" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-camera mr-1"></i>Kamera
                        </button>
                    </div>
                    <div id="cameraWrap" class="mt-3 d-none">
                        <div id="cameraReader"></div>
                    </div>
                </div>
            </div>

            {{-- Qty card (always visible, enabled only on qty phase) --}}
            <div class="card" id="qtyCard">
                <div class="card-header py-2">
                    <strong><i class="fas fa-cubes mr-1"></i>Qty Transfer</strong>
                </div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="text" id="qtyInput" class="form-control qty-input-lg"
                            inputmode="numeric" autocomplete="off" placeholder="—" disabled>
                        <div class="input-group-append">
                            <button type="button" id="btnAllQty" class="btn btn-outline-primary btn-lg px-3" disabled>
                                All
                            </button>
                        </div>
                    </div>
                    <div class="kb-hint mt-1" id="kbHintQty">
                        <kbd>Enter</kbd> konfirmasi &nbsp;&nbsp; <kbd>*</kbd> atau <kbd>A</kbd> = semua qty
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Status steps + stocks + result --}}
        <div class="col-lg-7 mb-3">
            {{-- 4 step cards --}}
            <div class="row mb-2">
                <div class="col-6 mb-2">
                    <div class="transfer-step active" id="stepSource">
                        <div class="small text-muted font-weight-bold">CELL ASAL</div>
                        <div class="h5 mb-1" id="sourceCellText">Belum discan</div>
                        <div class="small text-muted" id="sourceMeta">-</div>
                    </div>
                </div>
                <div class="col-6 mb-2">
                    <div class="transfer-step" id="stepItem">
                        <div class="small text-muted font-weight-bold">ITEM</div>
                        <div class="h5 mb-1" id="itemText">Belum dipilih</div>
                        <div class="small text-muted" id="itemMeta">-</div>
                    </div>
                </div>
                <div class="col-6 mb-2">
                    <div class="transfer-step" id="stepQty">
                        <div class="small text-muted font-weight-bold">QTY</div>
                        <div class="h5 mb-1" id="qtyText">Belum diisi</div>
                        <div class="small text-muted">Qty unit barang asli</div>
                    </div>
                </div>
                <div class="col-6 mb-2">
                    <div class="transfer-step" id="stepTarget">
                        <div class="small text-muted font-weight-bold">CELL TUJUAN</div>
                        <div class="h5 mb-1" id="targetCellText">Belum discan</div>
                        <div class="small text-muted" id="targetMeta">Transfer otomatis setelah scan.</div>
                    </div>
                </div>
            </div>

            {{-- Stok di cell asal --}}
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-list mr-1"></i>Stok di Cell Asal</strong>
                    <small class="text-muted kb-hint mb-0 py-0" id="itemKbHint" style="display:none!important">
                        Tekan <kbd>1</kbd>–<kbd>9</kbd> pilih item
                    </small>
                </div>
                <div class="card-body p-2" id="sourceStocks">
                    <div class="text-muted text-center py-4">Scan cell asal untuk melihat stok.</div>
                </div>
            </div>

            {{-- Last result + history --}}
            <div class="card result-panel mt-2">
                <div class="card-body pb-1" id="resultPanel">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle mr-1"></i>
                        Urutan: scan cell asal → scan barcode item → qty <kbd>Enter</kbd> → scan cell tujuan.
                    </div>
                </div>
            </div>

            <div class="card mt-2 d-none" id="historyCard">
                <div class="card-header py-2">
                    <strong><i class="fas fa-history mr-1 text-info"></i>Transfer Terakhir</strong>
                </div>
                <div class="card-body p-2" id="historyPanel"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script>
const cellLookupUrl = '{{ route("stock.transfer-scan.cell") }}';
const transferUrl   = '{{ route("stock.transfer") }}';
const csrfToken     = '{{ csrf_token() }}';

let phase         = 'source';
let sourceCell    = null;
let sourceStocks  = [];
let selectedStock = null;
let lastCameraCode = '';
let cameraScanner  = null;
let recentTransfers = [];

/* ── Audio feedback ─────────────────────────────────────────── */
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

/* ── Helpers ────────────────────────────────────────────────── */
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

function numberFmt(value) { return new Intl.NumberFormat('id-ID').format(value || 0); }

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
}

function timeAgo(date) {
    const s = Math.round((new Date() - date) / 1000);
    if (s < 60)  return s + 'd lalu';
    if (s < 3600) return Math.floor(s / 60) + 'm lalu';
    return Math.floor(s / 3600) + 'j lalu';
}

/* ── Phase management ───────────────────────────────────────── */
function setPhase(nextPhase) {
    phase = nextPhase;
    $('.transfer-step').removeClass('active');

    const labels = {
        source: ['1. Scan Cell Asal',    'Scan QR / barcode cell asal'],
        item:   ['2. Scan / Pilih Item', 'Scan barcode item atau tekan 1–9 untuk memilih'],
        qty:    ['3. Isi Qty',           'Masukkan jumlah transfer lalu Enter'],
        target: ['4. Scan Cell Tujuan',  'Scan QR / barcode cell tujuan'],
    };

    $('#phaseBadge').text(labels[nextPhase][0]);
    $('#scanLabel').text(labels[nextPhase][1]);

    if (nextPhase === 'source') $('#stepSource').addClass('active');
    if (nextPhase === 'item')   { $('#stepItem').addClass('active'); $('#itemKbHint').show(); }
    else                        { $('#itemKbHint').hide(); }
    if (nextPhase === 'qty')    $('#stepQty').addClass('active');
    if (nextPhase === 'target') $('#stepTarget').addClass('active');

    if (nextPhase === 'qty') {
        $('#qtyInput').prop('disabled', false).val('').focus().select();
        $('#btnAllQty').prop('disabled', false);
        $('#scanInput').prop('disabled', true);
    } else {
        $('#qtyInput').prop('disabled', true);
        $('#btnAllQty').prop('disabled', true);
        $('#scanInput').prop('disabled', false).val('').focus();
    }
}

/* ── Cell lookup ────────────────────────────────────────────── */
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

/* ── Stock rendering ────────────────────────────────────────── */
function renderSourceStocks() {
    if (!sourceStocks.length) {
        $('#sourceStocks').html('<div class="text-danger text-center py-4">Cell ini tidak memiliki stok available.</div>');
        return;
    }

    const html = sourceStocks.map(function (stock, idx) {
        const selected = selectedStock && selectedStock.stock_id === stock.stock_id ? ' selected' : '';
        const kbNum    = idx < 9 ? (idx + 1) : '';
        return '<div class="stock-choice mb-2 d-flex align-items-center' + selected + '" data-stock-id="' + stock.stock_id + '">' +
            (kbNum ? '<span class="kb-badge">' + kbNum + '</span>' : '') +
            '<div class="flex-grow-1">' +
                '<div class="font-weight-bold">' + escapeHtml(stock.name) + '</div>' +
                '<div class="small text-muted">' + escapeHtml(stock.sku) + (stock.barcode ? ' | ' + escapeHtml(stock.barcode) : '') + '</div>' +
            '</div>' +
            '<div class="text-right ml-2">' +
                '<div class="h6 mb-0 text-success">' + numberFmt(stock.quantity) + ' ' + escapeHtml(stock.unit) + '</div>' +
            '</div>' +
        '</div>';
    }).join('');

    $('#sourceStocks').html(html);
}

function selectStock(stock) {
    selectedStock = stock;
    $('#stepItem').addClass('done').removeClass('active');
    $('#itemText').text(stock.name);
    $('#itemMeta').text(stock.sku + ' | tersedia ' + numberFmt(stock.quantity) + ' ' + stock.unit);
    $('#qtyInput').attr('max', stock.quantity).val('');
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

/* ── Qty confirm ────────────────────────────────────────────── */
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

/* ── Execute transfer ───────────────────────────────────────── */
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

    $('#scanInput').prop('disabled', true);
    $('#targetCellText').text(target.code);
    const resolved = target.resolved_from_rack ? ' | dari rak ' + target.resolved_from_rack : '';
    $('#targetMeta').text('Kapasitas sisa: ' + target.capacity_remaining + '/' + target.capacity_max + ' ' + target.capacity_unit + resolved);
    $('#resultPanel').html('<div class="text-info"><i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan transfer...</div>');

    $.ajax({
        url: transferUrl,
        method: 'POST',
        data: {
            _token:    csrfToken,
            stock_id:  selectedStock.stock_id,
            to_cell_id: target.id,
            quantity:  qty,
            notes: 'Scan transfer: ' + (selectedStock.cell_code || sourceCell.code) + ' -> ' + target.code,
        },
        success: function (res) {
            $('#stepTarget').addClass('done');
            beepSound('success');
            flashScreen('success');
            const fromCode = selectedStock.cell_code || sourceCell.code;
            $('#resultPanel').html(
                '<div class="text-success font-weight-bold mb-1">' +
                    '<i class="fas fa-check-circle mr-1"></i>' + escapeHtml(res.message || 'Transfer berhasil.') +
                '</div>' +
                '<div class="small text-muted">' +
                    escapeHtml(selectedStock.sku) + ' | ' + numberFmt(qty) + ' ' + escapeHtml(selectedStock.unit) +
                    ' | ' + escapeHtml(fromCode) + ' → ' + escapeHtml(target.code) +
                '</div>'
            );
            addToHistory(selectedStock.sku, selectedStock.name, qty, selectedStock.unit, fromCode, target.code);
            setTimeout(resetFlow, 900);
        },
        error: function (xhr) {
            const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Transfer gagal.';
            beepSound('error');
            flashScreen('error');
            $('#resultPanel').html('<div class="text-danger"><i class="fas fa-times-circle mr-1"></i>' + escapeHtml(msg) + '</div>');
            setAlert('danger', escapeHtml(msg));
            setPhase('target');
        },
    });
}

/* ── Scan handler ───────────────────────────────────────────── */
function handleScan(code) {
    code = cleanCode(code);
    if (!code) return;
    clearAlert();

    if (phase === 'source') {
        lookupCell(code, 'source').done(function (res) {
            sourceCell   = res.cell;
            sourceStocks = res.stocks || [];
            selectedStock = null;
            $('#stepSource').addClass('done').removeClass('active');
            $('#sourceCellText').text(sourceCell.code);
            $('#sourceMeta').text('Stok: ' + sourceStocks.length + ' | Kapasitas: ' + sourceCell.capacity_used + '/' + sourceCell.capacity_max + ' ' + sourceCell.capacity_unit);
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
            setAlert('warning', 'Item ' + escapeHtml(code) + ' tidak ada di cell asal ' + sourceCell.code + '.');
            setPhase('item');
            return;
        }
        selectStock(stock);
        return;
    }

    if (phase === 'target') {
        lookupCell(code, 'target').done(function (res) {
            executeTransfer(res.cell);
        }).fail(function (xhr) {
            beepSound('error');
            setAlert('danger', escapeHtml(xhr.responseJSON?.message || 'Cell tujuan tidak ditemukan.'));
            setPhase('target');
        });
    }
}

/* ── Transfer history ───────────────────────────────────────── */
function addToHistory(sku, name, qty, unit, fromCode, toCode) {
    recentTransfers.unshift({ sku: sku, name: name, qty: qty, unit: unit, from: fromCode, to: toCode, time: new Date() });
    if (recentTransfers.length > 8) recentTransfers.pop();
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

/* ── Reset ──────────────────────────────────────────────────── */
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
    $('#targetMeta').text('Transfer otomatis setelah scan.');
    $('#qtyInput').val('').prop('disabled', true).removeAttr('max');
    $('#btnAllQty').prop('disabled', true);
    $('#sourceStocks').html('<div class="text-muted text-center py-4">Scan cell asal untuk melihat stok.</div>');
    $('#resultPanel').html('<div class="text-muted small"><i class="fas fa-info-circle mr-1"></i>Urutan: scan cell asal → scan barcode item → qty <kbd>Enter</kbd> → scan cell tujuan.</div>');
    $('.transfer-step').removeClass('done active');
    clearAlert();
    setPhase('source');
    renderHistory();
}

/* ── Event handlers ─────────────────────────────────────────── */
$('#scanInput').on('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = $(this).val();
        $(this).val('');
        handleScan(code);
    }
});

$('#qtyInput').on('keydown', function (e) {
    // * or A = fill all qty immediately
    if (e.key === '*' || e.key.toLowerCase() === 'a') {
        e.preventDefault();
        if (selectedStock) {
            $(this).val(selectedStock.quantity);
            confirmQty();
        }
        return;
    }

    if (e.key === 'Enter') {
        e.preventDefault();
        const raw = $(this).val().trim();
        // If scanner fired into qty field (contains non-numeric chars) → treat as scan
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

// Global keyboard shortcuts
$(document).on('keydown', function (e) {
    // Escape = reset from any phase
    if (e.key === 'Escape') {
        e.preventDefault();
        resetFlow();
        return;
    }

    // 1–9 = select item by index when in item phase
    if (phase === 'item' && e.key >= '1' && e.key <= '9' && !$(e.target).is('input, textarea')) {
        const idx = parseInt(e.key, 10) - 1;
        if (idx < sourceStocks.length) {
            selectStock(sourceStocks[idx]);
        }
        return;
    }
});

// Also allow 1–9 on scanInput when in item phase (scanner might send digit-only barcodes)
$('#scanInput').on('keyup', function (e) {
    if (phase !== 'item') return;
    const val = $(this).val().trim();
    // Single digit typed → select by index shortcut
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
