<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>{{ $cell->code }} — Info Cell</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    * { -webkit-tap-highlight-color: transparent; }
    body {
        background: #f0f2f5;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: #1a1a2e;
        min-height: 100vh;
    }

    /* ── Top bar ── */
    .topbar {
        background: linear-gradient(135deg, #1a2332 0%, #2d3a4f 100%);
        color: #fff; padding: 14px 16px;
        display: flex; align-items: center; gap: 10px;
        position: sticky; top: 0; z-index: 100;
        box-shadow: 0 2px 8px rgba(0,0,0,.25);
    }
    .topbar-logo {
        width: 32px; height: 32px; background: #28a745;
        border-radius: 8px; display: flex; align-items: center; justify-content: center;
        font-weight: 900; font-size: 14px; flex-shrink: 0;
    }
    .topbar-title { font-size: 13px; font-weight: 700; line-height: 1.2; }
    .topbar-sub   { font-size: 11px; opacity: .7; }

    /* ── Cell header card ── */
    .cell-header-card {
        background: #fff; margin: 12px;
        border-radius: 14px; overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,.08);
    }
    .cell-code-banner {
        background: linear-gradient(135deg, #1a2332 0%, #2d3a4f 100%);
        padding: 20px 20px 16px; color: #fff;
    }
    .cell-code-text {
        font-size: 32px; font-weight: 900; letter-spacing: 1px;
        font-family: 'Courier New', monospace;
    }
    .cell-code-label { font-size: 12px; opacity: .7; margin-bottom: 4px; }
    .cell-location-row {
        padding: 14px 20px; display: flex; gap: 20px; flex-wrap: wrap;
    }
    .loc-item { flex: 1; min-width: 80px; }
    .loc-label { font-size: 10px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .5px; }
    .loc-val   { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-top: 2px; }

    /* ── Status badge ── */
    .status-row { padding: 0 20px 16px; display: flex; gap: 8px; flex-wrap: wrap; }
    .status-chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
    }
    .chip-green  { background: #d4edda; color: #155724; }
    .chip-yellow { background: #fff3cd; color: #856404; }
    .chip-red    { background: #f8d7da; color: #721c24; }
    .chip-blue   { background: #cce5ff; color: #004085; }

    /* ── Capacity bar ── */
    .cap-section { padding: 0 20px 16px; }
    .cap-label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 6px; }
    .cap-bar   { height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden; }
    .cap-fill  { height: 100%; border-radius: 5px; transition: width .3s; }
    .cap-nums  { display: flex; justify-content: space-between; margin-top: 4px; font-size: 11px; color: #888; }

    /* ── Stocks section ── */
    .section-header {
        padding: 8px 12px 4px;
        font-size: 11px; font-weight: 800; color: #6c757d;
        text-transform: uppercase; letter-spacing: .8px;
    }
    .stock-card {
        background: #fff; margin: 0 12px 8px;
        border-radius: 12px; overflow: hidden;
        box-shadow: 0 1px 6px rgba(0,0,0,.06);
        border-left: 4px solid #dee2e6;
    }
    .stock-card-body { padding: 14px 16px; }
    .stock-item-name { font-weight: 700; font-size: 14px; line-height: 1.3; }
    .stock-item-sku  { font-size: 11px; color: #6c757d; margin-top: 2px; }
    .stock-item-merk { font-size: 11px; color: #495057; margin-top: 2px; }
    .stock-meta      { display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap; }
    .stock-meta-item { flex: 1; min-width: 70px; }
    .meta-label { font-size: 10px; font-weight: 700; color: #888; text-transform: uppercase; }
    .meta-val   { font-size: 16px; font-weight: 800; margin-top: 1px; }
    .meta-val.qty { color: #28a745; }

    /* ── Empty state ── */
    .empty-state {
        background: #fff; margin: 0 12px 12px;
        border-radius: 12px; padding: 32px 20px;
        text-align: center; color: #adb5bd;
        box-shadow: 0 1px 6px rgba(0,0,0,.06);
    }

    /* ── Login hint ── */
    .login-hint {
        margin: 8px 12px 16px;
        background: #e8f4fd; border-radius: 10px;
        padding: 12px 14px; font-size: 12px; color: #1565c0;
        display: flex; align-items: center; gap: 8px;
    }

    /* ── Footer ── */
    .page-footer {
        text-align: center; padding: 20px 16px;
        font-size: 11px; color: #adb5bd;
    }
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-logo">W</div>
    <div>
        <div class="topbar-title">WMS Avian</div>
        <div class="topbar-sub">Info Lokasi Penyimpanan</div>
    </div>
</div>

{{-- ── Cell Header ─────────────────────────────────────────────────── --}}
<div class="cell-header-card">

    <div class="cell-code-banner">
        <div class="cell-code-label">KODE CELL</div>
        <div class="cell-code-text">{{ $cell->code }}</div>
        @if ($cell->label && $cell->label !== $cell->code)
            <div style="font-size:13px;opacity:.8;margin-top:4px;">{{ $cell->label }}</div>
        @endif
    </div>

    <div class="cell-location-row">
        <div class="loc-item">
            <div class="loc-label">Gudang</div>
            <div class="loc-val">{{ $cell->rack?->zone?->warehouse?->name ?? '—' }}</div>
        </div>
        <div class="loc-item">
            <div class="loc-label">Zona</div>
            <div class="loc-val">{{ $cell->rack?->zone?->code ?? '—' }}</div>
        </div>
        <div class="loc-item">
            <div class="loc-label">Rak</div>
            <div class="loc-val">{{ $cell->rack?->code ?? '—' }}</div>
        </div>
        <div class="loc-item">
            <div class="loc-label">Level</div>
            <div class="loc-val">{{ $cell->level ?? '—' }}</div>
        </div>
    </div>

    {{-- Status chips --}}
    <div class="status-row">
        @php
            $statusChip = match($cell->status) {
                'available' => ['chip-green', 'fa-check-circle', 'Tersedia'],
                'full'      => ['chip-red',   'fa-times-circle', 'Penuh'],
                'partial'   => ['chip-yellow','fa-adjust',       'Sebagian Terisi'],
                default     => ['chip-blue',  'fa-circle',        ucfirst($cell->status ?? '-')],
            };
        @endphp
        <span class="status-chip {{ $statusChip[0] }}">
            <i class="fas {{ $statusChip[1] }}"></i> {{ $statusChip[2] }}
        </span>
        @if ($cell->zone_category)
        <span class="status-chip chip-blue">
            <i class="fas fa-tag"></i> {{ $cell->zone_category }}
        </span>
        @endif
        @if ($cell->dominantCategory)
        <span class="status-chip" style="background:#f3e8ff;color:#6b21a8;">
            <i class="fas fa-boxes"></i> {{ $cell->dominantCategory->name }}
        </span>
        @endif
    </div>

    {{-- Capacity bar --}}
    @if ($cell->capacity_max > 0)
    @php
        $capPct = min(100, round(($cell->capacity_used / $cell->capacity_max) * 100));
        $barColor = $capPct >= 90 ? '#dc3545' : ($capPct >= 70 ? '#ffc107' : '#28a745');
    @endphp
    <div class="cap-section">
        <div class="cap-label">Kapasitas</div>
        <div class="cap-bar">
            <div class="cap-fill" style="width:{{ $capPct }}%;background:{{ $barColor }};"></div>
        </div>
        <div class="cap-nums">
            <span>Terisi: <strong>{{ number_format($cell->capacity_used) }}</strong></span>
            <span>{{ $capPct }}%</span>
            <span>Maks: <strong>{{ number_format($cell->capacity_max) }}</strong></span>
        </div>
    </div>
    @endif

</div>

{{-- ── Stock Items ──────────────────────────────────────────────────── --}}
<div class="section-header">
    <i class="fas fa-box-open mr-1"></i> Isi Cell
    <span style="font-weight:400;color:#adb5bd;"> ({{ $stocks->count() }} item)</span>
</div>

@if ($stocks->isEmpty())
    <div class="empty-state">
        <i class="fas fa-inbox fa-3x mb-2" style="opacity:.2;"></i>
        <div style="font-size:14px;font-weight:600;">Cell Kosong</div>
        <div style="font-size:12px;margin-top:4px;">Tidak ada stok yang tersimpan di cell ini.</div>
    </div>
@else
    @foreach ($stocks as $stock)
    @php
        $cat = $stock->item?->category;
        $borderColor = $cat?->color_code ?? '#dee2e6';
    @endphp
    <div class="stock-card" style="border-left-color:{{ $borderColor }};">
        <div class="stock-card-body">
            <div class="stock-item-name">{{ $stock->item?->name ?? '—' }}</div>
            <div class="stock-item-sku">SKU: {{ $stock->item?->sku ?? '—' }}</div>
            @if ($stock->item?->merk)
                <div class="stock-item-merk"><i class="fas fa-tag" style="font-size:9px;"></i> {{ $stock->item->merk }}</div>
            @endif

            <div class="stock-meta">
                <div class="stock-meta-item">
                    <div class="meta-label">Qty Tersedia</div>
                    <div class="meta-val qty">{{ number_format($stock->quantity) }}
                        <span style="font-size:12px;font-weight:500;color:#6c757d;">{{ $stock->item?->unit?->code }}</span>
                    </div>
                </div>
                <div class="stock-meta-item">
                    <div class="meta-label">Inbound Date</div>
                    <div class="meta-val" style="font-size:13px;color:#495057;">
                        {{ $stock->inbound_date?->format('d M Y') ?? '—' }}
                    </div>
                </div>
                @if ($cat)
                <div class="stock-meta-item">
                    <div class="meta-label">Kategori</div>
                    <div class="meta-val" style="font-size:12px;">
                        <span class="badge px-2 py-1"
                              style="background:{{ $cat->color_code ?? '#6c757d' }};color:#fff;border-radius:6px;">
                            {{ $cat->name }}
                        </span>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
@endif

{{-- ── Login hint for staff ─────────────────────────────────────────── --}}
@guest
<div class="login-hint">
    <i class="fas fa-info-circle" style="flex-shrink:0;font-size:16px;"></i>
    <span>Anda melihat halaman publik. <a href="{{ route('login') }}" style="font-weight:700;color:#1565c0;">Login</a> untuk mengakses fitur manajemen gudang.</span>
</div>
@endguest

<div class="page-footer">
    <i class="fas fa-warehouse mr-1"></i> WMS Avian &mdash; Info diperbarui real-time<br>
    <span style="font-size:10px;">{{ now()->format('d M Y, H:i') }} WIB</span>
</div>

</body>
</html>
