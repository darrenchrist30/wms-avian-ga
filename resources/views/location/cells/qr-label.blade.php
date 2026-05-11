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
                <div class="card-body text-center py-4">

                    {{-- Label utama yang akan dicetak --}}
                    <div id="qr-label-print" class="d-inline-block border rounded p-3 text-left"
                        style="width:240px; background:#fff;">

                        {{-- Header gudang --}}
                        <div class="text-center mb-2">
                            <small style="font-size:9px; letter-spacing:1px; text-transform:uppercase; color:#6c757d;">
                                {{ $cell->rack?->zone?->warehouse?->name ?? 'Gudang Sparepart' }}
                            </small>
                        </div>

                        {{-- QR Code --}}
                        <div id="wrapQr" class="text-center mb-2">
                            <canvas id="qrCanvas"></canvas>
                        </div>

                        {{-- Barcode --}}
                        <div id="wrapBarcode" class="text-center mb-2" style="display:none;">
                            <svg id="barcodeBar"></svg>
                        </div>

                        {{-- Kode Cell Besar --}}
                        <div class="text-center mb-1">
                            <div style="font-size:28px; font-weight:900; letter-spacing:2px; color:#1a2332;">
                                {{ $cell->code }}
                            </div>
                        </div>

                        {{-- Info lokasi --}}
                        <div class="text-center mb-2" style="border-top:1px solid #eee; padding-top:6px;">
                            <table style="width:100%; font-size:10px;">
                                <tr>
                                    <td style="color:#6c757d;">Rak</td>
                                    <td class="font-weight-bold text-right">{{ $cell->rack?->code ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6c757d;">Level</td>
                                    <td class="font-weight-bold text-right">{{ chr(64 + $cell->level) }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6c757d;">Kapasitas</td>
                                    <td class="font-weight-bold text-right">{{ $cell->capacity_max }} unit</td>
                                </tr>
                                @if($cell->dominantCategory)
                                <tr>
                                    <td style="color:#6c757d;">Kategori</td>
                                    <td class="text-right">
                                        <span style="background:{{ $cell->dominantCategory->color_code ?? '#6c757d' }};
                                            color:#fff; padding:1px 5px; border-radius:3px; font-size:9px;">
                                            {{ $cell->dominantCategory->name }}
                                        </span>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </div>

                        {{-- Footer label --}}
                        <div class="text-center" style="border-top:1px solid #eee; padding-top:6px;">
                            <small style="font-size:9px; color:#6c757d;" id="labelFooterNote">
                                <i class="fas fa-qrcode"></i> Scan label ini untuk lihat isi &amp; stok cell
                            </small>
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
                            <td>{{ $cell->rack?->zone?->warehouse?->name ?? '—' }}</td>
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
                        <a href="{{ route('location.cells.scan') }}" class="btn btn-sm btn-outline-success ml-1">
                            <i class="fas fa-qrcode mr-1"></i>Buka Scanner
                        </a>
                    </div>
                </div>
            </div>

            {{-- Panduan cetak --}}
            <div class="card mt-3">
                <div class="card-header py-2 font-weight-bold">
                    <i class="fas fa-print mr-1"></i>Panduan Cetak & Pasang
                </div>
                <div class="card-body small">
                    <ol class="pl-3 mb-0" style="line-height:2;">
                        <li>Pilih format label: <strong>QR Code</strong> (tablet/kamera) atau <strong>Barcode</strong> (scanner fisik)</li>
                        <li>Klik tombol <strong>Cetak Label</strong> di atas</li>
                        <li>Pilih printer → atur ukuran kertas <strong>A4</strong></li>
                        <li>Cetak, lalu <strong>potong</strong> label sesuai garis</li>
                        <li><strong>Tempel</strong> di bagian depan cell yang sesuai pada rak fisik</li>
                        <li>Pastikan kode <strong>tidak tertutup</strong> dan mudah discan</li>
                        <li>Gunakan <strong>laminating/plastik</strong> agar tahan lama</li>
                    </ol>
                    <div class="alert alert-info py-1 mt-2 small mb-0">
                        <i class="fas fa-lightbulb mr-1"></i>
                        <strong>Tips:</strong> Pasang kedua format (QR + Barcode) jika memiliki dua jenis scanner.
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
const CELL_CODE = '{{ $cell->qr_code ?? $cell->code }}';
let currentFormat = 'qr';

// ─── Render QR Code ───────────────────────────────────────────────────────────
var qrObj = new QRious({
    element: document.getElementById('qrCanvas'),
    value: CELL_CODE,
    size: 160,
    level: 'H',
    background: '#ffffff',
    foreground: '#1a2332',
    padding: 6,
});

// ─── Render Barcode ───────────────────────────────────────────────────────────
function renderBarcode() {
    JsBarcode('#barcodeBar', CELL_CODE, {
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
        $('#wrapQr').show();
        $('#wrapBarcode').hide();
        $('#btnQr').removeClass('btn-outline-secondary').addClass('btn-success');
        $('#btnBarcode').removeClass('btn-success').addClass('btn-outline-secondary');
        $('#formatNote').text('QR Code cocok untuk scan via kamera tablet — bisa dari berbagai sudut');
        $('#labelFooterNote').text('Scan QR untuk lihat isi cell');
    } else {
        $('#wrapQr').hide();
        $('#wrapBarcode').show();
        renderBarcode();
        $('#btnBarcode').removeClass('btn-outline-secondary').addClass('btn-success');
        $('#btnQr').removeClass('btn-success').addClass('btn-outline-secondary');
        $('#formatNote').text('Barcode Code128 cocok untuk scanner fisik/pistol — scan satu arah horizontal');
        $('#labelFooterNote').text('Scan barcode untuk lihat isi cell');
    }
}
</script>
@endpush
