<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rak {{ $rack->code }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 portrait; margin: 12mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #f7f8fa;
    color: #111;
}

/* ── Toolbar ── */
.toolbar {
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    padding: 14px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.toolbar-left .meta {
    font-size: 11px;
    color: #9ca3af;
    letter-spacing: .5px;
    text-transform: uppercase;
    margin-bottom: 3px;
}
.toolbar-left h1 {
    font-size: 17px;
    font-weight: 700;
    color: #111;
    line-height: 1;
}
.toolbar-left .sub {
    font-size: 12px;
    color: #6b7280;
    margin-top: 3px;
}
.toolbar-right { display: flex; gap: 8px; align-items: center; }
.btn {
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: 1px solid #e5e7eb;
    font-family: inherit;
}
.btn-ghost { background: #fff; color: #374151; }
.btn-ghost:hover { background: #f3f4f6; }
.btn-primary { background: #0d8564; color: #fff; border-color: #0d8564; }
.btn-primary:hover { background: #0a6e52; }

/* ── Grid ── */
.grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10mm;
    padding: 10mm 12mm;
}

/* ── Card — identik dengan #qr-label-print di qr-label.blade.php ── */
.card {
    width: 60mm;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 12px 10px;
    text-align: center;
    page-break-inside: avoid;
    break-inside: avoid;
}
.card-wh {
    font-size: 9px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #0d8564;
    margin-bottom: 8px;
}
.card-qr {
    display: flex;
    justify-content: center;
    margin-bottom: 8px;
}
.card-code {
    font-size: 28px;
    font-weight: 900;
    letter-spacing: 2px;
    color: #1a2332;
    line-height: 1.1;
    margin-top: 6px;
}

/* ── Print ── */
@media print {
    .toolbar { display: none !important; }
    body { background: #fff; }
    .grid { padding: 0; gap: 6mm; }
    .card { border: 1px solid #ccc; border-radius: 4px; }
}
</style>
</head>
<body>

<div class="toolbar no-print">
    <div class="toolbar-left">
        {{-- <div class="meta">Avian WMS &nbsp;/&nbsp; Lokasi Gudang</div> --}}
        <h1>Rak {{ $rack->code }}</h1>
        <div class="sub">{{ $cells->count() }} label &nbsp;&middot;&nbsp; {{ $rack->warehouse->name ?? 'Gudang Sparepart' }}</div>
    </div>
    <div class="toolbar-right">
        <a href="{{ url()->previous() }}" class="btn btn-ghost">&#8592; Kembali</a>
        <button onclick="window.print()" class="btn btn-primary">Cetak {{ $cells->count() }} Label</button>
    </div>
</div>

<div class="grid">
    @foreach($cells as $cell)
    @php $displayCode = $cell->physical_code ?? $cell->code; @endphp
    <div class="card">
        <div class="card-wh">{{ $rack->warehouse->name ?? 'Gudang Sparepart' }}</div>
        <div class="card-qr">
            <canvas id="qr-{{ $cell->id }}" width="160" height="160"></canvas>
        </div>
        <div class="card-code">{{ $displayCode }}</div>
    </div>
    @endforeach
</div>

<script src="{{ asset('js/qrious.min.js') }}"></script>
<script>
const cells = @json($cells->map(fn($c) => [
    'id' => $c->id,
    'qr' => url('/c/' . ($c->qr_code ?? $c->physical_code ?? $c->code)),
]));
cells.forEach(function(c) {
    var el = document.getElementById('qr-' + c.id);
    if (!el) return;
    new QRious({ element: el, value: c.qr, size: 160, level: 'H', background: '#ffffff', foreground: '#1a2332', padding: 4 });
});
</script>
</body>
</html>
