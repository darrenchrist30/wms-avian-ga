<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Label QR Kolom{{ $rack ? ' — Rak '.$rack->code : '' }} ({{ $columns->count() }} kolom)</title>
<style>
@page { size: A4 portrait; margin: 10mm; }
* { box-sizing: border-box; }
body {
    margin: 0; padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    background: #f5f5f5;
    color: #1a2332;
}
.screen-header {
    background: #004230;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.screen-header h5  { margin: 0; font-size: 16px; }
.screen-header small { color: rgba(255,255,255,.6); font-size: 12px; }
.btn-print {
    padding: 8px 22px;
    background: #38c172;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    font-weight: 600;
}
.btn-print:hover { background: #2ea65f; }
.btn-back {
    padding: 8px 16px;
    background: transparent;
    color: #fff;
    border: 1px solid rgba(255,255,255,.3);
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    margin-right: 8px;
}
.btn-back:hover { background: rgba(255,255,255,.1); }

/* ── Info Banner ── */
.info-banner {
    background: #e8f5e9;
    border: 1px solid #a5d6a7;
    border-radius: 8px;
    padding: 10px 16px;
    margin: 0 16px 16px;
    font-size: 12px;
    color: #1b5e20;
}

/* ── Label Grid ── */
.label-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6mm;
    padding: 8mm;
    justify-content: flex-start;
}

/* ── Single Label Card ── */
.label-card {
    width: 60mm;
    border: 2px solid #004230;
    background: #fff;
    padding: 4mm 4mm 3mm;
    page-break-inside: avoid;
    break-inside: avoid;
    text-align: center;
    border-radius: 2mm;
}

.lbl-warehouse {
    font-size: 7px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 2mm;
    border-bottom: 1px solid #eee;
    padding-bottom: 2mm;
}

.lbl-qr-wrap { margin: 2mm 0; }

.lbl-code {
    font-size: 20px;
    font-weight: 900;
    letter-spacing: 1px;
    color: #004230;
    margin: 1mm 0;
    line-height: 1.1;
}

.lbl-type-badge {
    display: inline-block;
    background: #004230;
    color: #fff;
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 1px 6px;
    border-radius: 10px;
    margin-bottom: 2mm;
}

.lbl-baris-list {
    font-size: 7.5px;
    color: #6c757d;
    border-top: 1px solid #eee;
    padding-top: 2mm;
    margin-top: 1mm;
    text-align: left;
}
.lbl-baris-list span { font-weight: 700; color: #1a2332; }

@media print {
    .screen-header, .info-banner { display: none !important; }
    body { background: #fff; padding: 0; }
    .label-grid { padding: 0; gap: 5mm; }
    .label-card  { border: 2px solid #000; border-radius: 0; }
}
</style>
</head>
<body>

<div class="screen-header no-print">
    <div>
        <h5>Label QR Kolom{{ $rack ? ' — Rak '.$rack->code : '' }}</h5>
        <small>
            {{ $columns->count() }} kolom
            @if($rack) · {{ $rack->warehouse->name ?? '—' }} @endif
            &nbsp;·&nbsp; Tempel 1 label per kolom di tiang rak
        </small>
    </div>
    <div>
        <a href="{{ url()->previous() }}" class="btn-back">← Kembali</a>
        <button onclick="window.print()" class="btn-print">Cetak {{ $columns->count() }} Label Kolom</button>
    </div>
</div>

<div class="info-banner">
    Label ini mewakili <strong>satu kolom</strong> (misal: <strong>1-F-1</strong>). Tempel di tiang rak.
    Saat di-scan, sistem akan menampilkan pilihan baris (1-F-1-1, 1-F-1-2, dst).
</div>

<div class="label-grid">
    @foreach($columns as $columnCode => $columnCells)
    @php $firstCell = $columnCells->first(); @endphp
    <div class="label-card">

        <div class="lbl-warehouse">
            {{ $firstCell->rack?->warehouse?->name ?? ($rack?->warehouse?->name ?? 'Gudang Sparepart') }}
        </div>

        <div class="lbl-qr-wrap">
            <canvas class="qr-canvas" data-code="{{ url('/c/' . $columnCode) }}" width="130" height="130"></canvas>
        </div>

        <div class="lbl-code">{{ $columnCode }}</div>
        <div class="lbl-type-badge">Kode Kolom</div>

        <div class="lbl-baris-list">
            Baris:
            @foreach($columnCells as $c)
                <span>{{ $c->baris }}</span>{{ !$loop->last ? ' · ' : '' }}
            @endforeach
        </div>

    </div>
    @endforeach
</div>

<script src="{{ asset('js/qrious.min.js') }}"></script>
<script>
document.querySelectorAll('.qr-canvas').forEach(function (canvas) {
    new QRious({
        element   : canvas,
        value     : canvas.dataset.code,
        size      : 130,
        level     : 'H',
        background: '#ffffff',
        foreground: '#004230',
        padding   : 5,
    });
});
</script>
</body>
</html>
