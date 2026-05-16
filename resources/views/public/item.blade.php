<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>{{ $item->sku }} — Info Barang</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    * { -webkit-tap-highlight-color: transparent; }
    body {
        background: #f0f2f5;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: #1a1a2e; min-height: 100vh;
    }
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

    .item-header-card {
        background: #fff; margin: 12px;
        border-radius: 14px; overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,.08);
    }
    .item-code-banner {
        background: linear-gradient(135deg, #1a2332 0%, #2d3a4f 100%);
        padding: 20px 20px 16px; color: #fff;
    }
    .item-code-label { font-size: 11px; opacity: .7; margin-bottom: 3px; }
    .item-code-text  { font-size: 26px; font-weight: 900; letter-spacing: .5px; font-family: 'Courier New', monospace; line-height: 1.2; }
    .item-name-text  { font-size: 13px; opacity: .8; margin-top: 6px; line-height: 1.4; }

    .item-meta-row  { padding: 14px 20px; display: flex; gap: 20px; flex-wrap: wrap; }
    .meta-item      { flex: 1; min-width: 90px; }
    .meta-label     { font-size: 10px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .5px; }
    .meta-val       { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-top: 2px; }

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
    .stock-cell-code { font-weight: 800; font-size: 18px; color: #1a2332; letter-spacing: .3px; }
    .stock-cell-rack { font-size: 11px; color: #6c757d; margin-top: 2px; }
    .stock-meta      { display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap; }
    .stock-meta-item { flex: 1; min-width: 70px; }
    .smeta-label     { font-size: 10px; font-weight: 700; color: #888; text-transform: uppercase; }
    .smeta-val       { font-size: 16px; font-weight: 800; margin-top: 1px; }
    .smeta-val.qty   { color: #28a745; }

    .empty-state {
        background: #fff; margin: 0 12px 12px;
        border-radius: 12px; padding: 28px 20px;
        text-align: center; color: #adb5bd;
        box-shadow: 0 1px 6px rgba(0,0,0,.06);
    }

    .login-hint {
        margin: 8px 12px 16px;
        background: #e8f4fd; border-radius: 10px;
        padding: 12px 14px; font-size: 12px; color: #1565c0;
        display: flex; align-items: center; gap: 8px;
    }
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
        <div class="topbar-sub">Info Barang</div>
    </div>
</div>

{{-- ── Item Header ──────────────────────────────────────────────────── --}}
<div class="item-header-card">
    <div class="item-code-banner">
        <div class="item-code-label">SKU BARANG</div>
        <div class="item-code-text">{{ $item->sku }}</div>
        <div class="item-name-text">{{ $item->name }}</div>
    </div>

    <div class="item-meta-row">
        @if($item->category)
        <div class="meta-item">
            <div class="meta-label">Kategori</div>
            <div class="meta-val">
                <span class="badge px-2 py-1"
                      style="background:{{ $item->category->color_code ?? '#6c757d' }};color:#fff;border-radius:6px;font-size:12px;">
                    {{ $item->category->name }}
                </span>
            </div>
        </div>
        @endif
        @if($item->unit)
        <div class="meta-item">
            <div class="meta-label">Satuan</div>
            <div class="meta-val">{{ $item->unit->code ?? $item->unit->name }}</div>
        </div>
        @endif
        @if($item->merk)
        <div class="meta-item">
            <div class="meta-label">Merk</div>
            <div class="meta-val" style="font-size:13px;">{{ $item->merk }}</div>
        </div>
        @endif
        <div class="meta-item">
            <div class="meta-label">Total Stok</div>
            <div class="meta-val" style="color:#28a745;">
                {{ $itemStocks->sum('quantity') }}
                @if($item->unit) <span style="font-size:11px;font-weight:500;color:#6c757d;">{{ $item->unit->code ?? $item->unit->name }}</span> @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Stock Locations ──────────────────────────────────────────────── --}}
<div class="section-header">
    <i class="fas fa-map-marker-alt mr-1"></i> Lokasi Penyimpanan
    <span style="font-weight:400;color:#adb5bd;"> ({{ $itemStocks->count() }} sel)</span>
</div>

@if($itemStocks->isEmpty())
    <div class="empty-state">
        <i class="fas fa-inbox fa-2x mb-2" style="opacity:.2;"></i>
        <div style="font-size:14px;font-weight:600;">Stok Kosong</div>
        <div style="font-size:12px;margin-top:4px;">Barang ini belum tersimpan di sel mana pun.</div>
    </div>
@else
    @foreach($itemStocks as $stock)
    @php
        $cell    = $stock->cell;
        $cellCode = $cell ? ($cell->physical_code ?? $cell->code) : '—';
        $rackCode = null;
        if ($cell && $cell->blok !== null && $cell->grup !== null) {
            $rackCode = $cell->blok . '-' . strtoupper($cell->grup);
        } elseif ($cell?->rack) {
            $rackCode = $cell->rack->code;
        }
        $warehouse = $cell?->rack?->zone?->warehouse?->name ?? $cell?->rack?->warehouse?->name ?? null;
    @endphp
    <div class="stock-card" style="border-left-color:{{ $item->category->color_code ?? '#28a745' }};">
        <div class="stock-card-body">
            <div class="stock-cell-code">{{ $cellCode }}</div>
            <div class="stock-cell-rack">
                @if($rackCode) Rak {{ $rackCode }} @endif
                @if($warehouse) &mdash; {{ $warehouse }} @endif
            </div>
            <div class="stock-meta">
                <div class="stock-meta-item">
                    <div class="smeta-label">Qty Tersedia</div>
                    <div class="smeta-val qty">{{ number_format($stock->quantity) }}
                        <span style="font-size:12px;font-weight:500;color:#6c757d;">{{ $item->unit?->code }}</span>
                    </div>
                </div>
                <div class="stock-meta-item">
                    <div class="smeta-label">Inbound Date</div>
                    <div class="smeta-val" style="font-size:13px;color:#495057;">
                        {{ $stock->inbound_date?->format('d M Y') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
@endif

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
