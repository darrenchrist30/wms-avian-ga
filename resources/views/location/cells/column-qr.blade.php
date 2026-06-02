@extends('layouts.adminlte')
@section('title', 'Label QR Kolom' . ($columns->count() === 1 ? ' ' . $columns->keys()->first() : ''))

@section('content')
<div class="container-fluid pb-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-qrcode text-success mr-2"></i>Label QR Kolom
            </h5>
            {{-- <small class="text-muted">
                {{ $columns->count() }} kolom &nbsp;·&nbsp; Tempel 1 label per kolom di tiang rak
            </small> --}}
        </div>
        <div class="d-flex" style="gap:6px;">
            <a href="{{ url()->previous() }}" class="btn btn-sm btn-light border">
                <i class="fas fa-arrow-left mr-1"></i>Kembali
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-success">
                <i class="fas fa-print mr-1"></i>Cetak {{ $columns->count() }} Label Kolom
            </button>
        </div>
    </div>

    @if($columns->count() === 1)
    {{-- ── Satu kolom: layout 2 kolom persis seperti qr-label.blade.php ── --}}
    @php
        $columnCode  = $columns->keys()->first();
        $columnCells = $columns->first();
        $firstCell   = $columnCells->first();
        $warehouseName = $firstCell->rack?->warehouse?->name ?? 'Gudang Sparepart';
    @endphp

    <div class="row">

        {{-- Kiri: toggle format + preview label --}}
        <div class="col-md-6">

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
                    {{-- <div class="text-center mt-2">
                        <small class="text-muted" id="formatNote">
                            QR Code cocok untuk scan via kamera tablet — bisa dari berbagai sudut
                        </small>
                    </div> --}}
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 font-weight-bold">
                    <i class="fas fa-eye mr-1"></i>Preview Label
                </div>
                <div class="card-body py-4 d-flex flex-column align-items-center">

                    <div id="qr-label-print" class="border rounded text-center"
                         style="width:220px;padding:12px 10px;background:#fff;">

                        <div class="text-center mb-2">
                            <small style="font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#6c757d;">
                                {{ $warehouseName }}
                            </small>
                        </div>

                        <div id="wrapQr" class="mb-2" style="width:100%;text-align:center;">
                            <canvas id="qrCanvas" style="display:block;margin:0 auto;"></canvas>
                        </div>

                        <div id="wrapBarcode" class="mb-2" style="display:none;width:100%;text-align:center;">
                            <svg id="barcodeBar" style="display:block;margin:0 auto;"></svg>
                        </div>

                        <div class="text-center mb-1">
                            <div style="font-size:28px;font-weight:900;letter-spacing:2px;color:#1a2332;">
                                {{ $columnCode }}
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Kanan: info kolom --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2 font-weight-bold">
                    <i class="fas fa-info-circle mr-1"></i>Informasi Kolom
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted" width="40%">Kode Kolom</td>
                            <td class="font-weight-bold">{{ $columnCode }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Gudang</td>
                            <td>{{ $warehouseName }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Rak</td>
                            <td>{{ $firstCell->rack?->code ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Blok</td>
                            <td>{{ $firstCell->blok }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Grup</td>
                            <td>{{ strtoupper($firstCell->grup) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nomor Kolom</td>
                            <td>{{ $firstCell->kolom }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Jumlah Baris</td>
                            <td>{{ $columnCells->count() }} baris</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nilai Kode</td>
                            <td><code>{{ url('/c/' . $columnCode) }}</code></td>
                        </tr>
                    </table>

                    {{-- <div class="alert alert-success mt-3 mb-0" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Label ini mewakili <strong>satu kolom</strong>. Tempel di tiang rak.
                        Saat di-scan, sistem menampilkan pilihan baris
                        ({{ $columnCells->pluck('baris')->implode(', ') }}).
                    </div> --}}
                </div>
            </div>
        </div>
    </div>

    @else
    {{-- ── Banyak kolom: grid dalam AdminLTE card ── --}}
    <div class="card">
        <div class="card-body">
            <div id="multi-label-grid" class="d-flex flex-wrap" style="gap:16px;">
                @foreach($columns as $columnCode => $columnCells)
                @php $firstCell = $columnCells->first(); @endphp
                <div class="text-center"
                     style="width:190px;padding:12px 10px;background:#fff;border:2px solid #004230;border-radius:4px;">

                    <div style="font-size:8px;letter-spacing:1px;text-transform:uppercase;color:#6c757d;margin-bottom:6px;border-bottom:1px solid #eee;padding-bottom:4px;">
                        {{ $firstCell->rack?->warehouse?->name ?? 'Gudang' }}
                    </div>

                    <canvas class="qr-canvas" data-code="{{ url('/c/' . $columnCode) }}"
                            width="120" height="120"></canvas>

                    <div style="font-size:20px;font-weight:900;letter-spacing:2px;color:#004230;margin:4px 0 2px;">
                        {{ $columnCode }}
                    </div>
                    <div style="display:inline-block;background:#004230;color:#fff;font-size:7px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:1px 6px;border-radius:10px;margin-bottom:4px;">
                        Kode Kolom
                    </div>

                    <div style="font-size:7px;color:#6c757d;border-top:1px solid #eee;padding-top:4px;margin-top:2px;text-align:left;">
                        Baris:
                        @foreach($columnCells as $c)
                            <span style="font-weight:700;color:#1a2332;">{{ $c->baris }}</span>{{ !$loop->last ? ' · ' : '' }}
                        @endforeach
                    </div>

                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

</div>
@endsection

@push('styles')
<style>
@page { size: A4 portrait; margin: 15mm; }

@media print {
    body * { visibility: hidden !important; }

    @if($columns->count() === 1)
    #qr-label-print,
    #qr-label-print * { visibility: visible !important; }
    #qr-label-print {
        position: fixed !important;
        top: 15mm !important;
        left: calc(50% - 30mm) !important;
        width: 60mm !important;
        border: 2px solid #000 !important;
        background: #fff !important;
        padding: 8px !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    @else
    #multi-label-grid,
    #multi-label-grid * { visibility: visible !important; }
    #multi-label-grid {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
    }
    @endif

    #qrCanvas, #barcodeBar, .qr-canvas {
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

@if($columns->count() === 1)
<script>
const COL_URL  = '{{ url("/c/" . $columns->keys()->first()) }}';
const COL_CODE = '{{ $columns->keys()->first() }}';

var qrObj = new QRious({
    element   : document.getElementById('qrCanvas'),
    value     : COL_URL,
    size      : 200,
    level     : 'H',
    background: '#ffffff',
    foreground: '#1a2332',
    padding   : 4,
});

function renderBarcode() {
    JsBarcode('#barcodeBar', COL_CODE, {
        format      : 'CODE128',
        width       : 2,
        height      : 55,
        displayValue: true,
        fontSize    : 13,
        margin      : 6,
        background  : '#ffffff',
        lineColor   : '#1a2332',
    });
}

function setFormat(format) {
    if (format === 'qr') {
        $('#wrapQr').show();
        $('#wrapBarcode').hide();
        $('#btnQr').removeClass('btn-outline-secondary').addClass('btn-success');
        $('#btnBarcode').removeClass('btn-success').addClass('btn-outline-secondary');
        $('#formatNote').text('QR Code cocok untuk scan via kamera tablet — bisa dari berbagai sudut');
    } else {
        $('#wrapQr').hide();
        $('#wrapBarcode').show();
        renderBarcode();
        $('#btnBarcode').removeClass('btn-outline-secondary').addClass('btn-success');
        $('#btnQr').removeClass('btn-success').addClass('btn-outline-secondary');
        $('#formatNote').text('Barcode Code128 cocok untuk scanner fisik/pistol — scan satu arah horizontal');
    }
}
</script>
@else
<script>
document.querySelectorAll('.qr-canvas').forEach(function (canvas) {
    new QRious({
        element   : canvas,
        value     : canvas.dataset.code,
        size      : 120,
        level     : 'H',
        background: '#ffffff',
        foreground: '#004230',
        padding   : 4,
    });
});
</script>
@endif
@endpush
