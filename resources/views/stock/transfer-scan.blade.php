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
.transfer-step.done { border-left-color: #17a2b8; background: #f8fffb; }
.scan-input {
    height: 58px;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0;
}
.stock-choice {
    cursor: pointer;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 10px 12px;
    background: #fff;
}
.stock-choice:hover,
.stock-choice.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.result-panel {
    min-height: 86px;
}
#cameraReader video {
    width: 100% !important;
    border-radius: 6px;
}
#cameraReader img { display: none; }
</style>
@endpush

@section('content')
<div class="container-fluid pb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-qrcode text-success mr-2"></i>Scan Transfer Stok
            </h5>
            <small class="text-muted">Scan cell asal, scan item bila perlu, isi qty, lalu scan cell tujuan.</small>
        </div>
        <a href="{{ route('stock.movements') }}" class="btn btn-sm btn-outline-info">
            <i class="fas fa-history mr-1"></i>Mutasi Stok
        </a>
    </div>

    <div id="scanAlert" class="alert d-none" role="alert"></div>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card">
                <div class="card-header py-2">
                    <strong><i class="fas fa-barcode mr-1"></i>Scanner</strong>
                    <span class="badge badge-success float-right" id="phaseBadge">1. Scan Cell Asal</span>
                </div>
                <div class="card-body">
                    <label class="small font-weight-bold" id="scanLabel">Scan QR / barcode cell asal</label>
                    <input type="text" id="scanInput" class="form-control scan-input"
                        autocomplete="off" inputmode="text" placeholder="Arahkan scanner lalu Enter">
                    <div class="d-flex flex-wrap mt-2" style="gap:6px;">
                        <button type="button" id="btnResetFlow" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-redo mr-1"></i>Reset
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

            <div class="card">
                <div class="card-header py-2">
                    <strong><i class="fas fa-cubes mr-1"></i>Qty Transfer</strong>
                </div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="number" id="qtyInput" class="form-control form-control-lg"
                            min="1" placeholder="Isi qty" disabled>
                        <div class="input-group-append">
                            <button type="button" id="btnAllQty" class="btn btn-outline-primary" disabled>All</button>
                        </div>
                    </div>
                    <small class="text-muted">Tekan Enter setelah qty. Jika full transfer, pakai All.</small>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <div class="transfer-step active" id="stepSource">
                        <div class="small text-muted font-weight-bold">CELL ASAL</div>
                        <div class="h5 mb-1" id="sourceCellText">Belum discan</div>
                        <div class="small text-muted" id="sourceMeta">-</div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="transfer-step" id="stepItem">
                        <div class="small text-muted font-weight-bold">ITEM</div>
                        <div class="h5 mb-1" id="itemText">Belum dipilih</div>
                        <div class="small text-muted" id="itemMeta">-</div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="transfer-step" id="stepQty">
                        <div class="small text-muted font-weight-bold">QTY</div>
                        <div class="h5 mb-1" id="qtyText">Belum diisi</div>
                        <div class="small text-muted">Qty tetap unit barang, kapasitas dihitung sebagai poin dari max stock SKU.</div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="transfer-step" id="stepTarget">
                        <div class="small text-muted font-weight-bold">CELL TUJUAN</div>
                        <div class="h5 mb-1" id="targetCellText">Belum discan</div>
                        <div class="small text-muted" id="targetMeta">Transfer otomatis setelah scan tujuan.</div>
                    </div>
                </div>
            </div>

            <div class="card mt-2">
                <div class="card-header py-2">
                    <strong><i class="fas fa-list mr-1"></i>Stok di Cell Asal</strong>
                </div>
                <div class="card-body p-2" id="sourceStocks">
                    <div class="text-muted text-center py-4">Scan cell asal untuk melihat stok.</div>
                </div>
            </div>

            <div class="card result-panel">
                <div class="card-body" id="resultPanel">
                    <div class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Urutan tercepat: scan cell asal -> scan barcode/SKU item -> qty Enter -> scan cell tujuan.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script>
const cellLookupUrl = '{{ route("stock.transfer-scan.cell") }}';
const transferUrl = '{{ route("stock.transfer") }}';
const csrfToken = '{{ csrf_token() }}';

let phase = 'source';
let sourceCell = null;
let sourceStocks = [];
let selectedStock = null;
let lastCameraCode = '';
let cameraScanner = null;

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

function clearAlert() {
    $('#scanAlert').addClass('d-none').html('');
}

function setPhase(nextPhase) {
    phase = nextPhase;
    $('.transfer-step').removeClass('active');

    const labels = {
        source: ['1. Scan Cell Asal', 'Scan QR / barcode cell asal'],
        item: ['2. Scan Item', 'Scan barcode / SKU item dari cell asal'],
        qty: ['3. Isi Qty', 'Isi jumlah transfer lalu Enter'],
        target: ['4. Scan Cell Tujuan', 'Scan QR / barcode cell tujuan']
    };

    $('#phaseBadge').text(labels[phase][0]);
    $('#scanLabel').text(labels[phase][1]);

    if (phase === 'source') $('#stepSource').addClass('active');
    if (phase === 'item') $('#stepItem').addClass('active');
    if (phase === 'qty') $('#stepQty').addClass('active');
    if (phase === 'target') $('#stepTarget').addClass('active');

    if (phase === 'qty') {
        $('#qtyInput').prop('disabled', false).focus().select();
    } else {
        $('#scanInput').prop('disabled', false).val('').focus();
    }
}

function lookupCell(code, purpose) {
    const data = { code: cleanCode(code), purpose: purpose || 'source' };
    if (purpose === 'target' && selectedStock) {
        data.stock_id = selectedStock.stock_id;
    }

    return $.get(cellLookupUrl, data).then(function(res) {
        if (!res.found) throw new Error(res.message || 'Cell tidak ditemukan.');
        return res;
    });
}

function renderSourceStocks() {
    if (!sourceStocks.length) {
        $('#sourceStocks').html('<div class="text-danger text-center py-4">Cell ini tidak memiliki stok available.</div>');
        return;
    }

    const html = sourceStocks.map(function(stock) {
        const selected = selectedStock && selectedStock.stock_id === stock.stock_id ? ' selected' : '';
        return '<div class="stock-choice mb-2' + selected + '" data-stock-id="' + stock.stock_id + '">' +
            '<div class="d-flex justify-content-between align-items-start" style="gap:8px;">' +
                '<div>' +
                    '<div class="font-weight-bold">' + escapeHtml(stock.name) + '</div>' +
                    '<div class="small text-muted">' + escapeHtml(stock.sku) + (stock.barcode ? ' | ' + escapeHtml(stock.barcode) : '') + '</div>' +
                    '<div class="small text-muted"><i class="fas fa-map-marker-alt mr-1"></i>' + escapeHtml(stock.cell_code || '-') + '</div>' +
                '</div>' +
                '<div class="text-right">' +
                    '<div class="h6 mb-0 text-success">' + numberFmt(stock.quantity) + ' ' + escapeHtml(stock.unit) + '</div>' +
                    '<small class="text-muted">stock #' + stock.stock_id + '</small>' +
                '</div>' +
            '</div>' +
        '</div>';
    }).join('');

    $('#sourceStocks').html(html);
}

function selectStock(stock) {
    selectedStock = stock;
    $('#stepItem').addClass('done');
    $('#itemText').text(stock.name);
    $('#itemMeta').text(stock.sku + ' | ' + (stock.cell_code || '-') + ' | tersedia ' + numberFmt(stock.quantity) + ' ' + stock.unit);
    $('#qtyInput').attr('max', stock.quantity).val('').prop('disabled', false);
    $('#btnAllQty').prop('disabled', false);
    renderSourceStocks();
    setPhase('qty');
}

function findStockByCode(code) {
    const needle = cleanCode(code).toUpperCase();
    return sourceStocks.find(function(stock) {
        return [stock.sku, stock.barcode, stock.erp_item_code, stock.lpn, String(stock.stock_id)]
            .filter(Boolean)
            .some(value => String(value).toUpperCase() === needle);
    });
}

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
    const resolved = target.resolved_from_rack ? ' | dari scan rak ' + target.resolved_from_rack : '';
    $('#targetMeta').text('Sisa kapasitas: ' + target.capacity_remaining + '/' + target.capacity_max + ' ' + target.capacity_unit + resolved);
    $('#resultPanel').html('<div class="text-info"><i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan transfer...</div>');

    $.ajax({
        url: transferUrl,
        method: 'POST',
        data: {
            _token: csrfToken,
            stock_id: selectedStock.stock_id,
            to_cell_id: target.id,
            quantity: qty,
            notes: 'Scan transfer: ' + (selectedStock.cell_code || sourceCell.code) + ' -> ' + target.code
        },
        success: function(res) {
            $('#stepTarget').addClass('done');
            $('#resultPanel').html(
                '<div class="text-success font-weight-bold mb-1"><i class="fas fa-check-circle mr-1"></i>' + escapeHtml(res.message || 'Transfer berhasil.') + '</div>' +
                '<div class="small text-muted">' + escapeHtml(selectedStock.sku) + ' | ' + numberFmt(qty) + ' ' + escapeHtml(selectedStock.unit) + ' | ' + escapeHtml(selectedStock.cell_code || sourceCell.code) + ' -> ' + escapeHtml(target.code) + '</div>'
            );
            setTimeout(resetFlow, 900);
        },
        error: function(xhr) {
            const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Transfer gagal.';
            $('#resultPanel').html('<div class="text-danger"><i class="fas fa-times-circle mr-1"></i>' + escapeHtml(msg) + '</div>');
            setAlert('danger', escapeHtml(msg));
            setPhase('target');
        }
    });
}

function handleScan(code) {
    code = cleanCode(code);
    if (!code) return;
    clearAlert();

    if (phase === 'source') {
        lookupCell(code, 'source').done(function(res) {
            sourceCell = res.cell;
            sourceStocks = res.stocks || [];
            selectedStock = null;
            $('#stepSource').addClass('done');
            $('#sourceCellText').text(sourceCell.code);
            $('#sourceMeta').text('Stok: ' + sourceStocks.length + ' | Kapasitas: ' + sourceCell.capacity_used + '/' + sourceCell.capacity_max + ' ' + sourceCell.capacity_unit);
            renderSourceStocks();

            if (!sourceStocks.length) {
                setAlert('warning', 'Cell ' + sourceCell.code + ' tidak memiliki stok available.');
                setPhase('source');
                return;
            }
            if (sourceStocks.length === 1) {
                selectStock(sourceStocks[0]);
                return;
            }
            setPhase('item');
        }).fail(function(xhr) {
            setAlert('danger', escapeHtml(xhr.responseJSON?.message || 'Cell asal tidak ditemukan.'));
            setPhase('source');
        });
        return;
    }

    if (phase === 'item') {
        const stock = findStockByCode(code);
        if (!stock) {
            setAlert('warning', 'Item ' + escapeHtml(code) + ' tidak ada di cell asal ' + sourceCell.code + '.');
            setPhase('item');
            return;
        }
        selectStock(stock);
        return;
    }

    if (phase === 'target') {
        lookupCell(code, 'target').done(function(res) {
            executeTransfer(res.cell);
        }).fail(function(xhr) {
            setAlert('danger', escapeHtml(xhr.responseJSON?.message || 'Cell tujuan tidak ditemukan.'));
            setPhase('target');
        });
    }
}

function resetFlow() {
    sourceCell = null;
    sourceStocks = [];
    selectedStock = null;
    $('#sourceCellText').text('Belum discan');
    $('#sourceMeta').text('-');
    $('#itemText').text('Belum dipilih');
    $('#itemMeta').text('-');
    $('#qtyText').text('Belum diisi');
    $('#targetCellText').text('Belum discan');
    $('#targetMeta').text('Transfer otomatis setelah scan tujuan.');
    $('#qtyInput').val('').prop('disabled', true).removeAttr('max');
    $('#btnAllQty').prop('disabled', true);
    $('#sourceStocks').html('<div class="text-muted text-center py-4">Scan cell asal untuk melihat stok.</div>');
    $('#resultPanel').html('<div class="text-muted"><i class="fas fa-info-circle mr-1"></i>Urutan tercepat: scan cell asal -> scan barcode/SKU item -> qty Enter -> scan cell tujuan.</div>');
    $('.transfer-step').removeClass('done active');
    clearAlert();
    setPhase('source');
}

function numberFmt(value) {
    return new Intl.NumberFormat('id-ID').format(value || 0);
}

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
}

$('#scanInput').on('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = $(this).val();
        $(this).val('');
        handleScan(code);
    }
});

$('#qtyInput').on('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        confirmQty();
    }
});

$('#btnAllQty').on('click', function() {
    if (!selectedStock) return;
    $('#qtyInput').val(selectedStock.quantity);
    confirmQty();
});

$(document).on('click', '.stock-choice', function() {
    const id = parseInt($(this).data('stock-id'), 10);
    const stock = sourceStocks.find(s => s.stock_id === id);
    if (stock) selectStock(stock);
});

$('#btnResetFlow').on('click', resetFlow);

$('#btnCamera').on('click', async function() {
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
            { fps: 10, qrbox: function(w, h) { const s = Math.round(Math.min(w, h) * 0.72); return { width: s, height: s }; } },
            function(decodedText) {
                if (decodedText === lastCameraCode) return;
                lastCameraCode = decodedText;
                setTimeout(function() { lastCameraCode = ''; }, 1500);
                handleScan(decodedText);
            },
            function() {}
        );
    } catch (e) {
        setAlert('danger', 'Kamera tidak bisa dibuka. Gunakan scanner fisik atau input manual.');
        $('#cameraWrap').addClass('d-none');
        cameraScanner = null;
        $('#btnCamera').html('<i class="fas fa-camera mr-1"></i>Kamera');
    }
});

$(function() {
    setPhase('source');
});
</script>
@endpush
