<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Label QR — Rak {{ $rack->code }} ({{ $cells->count() }} cell)</title>
<style>
/* ── Print Page Setup ───────────────────────────────────── */
@page {
    size: A4 portrait;
    margin: 10mm;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    background: #f5f5f5;
    color: #1a2332;
}

/* ── Screen toolbar (hidden on print) ─────────────────────── */
.screen-header {
    background: #1a2332;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.screen-header h5  { margin: 0; font-size: 16px; }
.screen-header small { color: #94a3b8; font-size: 12px; }
.btn-print {
    padding: 8px 22px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    font-weight: 600;
}
.btn-print:hover { background: #218838; }
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

/* ── Label Grid ───────────────────────────────────────────── */
.label-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6mm;
    padding: 8mm;
    justify-content: flex-start;
}

/* ── Single Label Card ────────────────────────────────────── */
.label-card {
    width: 60mm;
    border: 1px solid #333;
    background: #fff;
    padding: 4mm 4mm 3mm;
    page-break-inside: avoid;
    break-inside: avoid;
    text-align: center;
    position: relative;
}

/* Crop corner marks */
.label-card::before,
.label-card::after {
    display: none; /* shown only on screen for guide */
}

.lbl-warehouse {
    font-size: 7.5px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 2mm;
    border-bottom: 1px solid #eee;
    padding-bottom: 2mm;
}

.lbl-qr-wrap { margin: 2mm 0; }

.lbl-code {
    font-size: 22px;
    font-weight: 900;
    letter-spacing: 2px;
    color: #1a2332;
    margin: 1mm 0;
    line-height: 1.1;
}

.lbl-info-table {
    width: 100%;
    font-size: 8px;
    border-top: 1px solid #eee;
    padding-top: 2mm;
    margin-top: 1mm;
    border-collapse: collapse;
}
.lbl-info-table td { padding: 0.5mm 0; }
.lbl-info-table td:first-child { color: #6c757d; text-align: left; }
.lbl-info-table td:last-child  { font-weight: bold; text-align: right; }

.lbl-footer {
    font-size: 7px;
    color: #6c757d;
    border-top: 1px solid #eee;
    padding-top: 1.5mm;
    margin-top: 1.5mm;
}

/* ── Print overrides ──────────────────────────────────────── */
@media print {
    .screen-header { display: none !important; }
    body { background: #fff; padding: 0; }
    .label-grid { padding: 0; gap: 5mm; }
    .label-card  { border: 1px solid #000; }
}
</style>
</head>
<body>

{{-- Screen-only toolbar --}}
<div class="screen-header no-print">
    <div>
        <h5><i>🏷</i> Label QR — Rak {{ $rack->code }}</h5>
        <small>
            {{ $cells->count() }} label ·
            {{ $rack->zone->name ?? '—' }} ·
            {{ $rack->zone->warehouse->name ?? '—' }}
            &nbsp;·&nbsp; Ukuran label: 60 × 90 mm
        </small>
    </div>
    <div>
        <a href="{{ url()->previous() }}" class="btn-back">← Kembali</a>
        <button onclick="window.print()" class="btn-print">🖨 Cetak Semua {{ $cells->count() }} Label</button>
    </div>
</div>

<div class="label-grid">
    @foreach($cells as $cell)
    <div class="label-card">

        <div class="lbl-warehouse">
            {{ $rack->zone->warehouse->name ?? 'Gudang Sparepart' }}
        </div>

        <div class="lbl-qr-wrap">
            <canvas id="qr-{{ $cell->id }}" width="130" height="130"></canvas>
        </div>

        <div class="lbl-code">{{ $cell->code }}</div>

        <table class="lbl-info-table">
            <tr>
                <td>Rak</td>
                <td>{{ $cell->rack->code ?? $rack->code }}</td>
            </tr>
            <tr>
                <td>Level</td>
                <td>{{ chr(64 + $cell->level) }}</td>
            </tr>
            <tr>
                <td>Kapasitas</td>
                <td>{{ number_format($cell->capacity_max) }} unit</td>
            </tr>
        </table>

        <div class="lbl-footer">
            Scan QR untuk detail &amp; stok cell
        </div>
    </div>
    @endforeach
</div>

<script src="{{ asset('js/qrious.min.js') }}"></script>
<script>
const cellData = @json($cells->map(fn($c) => [
    'id' => $c->id,
    'qr' => $c->qr_code ?? $c->code,
]));

cellData.forEach(function (cell) {
    var canvas = document.getElementById('qr-' + cell.id);
    if (!canvas) return;
    new QRious({
        element: canvas,
        value  : cell.qr,
        size   : 130,
        level  : 'H',
        background: '#ffffff',
        foreground: '#1a2332',
        padding: 5,
    });
});
</script>
</body>
</html>
