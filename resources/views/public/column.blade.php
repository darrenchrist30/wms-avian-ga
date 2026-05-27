<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>{{ $columnCode }} — WMS Avian</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        background: #f7f8fa;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: #111;
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }
    .topbar {
        background: #004230;
        height: 50px;
        display: flex;
        align-items: center;
        padding: 0 20px;
        gap: 10px;
    }
    .topbar i { color: rgba(255,255,255,.7); font-size: 14px; }
    .topbar-name { font-size: 13px; font-weight: 700; color: #fff; }
    .topbar-sep  { color: rgba(255,255,255,.3); margin: 0 2px; }
    .topbar-sub  { font-size: 12px; color: rgba(255,255,255,.5); }
    .wrap { max-width: 600px; margin: 0 auto; padding: 28px 20px 48px; }

    .col-header { margin-bottom: 24px; }
    .col-label { font-size: 11px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase; color: #0d8564; margin-bottom: 6px; }
    .col-code { font-size: 36px; font-weight: 800; color: #111; letter-spacing: 1px; line-height: 1; margin-bottom: 4px; }
    .col-sub { font-size: 13px; color: #888; }

    .section { margin-bottom: 20px; }
    .section-title {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 1px; color: #aaa; margin-bottom: 12px;
    }

    .baris-card {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 14px;
        text-decoration: none;
        color: inherit;
        transition: box-shadow .15s;
    }
    .baris-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .baris-card:last-child { margin-bottom: 0; }

    .baris-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .baris-num { font-size: 17px; font-weight: 800; color: #111; flex: 1; }
    .baris-code { font-size: 12px; color: #999; }
    .baris-cap { font-size: 12px; font-weight: 600; text-align: right; }
    .baris-status {
        font-size: 11px; font-weight: 600;
        padding: 3px 8px; border-radius: 5px;
    }
    .s-available { background:#dcfce7; color:#14532d; }
    .s-partial   { background:#fef3c7; color:#78350f; }
    .s-full      { background:#fee2e2; color:#7f1d1d; }
    .s-blocked   { background:#f3f4f6; color:#374151; }

    .cap-bar { height: 4px; background: #ebebeb; border-radius: 2px; margin-top: 4px; overflow: hidden; }
    .cap-fill { height: 100%; border-radius: 2px; }

    .foot { font-size: 11px; color: #ccc; text-align: center; line-height: 1.8; padding-top: 8px; border-top: 1px solid #ebebeb; }
</style>
</head>
<body>

<div class="topbar">
    <i class="fas fa-warehouse"></i>
    <span class="topbar-name">WMS Avian</span>
    <span class="topbar-sep">/</span>
    <span class="topbar-sub">Info Kolom</span>
</div>

<div class="wrap">

    <div class="col-header">
        <div class="col-label">Kode Kolom</div>
        <div class="col-code">{{ $columnCode }}</div>
        <div class="col-sub">{{ $warehouseName }} &nbsp;·&nbsp; {{ $columnCells->count() }} baris</div>
    </div>

    <div class="section">
        <div class="section-title">Pilih Baris</div>

        @foreach($columnCells as $cell)
        @php
            $used    = $cell->physical_capacity_used;
            $max     = $cell->capacity_max;
            $pct     = $max > 0 ? min(100, round($used / $max * 100)) : 0;
            $barColor = $pct >= 90 ? '#ef4444' : ($pct >= 60 ? '#f59e0b' : '#0d8564');
            $dotColor = match($cell->status) {
                'available' => '#22c55e',
                'partial'   => '#f59e0b',
                'full'      => '#ef4444',
                default     => '#9ca3af',
            };
            $statusClass = match($cell->status) {
                'available' => 's-available',
                'partial'   => 's-partial',
                'full'      => 's-full',
                default     => 's-blocked',
            };
            $statusLabel = match($cell->status) {
                'available' => 'Tersedia',
                'partial'   => 'Sebagian',
                'full'      => 'Penuh',
                default     => ucfirst($cell->status),
            };
        @endphp
        <a href="{{ route('public.cell', $cell->physical_code) }}" class="baris-card">
            <div class="baris-dot" style="background:{{ $dotColor }};"></div>
            <div style="flex:1;">
                <div class="baris-num">Baris {{ $cell->baris }}</div>
                <div class="baris-code">{{ $cell->physical_code }}</div>
                <div class="cap-bar">
                    <div class="cap-fill" style="width:{{ $pct }}%;background:{{ $barColor }};"></div>
                </div>
            </div>
            <div style="text-align:right;">
                <span class="baris-status {{ $statusClass }}">{{ $statusLabel }}</span>
                <div class="baris-cap" style="margin-top:4px;color:#888;">{{ $used }}/{{ $max }}</div>
            </div>
        </a>
        @endforeach
    </div>

    <div class="foot">
        Diperbarui {{ now()->format('d M Y, H:i') }} WIB
        @guest &nbsp;·&nbsp; <a href="{{ route('login') }}" style="color:#0d8564;font-weight:600;text-decoration:none;">Login</a> @endguest
    </div>

</div>
</body>
</html>
