<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Rak {{ $rackCode }} — Info Rak</title>
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

    .rack-header-card {
        background: #fff; margin: 12px;
        border-radius: 14px; overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,.08);
    }
    .rack-banner {
        background: linear-gradient(135deg, #1a2332 0%, #2d3a4f 100%);
        padding: 20px 20px 16px; color: #fff;
    }
    .rack-code-label { font-size: 11px; opacity: .7; margin-bottom: 3px; }
    .rack-code-text  { font-size: 36px; font-weight: 900; letter-spacing: 1px; font-family: 'Courier New', monospace; }
    .rack-sub-text   { font-size: 12px; opacity: .7; margin-top: 4px; }

    .stats-row { padding: 14px 20px; display: flex; gap: 12px; flex-wrap: wrap; }
    .stat-chip {
        flex: 1; min-width: 60px; text-align: center;
        border-radius: 10px; padding: 8px 6px;
    }
    .stat-num   { font-size: 22px; font-weight: 800; line-height: 1; }
    .stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-top: 3px; opacity: .8; }

    .section-header {
        padding: 8px 12px 4px;
        font-size: 11px; font-weight: 800; color: #6c757d;
        text-transform: uppercase; letter-spacing: .8px;
    }
    .cell-row {
        background: #fff; margin: 0 12px 6px;
        border-radius: 10px; overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
        border-left: 4px solid #dee2e6;
        display: flex; align-items: center;
        padding: 10px 14px; gap: 10px;
    }
    .cell-code   { font-weight: 800; font-size: 15px; color: #1a2332; flex: 1; }
    .cell-cat    { font-size: 10px; color: #6c757d; }
    .status-dot  { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .cap-mini    { text-align: right; font-size: 11px; color: #888; white-space: nowrap; }

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
        <div class="topbar-sub">Info Rak</div>
    </div>
</div>

@php
    $total     = $rackCells->count();
    $available = $rackCells->where('status', 'available')->count();
    $partial   = $rackCells->where('status', 'partial')->count();
    $full      = $rackCells->where('status', 'full')->count();
@endphp

{{-- ── Rack Header ───────────────────────────────────────────────────── --}}
<div class="rack-header-card">
    <div class="rack-banner">
        <div class="rack-code-label">RAK</div>
        <div class="rack-code-text">{{ $rackCode }}</div>
        <div class="rack-sub-text">{{ $warehouseName }} &mdash; {{ $total }} sel</div>
    </div>

    <div class="stats-row">
        <div class="stat-chip" style="background:#d4edda;color:#155724;">
            <div class="stat-num">{{ $available }}</div>
            <div class="stat-label">Tersedia</div>
        </div>
        <div class="stat-chip" style="background:#fff3cd;color:#856404;">
            <div class="stat-num">{{ $partial }}</div>
            <div class="stat-label">Sebagian</div>
        </div>
        <div class="stat-chip" style="background:#f8d7da;color:#721c24;">
            <div class="stat-num">{{ $full }}</div>
            <div class="stat-label">Penuh</div>
        </div>
        <div class="stat-chip" style="background:#e9ecef;color:#495057;">
            <div class="stat-num">{{ $total }}</div>
            <div class="stat-label">Total</div>
        </div>
    </div>
</div>

{{-- ── Cell List ─────────────────────────────────────────────────────── --}}
<div class="section-header">
    <i class="fas fa-th-large mr-1"></i> Daftar Sel
</div>

@foreach($rackCells as $cell)
@php
    $statusColor = match($cell->status) {
        'available' => '#28a745',
        'partial'   => '#ffc107',
        'full'      => '#dc3545',
        default     => '#adb5bd',
    };
    $borderColor = $cell->dominantCategory?->color_code ?? '#dee2e6';
    $capUsed = $cell->physical_capacity_used ?? $cell->capacity_used ?? 0;
    $capMax  = $cell->physical_capacity_max  ?? $cell->capacity_max  ?? 0;
@endphp
<div class="cell-row" style="border-left-color:{{ $borderColor }};">
    <div class="status-dot" style="background:{{ $statusColor }};"></div>
    <div style="flex:1;min-width:0;">
        <div class="cell-code">{{ $cell->physical_code ?? $cell->code }}</div>
        @if($cell->dominantCategory)
            <div class="cell-cat">{{ $cell->dominantCategory->name }}</div>
        @endif
    </div>
    <div class="cap-mini">
        {{ $capUsed }}/{{ $capMax }}
    </div>
</div>
@endforeach

@guest
<div class="login-hint" style="margin-top:8px;">
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
