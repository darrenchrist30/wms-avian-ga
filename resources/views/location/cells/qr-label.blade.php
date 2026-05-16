@extends('layouts.adminlte')
@section('title', 'Label Cell ' . $cell->code)

@section('content')
<div class="container-fluid pb-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-qrcode text-success mr-2"></i>Label Cell
            </h5>
            <small class="text-muted">Cetak dan tempel di lokasi cell fisik pada rak</small>
        </div>
        <div class="d-flex" style="gap:6px;">
            <a href="{{ route('location.cells.index') }}" class="btn btn-sm btn-light border">
                <i class="fas fa-arrow-left mr-1"></i>Kembali
            </a>
            @if($cell->rack_id)
            <a href="{{ route('location.cells.bulk-qr', ['rack_id' => $cell->rack_id]) }}"
               class="btn btn-sm btn-outline-primary" target="_blank"
               title="Cetak semua label cell dalam rak {{ $cell->rack?->code }}">
                <i class="fas fa-layer-group mr-1"></i>Cetak Semua Label Rak
            </a>
            @endif
            <button onclick="window.print()" class="btn btn-sm btn-success">
                <i class="fas fa-print mr-1"></i>Cetak Label
            </button>
        </div>
    </div>

    <div class="row">

        {{-- Kolom kiri: toggle format + preview label --}}
        <div class="col-md-6">

            {{-- Toggle QR / Barcode --}}
            <div class="card mb-3">
                <div class="card-header py-2 font-weight-bold">
                    <i class="fas fa-sliders-h mr-1"></i>Format Label
                </div>
                <div class="card-body py-3">
                    <div class="d-flex justify-content-center" style="gap:8px;">
                        <button id="btnQr" class="btn btn-success px-4" onclick="setFormat('qr')">
                            <i class="fas fa-qrcode mr-1"></i>QR Code
                            <small class="d-block" style="font-size:10px;opacity:.8;">Scan kamera/tablet</small>
                        </button>
                        <button id="btnBarcode" class="btn btn-outline-secondary px-4" onclick="setFormat('barcode')">
                            <i class="fas fa-barcode mr-1"></i>Barcode
                            <small class="d-block" style="font-size:10px;opacity:.8;">Scanner fisik/pistol</small>
                        </button>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted" id="formatNote">
                            QR Code cocok untuk scan via kamera tablet — bisa dari berbagai sudut
                        </small>
                    </div>
                </div>
            </div>

            {{-- Preview Label --}}
            <div class="card">
                <div class="card-header py-2 font-weight-bold">
                    <i class="fas fa-eye mr-1"></i>Preview Label
                </div>
                <div class="card-body py-4 d-flex flex-column align-items-center">

                    {{-- Label utama yang akan dicetak --}}
                    <div id="qr-label-print" class="border rounded text-center"
                        style="width:220px; padding:12px 10px; background:#fff;">

                        {{-- Header gudang --}}
                        <div class="text-center mb-2">
                            <small style="font-size:9px; letter-spacing:1px; text-transform:uppercase; color:#6c757d;">
                                {{ $cell->rack?->warehouse?->name ?? 'Gudang Sparepart' }}
                            </small>
                        </div>

                        {{-- QR Code --}}
                        <div id="wrapQr" class="mb-2 ml-2" style="width:100%;text-align:center; just">
                            <canvas id="qrCanvas" style="display:block;margin:0 0;"></canvas>
                        </div>

                        {{-- Barcode --}}
                        <div id="wrapBarcode" class="mb-2" style="display:none;width:100%;text-align:center;">
                            <svg id="barcodeBar" style="display:block;margin:0 auto;"></svg>
                        </div>

                        {{-- Kode Cell Besar --}}
                        <div class="text-center mb-1">
                            <div style="font-size:28px; font-weight:900; letter-spacing:2px; color:#1a2332;">
                                {{ $cell->code }}
                            </div>
                        </div>

                    </div>

                    <div class="mt-3 text-muted small">
                        <i class="fas fa-info-circle mr-1"></i>
                        Label ukuran 6×9 cm — cocok untuk label rak gudang
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom kanan: info + panduan --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2 font-weight-bold">
                    <i class="fas fa-info-circle mr-1"></i>Informasi Cell
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted" width="40%">Kode Cell</td>
                            <td class="font-weight-bold">{{ $cell->code }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Rak</td>
                            <td>{{ $cell->rack?->code ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Level</td>
                            <td>{{ chr(64 + $cell->level) }} (level {{ $cell->level }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Gudang</td>
                            <td>{{ $cell->rack?->warehouse?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Kapasitas Maks</td>
                            <td>{{ number_format($cell->capacity_max) }} unit</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Stok Aktual</td>
                            <td>
                                <span class="{{ $totalQty == 0 ? 'text-muted' : 'font-weight-bold' }}">
                                    {{ number_format($totalQty) }} unit
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                @php
                                    $smap = ['available'=>'badge-success','partial'=>'badge-warning','full'=>'badge-danger','blocked'=>'badge-dark','reserved'=>'badge-info'];
                                @endphp
                                <span class="badge {{ $smap[$cell->status] ?? 'badge-secondary' }}">{{ ucfirst($cell->status) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nilai Kode</td>
                            <td><code>{{ $cell->qr_code ?? $cell->code }}</code></td>
                        </tr>
                    </table>

                    <div class="mt-3">
                        <a href="{{ route('location.cells.stock', $cell->id) }}" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-box mr-1"></i>Lihat Stok Detail
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
@page { size: A4 portrait; margin: 15mm; }

@media print {
    body * { visibility: hidden !important; }

    #qr-label-print,
    #qr-label-print * { visibility: visible !important; }

    /* calc() lebih browser-compatible daripada transform untuk print */
    #qr-label-print {
        position: fixed !important;
        top: 15mm !important;
        left: calc(50% - 30mm) !important;
        width: 60mm !important;
        border: 1px solid #000 !important;
        background: #fff !important;
        padding: 8px !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    #qrCanvas, #barcodeBar {
        display: block !important;
        visibility: visible !important;
        max-width: 100% !important;
    }
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/qrious.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
const CELL_CODE      = '{{ url("/c/" . ($cell->qr_code ?? $cell->code)) }}';
const CELL_CODE_BARE = '{{ $cell->qr_code ?? $cell->code }}';
let currentFormat = 'qr';

// ─── Render QR Code ───────────────────────────────────────────────────────────
var qrObj = new QRious({
    element: document.getElementById('qrCanvas'),
    value: CELL_CODE,
    size: 200,
    level: 'H',
    background: '#ffffff',
    foreground: '#1a2332',
    padding: 4,
});

// ─── Render Barcode (encodes bare code so scanner gun outputs short string) ───
function renderBarcode() {
    JsBarcode('#barcodeBar', CELL_CODE_BARE, {
        format: 'CODE128',
        width: 2,
        height: 55,
        displayValue: true,
        fontSize: 13,
        margin: 6,
        background: '#ffffff',
        lineColor: '#1a2332',
    });
}

// ─── Toggle Format ────────────────────────────────────────────────────────────
function setFormat(format) {
    currentFormat = format;
    if (format === 'qr') {
        $('#wrapQr').css('display', 'block');
        $('#wrapBarcode').css('display', 'none');
        $('#btnQr').removeClass('btn-outline-secondary').addClass('btn-success');
        $('#btnBarcode').removeClass('btn-success').addClass('btn-outline-secondary');
        $('#formatNote').text('QR Code cocok untuk scan via kamera tablet — bisa dari berbagai sudut');
    } else {
        $('#wrapQr').css('display', 'none');
        $('#wrapBarcode').css('display', 'block');
        renderBarcode();
        $('#btnBarcode').removeClass('btn-outline-secondary').addClass('btn-success');
        $('#btnQr').removeClass('btn-success').addClass('btn-outline-secondary');
        $('#formatNote').text('Barcode Code128 cocok untuk scanner fisik/pistol — scan satu arah horizontal');
    }
}
</script>
@endpush
