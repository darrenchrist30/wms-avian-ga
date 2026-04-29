@extends('layouts.adminlte')

@section('title', 'Put-Away: ' . $order->do_number)

@push('styles')
    <style>
        /* ── Camera scanner ── */
        @keyframes scanMove {
            0%   { top: 8%;  opacity: 1; }
            45%  { top: 82%; opacity: .7; }
            55%  { top: 82%; opacity: .7; }
            100% { top: 8%;  opacity: 1; }
        }
        @keyframes scanPulse {
            0%, 100% { box-shadow: 0 0 4px #0d8564, 0 0 12px #0d856440; }
            50%       { box-shadow: 0 0 8px #0d8564, 0 0 24px #0d856480; }
        }
        #cameraViewport {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            background: #000;
            min-height: 200px;
        }
        /* html5-qrcode injects its own video element — normalize it */
        #qrCameraReader video {
            width: 100% !important;
            height: auto !important;
            display: block;
        }
        #qrCameraReader img { display: none; } /* hide default icon */
        #scanLine {
            position: absolute;
            left: 8%; width: 84%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #0d8564 40%, #38c172, #0d8564 60%, transparent);
            animation: scanMove 2s ease-in-out infinite, scanPulse 2s ease-in-out infinite;
            z-index: 10;
            pointer-events: none;
        }
        .cam-corner {
            position: absolute;
            width: 28px; height: 28px;
            border-color: #0d8564;
            border-style: solid;
            z-index: 9;
            pointer-events: none;
        }
        #camCornTL { top: 10%; left: 8%;  border-width: 3px 0 0 3px; border-radius: 3px 0 0 0; }
        #camCornTR { top: 10%; right: 8%; border-width: 3px 3px 0 0; border-radius: 0 3px 0 0; }
        #camCornBL { bottom: 10%; left: 8%;  border-width: 0 0 3px 3px; border-radius: 0 0 0 3px; }
        #camCornBR { bottom: 10%; right: 8%; border-width: 0 3px 3px 0; border-radius: 0 0 3px 0; }
        #cameraScanSuccess {
            display: none;
            position: absolute; inset: 0;
            background: rgba(13, 133, 100, .38);
            align-items: center; justify-content: center;
            z-index: 12; border-radius: 10px;
        }
        #cameraScanSuccess.visible { display: flex; }

        /* ── Send To box ── */
        .send-to-cell {
            background: #f0f7ff;
            border-left: 3px solid #007bff;
            border-radius: 4px;
            padding: 7px 9px;
        }

        .send-to-cell.overflow {
            background: #fff8e1;
            border-left-color: #ffc107;
        }

        .send-to-cell.alt-selected {
            background: #e8f5e9;
            border-left-color: #28a745;
        }

        .send-to-cell.scan-active {
            background: #fff3e0;
            border-left-color: #e65100;
        }

        .send-to-cell.done {
            background: #f0fff4;
            border-left-color: #28a745;
        }

        .cell-code {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .cell-code.c-ga {
            color: #0056b3;
        }

        .cell-code.c-alt {
            color: #1a7a38;
        }

        .cell-code.c-scan {
            color: #7b4f00;
        }

        .cell-code.c-done {
            color: #28a745;
        }

        /* ── Cap bar ── */
        .cap-bar-wrap {
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .cap-bar-fill {
            height: 100%;
            border-radius: 3px;
        }

        /* ── Row colors ── */
        tr.row-pending {
            background: #fff;
        }

        tr.row-overflow {
            background: #fffde7;
        }

        tr.row-done {
            background: #f1fff5;
        }

        /* ── Cell-usage card ── */
        .cell-use-card {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            min-width: 150px;
        }

        .cell-use-card.cu-ok {
            border-color: #28a745;
        }

        .cell-use-card.cu-over {
            border-color: #ffc107;
            background: #fffde7;
        }

        /* ── Modal saving overlay ── */
        #modalSavingOverlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.90);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 6px;
            border-radius: 12px;
        }

        /* ── Row save flash ── */
        @keyframes rowSaveFlash {
            0%   { background: #b8f0ca; }
            70%  { background: #c6f8d5; }
            100% { background: #f1fff5; }
        }
        tr.row-save-flash td {
            animation: rowSaveFlash 1s ease-out forwards;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        @php
            /* ── Kapasitas per cell ──────────────────────────────────── */
            $cellCapInfo = [];
            foreach ($order->items as $detail) {
                if ($detail->status === 'put_away') {
                    continue;
                }
                $gd = $gaDetailMap[$detail->id] ?? null;
                if (!$gd || !$gd->cell) {
                    continue;
                }
                $cid = $gd->cell_id;
                if (!isset($cellCapInfo[$cid])) {
                    $cellCapInfo[$cid] = [
                        'code' => $gd->cell->code,
                        'zone' => $gd->cell->zone_category ?? '-',
                        'rack' => $gd->cell->rack->code ?? '-',
                        'remaining' => $gd->cell->capacity_remaining,
                        'max' => $gd->cell->capacity_max,
                        'item_count' => 0,
                        'total_qty' => 0,
                    ];
                }
                $cellCapInfo[$cid]['item_count']++;
                $cellCapInfo[$cid]['total_qty'] += $detail->quantity_received;
            }
            $hasOverflow = collect($cellCapInfo)->contains(fn($c) => $c['total_qty'] > $c['remaining']);
            $doneCount = $order->items->where('status', 'put_away')->count();
            $totalCount = $order->items->count();
            $totalQtyAll = $order->items->sum('quantity_received');
            // LPN: tampilkan kolom hanya jika minimal 1 item punya LPN
            $hasLpn = $order->items->contains(fn($d) => !empty($d->lpn));
        @endphp

        {{-- ══════════════════════════════════════════════════════
     HEADER
══════════════════════════════════════════════════════ --}}
        <div class="row mb-2">
            <div class="col-md-8">
                <h4 class="mt-2 mb-0">
                    <i class="fas fa-dolly-flatbed mr-2 text-primary"></i>
                    Proses Put-Away &mdash; <code>{{ $order->do_number }}</code>
                    @if ($order->status === 'completed')
                        <span class="badge badge-success ml-2">COMPLETED</span>
                    @elseif($order->status === 'put_away')
                        <span class="badge badge-warning ml-2">BERLANGSUNG</span>
                    @else
                        <span class="badge badge-info ml-2">SIAP PUT-AWAY</span>
                    @endif
                </h4>
            </div>
            <div class="col-md-4 text-right pt-2">
                <a href="{{ route('putaway.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali
                </a>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
     INFO ORDER (Supplier, Tanggal, Warehouse, Diterima)
══════════════════════════════════════════════════════ --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row" style="font-size:13px">
                    <div class="col-6 col-md-3 mb-1">
                        <span class="text-muted d-block">Supplier</span>
                        <strong>{{ $order->supplier?->name ?? '-' }}</strong>
                    </div>
                    <div class="col-6 col-md-2 mb-1">
                        <span class="text-muted d-block">Tanggal DO</span>
                        <strong>{{ $order->do_date?->format('d M Y') ?? '-' }}</strong>
                    </div>
                    <div class="col-6 col-md-3 mb-1">
                        <span class="text-muted d-block">Gudang</span>
                        <strong>{{ $order->warehouse?->name ?? '-' }}</strong>
                    </div>
                    <div class="col-6 col-md-2 mb-1">
                        <span class="text-muted d-block">Diterima Oleh</span>
                        <strong>{{ $order->receivedBy?->name ?? '-' }}</strong>
                    </div>
                    <div class="col-6 col-md-1 mb-1 text-center">
                        <span class="text-muted d-block">Total Item</span>
                        <strong class="text-primary" style="font-size:16px">{{ $totalCount }}</strong>
                    </div>
                    <div class="col-6 col-md-1 mb-1 text-center">
                        <span class="text-muted d-block">Total Qty</span>
                        <strong class="text-primary" style="font-size:16px">{{ $totalQtyAll }}</strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
     PROGRESS + GA INFO + LEGEND
══════════════════════════════════════════════════════ --}}
        <div class="row mb-3">
            {{-- Progress --}}
            <div class="col-md-4">
                <div class="card card-outline card-primary h-100 mb-0">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="font-weight-bold text-muted">Progress Put-Away</span>
                            <strong id="progressText"
                                class="{{ $doneCount === $totalCount ? 'text-success' : 'text-primary' }}">
                                {{ $doneItems }} / {{ $totalItems }} item
                            </strong>
                        </div>
                        <div class="progress mb-1" style="height:20px;border-radius:10px">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="progressBar"
                                role="progressbar" style="width:{{ $progressPct }}%;border-radius:10px">
                                <span id="progressPct" class="font-weight-bold">{{ $progressPct }}%</span>
                            </div>
                        </div>
                        <small class="text-muted">
                            Selesai {{ $doneItems }} item · Menunggu {{ $totalItems - $doneItems }} item
                        </small>
                        @if ($order->status === 'completed')
                            <div class="alert alert-success mb-0 mt-2 py-1 text-sm">
                                <i class="fas fa-check-circle mr-1"></i>
                                Semua item selesai. Order <strong>completed</strong>.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Sisa Tugas — Operator-friendly task summary --}}
            <div class="col-md-5">
                @php
                    $pendingItems = $order->items->where('status', '!=', 'put_away');
                    $pendingCount = $pendingItems->count();
                    $pendingQty   = $pendingItems->sum('quantity_received');
                @endphp
                <div class="card h-100 mb-0" style="border:2px solid {{ $pendingCount === 0 ? '#28a745' : '#fd7e14' }};border-radius:8px">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted font-weight-bold mb-1" style="font-size:11px;letter-spacing:.5px">
                                    <i class="fas fa-tasks mr-1"></i> SISA TUGAS
                                </div>
                                @if ($pendingCount === 0)
                                    <div class="text-success font-weight-bold" style="font-size:26px;line-height:1">
                                        <i class="fas fa-check-circle mr-1"></i> Selesai!
                                    </div>
                                    <small class="text-success">Semua item berhasil di-put-away</small>
                                @else
                                    <div style="font-size:30px;font-weight:800;line-height:1;color:#e65c00">
                                        {{ $pendingCount }}
                                        <span style="font-size:14px;font-weight:600;color:#6c757d">item lagi</span>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:13px">
                                        <i class="fas fa-boxes mr-1"></i>
                                        <strong>{{ $pendingQty }}</strong> unit belum tersimpan
                                    </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-muted mb-1" style="font-size:10px;letter-spacing:.3px">SKOR GA</div>
                                <span class="badge badge-{{ $gaRecommendation->fitness_score >= 70 ? 'success' : ($gaRecommendation->fitness_score >= 50 ? 'warning' : 'danger') }}"
                                      style="font-size:14px;padding:5px 9px"
                                      title="Fitness score algoritma GA — makin tinggi makin optimal penempatan">
                                    {{ number_format($gaRecommendation->fitness_score, 1) }}
                                    <span style="font-size:9px;opacity:.75">/ 100</span>
                                </span>
                                <div class="text-muted mt-1" style="font-size:10px">Fitness score</div>
                            </div>
                        </div>
                        @if ($pendingCount > 0)
                            <div class="mt-2 px-2 py-1 rounded" style="background:#fff8f0;border:1px solid #ffd4a8;font-size:11px">
                                <i class="fas fa-lightbulb mr-1 text-warning"></i>
                                <strong>Tips:</strong> Scan QR di rak fisik → jika cocok GA, tersimpan
                                <strong>otomatis</strong> tanpa tombol tambahan.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Legend --}}
            <div class="col-md-3">
                <div class="card h-100 mb-0 border">
                    <div class="card-body py-3" style="font-size:12px">
                        <div class="font-weight-bold text-muted mb-2" style="font-size:11px;letter-spacing:.5px">
                            KETERANGAN KOLOM SEND TO
                        </div>
                        @foreach ([['#0056b3', 'Rekomendasi GA (default)'], ['#1a7a38', 'Cell dipilih / alternatif sistem'], ['#7b4f00', 'Cell dari scan QR'], ['#ffc107', 'Kapasitas penuh — wajib ganti'], ['#28a745', 'Sudah dikonfirmasi / put-away']] as [$color, $label])
                            <div class="mb-1 d-flex align-items-center">
                                <span
                                    style="display:inline-block;width:12px;height:12px;
                                 background:{{ $color }};border-radius:2px;
                                 flex-shrink:0;margin-right:6px"></span>
                                {{ $label }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
     PANDUAN ALUR PUT-AWAY (collapsible, default collapsed)
══════════════════════════════════════════════════════ --}}
        @if ($order->status !== 'completed')
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="callout callout-info mb-0 py-2">
                        <h6 class="mb-0">
                            <a data-toggle="collapse" href="#panduanCollapse" role="button"
                               aria-expanded="false" aria-controls="panduanCollapse"
                               style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:6px">
                                <i class="fas fa-route"></i>
                                <span>Panduan Alur Put-Away</span>
                                <small class="text-muted font-weight-normal">(klik untuk lihat / sembunyikan)</small>
                                <i class="fas fa-chevron-down ml-auto" style="font-size:10px;transition:transform .2s"></i>
                            </a>
                        </h6>
                        <div class="collapse" id="panduanCollapse">
                            <div class="row mt-2" style="font-size:12px">
                                <div class="col-md-3 mb-1">
                                    <span class="badge badge-primary">1</span>
                                    <strong> Cek kolom SEND TO</strong><br>
                                    <span class="text-muted">Lihat cell rekomendasi GA dan kapasitasnya.
                                        Baris <span class="text-warning font-weight-bold">kuning</span> = cell penuh →
                                        klik <strong>"Ganti Cell"</strong>.</span>
                                </div>
                                <div class="col-md-3 mb-1">
                                    <span class="badge badge-success">2</span>
                                    <strong> Scan QR di rak fisik</strong><br>
                                    <span class="text-muted">Klik <strong>Konfirmasi</strong> → scan QR / barcode
                                        di sticker rak. Jika <strong>cocok GA + kapasitas cukup</strong>
                                        → <span class="text-success font-weight-bold">tersimpan otomatis!</span></span>
                                </div>
                                <div class="col-md-3 mb-1">
                                    <span class="badge badge-warning text-dark">3</span>
                                    <strong> Beda lokasi / kapasitas kurang</strong><br>
                                    <span class="text-muted">Muncul layar konfirmasi manual →
                                        periksa cell & qty → klik <strong>Konfirmasi Sekarang</strong>.</span>
                                </div>
                                <div class="col-md-3 mb-1">
                                    <span class="badge badge-secondary">4</span>
                                    <strong> Tidak ada QR?</strong><br>
                                    <span class="text-muted">Klik <strong>"Lanjut tanpa scan"</strong> →
                                        sistem pakai rekomendasi GA langsung. Konfirmasi manual muncul.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════
     RINGKASAN CELL YANG DIGUNAKAN GA
══════════════════════════════════════════════════════ --}}
        @if (count($cellCapInfo) > 0 && $order->status !== 'completed')
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card mb-0">
                        <div class="card-header py-2">
                            <h6 class="mb-0" style="font-size:13px">
                                <i class="fas fa-th mr-1"></i>
                                Ringkasan Cell yang Digunakan GA
                                <small class="text-muted font-weight-normal ml-2">
                                    — Kapasitas total sebelum put-away dilakukan
                                </small>
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="d-flex flex-wrap" style="gap:10px">
                                @foreach ($cellCapInfo as $cid => $cap)
                                    @php
                                        $isCapOver = $cap['total_qty'] > $cap['remaining'];
                                        $fillPct =
                                            $cap['max'] > 0
                                                ? min(100, round(($cap['total_qty'] / $cap['max']) * 100))
                                                : 0;
                                        $usedPct =
                                            $cap['max'] > 0
                                                ? min(
                                                    100,
                                                    round((($cap['max'] - $cap['remaining']) / $cap['max']) * 100),
                                                )
                                                : 0;
                                    @endphp
                                    <div class="cell-use-card {{ $isCapOver ? 'cu-over' : 'cu-ok' }}">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-weight-bold" style="font-size:14px">
                                                {{ $cap['code'] }}
                                            </span>
                                            @if ($isCapOver)
                                                <span class="badge badge-danger" style="font-size:9px">PENUH</span>
                                            @else
                                                <span class="badge badge-success" style="font-size:9px">OK</span>
                                            @endif
                                        </div>
                                        <div class="text-muted mb-1">Zone {{ $cap['zone'] }} · Rack {{ $cap['rack'] }}
                                        </div>
                                        <div class="d-flex justify-content-between" style="font-size:11px">
                                            <span>{{ $cap['item_count'] }} item · {{ $cap['total_qty'] }} unit
                                                order</span>
                                            <span class="{{ $isCapOver ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                                Sisa: {{ $cap['remaining'] }}/{{ $cap['max'] }}
                                            </span>
                                        </div>
                                        <div class="cap-bar-wrap mt-1">
                                            <div class="cap-bar-fill bg-secondary" style="width:{{ $usedPct }}%">
                                            </div>
                                            <div class="cap-bar-fill {{ $isCapOver ? 'bg-danger' : 'bg-info' }}"
                                                style="width:{{ min(100 - $usedPct, $fillPct) }}%; margin-top:-5px"></div>
                                        </div>
                                        @if ($isCapOver)
                                            <div class="text-danger mt-1" style="font-size:10px">
                                                <i class="fas fa-exclamation-circle"></i>
                                                Kelebihan {{ $cap['total_qty'] - $cap['remaining'] }} unit
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════
     ALERT OVERFLOW
══════════════════════════════════════════════════════ --}}
        @if ($hasOverflow && $order->status !== 'completed')
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="alert alert-warning mb-0 py-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Perhatian — Kapasitas Cell Tidak Mencukupi!</strong>
                        GA merekomendasikan cell yang tidak muat semua item.
                        Klik <strong>"Ganti Cell"</strong> pada baris berwarna kuning.
                        <ul class="mb-0 mt-1">
                            @foreach ($cellCapInfo as $cap)
                                @if ($cap['total_qty'] > $cap['remaining'])
                                    <li>
                                        Cell <strong>{{ $cap['code'] }}</strong>:
                                        dibutuhkan <strong>{{ $cap['total_qty'] }}</strong> unit,
                                        tersedia <strong>{{ $cap['remaining'] }}/{{ $cap['max'] }}</strong> unit
                                        <span class="badge badge-danger ml-1">
                                            Kelebihan {{ $cap['total_qty'] - $cap['remaining'] }} unit
                                        </span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════
     QR SCAN (Opsional)
══════════════════════════════════════════════════════ --}}
        @if ($order->status !== 'completed')
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card card-outline card-secondary mb-0">
                        <div class="card-header py-2">
                            <h6 class="mb-0">
                                <i class="fas fa-qrcode mr-1 text-secondary"></i>
                                Scan QR Code Cell
                                <span class="badge badge-secondary ml-1" style="font-size:10px">OPSIONAL</span>
                                <small class="text-muted font-weight-normal ml-2">
                                    — Scan sticker QR yang menempel di rak fisik untuk verifikasi lokasi sebelum konfirmasi
                                </small>
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="qrInput" class="form-control"
                                            placeholder="Arahkan scanner atau ketik kode cell (contoh: R1A-A)..."
                                            autocomplete="off">
                                        <div class="input-group-append">
                                            <button class="btn btn-avian-secondary" id="btnScanQr" type="button"
                                                title="Tekan Enter atau klik untuk scan QR code cell">
                                                <i class="fas fa-search mr-1"></i> Cari
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8" id="qrResultPanel" style="display:none">
                                    <div class="d-flex align-items-center flex-wrap" style="gap:12px">
                                        <div>
                                            <span class="font-weight-bold" style="font-size:16px;color:#7b4f00"
                                                id="qrCellCode">-</span>
                                            <br>
                                            <small class="text-muted" id="qrCellMeta">-</small>
                                        </div>
                                        <div style="min-width:170px">
                                            <div class="d-flex justify-content-between" style="font-size:11px">
                                                <span class="text-muted">Kapasitas tersisa</span>
                                                <span id="qrCellCap" class="font-weight-bold">-</span>
                                            </div>
                                            <div class="cap-bar-wrap mt-1">
                                                <div class="cap-bar-fill bg-secondary" id="qrCapBar" style="width:0%">
                                                </div>
                                            </div>
                                        </div>
                                        <span class="badge badge-warning text-dark px-2 py-1">
                                            <i class="fas fa-qrcode mr-1"></i> Aktif — berlaku semua item
                                        </span>
                                        <button class="btn btn-xs btn-outline-danger" id="btnClearQr">
                                            <i class="fas fa-times mr-1"></i> Hapus
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Cell ini override rekomendasi GA saat klik Konfirmasi
                                        (kecuali item yang sudah dipilih alternatif manual)
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════
     TABEL UTAMA — PROSES PUT-AWAY
══════════════════════════════════════════════════════ --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-boxes mr-1"></i>
                            Daftar Item Inbound &mdash; Input Lokasi Penempatan (Send To)
                        </h6>
                        <small class="text-muted">
                            Kolom <strong>SEND TO</strong> = rekomendasi GA.
                            User dapat menerima atau mengganti ke lokasi lain.
                        </small>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="40" class="text-center align-middle">No.</th>
                                    <th class="align-middle">Deskripsi Item</th>
                                    <th width="60" class="text-center align-middle">Qty</th>
                                    @if ($hasLpn)
                                        <th width="85" class="align-middle">LPN</th>
                                    @endif
                                    <th class="align-middle" style="min-width:230px;background:#ffd740;color:#333">
                                        <i class="fas fa-map-marker-alt mr-1"></i> SEND TO
                                        <small class="d-block font-weight-normal" style="font-size:10px">
                                            Lokasi penempatan (GA suggest · bisa diubah)
                                        </small>
                                    </th>
                                    <th width="80" class="text-center align-middle">
                                        Fitness
                                        <small class="d-block font-weight-normal" style="font-size:9px">
                                            GA Score
                                        </small>
                                    </th>
                                    <th width="110" class="text-center align-middle">Status</th>
                                    <th width="130" class="text-center align-middle">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $firstPendingShown = false; @endphp
                                @foreach ($order->items as $i => $detail)
                                    @php
                                        $gaDetail = $gaDetailMap[$detail->id] ?? null;
                                        $isDone = $detail->status === 'put_away';
                                        $confirm = $detail->putAwayConfirmations->first();
                                        $capInfo =
                                            $gaDetail && $gaDetail->cell_id
                                                ? $cellCapInfo[$gaDetail->cell_id] ?? null
                                                : null;
                                        $isOver = !$isDone && $capInfo && $capInfo['total_qty'] > $capInfo['remaining'];
                                        $capRemain = $gaDetail?->cell?->capacity_remaining ?? 0;
                                        $capMax = $gaDetail?->cell?->capacity_max ?? 0;
                                        $usedPct =
                                            $capMax > 0 ? min(100, round((($capMax - $capRemain) / $capMax) * 100)) : 0;
                                        $orderQty = $capInfo['total_qty'] ?? $detail->quantity_received;
                                        $addPct =
                                            $capMax > 0 ? min(100 - $usedPct, round(($orderQty / $capMax) * 100)) : 0;
                                        $rowClass = $isDone ? 'row-done' : ($isOver ? 'row-overflow' : 'row-pending');
                                        $isNext = !$isDone && !$firstPendingShown && $order->status !== 'completed';
                                        if ($isNext) $firstPendingShown = true;
                                    @endphp
                                    <tr class="{{ $rowClass }}" id="row-{{ $detail->id }}"
                                        @if($isNext) style="outline:2px solid #28a745;outline-offset:-1px" @endif>

                                        {{-- No. --}}
                                        <td class="text-center align-middle font-weight-bold text-muted">
                                            {{ $i + 1 }}
                                            @if ($isNext)
                                                <div class="mt-1">
                                                    <span class="badge badge-success" style="font-size:9px;white-space:nowrap">
                                                        <i class="fas fa-arrow-right mr-1"></i>Berikutnya
                                                    </span>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Deskripsi --}}
                                        <td class="align-middle">
                                            <div class="font-weight-bold">{{ $detail->item?->name ?? '-' }}</div>
                                            <small class="text-muted">
                                                <code>{{ $detail->item?->sku ?? '-' }}</code>
                                                @if ($detail->item?->category)
                                                    &middot; {{ $detail->item->category->name }}
                                                @endif
                                            </small>
                                        </td>

                                        {{-- Qty --}}
                                        <td class="text-center align-middle">
                                            <span class="font-weight-bold" style="font-size:16px">
                                                {{ $detail->quantity_received }}
                                            </span>
                                            <br>
                                            <small class="text-muted">{{ $detail->item?->unit?->name ?? 'unit' }}</small>
                                        </td>

                                        {{-- LPN --}}
                                        @if ($hasLpn)
                                            <td class="align-middle">
                                                <code class="small">{{ $detail->lpn ?? '-' }}</code>
                                            </td>
                                        @endif

                                        {{-- ════ SEND TO ════ --}}
                                        <td class="align-middle px-2 py-2">
                                            @if ($isDone && $confirm)
                                                @php
                                                    $qtyStored = $confirm->quantity_stored ?? 0;
                                                    $qtyOrdered = $detail->quantity_received;
                                                    $isPartialStore = $qtyStored < $qtyOrdered;
                                                    $unitName = $detail->item?->unit?->name ?? 'unit';
                                                @endphp
                                                <div class="send-to-cell done">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="cell-code c-done">
                                                            <i class="fas fa-check-circle mr-1"></i>
                                                            {{ $confirm->cell?->code ?? '-' }}
                                                        </span>
                                                        @if (!$confirm->follow_recommendation)
                                                            <span class="badge badge-warning text-dark"
                                                                style="font-size:9px;cursor:help"
                                                                title="Item ditempatkan di luar rekomendasi GA — bisa karena kapasitas cell penuh atau keputusan supervisor.">
                                                                <i class="fas fa-exchange-alt mr-1"></i>Override
                                                            </span>
                                                        @else
                                                            <span class="badge badge-success" style="font-size:9px">
                                                                <i class="fas fa-check mr-1"></i>Sesuai GA
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <small class="text-muted">
                                                        {{ $confirm->cell?->rack?->zone?->name ?? '-' }}
                                                        &middot; Rack {{ $confirm->cell?->rack?->code ?? '-' }}
                                                    </small>
                                                    @if ($qtyStored)
                                                        <small
                                                            class="{{ $isPartialStore ? 'text-warning' : 'text-success' }} d-block mt-1">
                                                            <i class="fas fa-box mr-1"></i>
                                                            Qty Tersimpan:
                                                            <strong>{{ $qtyStored }}</strong>
                                                            dari {{ $qtyOrdered }} {{ $unitName }}
                                                            @if ($isPartialStore)
                                                                <span class="badge badge-warning text-dark ml-1"
                                                                    style="font-size:9px"
                                                                    title="Hanya sebagian qty yang disimpan di cell ini. Sisanya mungkin perlu konfirmasi ke cell lain.">
                                                                    Parsial
                                                                </span>
                                                            @endif
                                                        </small>
                                                    @endif
                                                    <small class="text-muted d-block mt-1" style="font-size:10px;border-top:1px solid #d4edda;padding-top:4px">
                                                        <i class="fas fa-user-check mr-1"></i>
                                                        <strong>{{ $confirm->user?->name ?? '-' }}</strong>
                                                        &middot;
                                                        {{ $confirm->confirmed_at?->format('d M Y H:i') ?? '-' }}
                                                    </small>
                                                </div>
                                            @elseif ($gaDetail && $gaDetail->cell)
                                                <div class="send-to-cell {{ $isOver ? 'overflow' : '' }}"
                                                    id="sendToBox-{{ $detail->id }}">

                                                    {{-- Cell code + badge --}}
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="cell-code c-ga" id="sendToCode-{{ $detail->id }}">
                                                            {{ $gaDetail->cell->code }}
                                                        </span>
                                                        <div class="d-flex align-items-center" style="gap:4px">
                                                            <span class="badge badge-primary" style="font-size:9px"
                                                                id="sendToBadge-{{ $detail->id }}">
                                                                GA Suggest
                                                            </span>
                                                            <a href="{{ route('warehouse3d.index', ['highlight_cell_id' => $gaDetail->cell_id]) }}"
                                                               target="_blank"
                                                               title="Lihat posisi cell ini di denah 3D"
                                                               class="badge badge-dark"
                                                               style="font-size:9px;text-decoration:none">
                                                                <i class="fas fa-cube mr-1"></i>3D
                                                            </a>
                                                        </div>
                                                    </div>

                                                    {{-- Lokasi --}}
                                                    <small class="text-muted d-block"
                                                        id="sendToMeta-{{ $detail->id }}">
                                                        Zone {{ $gaDetail->cell->zone_category ?? '-' }}
                                                        &middot; {{ $gaDetail->cell->rack?->zone?->name ?? '-' }}
                                                        &middot; Rack {{ $gaDetail->cell->rack?->code ?? '-' }}
                                                    </small>

                                                    {{-- Kapasitas bar --}}
                                                    <div class="mt-2">
                                                        <div class="d-flex justify-content-between"
                                                            style="font-size:10px;margin-bottom:2px">
                                                            <span
                                                                class="{{ $isOver ? 'text-danger font-weight-bold' : 'text-muted' }}"
                                                                id="sendToCapLabel-{{ $detail->id }}">
                                                                Order ke sini: {{ $orderQty }} unit
                                                            </span>
                                                            <span class="text-muted"
                                                                id="sendToCapRemain-{{ $detail->id }}">
                                                                Sisa: {{ $capRemain }}/{{ $capMax }}
                                                            </span>
                                                        </div>
                                                        <div class="cap-bar-wrap">
                                                            <div class="cap-bar-fill bg-secondary"
                                                                style="width:{{ $usedPct }}%"></div>
                                                            <div class="cap-bar-fill {{ $isOver ? 'bg-danger' : 'bg-info' }}"
                                                                style="width:{{ $addPct }}%;margin-top:-5px"></div>
                                                        </div>
                                                        <div style="font-size:10px;margin-top:2px"
                                                            id="sendToCapStatus-{{ $detail->id }}">
                                                            @if ($isOver)
                                                                <span class="text-danger">
                                                                    <i class="fas fa-times-circle"></i>
                                                                    Kelebihan
                                                                    {{ $capInfo['total_qty'] - $capInfo['remaining'] }} unit
                                                                </span>
                                                            @else
                                                                <span class="text-success">
                                                                    <i class="fas fa-check-circle"></i>
                                                                    Kapasitas mencukupi
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- Tombol Ganti Cell --}}
                                                    <div class="mt-2">
                                                        <button
                                                            class="btn btn-xs w-100
                                                    {{ $isOver ? 'btn-warning' : 'btn-outline-secondary' }}
                                                    btnPickAlt"
                                                            data-detail-id="{{ $detail->id }}"
                                                            data-item-name="{{ $detail->item?->name ?? '' }}"
                                                            data-qty="{{ $detail->quantity_received }}"
                                                            data-ga-cell-id="{{ $gaDetail->cell_id }}">
                                                            <i
                                                                class="fas fa-{{ $isOver ? 'magic' : 'exchange-alt' }} mr-1"></i>
                                                            {{ $isOver ? 'Ganti Cell (Penuh!)' : 'Ganti Cell' }}
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted small">Tidak ada rekomendasi</span>
                                            @endif
                                        </td>

                                        {{-- Fitness --}}
                                        <td class="text-center align-middle">
                                            @if ($gaDetail)
                                                @php $gf = $gaDetail->gene_fitness ?? 0; @endphp
                                                <div class="font-weight-bold {{ $gf >= 70 ? 'text-success' : ($gf >= 50 ? 'text-warning' : 'text-danger') }}"
                                                    style="font-size:18px"
                                                    title="Skor fitness GA untuk item ini (maks 100). Menggabungkan: kapasitas cell, kesesuaian kategori, afinitas, dan anti-split.">
                                                    {{ number_format($gf, 1) }}
                                                </div>
                                                <small class="text-muted" style="font-size:9px">/ 100</small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Status --}}
                                        <td class="text-center align-middle" id="status-{{ $detail->id }}">
                                            @if ($isDone)
                                                <span class="badge badge-success px-2 py-1">
                                                    <i class="fas fa-check mr-1"></i>Put Away
                                                </span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1">
                                                    <i class="fas fa-clock mr-1"></i>Menunggu
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Aksi --}}
                                        <td class="text-center align-middle" id="action-{{ $detail->id }}">
                                            @if (!$isDone && $order->status !== 'completed')
                                                <div class="d-flex flex-column" style="gap:4px">
                                                    <button class="btn btn-sm btn-success btnConfirm"
                                                        data-detail-id="{{ $detail->id }}"
                                                        data-item-name="{{ $detail->item?->name ?? '' }}"
                                                        data-qty="{{ $detail->quantity_received }}"
                                                        data-ga-cell="{{ $gaDetail?->cell?->code ?? '' }}"
                                                        data-ga-cell-id="{{ $gaDetail?->cell_id ?? '' }}"
                                                        data-cap-remaining="{{ $capRemain }}"
                                                        data-cap-max="{{ $capMax }}"
                                                        title="Klik untuk konfirmasi penempatan item ke cell tujuan">
                                                        <i class="fas fa-check mr-1"></i>Konfirmasi
                                                    </button>
                                                    @if (auth()->user()->isAdmin() || auth()->user()->isSupervisor())
                                                        <button class="btn btn-xs btn-outline-warning btnOverride"
                                                            data-detail-id="{{ $detail->id }}"
                                                            data-item-name="{{ $detail->item?->name ?? '' }}"
                                                            data-qty="{{ $detail->quantity_received }}"
                                                            title="Override: paksa penempatan ke cell berbeda dari rekomendasi GA. Hanya Admin/Supervisor.">
                                                            <i class="fas fa-exchange-alt mr-1"></i>Override Lokasi
                                                        </button>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-center">
                                                    <i class="fas fa-check-double text-success fa-lg"></i>
                                                    <div class="text-success" style="font-size:10px;margin-top:2px">
                                                        Selesai</div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer text-muted" style="font-size:12px">
                        <i class="fas fa-info-circle mr-1"></i>
                        Kolom <strong>SEND TO</strong> = lokasi rekomendasi GA. Jika cell penuh, klik
                        <strong>"Ganti Cell"</strong> agar sistem carikan alternatif otomatis.
                        Setelah lokasi dipastikan → klik <strong>Konfirmasi</strong> → isi qty aktual →
                        <strong>Save/Update</strong>.
                        @if (auth()->user()->isAdmin() || auth()->user()->isSupervisor())
                            &ensp;|&ensp;
                            <span class="text-warning">
                                <i class="fas fa-shield-alt mr-1"></i>
                                <strong>Override Lokasi</strong> = paksa cell berbeda dari GA (Admin/Supervisor).
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /container-fluid --}}

    {{-- ══════════════════════════════════════════════════════
     MODAL: Rekomendasi Cell Alternatif
══════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalAltCell" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#ffd740">
                    <h6 class="modal-title text-dark">
                        <i class="fas fa-magic mr-1"></i> Ganti Cell — Rekomendasi Sistem
                        <small class="font-weight-normal ml-1 text-dark" id="altModalItemName"></small>
                    </h6>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-0">
                    <div class="px-3 py-2 border-bottom bg-light" style="font-size:13px">
                        <i class="fas fa-map-marker-alt mr-1 text-danger"></i>
                        Cell GA saat ini: <strong id="altSrcCell">-</strong>
                        &ensp;|&ensp; Dibutuhkan: <strong id="altQtyNeeded">-</strong> unit
                        &ensp;|&ensp; Tersisa: <strong id="altSrcRemain">-</strong> unit
                        <span id="altSrcOverMsg" class="badge badge-danger ml-2" style="display:none">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Tidak cukup!
                        </span>
                    </div>
                    <div id="altLoading" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <div class="text-muted mt-2">Sistem sedang mencari cell alternatif...</div>
                    </div>
                    <div id="altList" style="display:none">
                        <div class="px-3 pt-3 pb-1">
                            <small class="text-muted">
                                <i class="fas fa-sort-amount-down mr-1"></i>
                                Diurutkan: zona sama dulu → muat semua qty → kapasitas terbesar.
                                &ensp;
                                <span class="badge badge-success">✓ Muat semua</span>
                                <span class="badge badge-warning text-dark ml-1">⚠ Partial</span>
                            </small>
                        </div>
                        <div class="px-3 pb-3" id="altCards"></div>
                        <div id="altEmpty" class="text-center text-muted py-4" style="display:none">
                            <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                            Tidak ada cell lain yang tersedia di warehouse ini.
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <small class="text-muted mr-auto">
                        <i class="fas fa-info-circle mr-1"></i>
                        Klik "Pilih" → lalu klik Konfirmasi di baris item untuk menyimpan.
                    </small>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     MODAL: Konfirmasi Put-Away — 2-Phase (Scan → Confirm)
══════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalConfirm" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;position:relative">

                {{-- ── Saving overlay (tampil saat AJAX berlangsung) ── --}}
                <div id="modalSavingOverlay" style="display:none">
                    <div style="background:#fff;border-radius:10px;
                                padding:2.2em 3em;text-align:center;
                                box-shadow:0 0 0 1px rgba(0,0,0,.06),0 8px 28px rgba(0,0,0,.18);
                                min-width:260px">
                        <div style="font-size:1.5em;font-weight:600;color:#545454;margin-bottom:16px">
                            Menyimpan…
                        </div>
                        <i class="fas fa-circle-notch fa-spin" style="font-size:2.4em;color:#0d8564"></i>
                    </div>
                </div>

                {{-- ── Header ── --}}
                <div class="modal-header py-2 px-3" id="confirmModalHeader" style="background:#28a745">
                    <div>
                        <h6 class="modal-title text-white mb-0">
                            <i class="fas fa-dolly-flatbed mr-1"></i>
                            <span id="confirmModalTitle">Konfirmasi Put-Away</span>
                        </h6>
                        <small class="text-white" style="opacity:.8;font-size:11px" id="confirmModalSubtitle"></small>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>

                {{-- ── Item info strip ── --}}
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"
                     style="background:#f8f9fa">
                    <div style="font-size:13px">
                        <span class="text-muted">Item: </span>
                        <strong id="confirmItemName">-</strong>
                    </div>
                    <div class="text-right">
                        <span class="font-weight-bold text-primary" style="font-size:20px" id="confirmItemQty">-</span>
                        <span class="text-muted" style="font-size:11px"> unit</span>
                    </div>
                </div>

                <div class="modal-body p-0">

                    {{-- ════════════════════════════════════
                         PHASE 1 — SCAN QR / KAMERA
                    ════════════════════════════════════ --}}
                    <div id="phaseScan" class="px-3 py-3">

                        {{-- ── CAMERA VIEWPORT (tersembunyi sampai kamera dibuka) ── --}}
                        <div id="cameraSection" style="display:none" class="mb-3">
                            <div id="cameraViewport">
                                <div id="qrCameraReader"></div>
                                <div id="scanLine"></div>
                                <div class="cam-corner" id="camCornTL"></div>
                                <div class="cam-corner" id="camCornTR"></div>
                                <div class="cam-corner" id="camCornBL"></div>
                                <div class="cam-corner" id="camCornBR"></div>
                                {{-- Flash sukses ─ hijau sebentar saat terdeteksi --}}
                                <div id="cameraScanSuccess">
                                    <i class="fas fa-check-circle"
                                       style="color:#fff;font-size:52px;
                                              text-shadow:0 2px 12px rgba(0,0,0,.4);
                                              animation:none"></i>
                                </div>
                            </div>
                            {{-- Controls: pilih kamera + torch + tutup --}}
                            <div class="d-flex align-items-center mt-2" style="gap:6px">
                                <select id="cameraSelect" class="form-control form-control-sm"
                                        style="flex:1;font-size:12px"></select>
                                <button type="button" id="btnTorch"
                                        class="btn btn-sm btn-outline-secondary"
                                        style="display:none;flex-shrink:0" title="Flash/Torch">
                                    <i class="fas fa-bolt"></i>
                                </button>
                                <button type="button" id="btnCloseCamera"
                                        class="btn btn-sm btn-outline-danger"
                                        style="flex-shrink:0">
                                    <i class="fas fa-times mr-1"></i>Tutup
                                </button>
                            </div>
                            <div id="cameraStatus" class="text-center mt-1" style="font-size:11px;color:#6c757d">
                                <i class="fas fa-circle-notch fa-spin mr-1"></i>Mengaktifkan kamera…
                            </div>
                        </div>

                        {{-- ── TOMBOL BUKA KAMERA ── --}}
                        <button type="button" id="btnOpenCamera" class="btn btn-block mb-3"
                                style="background:#1a2332;color:#fff;border:none;border-radius:8px;
                                       padding:11px 16px;font-size:14px;font-weight:600;
                                       box-shadow:0 3px 10px rgba(0,0,0,.18)">
                            <i class="fas fa-camera mr-2"></i>Scan dengan Kamera
                            <span style="font-size:10px;background:rgba(255,255,255,.15);
                                         padding:2px 8px;border-radius:10px;margin-left:6px">
                                QR &amp; Barcode 1D/2D
                            </span>
                        </button>

                        {{-- ── DIVIDER ── --}}
                        <div class="d-flex align-items-center mb-3">
                            <hr style="flex:1;margin:0">
                            <span class="text-muted px-2" style="font-size:11px;white-space:nowrap">
                                atau ketik kode manual
                            </span>
                            <hr style="flex:1;margin:0">
                        </div>

                        {{-- ── INPUT MANUAL (fisik scanner / keyboard) ── --}}
                        <div class="input-group mb-2" style="box-shadow:0 2px 8px rgba(0,0,0,.08)">
                            <div class="input-group-prepend">
                                <span class="input-group-text"
                                      style="background:#1a2332;border-color:#1a2332">
                                    <i class="fas fa-qrcode text-white" style="font-size:16px"></i>
                                </span>
                            </div>
                            <input type="text" id="modalQrInput"
                                   class="form-control"
                                   placeholder="Scan pistol / ketik kode cell…"
                                   autocomplete="off"
                                   style="font-size:16px;font-weight:600;
                                          letter-spacing:.5px;border-color:#dee2e6">
                            <div class="input-group-append">
                                <button class="btn" type="button" id="btnModalScanQr"
                                        style="background:#1a2332;color:#fff;
                                               border-color:#1a2332;font-size:13px">
                                    <i class="fas fa-search mr-1"></i>Cari
                                </button>
                            </div>
                        </div>

                        {{-- Spinner saat resolve cell --}}
                        <div id="scanLoading" class="text-center py-2" style="display:none">
                            <i class="fas fa-spinner fa-spin text-primary mr-1"></i>
                            <small class="text-muted">Mengidentifikasi cell…</small>
                        </div>

                        {{-- Status auto-confirm (muncul sebentar sebelum modal tutup) --}}
                        <div id="autoConfirmStatus" class="text-center py-2" style="display:none"></div>

                        {{-- ── DIVIDER + SKIP ── --}}
                        <div class="d-flex align-items-center my-3">
                            <hr style="flex:1;margin:0">
                            <span class="text-muted px-2" style="font-size:11px;white-space:nowrap">atau</span>
                            <hr style="flex:1;margin:0">
                        </div>
                        <button type="button" id="btnSkipScan"
                                class="btn btn-outline-secondary btn-block"
                                style="font-size:13px">
                            <i class="fas fa-forward mr-1"></i>
                            Lanjut tanpa scan — pakai rekomendasi GA
                            <span class="badge badge-primary ml-1" id="skipGaCellCode"
                                  style="font-size:11px"></span>
                        </button>
                        <small class="text-muted d-block text-center mt-2" style="font-size:11px">
                            Pilih ini jika QR belum tersedia di rak fisik
                        </small>
                    </div>

                    {{-- ════════════════════════════════════
                         PHASE 2 — CONFIRM
                    ════════════════════════════════════ --}}
                    <div id="phaseConfirm" style="display:none">

                        {{-- Cell result card --}}
                        <div id="cellResultCard" class="mx-3 mt-3 mb-2 p-3 rounded"
                             style="border:2px solid #28a745;background:#f0fff4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div style="font-size:28px;font-weight:800;letter-spacing:1px;line-height:1"
                                         id="resultCellCode">—</div>
                                    <div class="text-muted mt-1" style="font-size:12px" id="resultCellMeta"></div>
                                </div>
                                <div class="text-right">
                                    <div id="resultMatchBadge"></div>
                                    <div class="mt-1" style="font-size:11px" id="resultCapInfo"></div>
                                </div>
                            </div>
                            {{-- Capacity bar --}}
                            <div id="resultCapBar" class="mt-2" style="display:none">
                                <div class="cap-bar-wrap">
                                    <div class="cap-bar-fill bg-secondary" id="resultCapBarUsed" style="width:0%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Perbandingan Rekomendasi GA vs Cell yang Dipilih --}}
                        <div id="gaComparePanel" class="mx-3 mt-2 mb-0 p-2 rounded"
                             style="display:none;background:#f8f9fa;border:1px solid #dee2e6;font-size:12px">
                            <div class="row no-gutters mb-1">
                                <div class="col-6 pr-1">
                                    <span class="text-muted d-block" style="font-size:10px;text-transform:uppercase;letter-spacing:.4px">Rekomendasi GA</span>
                                    <span class="font-weight-bold" id="gaCompareGaCell" style="font-size:15px;color:#0056b3">—</span>
                                </div>
                                <div class="col-6 pl-1">
                                    <span class="text-muted d-block" style="font-size:10px;text-transform:uppercase;letter-spacing:.4px">Cell Dipilih</span>
                                    <span class="font-weight-bold" id="gaCompareScannedCell" style="font-size:15px;color:#155724">—</span>
                                </div>
                            </div>
                            <div id="gaCompareStatus"></div>
                        </div>

                        {{-- Warning kapasitas --}}
                        <div id="resultCapWarning" class="alert alert-warning mx-3 py-2 mb-0"
                             style="display:none;font-size:12px">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span id="resultCapWarningText"></span>
                        </div>

                        {{-- Qty (bisa diedit) --}}
                        <div class="px-3 pt-3 pb-1">
                            <div class="mb-1">
                                <label class="text-muted mb-0" style="font-size:12px">
                                    <i class="fas fa-boxes mr-1"></i>Qty yang Ditempatkan
                                    <small class="text-info ml-1">(harus = qty diterima di dock)</small>
                                </label>
                            </div>
                            <div id="qtyDisplay"
                                 style="font-size:32px;font-weight:800;text-align:center;
                                        color:#0d8564;line-height:1.1;padding:6px 0"
                                 id="qtyDisplayNum">-</div>
                            <input type="number" id="confirmQty" class="form-control"
                                   min="1" style="display:none;font-size:22px;font-weight:700;
                                   text-align:center;border:2px solid #0d8564">
                            <div class="text-muted text-center" style="font-size:11px" id="qtyUnitLabel"></div>
                        </div>

                        {{-- Catatan --}}
                        <div class="px-3 pb-2">
                            <input type="text" id="confirmNotes" class="form-control form-control-sm"
                                   placeholder="Catatan opsional…">
                        </div>

                        {{-- Tombol scan ulang --}}
                        <div class="px-3 pb-3">
                            <button type="button" id="btnRescan" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-redo mr-1"></i>Scan ulang cell lain
                            </button>
                        </div>
                    </div>

                </div>

                {{-- ── Footer ── --}}
                <div class="modal-footer py-2 px-3 justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Batal
                    </button>
                    {{-- Phase 1: tombol ini tersembunyi --}}
                    {{-- Phase 2: tombol utama besar --}}
                    <button type="button" class="btn btn-success" id="btnDoConfirm"
                            style="display:none;font-size:15px;padding:8px 28px;font-weight:700;
                                   box-shadow:0 3px 10px rgba(40,167,69,.35)">
                        <i class="fas fa-check-circle mr-2"></i>Konfirmasi Sekarang
                    </button>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- html5-qrcode: support QR Code, Code128, Code39, EAN, DataMatrix, dll --}}
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const orderId    = {{ $order->id }};
        const scanQrUrl  = "{{ route('putaway.scan-qr') }}";
        const altCellUrl = "{{ route('putaway.alternative-cells', $order->id) }}";
        const csrfToken  = $('meta[name="csrf-token"]').attr('content');

        // ── State ─────────────────────────────────────────────────────────────────────
        let selectedCellMap = {};   // cell dipilih dari modal alternatif, per item
        let currentDetailId = null;
        let isOverride      = false;
        let modalCell       = null; // cell aktif di phase 2
        let modalGaCell     = null; // referensi GA (untuk match indicator)
        let modalQty        = 0;    // qty inbound item saat ini
        let qtyEditing      = false;

        // ── QR SCAN (panel atas — opsional global) ───────────────────────────────────
        function doScanQr(code) {
            if (!code) return;
            $.ajax({
                url: scanQrUrl, method: 'POST',
                data: { _token: csrfToken, qr_code: code },
                success: function(res) {
                    const c = res.cell;
                    const rem = c.capacity_remaining || 0;
                    const max = c.capacity_max || 0;
                    const pct = max > 0 ? Math.min(100, Math.round((max - rem) / max * 100)) : 0;
                    $('#qrCellCode').text(c.code);
                    $('#qrCellMeta').text('Zone ' + (c.zone_category || '-') + ' · Rack ' + (c.rack_code || '-'));
                    $('#qrCellCap').text(rem + ' / ' + max + ' unit');
                    $('#qrCapBar').css('width', pct + '%');
                    $('#qrResultPanel').show();
                    $('#qrInput').val('');
                },
                error: function(xhr) {
                    Swal.fire('Cell Tidak Ditemukan', xhr.responseJSON?.message || 'Kode tidak dikenali.', 'error');
                }
            });
        }
        $('#btnScanQr').on('click', () => doScanQr($('#qrInput').val().trim()));
        $('#qrInput').on('keydown', function(e) {
            if (e.key === 'Enter') { doScanQr($(this).val().trim()); e.preventDefault(); }
        });
        $('#btnClearQr').on('click', function() {
            $('#qrResultPanel').hide();
            $('#qrInput').val('').focus();
        });

        // ── MODAL ALTERNATIF ─────────────────────────────────────────────────────────
        $(document).on('click', '.btnPickAlt', function() {
            const btn = $(this);
            currentDetailId = btn.data('detail-id');

            // Reset modal state
            $('#altSrcOverMsg').hide();
            $('#altLoading').show();
            $('#altList').hide();
            $('#altCards').empty();
            $('#altEmpty').hide();
            $('#altModalItemName').text('— ' + btn.data('item-name'));
            $('#modalAltCell').modal('show');

            $.ajax({
                url: altCellUrl,
                method: 'GET',
                data: {
                    for_cell_id: btn.data('ga-cell-id'),
                    qty: parseInt(btn.data('qty'))
                },
                success: function(res) {
                    $('#altSrcCell').text(res.source_cell.code);
                    $('#altQtyNeeded').text(res.qty_needed);
                    $('#altSrcRemain').text(res.source_cell.capacity_remaining);
                    if (res.source_cell.capacity_remaining < res.qty_needed) {
                        $('#altSrcOverMsg').show();
                    }
                    $('#altLoading').hide();
                    $('#altList').show();

                    if (!res.alternatives || res.alternatives.length === 0) {
                        $('#altEmpty').show();
                        return;
                    }

                    res.alternatives.forEach(function(cell) {
                        const usedPct = cell.capacity_max > 0 ?
                            Math.min(100, Math.round(cell.capacity_used / cell.capacity_max *
                                100)) : 0;
                        const badge = cell.fits_all ?
                            '<span class="badge badge-success"><i class="fas fa-check mr-1"></i>Muat semua</span>' :
                            '<span class="badge badge-warning text-dark"><i class="fas fa-exclamation mr-1"></i>Partial (' +
                            cell.capacity_remaining + ' unit)</span>';

                        $('#altCards').append(`
                <div class="card border mb-2">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div style="flex:1">
                                <div class="d-flex align-items-center mb-1" style="gap:8px">
                                    <span style="font-size:18px;font-weight:700;color:#0056b3">${cell.code}</span>
                                    ${badge}
                                </div>
                                <small class="text-muted">
                                    Zone ${cell.zone_category} &middot; ${cell.zone}
                                    &middot; Rack ${cell.rack_code}
                                </small>
                                <div class="mt-1">
                                    <div class="d-flex justify-content-between" style="font-size:11px">
                                        <span class="text-muted">Sisa: <strong>${cell.capacity_remaining}</strong> dari ${cell.capacity_max} unit</span>
                                        <span class="text-muted">Terpakai ${usedPct}%</span>
                                    </div>
                                    <div class="cap-bar-wrap mt-1">
                                        <div class="cap-bar-fill bg-secondary" style="width:${usedPct}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-3">
                                <button class="btn btn-primary btnSelectAlt"
                                    data-cell-id="${cell.id}" data-cell-code="${cell.code}"
                                    data-cap-remaining="${cell.capacity_remaining}"
                                    data-cap-max="${cell.capacity_max}">
                                    <i class="fas fa-check mr-1"></i> Pilih
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`);
                    });
                },
                error: function() {
                    Swal.fire('Error', 'Gagal mengambil rekomendasi cell.', 'error');
                    $('#modalAltCell').modal('hide');
                }
            });
        });

        // User pilih cell alternatif → update kolom Send To di tabel
        $(document).on('click', '.btnSelectAlt', function() {
            const btn = $(this);
            const cell = {
                id: btn.data('cell-id'),
                code: btn.data('cell-code'),
                capacity_remaining: parseInt(btn.data('cap-remaining')),
                capacity_max: parseInt(btn.data('cap-max')),
                source: 'alternatif',
            };
            selectedCellMap[currentDetailId] = cell;

            const qty = parseInt($('.btnPickAlt[data-detail-id="' + currentDetailId + '"]').data('qty'));
            const fits = cell.capacity_remaining >= qty;

            $('#sendToCode-' + currentDetailId)
                .removeClass('c-ga c-alt c-scan').addClass('c-alt')
                .html('<i class="fas fa-magic mr-1" style="font-size:11px"></i>' + cell.code);
            $('#sendToBadge-' + currentDetailId)
                .removeClass('badge-primary badge-warning badge-secondary')
                .addClass('badge-success').text('Dipilih Alternatif');
            $('#sendToMeta-' + currentDetailId)
                .html(
                '<span class="text-success"><i class="fas fa-check-circle mr-1"></i>Siap dikonfirmasi</span>');
            $('#sendToCapStatus-' + currentDetailId).html(
                fits ?
                '<span class="text-success"><i class="fas fa-check-circle"></i> Muat semua</span>' :
                '<span class="text-warning"><i class="fas fa-exclamation-circle"></i> Partial: ' + cell
                .capacity_remaining + ' unit</span>'
            );
            $('#sendToBox-' + currentDetailId).removeClass('overflow').addClass('alt-selected');
            $('#row-' + currentDetailId).removeClass('row-overflow').addClass('row-pending');

            $('#modalAltCell').modal('hide');
            Swal.fire({
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                title: 'Cell dipilih: ' + cell.code,
                text: 'Klik Konfirmasi di baris item untuk menyimpan.'
            });
        });

        // ══════════════════════════════════════════════════════════════════════
        //  MODAL KONFIRMASI — 2-PHASE
        //  Phase 1 (Scan)   : operator scan QR di rak
        //  Phase 2 (Confirm): tampil cell + qty auto-fill → tap Konfirmasi Sekarang
        // ══════════════════════════════════════════════════════════════════════

        // ── Helper: tampilkan Phase 1 ─────────────────────────────────────────────────
        function showScanPhase() {
            $('#phaseScan').show();
            $('#phaseConfirm').hide();
            $('#btnDoConfirm').hide();
            $('#autoConfirmStatus').hide();
            $('#scanLoading').hide();
            $('#modalQrInput').val('').focus();
        }

        // ── Helper: tampilkan Phase 2 dengan data cell ────────────────────────────────
        function showConfirmPhase(cell) {
            modalCell = cell;
            $('#modalConfirm').data('cell-id', cell.id);

            // ── Card cell ──
            const isMatch   = modalGaCell && cell.id === modalGaCell.id;
            const isDiff    = modalGaCell && cell.id !== modalGaCell.id;
            const isGaSkip  = cell.source === 'ga';

            // Warna card: scan-match=hijau, scan-beda=oranye, ga-skip=biru
            let cardBorder = '#28a745', cardBg = '#f0fff4';
            if (isGaSkip)       { cardBorder = '#007bff'; cardBg = '#f0f7ff'; }
            else if (isDiff)    { cardBorder = '#fd7e14'; cardBg = '#fff8f0'; }

            $('#cellResultCard').css({ borderColor: cardBorder, background: cardBg });
            $('#resultCellCode').text(cell.code);
            $('#resultCellMeta').text(
                (cell.zone_category ? 'Zone ' + cell.zone_category + ' · ' : '') +
                (cell.rack_code ? 'Rack ' + cell.rack_code : '')
            );

            // Badge cocok / beda / GA
            let badgeHtml = '';
            if (isGaSkip) {
                badgeHtml = '<span class="badge badge-primary"><i class="fas fa-dna mr-1"></i>Rekomendasi GA</span>';
            } else if (isMatch) {
                badgeHtml = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Cocok dengan GA ✓</span>';
            } else if (isDiff) {
                badgeHtml = '<span class="badge badge-warning text-dark"><i class="fas fa-exclamation-triangle mr-1"></i>Beda dari GA (' + modalGaCell.code + ')</span>';
            } else {
                badgeHtml = '<span class="badge badge-secondary">Scan QR</span>';
            }
            $('#resultMatchBadge').html(badgeHtml);

            // Kapasitas
            const rem = cell.capacity_remaining || 0;
            const max = cell.capacity_max || 0;
            let capOk = true;

            if (max > 0) {
                const usedPct = Math.min(100, Math.round((max - rem) / max * 100));
                $('#resultCapInfo').text('Sisa kapasitas: ' + rem + ' / ' + max + ' unit');
                $('#resultCapBarUsed').css('width', usedPct + '%');
                $('#resultCapBar').show();

                if (rem <= 0) {
                    $('#resultCapWarningText').text('Cell ini penuh — scan cell lain yang cukup kapasitasnya.');
                    $('#resultCapWarning').show();
                    capOk = false;
                } else if (modalQty > rem) {
                    $('#resultCapWarningText').html(
                        'Kapasitas tidak cukup: butuh <strong>' + modalQty + '</strong> unit, tersedia <strong>' + rem + '</strong> unit. ' +
                        'Scan atau pilih cell lain yang muat.'
                    );
                    $('#resultCapWarning').show();
                    capOk = false;
                } else {
                    $('#resultCapWarning').hide();
                }
            } else {
                $('#resultCapBar').hide();
                $('#resultCapWarning').hide();
                $('#resultCapInfo').text('');
            }

            // ── Panel perbandingan GA vs cell dipilih ──
            if (modalGaCell) {
                $('#gaCompareGaCell').text(modalGaCell.code);
                $('#gaCompareScannedCell').text(isGaSkip ? modalGaCell.code : cell.code);
                if (isMatch || isGaSkip) {
                    $('#gaCompareStatus').html(
                        '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Sesuai rekomendasi GA — dicatat sebagai mengikuti GA</span>'
                    );
                } else {
                    $('#gaCompareStatus').html(
                        '<span class="badge badge-warning text-dark"><i class="fas fa-exclamation-triangle mr-1"></i>Tidak sesuai — dicatat sebagai tidak mengikuti rekomendasi GA</span>'
                    );
                }
                $('#gaComparePanel').show();
            } else {
                $('#gaComparePanel').hide();
            }

            // ── Qty display (strict = quantity_received, tidak bisa diubah) ──
            qtyEditing = false;
            $('#confirmQty').val(modalQty).hide();
            $('#qtyDisplay').text(modalQty);
            $('#qtyUnitLabel').text('unit yang akan ditempatkan');
            $('#confirmNotes').val(isOverride ? '[OVERRIDE] ' : '');

            $('#phaseScan').hide();
            $('#phaseConfirm').show();

            // Disable konfirmasi jika kapasitas tidak mencukupi
            if (capOk) {
                $('#btnDoConfirm').prop('disabled', false)
                    .html('<i class="fas fa-check-circle mr-2"></i>Konfirmasi Sekarang')
                    .show();
            } else {
                $('#btnDoConfirm').prop('disabled', true)
                    .html('<i class="fas fa-times-circle mr-1"></i>Kapasitas tidak cukup — scan cell lain')
                    .show();
            }
        }

        // ── Simpan ke DB (dipanggil dari auto-confirm maupun tombol manual) ─────────
        const MIN_LOADER_MS = 800; // minimum durasi overlay terlihat

        function doSaveConfirm(cellId, qty, notes, cellCode) {
            const url = isOverride
                ? `/putaway/${orderId}/items/${currentDetailId}/override`
                : `/putaway/${orderId}/items/${currentDetailId}/confirm`;

            // Catat waktu mulai — untuk hitung sisa minimum durasi
            const overlayStart = Date.now();

            // Tampilkan overlay spinner di atas modal
            $('#modalSavingOverlay').show();
            // Disable + spinner pada tombol confirm (jika Phase 2 sedang aktif)
            $('#btnDoConfirm').prop('disabled', true)
                .html('<i class="fas fa-circle-notch fa-spin mr-2"></i>Menyimpan…');

            // Helper: jalankan fn setelah minimum durasi overlay terpenuhi
            function afterMinLoader(fn) {
                const elapsed  = Date.now() - overlayStart;
                const waitMore = Math.max(0, MIN_LOADER_MS - elapsed);
                setTimeout(fn, waitMore);
            }

            $.ajax({
                url, method: 'POST',
                data: { _token: csrfToken, cell_id: cellId, quantity_stored: qty, notes: notes || '' },
                success: function(res) {
                    if (res.status !== 'success') return;

                    afterMinLoader(function() {
                        $('#modalConfirm').modal('hide');

                        const isComplete = res.progress && res.progress.is_complete;
                        if (isComplete) {
                            // Semua item selesai → redirect ke daftar put-away
                            Swal.fire({
                                icon: 'success', title: 'Order Selesai!',
                                text: 'Semua item berhasil di-put-away.',
                                confirmButtonText: 'Kembali ke Daftar'
                            }).then(() => window.location.href = "{{ route('putaway.index') }}");
                        } else {
                            // Reload halaman agar data server selalu fresh
                            location.reload();
                        }
                    });
                },
                error: function(xhr) {
                    // Gagal: tunggu minimum durasi lalu sembunyikan overlay & kembali ke Phase 2
                    afterMinLoader(function() {
                        $('#modalSavingOverlay').hide();
                        $('#btnDoConfirm').prop('disabled', false)
                            .html('<i class="fas fa-check-circle mr-2"></i>Konfirmasi Sekarang');
                        if ($('#phaseConfirm').is(':hidden')) showScanPhase();
                        Swal.fire('Gagal Menyimpan',
                            xhr.responseJSON?.message || 'Terjadi kesalahan server.', 'error');
                    });
                },
                complete: function() {
                    // Reset button — overlay & modal ditangani di success/error masing-masing
                    afterMinLoader(function() {
                        $('#btnDoConfirm').prop('disabled', false)
                            .html('<i class="fas fa-check-circle mr-2"></i>Konfirmasi Sekarang');
                    });
                }
            });
        }

        // ── Scan QR di dalam modal — Smart routing ───────────────────────────────────
        //
        //  Cocok GA + kapasitas cukup  →  AUTO-CONFIRM (langsung DB, no button)
        //  Cocok GA + kapasitas kurang →  Phase 2 + warning (operator atur qty)
        //  Beda dari GA                →  Phase 2 + badge "Beda dari GA"
        //  Override mode               →  Phase 2 selalu (override = keputusan sadar)
        //
        function doModalScanQr(code) {
            if (!code) return;
            $('#modalQrInput').val('').prop('disabled', true);
            $('#autoConfirmStatus').hide();
            $('#scanLoading').show();

            $.ajax({
                url: scanQrUrl, method: 'POST',
                data: { _token: csrfToken, qr_code: code },
                success: function(res) {
                    const c    = res.cell;
                    const cell = {
                        id: c.id, code: c.code,
                        zone_category: c.zone_category,
                        rack_code:     c.rack_code,
                        capacity_remaining: c.capacity_remaining,
                        capacity_max:       c.capacity_max,
                        source: 'scan',
                    };

                    const matchesGa = !isOverride && modalGaCell && (cell.id == modalGaCell.id);
                    const capOk     = cell.capacity_remaining >= modalQty && modalQty > 0;

                    if (matchesGa && capOk) {
                        // ════ AUTO-CONFIRM ════════════════════════════════════════════
                        // Scan cocok GA + kapasitas OK → simpan langsung, tidak perlu tap lagi
                        modalCell = cell;
                        $('#modalConfirm').data('cell-id', cell.id);

                        $('#scanLoading').hide();
                        $('#autoConfirmStatus').html(
                            '<div style="background:#f0fff4;border:1px solid #c3e6cb;' +
                            'border-radius:8px;padding:12px 16px;font-size:13px;text-align:center">' +
                            '<i class="fas fa-circle-notch fa-spin mr-2" style="color:#0d8564;font-size:18px"></i>' +
                            '<strong style="color:#0d8564">Cocok dengan GA ✓</strong>' +
                            '<span class="text-muted ml-2">Menyimpan ke <strong>' + cell.code + '</strong>…</span>' +
                            '</div>'
                        ).show();

                        doSaveConfirm(cell.id, modalQty, '', cell.code);

                    } else {
                        // ════ PHASE 2 MANUAL ══════════════════════════════════════════
                        // Beda GA / kapasitas kurang / override → operator konfirmasi manual
                        modalCell = cell;
                        if (isOverride) selectedCellMap[currentDetailId] = cell;
                        showConfirmPhase(cell);
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error', toast: true, position: 'top-end',
                        showConfirmButton: false, timer: 3000,
                        title: xhr.responseJSON?.message || 'QR tidak dikenali sistem.'
                    });
                    $('#modalQrInput').focus();
                },
                complete: function() {
                    $('#scanLoading').hide();
                    $('#modalQrInput').prop('disabled', false);
                }
            });
        }

        $('#btnModalScanQr').on('click', () => doModalScanQr($('#modalQrInput').val().trim()));
        $('#modalQrInput').on('keydown', function(e) {
            if (e.key === 'Enter') { doModalScanQr($(this).val().trim()); e.preventDefault(); }
        });

        // ── Skip scan — pakai GA langsung ────────────────────────────────────────────
        $('#btnSkipScan').on('click', function() {
            if (modalGaCell) {
                showConfirmPhase(modalGaCell);
            }
        });

        // ── Scan ulang (balik ke phase 1) ────────────────────────────────────────────
        $('#btnRescan').on('click', showScanPhase);

        // ── Auto-fokus saat modal terbuka ─────────────────────────────────────────────
        $('#modalConfirm').on('shown.bs.modal', function() {
            $('#modalQrInput').focus();
        });

        // ── Buka modal: selalu mulai dari Phase 1 ────────────────────────────────────
        function openConfirmModal(detailId, itemName, qty, gaCell, gaCellId, gaCapRemain, gaCapMax, overrideMode) {
            currentDetailId = detailId;
            isOverride      = !!overrideMode;
            modalQty        = qty;
            modalCell       = null;

            modalGaCell = gaCellId ? {
                id: gaCellId, code: gaCell,
                zone_category: '', rack_code: '',
                capacity_remaining: gaCapRemain, capacity_max: gaCapMax, source: 'ga'
            } : null;

            // Header
            const headerBg = isOverride ? '#856404' : '#28a745';
            $('#confirmModalHeader').css('background', headerBg);
            $('#confirmModalTitle').text(isOverride ? 'Override Lokasi' : 'Konfirmasi Put-Away');
            $('#confirmModalSubtitle').text(itemName);
            $('#confirmItemName').text(itemName);
            $('#confirmItemQty').text(qty);

            // Tombol skip: tampilkan kode GA di label
            if (modalGaCell && !isOverride) {
                $('#skipGaCellCode').text(gaCell).show();
                $('#btnSkipScan').show();
            } else {
                $('#btnSkipScan').hide(); // Override wajib scan
            }

            // Jika sudah ada cell tersimpan (dari "Ganti Cell") → langsung Phase 2
            if (selectedCellMap[detailId]) {
                $('#modalConfirm').modal('show');
                showConfirmPhase(selectedCellMap[detailId]);
            } else {
                showScanPhase();
                $('#modalConfirm').modal('show');
            }
        }

        $(document).on('click', '.btnConfirm', function() {
            const b = $(this);
            openConfirmModal(b.data('detail-id'), b.data('item-name'), parseInt(b.data('qty')),
                b.data('ga-cell'), b.data('ga-cell-id'),
                parseInt(b.data('cap-remaining')) || 0, parseInt(b.data('cap-max')) || 0, false);
        });

        $(document).on('click', '.btnOverride', function() {
            const b = $(this);
            openConfirmModal(b.data('detail-id'), b.data('item-name'), parseInt(b.data('qty')),
                b.data('ga-cell') || '', b.data('ga-cell-id') || '',
                parseInt(b.data('cap-remaining')) || 0, parseInt(b.data('cap-max')) || 0, true);
        });

        // ── Tombol "Konfirmasi Sekarang" (Phase 2 — manual confirm) ─────────────────
        $('#btnDoConfirm').on('click', function() {
            const cellId = $('#modalConfirm').data('cell-id');
            const qty    = qtyEditing
                ? (parseInt($('#confirmQty').val()) || 0)
                : (parseInt($('#qtyDisplay').text()) || 0);
            const notes  = $('#confirmNotes').val();

            if (!cellId) {
                Swal.fire('Cell Belum Dipilih', 'Scan QR cell terlebih dahulu.', 'warning');
                return;
            }
            if (!qty || qty < 1) {
                Swal.fire('Error', 'Jumlah harus minimal 1.', 'error');
                return;
            }

            doSaveConfirm(cellId, qty, notes, modalCell?.code || '');
        });

        // ══════════════════════════════════════════════════════════════════════
        //  CAMERA SCANNER — html5-qrcode (QR + Barcode 1D/2D)
        // ══════════════════════════════════════════════════════════════════════
        //  CAMERA SCANNER
        //  Root cause NotReadableError: getCameras() membuka stream sementara
        //  yang tidak direlease, lalu start() gagal karena kamera sudah terpakai.
        //  Fix: gunakan facingMode constraint langsung — tidak perlu getCameras().
        // ══════════════════════════════════════════════════════════════════════
        let qrScanner    = null;
        let cameraActive = false;
        let torchOn      = false;

        // Opsi kamera: pakai facingMode, tidak ada getCameras()
        const CAM_MODES = [
            { label: 'Kamera Belakang / Default', constraint: { facingMode: 'environment' } },
            { label: 'Kamera Depan',              constraint: { facingMode: 'user' } },
        ];
        let activeModeIdx = 0;  // index CAM_MODES yang sedang aktif

        // ── Beep ─────────────────────────────────────────────────────────────
        function playBeep() {
            try {
                const ctx  = new (window.AudioContext || window.webkitAudioContext)();
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 1800;
                osc.type = 'sine';
                gain.gain.setValueAtTime(0.4, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.13);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.13);
            } catch (e) {}
        }

        // ── Decode callback ───────────────────────────────────────────────────
        function onCameraSuccess(decodedText) {
            playBeep();
            const flash = document.getElementById('cameraScanSuccess');
            if (flash) {
                flash.classList.add('visible');
                setTimeout(() => flash.classList.remove('visible'), 650);
            }
            stopCamera().then(() => doModalScanQr(decodedText.trim()));
        }

        // ── Kumpulkan format barcode yang didukung ────────────────────────────
        function getSupportedFormats() {
            try {
                const F = Html5QrcodeSupportedFormats;
                return [F.QR_CODE, F.CODE_128, F.CODE_39, F.CODE_93,
                        F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E,
                        F.DATA_MATRIX, F.AZTEC, F.PDF_417, F.ITF, F.CODABAR]
                       .filter(f => f !== undefined);
            } catch (e) { return []; }
        }

        // ── Start kamera dengan facingMode constraint ─────────────────────────
        async function startCamera(modeIdx) {
            activeModeIdx = modeIdx;
            const mode = CAM_MODES[modeIdx];

            // Buat instance baru jika belum ada (atau setelah stop)
            if (!qrScanner) {
                qrScanner = new Html5Qrcode('qrCameraReader', { verbose: false });
            }

            setStatus('<i class="fas fa-circle-notch fa-spin mr-1"></i>Mengaktifkan kamera…', '#6c757d');

            // Update dropdown
            $('#cameraSelect').val(String(modeIdx));

            const formats = getSupportedFormats();
            const config  = {
                fps: 15,
                qrbox: function (w, h) {
                    const s = Math.round(Math.min(w, h) * 0.72);
                    return { width: s, height: s };
                },
                aspectRatio: 1.1,
                ...(formats.length ? { formatsToSupport: formats } : {}),
            };

            try {
                // Pakai facingMode — tidak perlu enumerasi, tidak ada stream conflict
                await qrScanner.start(
                    mode.constraint,
                    config,
                    onCameraSuccess,
                    () => {}    // error per-frame: normal, abaikan
                );
                cameraActive = true;

                // Cek torch
                try {
                    const caps = qrScanner.getRunningTrackCapabilities();
                    if (caps && caps.torch) $('#btnTorch').show();
                } catch (e) {}

                setStatus(
                    '<i class="fas fa-circle mr-1" style="font-size:8px;color:#0d8564"></i>' +
                    'Kamera aktif — arahkan ke QR / barcode', '#0d8564'
                );

            } catch (err) {
                cameraActive = false;

                // Jika kamera belakang tidak ada (laptop) → coba kamera depan
                if (modeIdx === 0) {
                    setStatus(
                        '<i class="fas fa-info-circle mr-1"></i>' +
                        'Kamera belakang tidak ada, mencoba kamera depan…', '#f6993f'
                    );
                    setTimeout(() => startCamera(1), 400);
                    return;
                }

                // Benar-benar gagal
                await stopCamera();
                Swal.fire({
                    icon: 'error', toast: true, position: 'top-end',
                    showConfirmButton: false, timer: 5000,
                    title: 'Gagal membuka kamera',
                    text: err.message || String(err),
                });
            }
        }

        // ── Stop semua stream & reset UI ─────────────────────────────────────
        async function stopCamera() {
            if (qrScanner) {
                if (cameraActive) {
                    try { await qrScanner.stop(); } catch (e) {}
                }
                // Destroy agar tidak ada stream sisa
                try { await qrScanner.clear(); } catch (e) {}
                qrScanner = null;
            }
            cameraActive = false;
            torchOn      = false;
            $('#btnTorch').removeClass('btn-warning').addClass('btn-outline-secondary').hide();
            $('#cameraSection').hide();
            $('#btnOpenCamera').show();
        }

        function setStatus(html, color) {
            $('#cameraStatus').html(html).css('color', color || '#6c757d').show();
        }

        // ── Buka kamera ──────────────────────────────────────────────────────
        $('#btnOpenCamera').on('click', function () {
            $(this).hide();
            // Isi dropdown pilihan kamera
            const $sel = $('#cameraSelect').empty();
            CAM_MODES.forEach((m, i) => $sel.append(`<option value="${i}">${m.label}</option>`));
            $('#cameraSection').show();
            startCamera(0);     // mulai dari kamera belakang/default
        });

        // ── Tutup kamera ─────────────────────────────────────────────────────
        $('#btnCloseCamera').on('click', stopCamera);

        // ── Ganti kamera via dropdown ─────────────────────────────────────────
        $('#cameraSelect').on('change', async function () {
            if (qrScanner && cameraActive) {
                try { await qrScanner.stop(); } catch (e) {}
                try { await qrScanner.clear(); } catch (e) {}
                qrScanner    = null;
                cameraActive = false;
            }
            startCamera(parseInt($(this).val()));
        });

        // ── Flash / Torch ─────────────────────────────────────────────────────
        $('#btnTorch').on('click', async function () {
            if (!qrScanner || !cameraActive) return;
            torchOn = !torchOn;
            try {
                await qrScanner.applyVideoConstraints({ advanced: [{ torch: torchOn }] });
                $(this).toggleClass('btn-outline-secondary btn-warning');
            } catch (e) {
                torchOn = !torchOn;
                Swal.fire({
                    icon: 'info', toast: true, position: 'top-end',
                    showConfirmButton: false, timer: 2000,
                    title: 'Flash tidak didukung perangkat ini',
                });
            }
        });

        // ── Stop kamera & reset overlay saat modal ditutup ──────────────────────
        $('#modalConfirm').on('hide.bs.modal', function () {
            stopCamera();
            $('#modalSavingOverlay').hide();
        });

    </script>
@endpush
