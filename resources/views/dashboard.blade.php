@extends('layouts.adminlte')

@section('title', 'AVIAN WMS – Dashboard')

@section('page_title', '')

@push('styles')
    <style>
        /* ─── KPI Cards ─────────────────────────────────────── */
        .kpi-card {
            border-radius: 10px;
            padding: 20px 22px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .15);
            margin-bottom: 20px;
            transition: transform .2s;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
        }

        .kpi-card .kpi-icon {
            position: absolute;
            right: 18px;
            top: 18px;
            font-size: 42px;
            opacity: .25;
        }

        .kpi-card .kpi-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .kpi-card .kpi-label {
            font-size: 13px;
            font-weight: 400;
            opacity: .9;
        }

        .kpi-card .kpi-trend {
            font-size: 12px;
            margin-top: 8px;
            opacity: .85;
        }

        .kpi-card .kpi-trend .up {
            color: #a7f3d0;
        }

        .kpi-card .kpi-trend .down {
            color: #fca5a5;
        }

        .kpi-green {
            background: linear-gradient(135deg, #0d8564 0%, #004230 100%);
        }

        .kpi-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
        }

        .kpi-amber {
            background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
        }

        .kpi-red {
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
        }

        .kpi-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #5b21b6 100%);
        }

        .kpi-teal {
            background: linear-gradient(135deg, #14b8a6 0%, #0f766e 100%);
        }

        .kpi-orange {
            background: linear-gradient(135deg, #f97316 0%, #c2410c 100%);
        }

        .kpi-slate {
            background: linear-gradient(135deg, #64748b 0%, #334155 100%);
        }

        /* ─── Section Title ───────────────────────────────── */
        .section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #6b7280;
            margin: 22px 0 12px;
            border-left: 3px solid #0d8564;
            padding-left: 10px;
        }

        /* ─── Panel Card ──────────────────────────────────── */
        .panel-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .dashboard-section {
            margin-bottom: 28px;
        }

        .panel-card .panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-card .panel-header h6 {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .panel-card .panel-body {
            padding: 16px 18px;
        }

        /* ─── Storage Zone Progress ───────────────────────── */
        .zone-row {
            margin-bottom: 12px;
        }

        .zone-row .zone-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #374151;
            margin-bottom: 4px;
        }

        .zone-bar {
            height: 10px;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .zone-fill {
            height: 10px;
            border-radius: 999px;
        }

        /* ─── Alert Badge ─────────────────────────────────── */
        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 5px;
        }

        .alert-dot.critical {
            background: #ef4444;
        }

        .alert-dot.warning {
            background: #f59e0b;
        }

        .alert-dot.info {
            background: #3b82f6;
        }

        .alert-item-text {
            font-size: 13px;
            color: #374151;
        }

        .alert-item-time {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* ─── Activity Timeline ───────────────────────────── */
        .timeline-item {
            display: flex;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-content .t-action {
            font-size: 13px;
            color: #1f2937;
            font-weight: 500;
        }

        .timeline-content .t-meta {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ─── Table tweaks ────────────────────────────────── */
        .wms-table thead th {
            background: #f9fafb;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
            padding: 10px 12px;
        }

        .wms-table tbody td {
            font-size: 13px;
            color: #374151;
            padding: 10px 12px;
            vertical-align: middle;
        }

        .wms-table tbody tr:hover {
            background: #f9fafb;
        }

        /* ─── Status Badges ───────────────────────────────── */
        .badge-status {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: .3px;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-transit {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-critical {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-processing {
            background: #ede9fe;
            color: #5b21b6;
        }

        /* ─── Quick Access Buttons ────────────────────────── */
        .quick-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 18px 10px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            background: #fff;
            color: #374151;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all .2s;
            cursor: pointer;
        }

        .quick-btn i {
            font-size: 22px;
        }

        .quick-btn:hover {
            border-color: #0d8564;
            color: #0d8564;
            background: #f0fdf4;
            text-decoration: none;
        }

        /* ─── Gauge / Donut label ─────────────────────────── */
        .donut-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .donut-wrap {
            position: relative;
        }

        .donut-center .pct {
            font-size: 26px;
            font-weight: 700;
            color: #1f2937;
        }

        .donut-center .sub {
            font-size: 11px;
            color: #6b7280;
        }

        /* ─── Live clock ──────────────────────────────────── */
        #live-clock {
            font-size: 14px;
            color: rgba(255, 255, 255, .85);
            font-weight: 400;
        }

        .mini-stat {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            background: #fff;
            height: 100%;
        }

        .mini-stat .mini-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 5px;
        }

        .mini-stat .mini-value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1;
        }

        .mini-stat .mini-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 6px;
        }

        .funnel-step {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }

        .funnel-name {
            width: 105px;
            font-size: 12px;
            color: #374151;
            flex-shrink: 0;
        }

        .funnel-track {
            flex: 1;
            height: 22px;
            border-radius: 6px;
            background: #f3f4f6;
            overflow: hidden;
        }

        .funnel-fill {
            height: 22px;
            border-radius: 6px;
            min-width: 2px;
        }

        .funnel-count {
            width: 36px;
            text-align: right;
            font-size: 12px;
            font-weight: 700;
            color: #1f2937;
            flex-shrink: 0;
        }

        .process-row {
            align-items: stretch;
        }

        .process-card {
            height: 100%;
        }

        .process-card .panel-body {
            min-height: 292px;
        }

        .bottleneck-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }

        .bottleneck-chip {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fff;
            position: relative;
            transition: border-color .15s, box-shadow .15s;
        }

        .bottleneck-chip.has-link {
            cursor: pointer;
        }

        .bottleneck-chip.has-link:hover {
            border-color: #9ca3af;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .bottleneck-chip .chip-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .35px;
            margin-bottom: 4px;
        }

        .bottleneck-chip .chip-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1;
        }

        .bottleneck-chip .chip-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 6px;
        }

        .capacity-row {
            align-items: stretch;
        }

        .capacity-card {
            height: 100%;
        }

        .capacity-card .panel-body {
            min-height: 360px;
        }

        .capacity-card .donut-wrap {
            max-width: 210px;
            height: 170px;
            margin-left: auto;
            margin-right: auto;
        }

        .chart-box {
            position: relative;
            width: 100%;
        }

        .chart-box.flow {
            height: 195px;
        }

        .chart-box.order-status {
            height: 155px;
        }

        .chart-box.trend-main {
            height: 285px;
        }

        .chart-box.visual {
            height: 260px;
        }

        .chart-box .hc-chart {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        .capacity-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }

        .capacity-stat {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            background: #fff;
        }

        .capacity-stat strong {
            display: block;
            font-size: 16px;
            line-height: 1;
            color: #1f2937;
        }

        .capacity-stat span {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            color: #6b7280;
        }

        /* ─── Responsive tweaks ───────────────────────────── */
        @media(max-width:768px) {
            .kpi-card .kpi-value {
                font-size: 24px;
            }
        }
    </style>
@endpush

@section('content')

    {{-- ══════════════════════════════════════════════════════
     HEADER BANNER
══════════════════════════════════════════════════════ --}}
    <div class="welcome-card d-flex align-items-center justify-content-between flex-wrap" style="padding:28px 30px;">
        <div>
            <h5 style="margin-bottom:4px;font-size:14px;opacity:.9;">
                <i class="fas fa-warehouse mr-1"></i> Warehouse Management System — PT XYZ
            </h5>
            <h3 style="margin-bottom:6px;font-size:26px;">Dashboard Monitoring Gudang</h3>
            <div style="font-size:13px;opacity:.85;">
                <i class="fas fa-map-marker-alt mr-1"></i> Gudang Sparepart · Surabaya, Jawa Timur
                &nbsp;|&nbsp;
                <i class="fas fa-calendar-alt mr-1"></i> <span id="live-date"></span>
                &nbsp;|&nbsp;
                <i class="fas fa-clock mr-1"></i> <span id="live-clock"></span>
            </div>
        </div>
        <div class="text-right mt-2">
            <span style="font-size:13px;opacity:.85;"><i class="fas fa-circle text-success mr-1"></i> Sistem
                Online</span><br>
            <span style="font-size:12px;opacity:.7;">Last sync: <span id="last-sync"></span></span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ACTION REQUIRED — Hanya tampil jika ada yang pending
══════════════════════════════════════════════════════ --}}
    @php
        $totalPending = $inboundHariIni + $pendingQtyConfirm + $pendingGaRun + $pendingGaAccept + $pendingPutAway;
    @endphp
    @if ($totalPending > 0)
        <div class="alert mb-3 py-2 px-3 d-flex align-items-center justify-content-between flex-wrap"
            style="background:#fffbeb;border:1.5px solid #f59e0b;border-radius:10px;gap:8px">
            <div>
                <i class="fas fa-bell text-warning mr-2"></i>
                <strong>{{ $totalPending }} hal butuh tindakan sekarang</strong>
                <span class="text-muted ml-1" style="font-size:12px">— Klik kartu di bawah untuk langsung aksi</span>
            </div>
        </div>
        <div class="row mb-3">
            @if ($inboundHariIni > 0)
                <div class="col-6 col-md-3 mb-2">
                    <a href="{{ route('inbound.orders.index', ['status' => 'draft']) }}"
                        class="d-block text-decoration-none">
                        <div class="card border-0 shadow-sm h-100"
                            style="border-left:4px solid #e53e3e!important;border-radius:10px;cursor:pointer;transition:transform .15s"
                            onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div
                                        style="width:36px;height:36px;border-radius:50%;background:#fff5f5;
                                        display:flex;align-items:center;justify-content:center;margin-right:10px">
                                        <i class="fas fa-truck-loading" style="color:#e53e3e;font-size:15px"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size:22px;color:#c53030;line-height:1">
                                            {{ $inboundHariIni }}
                                        </div>
                                        <small class="text-muted" style="font-size:10px;line-height:1">DO</small>
                                    </div>
                                </div>
                                <div style="font-size:12px;font-weight:600;color:#c53030">Inbound Datang Hari Ini</div>
                                <div style="font-size:11px;color:#6c757d;margin-top:2px">Surat jalan tiba, belum diproses</div>
                                <div class="mt-2">
                                    <span class="badge" style="font-size:10px;background:#e53e3e;color:#fff">
                                        <i class="fas fa-arrow-right mr-1"></i>Lihat DO
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif

            @if ($pendingQtyConfirm > 0)
                <div class="col-6 col-md-3 mb-2">
                    <a href="{{ route('inbound.orders.index', ['status' => 'draft']) }}"
                        class="d-block text-decoration-none">
                        <div class="card border-0 shadow-sm h-100"
                            style="border-left:4px solid #6c757d!important;border-radius:10px;cursor:pointer;transition:transform .15s"
                            onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div
                                        style="width:36px;height:36px;border-radius:50%;background:#f1f3f5;
                                        display:flex;align-items:center;justify-content:center;margin-right:10px">
                                        <i class="fas fa-clipboard-list" style="color:#6c757d;font-size:15px"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size:22px;color:#343a40;line-height:1">
                                            {{ $pendingQtyConfirm }}
                                        </div>
                                        <small class="text-muted" style="font-size:10px;line-height:1">DO</small>
                                    </div>
                                </div>
                                <div style="font-size:12px;font-weight:600;color:#343a40">Konfirmasi Qty Fisik</div>
                                <div style="font-size:11px;color:#6c757d;margin-top:2px">Operator belum input qty diterima
                                </div>
                                <div class="mt-2">
                                    <span class="badge badge-secondary" style="font-size:10px">
                                        <i class="fas fa-arrow-right mr-1"></i>Konfirmasi Sekarang
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif

            @if ($pendingGaRun > 0)
                <div class="col-6 col-md-3 mb-2">
                    <a href="{{ route('inbound.orders.index', ['status' => 'draft']) }}"
                        class="d-block text-decoration-none">
                        <div class="card border-0 shadow-sm h-100"
                            style="border-left:4px solid #3b82f6!important;border-radius:10px;cursor:pointer;transition:transform .15s"
                            onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div
                                        style="width:36px;height:36px;border-radius:50%;background:#eff6ff;
                                        display:flex;align-items:center;justify-content:center;margin-right:10px">
                                        <i class="fas fa-dna" style="color:#3b82f6;font-size:15px"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size:22px;color:#1e40af;line-height:1">
                                            {{ $pendingGaRun }}
                                        </div>
                                        <small class="text-muted" style="font-size:10px;line-height:1">DO</small>
                                    </div>
                                </div>
                                <div style="font-size:12px;font-weight:600;color:#1e40af">Jalankan Genetic Algorithm</div>
                                <div style="font-size:11px;color:#6c757d;margin-top:2px">Qty sudah dikonfirmasi, siap GA
                                </div>
                                <div class="mt-2">
                                    <span class="badge" style="font-size:10px;background:#3b82f6;color:#fff">
                                        <i class="fas fa-arrow-right mr-1"></i>Proses GA
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif

            @if ($pendingGaAccept > 0)
                <div class="col-6 col-md-3 mb-2">
                    <a href="{{ route('inbound.orders.index', ['status' => 'recommended']) }}"
                        class="d-block text-decoration-none">
                        <div class="card border-0 shadow-sm h-100"
                            style="border-left:4px solid #f59e0b!important;border-radius:10px;cursor:pointer;transition:transform .15s"
                            onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div
                                        style="width:36px;height:36px;border-radius:50%;background:#fffbeb;
                                        display:flex;align-items:center;justify-content:center;margin-right:10px">
                                        <i class="fas fa-check-circle" style="color:#f59e0b;font-size:15px"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size:22px;color:#b45309;line-height:1">
                                            {{ $pendingGaAccept }}
                                        </div>
                                        <small class="text-muted" style="font-size:10px;line-height:1">DO</small>
                                    </div>
                                </div>
                                <div style="font-size:12px;font-weight:600;color:#b45309">Review Rekomendasi GA</div>
                                <div style="font-size:11px;color:#6c757d;margin-top:2px">Hasil GA perlu review Supervisor (<code style="font-size:10px;">pending_review</code>)
                                </div>
                                <div class="mt-2">
                                    <span class="badge badge-warning" style="font-size:10px">
                                        <i class="fas fa-arrow-right mr-1"></i>Review Sekarang
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif

            @if ($pendingPutAway > 0)
                <div class="col-6 col-md-3 mb-2">
                    <a href="{{ route('putaway.index') }}" class="d-block text-decoration-none">
                        <div class="card border-0 shadow-sm h-100"
                            style="border-left:4px solid #38c172!important;border-radius:10px;cursor:pointer;transition:transform .15s"
                            onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div
                                        style="width:36px;height:36px;border-radius:50%;background:#f0fff4;
                                        display:flex;align-items:center;justify-content:center;margin-right:10px">
                                        <i class="fas fa-dolly-flatbed" style="color:#38c172;font-size:15px"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size:22px;color:#065f46;line-height:1">
                                            {{ $pendingPutAway }}
                                        </div>
                                        <small class="text-muted" style="font-size:10px;line-height:1">DO</small>
                                    </div>
                                </div>
                                <div style="font-size:12px;font-weight:600;color:#065f46">Lakukan Put-Away</div>
                                <div style="font-size:11px;color:#6c757d;margin-top:2px">Operator bisa mulai letakkan
                                    barang</div>
                                <div class="mt-2">
                                    <span class="badge badge-success" style="font-size:10px">
                                        <i class="fas fa-arrow-right mr-1"></i>Mulai Put-Away
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
     ROW 1 — 8 KPI UTAMA
══════════════════════════════════════════════════════ --}}
    <p class="section-title"><i class="fas fa-tachometer-alt mr-1"></i> Ringkasan Utama</p>
    <div class="row">
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-green">
                <i class="fas fa-boxes kpi-icon"></i>
                <div class="kpi-value">{{ number_format($totalItems) }}</div>
                <div class="kpi-label">Total SKU / Item</div>
                <div class="kpi-trend"><span class="up">▲ Aktif</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-blue">
                <i class="fas fa-layer-group kpi-icon"></i>
                <div class="kpi-value">{{ number_format($usedCells) }}</div>
                <div class="kpi-label">Cell Terisi / {{ number_format($totalCells) }} Total</div>
                <div class="kpi-trend"><span class="up">{{ $utilizationPct }}%</span> utilisasi</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-teal">
                <i class="fas fa-sign-in-alt kpi-icon"></i>
                <div class="kpi-value">{{ number_format($inboundToday) }}</div>
                <div class="kpi-label">Inbound Hari Ini</div>
                <div class="kpi-trend"><span class="up">Surat jalan diterima</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-orange">
                <i class="fas fa-shipping-fast kpi-icon"></i>
                <div class="kpi-value">{{ number_format($outboundToday) }}</div>
                <div class="kpi-label">Outbound Hari Ini</div>
                <div class="kpi-trend"><span class="up">Pergerakan barang keluar</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-amber">
                <i class="fas fa-exclamation-triangle kpi-icon"></i>
                <div class="kpi-value">{{ number_format($lowStockItems) }}</div>
                <div class="kpi-label">Item Stok Menipis</div>
                <div class="kpi-trend"><span class="down">▼ Perlu reorder segera</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-red">
                <i class="fas fa-calendar-times kpi-icon"></i>
                <div class="kpi-value">{{ number_format($nearExpiryItems) }}</div>
                <div class="kpi-label">Item Mendekati Kadaluarsa</div>
                <div class="kpi-trend"><span class="down">▼ ≤ 30 hari</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-purple">
                <i class="fas fa-clipboard-list kpi-icon"></i>
                <div class="kpi-value">{{ number_format($activeOrders) }}</div>
                <div class="kpi-label">Order Inbound Aktif</div>
                <div class="kpi-trend"><span class="up">Sedang diproses</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-slate">
                <i class="fas fa-cubes kpi-icon"></i>
                <div class="kpi-value">{{ number_format($totalStockQty) }}</div>
                <div class="kpi-label">Total Stok Tersedia (unit)</div>
                <div class="kpi-trend"><span class="up">Semua SKU aktif · status available</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card" style="background:linear-gradient(135deg,#374151,#1f2937);">
                <i class="fas fa-hourglass-half kpi-icon"></i>
                <div class="kpi-value">{{ number_format($deadstockCount) }}</div>
                <div class="kpi-label">Item Deadstock</div>
                <div class="kpi-trend"><span class="down">▼ Tidak bergerak ≥ 90 hari</span></div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
    ROW 2 — KAPASITAS GUDANG + GRAFIK INBOUND/OUTBOUND
══════════════════════════════════════════════════════ --}}
    <p class="section-title"><i class="fas fa-chart-line mr-1"></i> Trend Inbound vs Outbound Harian</p>
    <div class="row dashboard-section">
        <div class="col-md-12">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-exchange-alt mr-1 text-primary"></i> Arus Barang Harian - 7 Hari Terakhir</h6>
                    <span style="font-size:12px;color:#6b7280;">hover titik chart untuk detail qty</span>
                </div>
                <div class="panel-body">
                    @php
                        $trendInboundTotal = array_sum($chartInbound);
                        $trendOutboundTotal = array_sum($chartOutbound);
                        $trendNetFlow = $trendInboundTotal - $trendOutboundTotal;
                    @endphp
                    <div class="capacity-stats">
                        <div class="capacity-stat">
                            <strong style="color:#0d8564">{{ number_format($trendInboundTotal) }}</strong>
                            <span>Total Inbound</span>
                        </div>
                        <div class="capacity-stat">
                            <strong style="color:#3b82f6">{{ number_format($trendOutboundTotal) }}</strong>
                            <span>Total Outbound</span>
                        </div>
                        <div class="capacity-stat">
                            <strong style="color:{{ $trendNetFlow >= 0 ? '#0d8564' : '#ef4444' }}">{{ number_format($trendNetFlow) }}</strong>
                            <span>Net Flow</span>
                        </div>
                    </div>
                    <div class="chart-box trend-main">
                        <div id="chartDailyInOutTrend" class="hc-chart"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Process Queue --}}
    <p class="section-title"><i class="fas fa-route mr-1"></i> Antrian Proses Inbound</p>
    <div class="row process-row">
        <div class="col-md-5">
            <div class="panel-card process-card">
                <div class="panel-header">
                    <h6><i class="fas fa-filter mr-1 text-primary"></i> Status DO Berjalan</h6>
                    <span style="font-size:12px;color:#6b7280;">Jumlah DO per tahap</span>
                </div>
                <div class="panel-body">
                    @php $maxFunnel = max(1, collect($processFunnel)->max('count')); @endphp
                    @foreach ($processFunnel as $step)
                        @php $width = max(4, round($step['count'] / $maxFunnel * 100)); @endphp
                        <div class="funnel-step">
                            <div class="funnel-name">{{ $step['label'] }}</div>
                            <div class="funnel-track">
                                <div class="funnel-fill" style="width:{{ $width }}%;background:{{ $step['color'] }};"></div>
                            </div>
                            <div class="funnel-count">{{ number_format($step['count']) }}</div>
                        </div>
                    @endforeach
                    <div class="mt-2 pt-2" style="border-top:1px solid #f0f0f0;font-size:11px;color:#6b7280;">
                        Dipakai untuk melihat tahap proses yang sedang menumpuk.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel-card process-card">
                <div class="panel-header">
                    <h6><i class="fas fa-hourglass-half mr-1 text-warning"></i> Bottleneck Proses</h6>
                    <span style="font-size:12px;color:#6b7280;">Tertahan & usia terlama</span>
                </div>
                <div class="panel-body">
                    <div id="chartBottleneck" class="hc-chart" style="height:145px;"></div>
                    <div class="bottleneck-list">
                        @foreach ($bottleneckSummary as $b)
                            <div class="bottleneck-chip {{ $b['count'] > 0 ? 'has-link' : '' }}">
                                <div class="chip-label">{{ $b['label'] }}</div>
                                <div class="chip-value" style="color:{{ $b['color'] }}">{{ number_format($b['count']) }}</div>
                                <div class="chip-sub">
                                    Terlama {{ $b['oldest_days'] }} hari
                                    @if ($b['count'] > 0)
                                    &nbsp;·&nbsp;<span style="color:{{ $b['color'] }};font-weight:600;">{{ $b['url_label'] }} →</span>
                                    @endif
                                </div>
                                @if ($b['count'] > 0)
                                <a href="{{ $b['url'] }}" aria-label="{{ $b['url_label'] }}"
                                   style="position:absolute;top:0;left:0;right:0;bottom:0;border-radius:8px;z-index:1;"></a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel-card process-card">
                <div class="panel-header">
                    <h6><i class="fas fa-stopwatch mr-1 text-danger"></i> DO Terlama</h6>
                </div>
                <div class="panel-body p-0">
                    <table class="table mb-0 wms-table">
                        <thead>
                            <tr>
                                <th>DO</th>
                                <th>Status</th>
                                <th class="text-right">Hari</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($oldestOpenOrders as $order)
                                @php
                                    $orderUrl = in_array($order['status'], ['recommended', 'put_away'])
                                        ? route('putaway.show', $order['id'])
                                        : route('inbound.orders.show', $order['id']);
                                @endphp
                                <tr style="cursor:pointer;" onclick="location.href='{{ $orderUrl }}'">
                                    <td>
                                        <strong class="text-primary">{{ $order['do_number'] }}</strong><br>
                                        <small class="text-muted">{{ $order['supplier'] }}</small>
                                    </td>
                                    <td><span class="badge badge-status badge-pending">{{ ucfirst(str_replace('_', ' ', $order['status'])) }}</span></td>
                                    <td class="text-right"><strong>{{ $order['age_days'] }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Tidak ada DO terbuka.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p class="section-title"><i class="fas fa-chart-bar mr-1"></i> Kapasitas & Arus Barang</p>
    <div class="row capacity-row dashboard-section">

        {{-- Utilisasi per Zona Penyimpanan --}}
        <div class="col-md-4">
            <div class="panel-card capacity-card">
                <div class="panel-header">
                    <h6><i class="fas fa-th-large mr-1 text-success"></i> Utilisasi Kapasitas Gudang</h6>
                    <span style="font-size:12px;color:#6b7280;">Total & per zona</span>
                </div>
                <div class="panel-body">
                    @php
                        $capacityUsedTotal = $zones->sum('used');
                        $capacityMaxTotal = max(1, $zones->sum('max'));
                        $capacityFreeTotal = max(0, $capacityMaxTotal - $capacityUsedTotal);
                    @endphp
                    <div class="capacity-stats">
                        <div class="capacity-stat">
                            <strong>{{ number_format($capacityUsedTotal) }}</strong>
                            <span>Terpakai</span>
                        </div>
                        <div class="capacity-stat">
                            <strong>{{ number_format($capacityFreeTotal) }}</strong>
                            <span>Kosong</span>
                        </div>
                        <div class="capacity-stat">
                            <strong>{{ number_format($capacityMaxTotal) }}</strong>
                            <span>Total</span>
                        </div>
                    </div>
                    <div class="donut-wrap chart-box mb-3">
                        <div id="chartZoneDonut" class="hc-chart"></div>
                        <div class="donut-center">
                            <div class="pct">{{ $utilizationPct }}%</div>
                            <div class="sub">Terisi</div>
                        </div>
                    </div>
                    <!-- Progress bars per zona -->
                    @php $zoneColors = ['#0d8564','#3b82f6','#f59e0b','#8b5cf6','#ef4444']; @endphp
                    @foreach ($zones as $i => $zone)
                        <div class="zone-row">
                            <div class="zone-label">
                                <span>{{ $zone['name'] }}</span>
                                <span>{{ $zone['percent'] }}%</span>
                            </div>
                            <div class="zone-bar">
                                <div class="zone-fill"
                                    style="width:{{ $zone['percent'] }}%;background:{{ $zoneColors[$i % count($zoneColors)] }};">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Grafik Inbound vs Outbound 7 hari --}}
        <div class="col-md-5">
            <div class="panel-card capacity-card">
                <div class="panel-header">
                    <h6><i class="fas fa-exchange-alt mr-1 text-primary"></i> Arus Barang – 7 Hari Terakhir</h6>
                    <span style="font-size:12px;color:#6b7280;">Unit barang masuk / keluar</span>
                </div>
                <div class="panel-body">
                    @php
                        $inbound7Total = array_sum($chartInbound);
                        $outbound7Total = array_sum($chartOutbound);
                        $netFlow7 = $inbound7Total - $outbound7Total;
                    @endphp
                    <div class="capacity-stats">
                        <div class="capacity-stat">
                            <strong style="color:#0d8564">{{ number_format($inbound7Total) }}</strong>
                            <span>Inbound</span>
                        </div>
                        <div class="capacity-stat">
                            <strong style="color:#3b82f6">{{ number_format($outbound7Total) }}</strong>
                            <span>Outbound</span>
                        </div>
                        <div class="capacity-stat">
                            <strong style="color:{{ $netFlow7 >= 0 ? '#0d8564' : '#ef4444' }}">{{ number_format($netFlow7) }}</strong>
                            <span>Net Flow</span>
                        </div>
                    </div>
                    <div class="chart-box flow">
                        <div id="chartInOut" class="hc-chart"></div>
                    </div>
                    <div class="mt-2 pt-2" style="border-top:1px solid #f0f0f0;font-size:11px;color:#6b7280;">
                        Membandingkan qty inbound dan outbound harian untuk membaca arus stok gudang.
                    </div>
                </div>
            </div>
        </div>

        {{-- Order Status Breakdown (real) --}}
        <div class="col-md-3">
            @php
                $statusDef = [
                    'draft' => ['label' => 'Draft / Terjadwal', 'color' => '#6b7280'],
                    'processing' => ['label' => 'Konfirmasi Qty', 'color' => '#3b82f6'],
                    'recommended' => ['label' => 'Menunggu GA Accept', 'color' => '#f59e0b'],
                    'put_away' => ['label' => 'Put-Away Berlangsung', 'color' => '#14b8a6'],
                    'completed' => ['label' => 'Selesai', 'color' => '#0d8564'],
                    'cancelled' => ['label' => 'Dibatalkan', 'color' => '#ef4444'],
                ];
                $totalOrders = $orderStatusCounts->sum();
            @endphp
            <div class="panel-card capacity-card">
                <div class="panel-header">
                    <h6><i class="fas fa-tasks mr-1" style="color:#8b5cf6;"></i> Status Order Inbound</h6>
                    <span style="font-size:12px;color:#6b7280;">{{ $totalOrders }} total</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box order-status">
                        <div id="chartOrderStatus" class="hc-chart"></div>
                    </div>
                    <div class="mt-2" style="font-size:12px;">
                        @foreach ($statusDef as $key => $def)
                            @php $cnt = $orderStatusCounts[$key] ?? 0; @endphp
                            @if ($cnt > 0)
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><span style="color:{{ $def['color'] }};">●</span>
                                        {{ $def['label'] }}</span>
                                    <strong>{{ $cnt }}</strong>
                                </div>
                            @endif
                        @endforeach
                        @if ($totalOrders === 0)
                            <div class="text-center text-muted py-2" style="font-size:12px">Belum ada order</div>
                        @endif
                    </div>
                    @if ($completionRate > 0)
                        <div class="mt-2 pt-2" style="border-top:1px solid #f0f0f0;font-size:11px;color:#6b7280">
                            <i class="fas fa-chart-line mr-1"></i>
                            Completion rate bulan ini:
                            <strong style="color:#0d8564">{{ $completionRate }}%</strong>
                            ({{ $completedThisMonth }}/{{ $totalThisMonth }})
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ROW 3 — STOK PER KATEGORI + TOP 5 SKU PALING AKTIF
══════════════════════════════════════════════════════ --}}
    <div class="row dashboard-section">
        {{-- Stok tersedia per kategori (real) --}}
        <div class="col-md-8">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-layer-group mr-1" style="color:#14b8a6;"></i> Stok Tersedia per Kategori Item
                    </h6>
                    <span style="font-size:12px;color:#6b7280;">Total unit status <em>available</em> di gudang</span>
                </div>
                <div class="panel-body">
                    @if ($stockByCategory->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            Belum ada data stok tersedia.
                        </div>
                    @else
                        @php $maxQty = $stockByCategory->max('total_qty') ?: 1; @endphp
                        <div class="row">
                            <div class="col-md-5 d-flex align-items-center justify-content-center">
                                <div style="position:relative;width:200px;height:200px">
                                    <div id="chartCategoryStock" class="hc-chart" style="width:200px;height:200px;"></div>
                                    <div
                                        style="position:absolute;inset:0;display:flex;flex-direction:column;
                                                align-items:center;justify-content:center;">
                                        <div style="font-size:22px;font-weight:700;color:#1f2937">
                                            {{ number_format($stockByCategory->sum('total_qty')) }}
                                        </div>
                                        <div style="font-size:10px;color:#6b7280">unit total</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                @foreach ($stockByCategory as $cat)
                                    @php
                                        $color = $cat->color_code ?: '#6b7280';
                                        $pct = $maxQty > 0 ? round(($cat->total_qty / $maxQty) * 100) : 0;
                                    @endphp
                                    <div class="zone-row">
                                        <div class="zone-label">
                                            <span style="font-size:12px">
                                                <span style="color:{{ $color }}">●</span>
                                                {{ $cat->name }}
                                                <span class="text-muted">({{ $cat->item_count }} SKU)</span>
                                            </span>
                                            <span style="font-size:12px;font-weight:600;color:#374151">
                                                {{ number_format($cat->total_qty) }}
                                            </span>
                                        </div>
                                        <div class="zone-bar">
                                            <div class="zone-fill"
                                                style="width:{{ $pct }}%;background:{{ $color }};"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Top 5 SKU Paling Aktif (real) --}}
        <div class="col-md-4">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-fire mr-1" style="color:#f97316;"></i> Top 5 SKU Paling Aktif</h6>
                    <span style="font-size:11px;color:#6b7280">by total movement</span>
                </div>
                <div class="panel-body" style="padding-top:8px;">
                    @if ($topMovedItems->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            Belum ada pergerakan stok.
                        </div>
                    @else
                        @php $maxMoved = $topMovedItems->max('total_qty') ?: 1; @endphp
                        @foreach ($topMovedItems as $idx => $mv)
                            @php $pct = round($mv->total_qty / $maxMoved * 100); @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div style="flex:1;min-width:0">
                                        <span class="badge badge-secondary mr-1"
                                            style="font-size:10px">{{ $idx + 1 }}</span>
                                        <strong
                                            style="font-size:12px">{{ \Illuminate\Support\Str::limit($mv->item?->name ?? '–', 22) }}</strong>
                                        <br>
                                        <small class="text-muted" style="font-size:10px">
                                            <code>{{ $mv->item?->sku ?? '–' }}</code>
                                            &middot; {{ $mv->movement_count }}× gerakan
                                        </small>
                                    </div>
                                    <div class="text-right ml-2">
                                        <strong
                                            style="font-size:14px;color:#f97316">{{ number_format($mv->total_qty) }}</strong>
                                        <div style="font-size:10px;color:#6b7280">{{ $mv->item?->unit?->name ?? 'unit' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="zone-bar">
                                    <div class="zone-fill"
                                        style="width:{{ $pct }}%;background:#f97316;opacity:.7"></div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ROW 4 — ALERT STOK + PENERIMAAN TERJADWAL + QUICK ACCESS
══════════════════════════════════════════════════════ --}}
    <p class="section-title"><i class="fas fa-bell mr-1"></i> Peringatan & Jadwal</p>
    <div class="row">

        {{-- Alert & Notifikasi (dinamis) --}}
        <div class="col-md-4">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-bell mr-1 text-danger"></i> Alert &amp; Notifikasi</h6>
                    @php
                        $totalAlerts =
                            $lowStockAlerts->count() +
                            $fullZones->count() +
                            ($nearExpiryItems > 0 ? 1 : 0) +
                            ($deadstockCount > 0 ? 1 : 0);
                    @endphp
                    <span class="badge badge-danger" style="font-size:11px;">
                        {{ $totalAlerts }} Aktif
                    </span>
                </div>
                <div class="panel-body" style="padding-top:8px;">

                    {{-- Zona hampir penuh (≥ 85%) --}}
                    @foreach ($fullZones as $fz)
                        <div class="alert-item">
                            <div class="alert-dot critical"></div>
                            <div>
                                <div class="alert-item-text">
                                    <strong>Kapasitas Kritis:</strong> {{ $fz['name'] }} ({{ $fz['percent'] }}%)
                                </div>
                                <div class="alert-item-time">
                                    {{ $fz['used'] }}/{{ $fz['max'] }} unit · Perlu realokasi
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Stok menipis --}}
                    @foreach ($lowStockAlerts as $ls)
                        <div class="alert-item">
                            <div class="alert-dot warning"></div>
                            <div>
                                <div class="alert-item-text">
                                    <strong>Stok Menipis:</strong> {{ $ls['name'] }}
                                </div>
                                <div class="alert-item-time">
                                    Tersisa {{ $ls['current'] }} unit · Min: {{ $ls['min'] }} unit
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Hampir kadaluarsa --}}
                    @if ($nearExpiryItems > 0)
                        <div class="alert-item">
                            <div class="alert-dot warning"></div>
                            <div>
                                <div class="alert-item-text">
                                    <strong>Mendekati Kadaluarsa:</strong>
                                    {{ number_format($nearExpiryItems) }} record stok (≤ 30 hari)
                                </div>
                                <div class="alert-item-time">Lihat tabel di bawah untuk detail</div>
                            </div>
                        </div>
                    @endif

                    {{-- Deadstock --}}
                    @if ($deadstockCount > 0)
                        <div class="alert-item">
                            <div class="alert-dot info"></div>
                            <div>
                                <div class="alert-item-text">
                                    <strong>Deadstock:</strong>
                                    {{ number_format($deadstockCount) }} SKU tidak bergerak ≥ 90 hari
                                </div>
                                <div class="alert-item-time">Cek tabel Deadstock di bawah</div>
                            </div>
                        </div>
                    @endif

                    @if ($totalAlerts === 0)
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                            Tidak ada peringatan aktif saat ini.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Jadwal Penerimaan Barang --}}
        <div class="col-md-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-truck mr-1 text-success"></i> Jadwal Penerimaan &amp; Pengiriman</h6>
                    <a href="{{ route('inbound.orders.index') }}" style="font-size:12px;color:#0d8564;">Lihat Semua →</a>
                </div>
                <div class="panel-body" style="padding-top:4px;">
                    <table class="table mb-0 wms-table">
                        <thead>
                            <tr>
                                <th>No. Ref</th>
                                <th>Supplier / Tujuan</th>
                                <th>ETA/ETD</th>
                                <th>Item</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scheduledInbound as $order)
                                @php
                                    $statusMap = [
                                        'draft' => ['label' => 'Terjadwal', 'class' => 'badge-pending'],
                                        'processing' => ['label' => 'Sedang Diproses', 'class' => 'badge-processing'],
                                        'recommended' => ['label' => 'Rekomendasi GA', 'class' => 'badge-info'],
                                        'put_away' => ['label' => 'Put-Away', 'class' => 'badge-transit'],
                                        'completed' => ['label' => 'Selesai', 'class' => 'badge-completed'],
                                    ];
                                    $st = $statusMap[$order->status] ?? [
                                        'label' => ucfirst($order->status),
                                        'class' => 'badge-pending',
                                    ];
                                @endphp
                                <tr>
                                    <td><strong>{{ $order->do_number }}</strong></td>
                                    <td>{{ $order->supplier?->name ?? '–' }}</td>
                                    <td>{{ $order->do_date ? \Carbon\Carbon::parse($order->do_date)->format('d M, H:i') : '–' }}
                                    </td>
                                    <td>{{ number_format($order->items?->sum('quantity') ?? 0) }} unit</td>
                                    <td><span class="badge-status {{ $st['class'] }}">{{ $st['label'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Tidak ada jadwal penerimaan
                                        aktif.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Quick Access --}}
        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-bolt mr-1" style="color:#f59e0b;"></i> Akses Cepat</h6>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <a href="{{ route('inbound.orders.index') }}" class="quick-btn">
                                <i class="fas fa-sign-in-alt text-success"></i>
                                <span>Terima Barang</span>
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="{{ route('putaway.index') }}" class="quick-btn">
                                <i class="fas fa-dolly-flatbed text-primary"></i>
                                <span>Put-Away</span>
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="{{ route('stock.index') }}" class="quick-btn">
                                <i class="fas fa-boxes" style="color:#8b5cf6;"></i>
                                <span>Lihat Stok</span>
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="{{ route('location.cells.index') }}" class="quick-btn">
                                <i class="fas fa-th-large" style="color:#14b8a6;"></i>
                                <span>Lokasi Cell</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('reports.inventory') }}" class="quick-btn">
                                <i class="fas fa-file-alt" style="color:#f59e0b;"></i>
                                <span>Laporan</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('master.items.index') }}" class="quick-btn">
                                <i class="fas fa-barcode" style="color:#f97316;"></i>
                                <span>Master Item</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ROW 5 — AKTIVITAS TERKINI + ITEM HAMPIR KADALUARSA
══════════════════════════════════════════════════════ --}}
    <p class="section-title"><i class="fas fa-history mr-1"></i> Aktivitas & Kondisi Stok</p>
    <div class="row">

        {{-- Timeline Aktivitas Terkini --}}
        <div class="col-md-6">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-stream mr-1 text-info"></i> Aktivitas Terkini</h6>
                    <a href="{{ route('stock.movements') }}" style="font-size:12px;color:#0d8564;">Semua Aktivitas →</a>
                </div>
                <div class="panel-body" style="padding-top:4px;">
                    @forelse($recentMovements as $mov)
                        @php
                            $icons = [
                                'inbound' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-plus'],
                                'outbound' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-truck'],
                                'transfer' => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'icon' => 'fa-arrows-alt'],
                                'adjustment' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'fa-edit'],
                                'return_inbound' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-undo'],
                                'return_outbound' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-undo-alt'],
                            ];
                            $ic = $icons[$mov->movement_type] ?? [
                                'bg' => '#f3f4f6',
                                'color' => '#374151',
                                'icon' => 'fa-circle',
                            ];
                            $label =
                                [
                                    'inbound' => 'Penerimaan',
                                    'outbound' => 'Pengiriman',
                                    'transfer' => 'Pemindahan',
                                    'adjustment' => 'Adjustment',
                                    'return_inbound' => 'Return Masuk',
                                    'return_outbound' => 'Return Keluar',
                                ][$mov->movement_type] ?? ucfirst($mov->movement_type);
                            $from = $mov->fromCell?->rack?->zone?->name ?? '-';
                            $to = $mov->toCell?->rack?->zone?->name ?? '-';
                        @endphp
                        <div class="timeline-item">
                            <div class="timeline-icon"
                                style="background:{{ $ic['bg'] }};color:{{ $ic['color'] }};"><i
                                    class="fas {{ $ic['icon'] }}"></i></div>
                            <div class="timeline-content">
                                <div class="t-action">
                                    {{ $label }}: {{ number_format($mov->quantity) }} unit
                                    {{ $mov->item?->name ?? '–' }}
                                    @if ($mov->movement_type === 'transfer')
                                        <span class="text-muted">({{ $from }} → {{ $to }})</span>
                                    @endif
                                </div>
                                <div class="t-meta">
                                    Ref: {{ $mov->reference_no ?? '–' }}
                                    @if ($mov->performedBy)
                                        · {{ $mov->performedBy->name }}
                                    @endif
                                    · {{ $mov->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-3"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Belum ada
                            aktivitas hari ini.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Item Hampir Kadaluarsa --}}
        <div class="col-md-6">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-calendar-times mr-1 text-danger"></i> Item Mendekati Kadaluarsa</h6>
                    <a href="{{ route('stock.near-expiry') }}" style="font-size:12px;color:#0d8564;">Kelola Stok →</a>
                </div>
                <div class="panel-body" style="padding-top:4px;">
                    <table class="table mb-0 wms-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th>Batch</th>
                                <th>Exp. Date</th>
                                <th>Sisa Hari</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expiryStocks as $stock)
                                @php
                                    $daysLeft = now()->diffInDays($stock->expiry_date, false);
                                    if ($daysLeft <= 20) {
                                        $badgeClass = 'badge-critical';
                                    } elseif ($daysLeft <= 45) {
                                        $badgeClass = 'badge-pending';
                                    } else {
                                        $badgeClass = 'badge-completed';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $stock->item?->sku ?? '–' }}</td>
                                    <td>{{ $stock->item?->name ?? '–' }}</td>
                                    <td>{{ $stock->batch_no ?? '–' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($stock->expiry_date)->format('d M \'y') }}</td>
                                    <td><span class="badge-status {{ $badgeClass }}">{{ $daysLeft }} hari</span>
                                    </td>
                                    <td>{{ number_format($stock->quantity) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Tidak ada stok mendekati
                                        kadaluarsa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ROW 6 — DEADSTOCK MONITORING
══════════════════════════════════════════════════════ --}}
    <p class="section-title">
        <i class="fas fa-hourglass-half mr-1" style="color:#374151;"></i> Monitoring Deadstock
        <span class="badge badge-secondary ml-1" style="font-size:11px;font-weight:600;">
            Barang Tidak Bergerak ≥ Batas Hari
        </span>
    </p>
    <div class="row">
        <div class="col-12">
            <div class="panel-card">
                <div class="panel-header">
                    <h6>
                        <i class="fas fa-boxes mr-1" style="color:#374151;"></i>
                        Daftar Item Deadstock
                        <span class="badge ml-1" style="background:#374151;color:#fff;font-size:11px;">
                            {{ $deadstockCount }} SKU
                        </span>
                    </h6>
                    <span style="font-size:12px;color:#6b7280;">
                        Stok tersedia yang tidak bergerak melampaui batas threshold item (default 90 hari)
                    </span>
                </div>
                <div class="panel-body" style="padding-top:4px;">
                    @if ($deadstockStocks->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                            <strong>Tidak ada item deadstock.</strong><br>
                            <small>Semua stok tersedia masih dalam batas waktu pergerakan normal.</small>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table mb-0 wms-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>SKU</th>
                                        <th>Nama Item</th>
                                        <th>Kategori</th>
                                        <th>Lokasi</th>
                                        <th>Qty</th>
                                        <th>Tgl Masuk</th>
                                        <th>Terakhir Bergerak</th>
                                        <th>Hari Statis</th>
                                        <th>Threshold</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deadstockStocks as $idx => $ds)
                                        @php
                                            $daysStatic = $ds->days_since_last_movement;
                                            $threshold = $ds->item?->deadstock_threshold_days ?? 90;
                                            $overDays = $daysStatic - $threshold;
                                            $zoneName = $ds->cell?->rack?->zone?->name ?? '–';
                                            $cellCode = $ds->cell?->code ?? '–';
                                        @endphp
                                        <tr>
                                            <td>{{ $idx + 1 }}</td>
                                            <td><code style="font-size:12px;">{{ $ds->item?->sku ?? '–' }}</code></td>
                                            <td><strong>{{ $ds->item?->name ?? '–' }}</strong></td>
                                            <td>
                                                <span class="badge"
                                                    style="
                                                background:{{ $ds->item?->category?->color_code ?? '#9ca3af' }}22;
                                                color:{{ $ds->item?->category?->color_code ?? '#374151' }};
                                                border:1px solid {{ $ds->item?->category?->color_code ?? '#9ca3af' }}55;
                                                font-size:11px;padding:3px 7px;border-radius:4px;">
                                                    {{ $ds->item?->category?->name ?? '–' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size:12px;">
                                                    <i class="fas fa-map-marker-alt text-muted mr-1"></i>
                                                    {{ $zoneName }} · <strong>{{ $cellCode }}</strong>
                                                </span>
                                            </td>
                                            <td>{{ number_format($ds->quantity) }}</td>
                                            <td>{{ $ds->inbound_date?->format('d M Y') ?? '–' }}</td>
                                            <td>
                                                @if ($ds->last_moved_at)
                                                    {{ $ds->last_moved_at->format('d M Y') }}
                                                @else
                                                    <span class="text-muted" style="font-size:11px;">
                                                        <i class="fas fa-minus"></i> Belum pernah
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong style="color:#374151;">{{ $daysStatic }}</strong>
                                                <span class="text-muted" style="font-size:11px;"> hari</span>
                                            </td>
                                            <td>
                                                <span class="text-muted" style="font-size:12px;">
                                                    {{ $threshold }} hari
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-critical"
                                                    style="font-size:11px;white-space:nowrap;">
                                                    +{{ $overDays }} hari lewat
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($deadstockCount > 8)
                            <div class="text-center pt-2" style="font-size:12px;color:#6b7280;">
                                Menampilkan 8 dari {{ number_format($deadstockCount) }} item.
                                <a href="{{ route('stock.index') }}" style="color:#0d8564;">Lihat semua →</a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ROW 7 — AKTIVITAS PUT-AWAY HARI INI + STATISTIK GA + KPI PROSES WMS
══════════════════════════════════════════════════════ --}}
    <p class="section-title"><i class="fas fa-users mr-1"></i> Aktivitas Tim & Kinerja Sistem</p>
    <div class="row">

        {{-- Aktivitas Put-Away Hari Ini (real) --}}
        <div class="col-md-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-dolly mr-1" style="color:#0d8564;"></i> Aktivitas Put-Away Hari Ini</h6>
                    <span style="font-size:12px;color:#6b7280;">
                        {{ $putAwayTodayTotal }} item dikonfirmasi hari ini
                    </span>
                </div>
                <div class="panel-body" style="padding-top:4px;">
                    @if ($putAwayToday->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            Belum ada aktivitas put-away hari ini.
                        </div>
                    @else
                        <table class="table mb-0 wms-table">
                            <thead>
                                <tr>
                                    <th>Operator</th>
                                    <th class="text-center">Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Sesuai GA</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($putAwayToday as $pa)
                                    @php
                                        $followPct =
                                            $pa->items_count > 0
                                                ? round(($pa->follow_ga_count / $pa->items_count) * 100)
                                                : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $pa->user?->name ?? 'N/A' }}</strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-success">{{ $pa->items_count }}</span>
                                        </td>
                                        <td class="text-center">
                                            <strong>{{ number_format($pa->total_qty) }}</strong>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge-status {{ $followPct >= 80 ? 'badge-completed' : ($followPct >= 50 ? 'badge-pending' : 'badge-critical') }}">
                                                {{ $followPct }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Statistik Genetic Algorithm (real) --}}
        <div class="col-md-4">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-dna mr-1" style="color:#8b5cf6;"></i> Statistik Genetic Algorithm</h6>
                </div>
                <div class="panel-body">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div style="font-size:24px;font-weight:700;color:#8b5cf6">{{ $gaTotal }}</div>
                            <small class="text-muted" style="font-size:11px">Total GA Run</small>
                        </div>
                        <div class="col-4">
                            <div style="font-size:24px;font-weight:700;color:#0d8564">{{ $gaAccepted }}</div>
                            <small class="text-muted" style="font-size:11px">Diterima</small>
                        </div>
                        <div class="col-4">
                            <div style="font-size:24px;font-weight:700;color:#ef4444">{{ $gaRejected }}</div>
                            <small class="text-muted" style="font-size:11px">Ditolak</small>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label">
                            <span>Acceptance Rate</span>
                            <span style="color:#0d8564;font-weight:600">{{ $gaAcceptRate }}%</span>
                        </div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:{{ $gaAcceptRate }}%;background:#0d8564;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label">
                            <span>Follow GA Rate (put-away)</span>
                            <span style="color:#3b82f6;font-weight:600">{{ $followGaRate }}%</span>
                        </div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:{{ $followGaRate }}%;background:#3b82f6;"></div>
                        </div>
                    </div>
                    <div class="mt-3 pt-2" style="border-top:1px solid #f0f0f0;">
                        <div class="d-flex justify-content-between" style="font-size:12px">
                            <span class="text-muted">Avg. Fitness Score (accepted)</span>
                            <strong style="color:#0d8564">{{ $gaAvgFitness }} / 100</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1" style="font-size:12px">
                            <span class="text-muted">Avg. Waktu Eksekusi</span>
                            <strong>{{ number_format($gaAvgExecMs) }} ms</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1" style="font-size:12px">
                            <span class="text-muted">Total Konfirmasi Put-Away</span>
                            <strong>{{ number_format($totalConfirms) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI Proses WMS (real) --}}
        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-bullseye mr-1 text-danger"></i> KPI Proses WMS</h6>
                </div>
                <div class="panel-body">
                    <div class="zone-row">
                        <div class="zone-label">
                            <span>GA Acceptance Rate</span>
                            <span style="color:#8b5cf6;font-weight:600">{{ $gaAcceptRate }}%</span>
                        </div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:{{ $gaAcceptRate }}%;background:#8b5cf6;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label">
                            <span>Follow GA (put-away)</span>
                            <span style="color:#3b82f6;font-weight:600">{{ $followGaRate }}%</span>
                        </div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:{{ $followGaRate }}%;background:#3b82f6;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label">
                            <span>Order Completion (bln ini)</span>
                            <span style="color:#0d8564;font-weight:600">{{ $completionRate }}%</span>
                        </div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:{{ $completionRate }}%;background:#0d8564;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label">
                            <span>Utilisasi Kapasitas Gudang</span>
                            <span style="color:#f59e0b;font-weight:600">{{ $utilizationPct }}%</span>
                        </div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:{{ $utilizationPct }}%;background:#f59e0b;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label">
                            <span>Avg GA Fitness Score</span>
                            <span style="color:#14b8a6;font-weight:600">{{ $gaAvgFitness }}</span>
                        </div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:{{ $gaAvgFitness }}%;background:#14b8a6;"></div>
                        </div>
                    </div>
                    <div class="mt-2 pt-2" style="border-top:1px solid #f0f0f0;font-size:10px;color:#9ca3af">
                        <i class="fas fa-info-circle mr-1"></i>
                        Semua metrik dihitung dari data transaksi real di sistem.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Visual Analytics --}}
    <p class="section-title"><i class="fas fa-chart-pie mr-1"></i> Visualisasi Risiko & Produktivitas</p>
    <div class="row dashboard-section">
        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-shield-alt mr-1 text-danger"></i> Risiko Inventory</h6>
                    <span style="font-size:12px;color:#6b7280;">stok bermasalah</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box visual">
                        <div id="chartInventoryRisk" class="hc-chart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-calendar-times mr-1 text-warning"></i> Bucket Expiry</h6>
                    <span style="font-size:12px;color:#6b7280;">record stok</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box visual">
                        <div id="chartExpiryBucket" class="hc-chart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-hourglass-half mr-1 text-secondary"></i> Bucket Deadstock</h6>
                    <span style="font-size:12px;color:#6b7280;">usia tidak bergerak</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box visual">
                        <div id="chartDeadstockBucket" class="hc-chart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-user-check mr-1 text-success"></i> Produktivitas Operator</h6>
                    <span style="font-size:12px;color:#6b7280;">hari ini</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box visual">
                        <div id="chartOperatorProductivity" class="hc-chart"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- GA Analytics --}}
    <p class="section-title"><i class="fas fa-dna mr-1"></i> Analitik Genetic Algorithm</p>
    <div class="row">
        <div class="col-md-3 col-6 mb-3">
            <div class="mini-stat">
                <div class="mini-label">Best Fitness</div>
                <div class="mini-value" style="color:#0d8564">{{ $gaBestFitness }}</div>
                <div class="mini-sub">Skor rekomendasi terbaik</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="mini-stat">
                <div class="mini-label">Worst Fitness</div>
                <div class="mini-value" style="color:#ef4444">{{ $gaWorstFitness }}</div>
                <div class="mini-sub">Skor terendah yang pernah muncul</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="mini-stat">
                <div class="mini-label">Avg Generations</div>
                <div class="mini-value" style="color:#8b5cf6">{{ number_format($gaAvgGen) }}</div>
                <div class="mini-sub">Rata-rata generasi per run</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="mini-stat">
                <div class="mini-label">Override Rate</div>
                <div class="mini-value" style="color:#f59e0b">{{ $gaOverrideRate }}%</div>
                <div class="mini-sub">Put-away di luar rekomendasi</div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-chart-line mr-1 text-primary"></i> Trend GA Run & Fitness</h6>
                    <span style="font-size:12px;color:#6b7280;">7 hari terakhir</span>
                </div>
                <div class="panel-body">
                    <div id="chartGaTrend" class="hc-chart" style="height:210px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-chart-pie mr-1 text-success"></i> Distribusi Fitness GA</h6>
                    <span style="font-size:12px;color:#6b7280;">Kualitas rekomendasi</span>
                </div>
                <div class="panel-body">
                    <div id="chartGaFitnessDistribution" class="hc-chart" style="height:210px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Warehouse Capacity Drilldown --}}
    <p class="section-title"><i class="fas fa-warehouse mr-1"></i> Detail Kapasitas Rack & Cell</p>
    <div class="row">
        <div class="col-md-6">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-layer-group mr-1 text-warning"></i> Rack Paling Padat</h6>
                    <span style="font-size:12px;color:#6b7280;">Top 8 berdasarkan utilisasi</span>
                </div>
                <div class="panel-body">
                    @forelse($topRacksUtilization as $rack)
                        <div class="zone-row">
                            <div class="zone-label">
                                <span><strong>{{ $rack['code'] }}</strong> - {{ $rack['zone'] }}</span>
                                <span>{{ $rack['percent'] }}%</span>
                            </div>
                            <div class="zone-bar">
                                <div class="zone-fill"
                                    style="width:{{ min(100, $rack['percent']) }}%;background:{{ $rack['percent'] >= 85 ? '#ef4444' : ($rack['percent'] >= 70 ? '#f59e0b' : '#0d8564') }};">
                                </div>
                            </div>
                            <div class="mt-1" style="font-size:11px;color:#6b7280;">
                                {{ number_format($rack['used']) }} / {{ number_format($rack['max']) }} unit terpakai
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">Belum ada data rack aktif.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-border-all mr-1 text-danger"></i> Cell Hampir Penuh</h6>
                    <span style="font-size:12px;color:#6b7280;">Prioritas monitoring kapasitas</span>
                </div>
                <div class="panel-body p-0">
                    <table class="table mb-0 wms-table">
                        <thead>
                            <tr>
                                <th>Cell</th>
                                <th>Rack/Zona</th>
                                <th class="text-right">Terpakai</th>
                                <th class="text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($criticalCells as $cell)
                                <tr>
                                    <td><strong>{{ $cell['code'] }}</strong><br><small class="text-muted">{{ $cell['status'] }}</small></td>
                                    <td>{{ $cell['rack'] }}<br><small class="text-muted">{{ $cell['zone'] }}</small></td>
                                    <td class="text-right">
                                        <strong style="color:{{ $cell['percent'] >= 85 ? '#ef4444' : '#f59e0b' }}">{{ $cell['percent'] }}%</strong><br>
                                        <small class="text-muted">{{ number_format($cell['used']) }}/{{ number_format($cell['max']) }}</small>
                                    </td>
                                    <td class="text-right">{{ number_format($cell['remaining']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada data cell.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script>
        // ── Live Clock & Date ───────────────────────────────────────────────────
        function updateClock() {
            const now = new Date();
            const opts = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            document.getElementById('live-date').textContent = now.toLocaleDateString('id-ID', opts);
            document.getElementById('live-clock').textContent = now.toLocaleTimeString('id-ID');
            document.getElementById('last-sync').textContent = now.toLocaleTimeString('id-ID');
        }
        updateClock();
        setInterval(updateClock, 1000);

        let highchartsFallbackTried = false;

        function showChartLoadError() {
            document.querySelectorAll('.hc-chart').forEach((el) => {
                if (!el.dataset.chartError) {
                    el.dataset.chartError = '1';
                    el.innerHTML = '<div class="text-center text-muted py-4" style="font-size:12px;">Chart belum bisa dimuat. Periksa koneksi CDN Highcharts.</div>';
                }
            });
        }

        function renderDashboardCharts() {
            if (!window.Highcharts) {
                if (!highchartsFallbackTried) {
                    highchartsFallbackTried = true;
                    const fallback = document.createElement('script');
                    fallback.src = 'https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.4.8/highcharts.js';
                    fallback.onload = renderDashboardCharts;
                    fallback.onerror = showChartLoadError;
                    document.head.appendChild(fallback);
                    return;
                }
                showChartLoadError();
                return;
            }

        Highcharts.setOptions({
            chart: {
                backgroundColor: 'transparent',
                style: { fontFamily: 'Roboto, Arial, sans-serif' }
            },
            credits: { enabled: false },
            title: { text: null },
            lang: { thousandsSep: '.' }
        });

        // ── 1. Donut Utilisasi Zona ─────────────────────────────────────────────
        @php
            $capacityChartLabels = ['Terpakai', 'Kosong'];
            $capacityChartData = [$capacityUsedTotal, $capacityFreeTotal];
        @endphp
        Highcharts.chart('chartZoneDonut', {
            chart: { type: 'pie', spacing: [0, 0, 0, 0] },
            tooltip: { pointFormat: '<b>{point.y:,.0f}</b> unit' },
            plotOptions: {
                pie: {
                    innerSize: '72%',
                    borderWidth: 2,
                    dataLabels: { enabled: false },
                    showInLegend: false
                }
            },
            series: [{
                name: 'Kapasitas',
                data: [
                    { name: 'Terpakai', y: {{ (int) $capacityUsedTotal }}, color: '#0d8564' },
                    { name: 'Kosong', y: {{ (int) $capacityFreeTotal }}, color: '#e5e7eb' }
                ]
            }]
        });

        // ── 2. Bar Chart Inbound vs Outbound (real 7-hari) ──────────────────────
        @php
            $chartLabelsJson = json_encode($chartLabels);
            $chartInboundJson = json_encode($chartInbound);
            $chartOutboundJson = json_encode($chartOutbound);
        @endphp
        Highcharts.chart('chartDailyInOutTrend', {
            chart: { type: 'spline' },
            xAxis: { categories: {!! $chartLabelsJson !!}, crosshair: true },
            yAxis: {
                min: 0,
                title: { text: 'Total Qty' },
                gridLineColor: '#f3f4f6'
            },
            legend: { align: 'center', verticalAlign: 'bottom' },
            tooltip: {
                shared: true,
                valueSuffix: ' unit'
            },
            plotOptions: {
                spline: {
                    marker: {
                        enabled: true,
                        radius: 4
                    },
                    lineWidth: 3
                }
            },
            series: [
                { name: 'Inbound', data: {!! $chartInboundJson !!}, color: '#0d8564' },
                { name: 'Outbound', data: {!! $chartOutboundJson !!}, color: '#3b82f6' }
            ]
        });

        Highcharts.chart('chartInOut', {
            chart: { type: 'column' },
            xAxis: { categories: {!! $chartLabelsJson !!}, crosshair: true },
            yAxis: { min: 0, title: { text: null }, gridLineColor: '#f3f4f6' },
            legend: { align: 'center', verticalAlign: 'bottom' },
            tooltip: { shared: true, valueSuffix: ' unit' },
            plotOptions: { column: { borderRadius: 4, pointPadding: 0.12, groupPadding: 0.12 } },
            series: [
                { name: 'Inbound', data: {!! $chartInboundJson !!}, color: '#0d8564' },
                { name: 'Outbound', data: {!! $chartOutboundJson !!}, color: '#3b82f6' }
            ]
        });

        // ── 3. Pie Chart Status Order (real) ────────────────────────────────────
        @php
            $osLabels = ['Selesai', 'Put-Away', 'Menunggu GA Accept', 'Konfirmasi Qty', 'Draft', 'Dibatalkan'];
            $osKeys = ['completed', 'put_away', 'recommended', 'processing', 'draft', 'cancelled'];
            $osData = array_map(fn($k) => (int) ($orderStatusCounts[$k] ?? 0), $osKeys);
            $osColors = ['#0d8564', '#14b8a6', '#f59e0b', '#3b82f6', '#6b7280', '#ef4444'];
        @endphp
        Highcharts.chart('chartOrderStatus', {
            chart: { type: 'pie', spacing: [0, 0, 0, 0] },
            tooltip: { pointFormat: '<b>{point.y}</b> DO ({point.percentage:.1f}%)' },
            plotOptions: {
                pie: {
                    innerSize: '60%',
                    borderWidth: 2,
                    dataLabels: { enabled: false },
                    showInLegend: false
                }
            },
            series: [{
                name: 'Order',
                data: {!! json_encode(array_map(fn($label, $value, $color) => ['name' => $label, 'y' => $value, 'color' => $color], $osLabels, $osData, $osColors)) !!}
            }]
        });

        // ── 4. Donut Chart Stok per Kategori (real) ──────────────────────────────
        @php
            $catChartLabels = $stockByCategory->pluck('name')->toArray();
            $catChartData = $stockByCategory->pluck('total_qty')->map(fn($v) => (int) $v)->toArray();
            $catChartColors = $stockByCategory->map(fn($c) => $c->color_code ?: '#9ca3af')->toArray();
        @endphp
        @if (!$stockByCategory->isEmpty())
            Highcharts.chart('chartCategoryStock', {
                chart: { type: 'pie', spacing: [0, 0, 0, 0] },
                tooltip: { pointFormat: '<b>{point.y:,.0f}</b> unit' },
                plotOptions: {
                    pie: {
                        innerSize: '68%',
                        borderWidth: 2,
                        dataLabels: { enabled: false },
                        showInLegend: false
                    }
                },
                series: [{
                    name: 'Stok',
                    data: {!! json_encode(array_map(fn($label, $value, $color) => ['name' => $label, 'y' => $value, 'color' => $color], $catChartLabels, $catChartData, $catChartColors)) !!}
                }]
            });
        @endif

        // ── 5. Bottleneck Aging ───────────────────────────────────────────
        @php
            $bottleneckLabels = collect($bottleneckSummary)->pluck('label')->toArray();
            $bottleneckCounts = collect($bottleneckSummary)->pluck('count')->map(fn($v) => (int) $v)->toArray();
            $bottleneckColors = collect($bottleneckSummary)->pluck('color')->toArray();
        @endphp
        Highcharts.chart('chartBottleneck', {
            chart: { type: 'bar', spacing: [4, 4, 4, 4] },
            xAxis: { categories: {!! json_encode($bottleneckLabels) !!}, title: { text: null }, lineWidth: 0 },
            yAxis: { min: 0, title: { text: null }, gridLineColor: '#f3f4f6', allowDecimals: false },
            legend: { enabled: false },
            tooltip: { pointFormat: '<b>{point.y}</b> DO' },
            plotOptions: { bar: { borderRadius: 4, colorByPoint: true, colors: {!! json_encode($bottleneckColors) !!} } },
            series: [{ name: 'Jumlah DO', data: {!! json_encode($bottleneckCounts) !!} }]
        });

        // ── 6. GA Trend ───────────────────────────────────────────────────
        @php
            $riskLabels = array_keys($inventoryRiskChart);
            $riskData = array_values($inventoryRiskChart);
            $expiryLabels = array_keys($expiryBucketChart);
            $expiryData = array_values($expiryBucketChart);
            $deadstockLabels = array_keys($deadstockBucketChart);
            $deadstockData = array_values($deadstockBucketChart);
            $operatorLabels = $operatorProductivity->pluck('name')->toArray();
            $operatorItems = $operatorProductivity->pluck('items')->toArray();
            $operatorQty = $operatorProductivity->pluck('qty')->toArray();
            $operatorFollow = $operatorProductivity->pluck('follow_rate')->toArray();
        @endphp

        Highcharts.chart('chartInventoryRisk', {
            chart: { type: 'pie' },
            tooltip: { pointFormat: '<b>{point.y}</b> data ({point.percentage:.1f}%)' },
            plotOptions: {
                pie: { innerSize: '58%', borderWidth: 2, dataLabels: { enabled: false }, showInLegend: true }
            },
            legend: { itemStyle: { fontSize: '11px' } },
            series: [{
                name: 'Risiko',
                data: {!! json_encode(array_map(fn($label, $value, $color) => ['name' => $label, 'y' => $value, 'color' => $color], $riskLabels, $riskData, ['#dc2626', '#f59e0b', '#3b82f6', '#374151'])) !!}
            }]
        });

        Highcharts.chart('chartExpiryBucket', {
            chart: { type: 'column' },
            xAxis: { categories: {!! json_encode($expiryLabels) !!}, crosshair: true },
            yAxis: { min: 0, title: { text: null }, allowDecimals: false, gridLineColor: '#f3f4f6' },
            legend: { enabled: false },
            tooltip: { pointFormat: '<b>{point.y}</b> record stok' },
            plotOptions: { column: { borderRadius: 5, colorByPoint: true, colors: ['#dc2626', '#f59e0b', '#3b82f6'] } },
            series: [{ name: 'Near Expiry', data: {!! json_encode($expiryData) !!} }]
        });

        Highcharts.chart('chartDeadstockBucket', {
            chart: { type: 'bar' },
            xAxis: { categories: {!! json_encode($deadstockLabels) !!}, title: { text: null } },
            yAxis: { min: 0, title: { text: null }, allowDecimals: false, gridLineColor: '#f3f4f6' },
            legend: { enabled: false },
            tooltip: { pointFormat: '<b>{point.y}</b> record stok' },
            plotOptions: { bar: { borderRadius: 5, colorByPoint: true, colors: ['#64748b', '#f59e0b', '#dc2626'] } },
            series: [{ name: 'Deadstock', data: {!! json_encode($deadstockData) !!} }]
        });

        Highcharts.chart('chartOperatorProductivity', {
            chart: { zoomType: 'xy' },
            xAxis: { categories: {!! json_encode($operatorLabels) !!}, crosshair: true },
            yAxis: [{
                min: 0,
                title: { text: 'Item/Qty' },
                allowDecimals: false,
                gridLineColor: '#f3f4f6'
            }, {
                min: 0,
                max: 100,
                title: { text: 'Follow GA %' },
                opposite: true,
                gridLineWidth: 0
            }],
            tooltip: { shared: true },
            legend: { align: 'center', verticalAlign: 'bottom' },
            series: [{
                name: 'Item',
                type: 'column',
                data: {!! json_encode($operatorItems) !!},
                color: '#0d8564'
            }, {
                name: 'Qty',
                type: 'column',
                data: {!! json_encode($operatorQty) !!},
                color: '#3b82f6'
            }, {
                name: 'Follow GA',
                type: 'spline',
                yAxis: 1,
                data: {!! json_encode($operatorFollow) !!},
                color: '#f59e0b',
                tooltip: { valueSuffix: '%' }
            }]
        });

        Highcharts.chart('chartGaTrend', {
            chart: { zoomType: 'xy' },
            xAxis: { categories: {!! json_encode($gaTrendLabels) !!}, crosshair: true },
            yAxis: [{
                min: 0,
                title: { text: 'GA Run' },
                allowDecimals: false,
                gridLineColor: '#f3f4f6'
            }, {
                min: 0,
                max: 100,
                title: { text: 'Fitness' },
                opposite: true,
                gridLineWidth: 0
            }],
            tooltip: { shared: true },
            legend: { align: 'center', verticalAlign: 'bottom' },
            series: [{
                name: 'GA Run',
                type: 'column',
                data: {!! json_encode($gaTrendRuns) !!},
                color: '#8b5cf6'
            }, {
                name: 'Avg Fitness',
                type: 'spline',
                yAxis: 1,
                data: {!! json_encode($gaTrendFitness) !!},
                color: '#0d8564'
            }]
        });

        // ── 7. GA Fitness Distribution ────────────────────────────────────
        Highcharts.chart('chartGaFitnessDistribution', {
            chart: { type: 'bar' },
            xAxis: { categories: {!! json_encode(array_keys($gaFitnessDistribution)) !!}, title: { text: null } },
            yAxis: { min: 0, title: { text: null }, allowDecimals: false, gridLineColor: '#f3f4f6' },
            legend: { enabled: false },
            tooltip: { pointFormat: '<b>{point.y}</b> GA run' },
            plotOptions: { bar: { borderRadius: 4, colorByPoint: true, colors: ['#ef4444', '#f59e0b', '#3b82f6', '#0d8564'] } },
            series: [{
                name: 'Jumlah GA Run',
                data: {!! json_encode(array_values($gaFitnessDistribution)) !!}
            }]
        });
        }

        window.addEventListener('load', renderDashboardCharts);
    </script>
@endpush
