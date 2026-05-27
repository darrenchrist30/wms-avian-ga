<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>{{ $cell->physical_code ?? $cell->code }} — WMS Avian</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    *, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }

    body {
        background: #f7f8fa;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: #111;
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    /* ── Top bar ── */
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

    /* ── Layout ── */
    .wrap {
        max-width: 600px;
        margin: 0 auto;
        padding: 28px 20px 48px;
    }

    /* ── Cell code block ── */
    .cell-code {
        margin-bottom: 24px;
    }
    .cell-code-label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #0d8564;
        margin-bottom: 6px;
    }
    .cell-code-value {
        font-size: 40px;
        font-weight: 800;
        color: #111;
        letter-spacing: 1px;
        line-height: 1;
        margin-bottom: 10px;
    }
    @if ($cell->label && $cell->label !== $cell->code)
    .cell-code-sub {
        font-size: 13px;
        color: #888;
        margin-bottom: 10px;
    }
    @endif

    /* ── Meta row ── */
    .meta-row {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e8e8e8;
    }
    .meta-item {}
    .meta-label {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #aaa;
        margin-bottom: 3px;
    }
    .meta-val {
        font-size: 14px;
        font-weight: 600;
        color: #111;
    }

    /* ── Section ── */
    .section { margin-bottom: 24px; }
    .section-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #aaa;
        margin-bottom: 12px;
    }

    /* ── Status + chips ── */
    .chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12.5px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 6px;
        border: 1.5px solid transparent;
    }
    .chip-available { background: #dcfce7; color: #14532d; border-color: #86efac; }
    .chip-partial   { background: #fef3c7; color: #78350f; border-color: #fcd34d; }
    .chip-full      { background: #fee2e2; color: #7f1d1d; border-color: #fca5a5; }
    .chip-zone      { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }

    /* ── Capacity ── */
    .cap-numbers {
        display: flex;
        justify-content: space-between;
        font-size: 12.5px;
        color: #666;
        margin-bottom: 8px;
    }
    .cap-numbers strong { color: #111; }
    .cap-track {
        height: 6px;
        background: #ebebeb;
        border-radius: 3px;
        overflow: hidden;
    }
    .cap-fill { height: 100%; border-radius: 3px; }
    .cap-note {
        font-size: 11px;
        color: #bbb;
        margin-top: 6px;
    }

    /* ── Stock list ── */
    .stock-item {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 10px;
    }
    .stock-item:last-child { margin-bottom: 0; }
    .stock-name {
        font-size: 14.5px;
        font-weight: 700;
        color: #111;
        margin-bottom: 3px;
        line-height: 1.4;
    }
    .stock-sku {
        font-size: 12px;
        color: #999;
        font-weight: 500;
        margin-bottom: 14px;
    }
    .stock-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .stock-field {}
    .sf-label {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .7px;
        color: #bbb;
        margin-bottom: 4px;
    }
    .sf-val {
        font-size: 13px;
        font-weight: 600;
        color: #222;
    }
    .sf-val.big {
        font-size: 26px;
        font-weight: 800;
        color: #0d8564;
        line-height: 1;
    }
    .sf-unit {
        font-size: 12px;
        font-weight: 500;
        color: #999;
        margin-left: 2px;
    }
    .cat-dot {
        display: inline-block;
        width: 8px; height: 8px;
        border-radius: 50%;
        margin-right: 5px;
        vertical-align: middle;
    }

    /* ── Empty ── */
    .empty {
        text-align: center;
        padding: 40px 0;
        color: #ccc;
    }
    .empty i { font-size: 32px; margin-bottom: 10px; display: block; }
    .empty p { font-size: 13px; color: #aaa; }

    /* ── Footer ── */
    .foot {
        font-size: 11px;
        color: #ccc;
        text-align: center;
        line-height: 1.8;
        padding-top: 8px;
        border-top: 1px solid #ebebeb;
    }
</style>
</head>
<body>

<div class="topbar">
    <i class="fas fa-warehouse"></i>
    <span class="topbar-name">WMS Avian</span>
    <span class="topbar-sep">/</span>
    <span class="topbar-sub">Info Cell</span>
</div>

<div class="wrap">

    {{-- Cell code --}}
    <div class="cell-code">
        <div class="cell-code-label">Kode Cell</div>
        <div class="cell-code-value">{{ $cell->physical_code ?? $cell->code }}</div>
        @if ($cell->label && $cell->label !== $cell->code)
            <div style="font-size:13px;color:#888;margin-top:6px;">{{ $cell->label }}</div>
        @endif
    </div>

    {{-- Location meta --}}
    <div class="meta-row">
        <div class="meta-item">
            <div class="meta-label">Gudang</div>
            <div class="meta-val">{{ $cell->rack?->warehouse?->name ?? '—' }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Rak</div>
            <div class="meta-val">{{ $cell->rack?->code ?? '—' }}</div>
        </div>
        @if ($cell->level)
        <div class="meta-item">
            <div class="meta-label">Level</div>
            <div class="meta-val">{{ $cell->level }}</div>
        </div>
        @endif
    </div>

    {{-- Status --}}
    <div class="section">
        <div class="section-title">Status</div>
        <div class="chips">
            @php
                [$chipClass, $chipLabel] = match($cell->status) {
                    'available' => ['chip-available', 'Tersedia'],
                    'full'      => ['chip-full',      'Penuh'],
                    'partial'   => ['chip-partial',   'Sebagian Terisi'],
                    default     => ['chip-zone',      ucfirst($cell->status ?? '-')],
                };
            @endphp
            <span class="chip {{ $chipClass }}">{{ $chipLabel }}</span>

            @if ($cell->dominantCategory)
                @php $dc = $cell->dominantCategory; $dcColor = $dc->color_code ?? '#6b7280'; @endphp
                <span class="chip" style="background:{{ $dcColor }}18;color:{{ $dcColor }};border-color:{{ $dcColor }}55;">
                    {{ $dc->name }}
                </span>
            @endif
            @if ($cell->zone_category)
                <span class="chip chip-zone">{{ $cell->zone_category }}</span>
            @endif
        </div>
    </div>

    {{-- Capacity --}}
    @if ($cell->capacity_max > 0)
    @php
        $capPct   = min(100, round(($cell->capacity_used / $cell->capacity_max) * 100));
        $barColor = $capPct >= 90 ? '#ef4444' : ($capPct >= 70 ? '#f59e0b' : '#0d8564');
    @endphp
    <div class="section">
        <div class="section-title">Kapasitas</div>
        <div class="cap-numbers">
            <span><strong>{{ number_format($cell->capacity_used) }}</strong> terisi</span>
            <span>Maks <strong>{{ number_format($cell->capacity_max) }}</strong> &nbsp;·&nbsp; <strong>{{ $capPct }}%</strong></span>
        </div>
        <div class="cap-track">
            <div class="cap-fill" style="width:{{ $capPct }}%;background:{{ $barColor }};"></div>
        </div>
    </div>
    @endif

    {{-- Stock items --}}
    <div class="section">
        <div class="section-title">Isi Cell &mdash; {{ $stocks->count() }} item</div>

        @if ($stocks->isEmpty())
            <div class="empty">
                <i class="fas fa-inbox"></i>
                <p>Cell ini kosong.</p>
            </div>
        @else
            @foreach ($stocks as $stock)
            @php $cat = $stock->item?->category; @endphp
            <div class="stock-item">
                <div class="stock-name">{{ $stock->item?->name ?? '—' }}</div>
                <div class="stock-sku">{{ $stock->item?->sku ?? '—' }}</div>

                <div class="stock-row">
                    <div class="stock-field">
                        <div class="sf-label">Qty</div>
                        <div class="sf-val big">
                            {{ number_format($stock->quantity) }}<span class="sf-unit">{{ $stock->item?->unit?->code }}</span>
                        </div>
                    </div>
                    <div class="stock-field">
                        <div class="sf-label">Inbound</div>
                        <div class="sf-val">{{ $stock->inbound_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                    @if ($cat)
                    <div class="stock-field">
                        <div class="sf-label">Kategori</div>
                        <div class="sf-val">
                            <span class="cat-dot" style="background:{{ $cat->color_code ?? '#ccc' }};"></span>
                            {{ $cat->name }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- Footer --}}
    <div class="foot">
        Diperbarui {{ now()->format('d M Y, H:i') }} WIB
        @guest &nbsp;·&nbsp; <a href="{{ route('login') }}" style="color:#0d8564;font-weight:600;text-decoration:none;">Login</a> @endguest
    </div>

</div>
</body>
</html>
