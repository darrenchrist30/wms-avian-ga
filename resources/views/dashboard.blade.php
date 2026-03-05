@extends('layouts.adminlte')

@section('title', 'AVIAN WMS – Dashboard')

@section('page_title', '')

@push('styles')
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/chart.js/Chart.min.css') }}">
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
                <i class="fas fa-percentage kpi-icon"></i>
                <div class="kpi-value">{{ $utilizationPct }}%</div>
                <div class="kpi-label">Utilisasi Kapasitas Gudang</div>
                <div class="kpi-trend"><span class="up">{{ $usedCells }}/{{ $totalCells }} cell</span></div>
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
    <p class="section-title"><i class="fas fa-chart-bar mr-1"></i> Kapasitas & Arus Barang</p>
    <div class="row">

        {{-- Utilisasi per Zona Penyimpanan --}}
        <div class="col-md-4">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-th-large mr-1 text-success"></i> Utilisasi Zona Penyimpanan</h6>
                    <span style="font-size:12px;color:#6b7280;">Kapasitas maks. per zona</span>
                </div>
                <div class="panel-body">
                    <!-- Donut + legend -->
                    <div class="donut-wrap mb-3" style="height:180px;">
                        <canvas id="chartZoneDonut" height="180"></canvas>
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
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-exchange-alt mr-1 text-primary"></i> Arus Barang – 7 Hari Terakhir</h6>
                    <span style="font-size:12px;color:#6b7280;">Unit barang masuk / keluar</span>
                </div>
                <div class="panel-body">
                    <canvas id="chartInOut" height="220"></canvas>
                </div>
            </div>
        </div>

        {{-- Order Status Breakdown --}}
        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-tasks mr-1 text-purple" style="color:#8b5cf6;"></i> Status Order</h6>
                </div>
                <div class="panel-body">
                    <canvas id="chartOrderStatus" height="200"></canvas>
                    <div class="mt-3" style="font-size:12px;">
                        <div class="d-flex justify-content-between mb-1">
                            <span><span style="color:#0d8564;">●</span> Completed</span><strong>48</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><span style="color:#3b82f6;">●</span> Processing</span><strong>32</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><span style="color:#f59e0b;">●</span> Pending</span><strong>22</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><span style="color:#ef4444;">●</span> On Hold</span><strong>8</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><span style="color:#8b5cf6;">●</span> Cancelled</span><strong>3</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ROW 3 — TREN STOK BULANAN + PERFORMA KARYAWAN
══════════════════════════════════════════════════════ --}}
    <div class="row">
        {{-- Tren stok bulanan --}}
        <div class="col-md-8">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-chart-line mr-1 text-teal" style="color:#14b8a6;"></i> Tren Stok Bulanan (Jan–Feb
                        2026)</h6>
                    <select class="form-control form-control-sm" style="width:120px;font-size:12px;">
                        <option>Semua Zona</option>
                        <option>Zona A</option>
                        <option>Zona B</option>
                    </select>
                </div>
                <div class="panel-body">
                    <canvas id="chartStockTrend" height="160"></canvas>
                </div>
            </div>
        </div>

        {{-- Top 5 SKU bergerak --}}
        <div class="col-md-4">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-fire mr-1" style="color:#f97316;"></i> Top 5 SKU Paling Aktif</h6>
                </div>
                <div class="panel-body" style="padding-top:8px;">
                    <table class="table mb-0 wms-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>SKU</th>
                                <th>Nama</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>AV-01024</td>
                                <td>Cat Dinding 20Kg</td>
                                <td><strong>4,230</strong></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>AV-00872</td>
                                <td>Cat Tembok 5Kg</td>
                                <td><strong>3,812</strong></td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>AV-01155</td>
                                <td>Thinner 1L</td>
                                <td><strong>2,940</strong></td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>AV-00540</td>
                                <td>Epoxy Primer 4Kg</td>
                                <td><strong>1,875</strong></td>
                            </tr>
                            <tr>
                                <td>5</td>
                                <td>AV-00318</td>
                                <td>Varnish 1L</td>
                                <td><strong>1,421</strong></td>
                            </tr>
                        </tbody>
                    </table>
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
                    <a href="#" style="font-size:12px;color:#0d8564;">Lihat Semua →</a>
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
                            <a href="#" class="quick-btn">
                                <i class="fas fa-sign-in-alt text-success"></i>
                                <span>Terima Barang</span>
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="#" class="quick-btn">
                                <i class="fas fa-shipping-fast text-primary"></i>
                                <span>Kirim Barang</span>
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="#" class="quick-btn">
                                <i class="fas fa-clipboard-check text-warning"></i>
                                <span>Stock Opname</span>
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="#" class="quick-btn">
                                <i class="fas fa-dolly" style="color:#8b5cf6;"></i>
                                <span>Move Stok</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="#" class="quick-btn">
                                <i class="fas fa-file-alt" style="color:#14b8a6;"></i>
                                <span>Laporan</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="#" class="quick-btn">
                                <i class="fas fa-barcode" style="color:#f97316;"></i>
                                <span>Scan Barcode</span>
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
                    <a href="#" style="font-size:12px;color:#0d8564;">Semua Aktivitas →</a>
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
                    <a href="#" style="font-size:12px;color:#0d8564;">Kelola Stok →</a>
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
                                <a href="#" style="color:#0d8564;">Lihat semua →</a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
     ROW 7 — PERFORMA TIM + STATUS DOCK
══════════════════════════════════════════════════════ --}}
    <p class="section-title"><i class="fas fa-users mr-1"></i> Performa Tim & Fasilitas Gudang</p>
    <div class="row">

        {{-- Performa Staff / Shift --}}
        <div class="col-md-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-user-clock mr-1" style="color:#0d8564;"></i> Performa Staff Hari Ini</h6>
                </div>
                <div class="panel-body" style="padding-top:4px;">
                    <table class="table mb-0 wms-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Shift</th>
                                <th>Tugas</th>
                                <th>Selesai</th>
                                <th>Akurasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Budi Santoso</td>
                                <td>Pagi</td>
                                <td>Receiving</td>
                                <td>12 / 15</td>
                                <td><span class="badge-status badge-completed">98%</span></td>
                            </tr>
                            <tr>
                                <td>Rina Wati</td>
                                <td>Pagi</td>
                                <td>Packing</td>
                                <td>28 / 30</td>
                                <td><span class="badge-status badge-completed">100%</span></td>
                            </tr>
                            <tr>
                                <td>Agus Triyono</td>
                                <td>Pagi</td>
                                <td>Shipping</td>
                                <td>8 / 10</td>
                                <td><span class="badge-status badge-completed">95%</span></td>
                            </tr>
                            <tr>
                                <td>Dewi Lestari</td>
                                <td>Siang</td>
                                <td>Picking</td>
                                <td>5 / 20</td>
                                <td><span class="badge-status badge-pending">87%</span></td>
                            </tr>
                            <tr>
                                <td>Hendra G.</td>
                                <td>Siang</td>
                                <td>Stock Opname</td>
                                <td>2 / 5</td>
                                <td><span class="badge-status badge-processing">92%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Status Dock / Loading Bay --}}
        <div class="col-md-4">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-door-open mr-1" style="color:#f97316;"></i> Status Dock &amp; Loading Bay</h6>
                </div>
                <div class="panel-body">
                    <div class="row text-center">
                        @php
                            $docks = [
                                [
                                    'label' => 'Dock 1',
                                    'status' => 'Aktif – Unloading',
                                    'color' => '#0d8564',
                                    'bg' => '#d1fae5',
                                ],
                                [
                                    'label' => 'Dock 2',
                                    'status' => 'Aktif – Loading',
                                    'color' => '#3b82f6',
                                    'bg' => '#dbeafe',
                                ],
                                ['label' => 'Dock 3', 'status' => 'Kosong', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
                                ['label' => 'Dock 4', 'status' => 'Kosong', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
                                [
                                    'label' => 'Dock 5',
                                    'status' => 'Maintenance',
                                    'color' => '#ef4444',
                                    'bg' => '#fee2e2',
                                ],
                                [
                                    'label' => 'Dock 6',
                                    'status' => 'Aktif – Unloading',
                                    'color' => '#0d8564',
                                    'bg' => '#d1fae5',
                                ],
                            ];
                        @endphp
                        @foreach ($docks as $dock)
                            <div class="col-4 mb-3">
                                <div style="border-radius:8px;padding:14px 8px;background:{{ $dock['bg'] }};">
                                    <div style="font-size:11px;font-weight:700;color:{{ $dock['color'] }};">
                                        {{ $dock['label'] }}</div>
                                    <div style="font-size:10px;color:#374151;margin-top:4px;">{{ $dock['status'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI Akurasi Gudang --}}
        <div class="col-md-3">
            <div class="panel-card">
                <div class="panel-header">
                    <h6><i class="fas fa-bullseye mr-1 text-danger"></i> KPI Akurasi Gudang</h6>
                </div>
                <div class="panel-body">
                    <div class="zone-row">
                        <div class="zone-label"><span>Akurasi Picking</span><span
                                style="color:#0d8564;font-weight:600;">98.7%</span></div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:98.7%;background:#0d8564;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label"><span>Akurasi Packing</span><span
                                style="color:#3b82f6;font-weight:600;">99.1%</span></div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:99.1%;background:#3b82f6;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label"><span>On-Time Delivery</span><span
                                style="color:#f59e0b;font-weight:600;">94.2%</span></div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:94.2%;background:#f59e0b;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label"><span>Akurasi Stok Opname</span><span
                                style="color:#8b5cf6;font-weight:600;">99.8%</span></div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:99.8%;background:#8b5cf6;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label"><span>Fill Rate</span><span
                                style="color:#14b8a6;font-weight:600;">91.5%</span></div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:91.5%;background:#14b8a6;"></div>
                        </div>
                    </div>
                    <div class="zone-row">
                        <div class="zone-label"><span>Return Rate</span><span
                                style="color:#ef4444;font-weight:600;">2.3%</span></div>
                        <div class="zone-bar">
                            <div class="zone-fill" style="width:2.3%;background:#ef4444;min-width:20px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('adminlte/plugins/chart.js/Chart.bundle.min.js') }}"></script>
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

        // ── Chart Defaults ──────────────────────────────────────────────────────
        Chart.defaults.global.defaultFontFamily = "'Roboto', sans-serif";
        Chart.defaults.global.defaultFontSize = 12;

        // ── 1. Donut Utilisasi Zona ─────────────────────────────────────────────
        @php
            $zoneLabels = $zones->pluck('name')->map(fn($n) => \Illuminate\Support\Str::limit($n, 20))->toJson();
            $zoneData = $zones->pluck('percent')->toJson();
            $emptySpace = max(0, 100 - $zones->avg('percent'));
        @endphp
        new Chart(document.getElementById('chartZoneDonut'), {
            type: 'doughnut',
            data: {
                labels: {!! $zoneLabels !!},
                datasets: [{
                    data: {!! $zoneData !!},
                    backgroundColor: ['#0d8564', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#e5e7eb'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                cutoutPercentage: 72,
                legend: {
                    display: false
                },
                tooltips: {
                    callbacks: {
                        label: (i, d) => d.labels[i.index] + ': ' + d.datasets[0].data[i.index] + '%'
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        });

        // ── 2. Bar Chart Inbound vs Outbound ────────────────────────────────────
        new Chart(document.getElementById('chartInOut'), {
            type: 'bar',
            data: {
                labels: ['21 Feb', '22 Feb', '23 Feb', '24 Feb', '25 Feb', '26 Feb', '27 Feb'],
                datasets: [{
                        label: 'Inbound (unit)',
                        data: [320, 280, 410, 365, 290, 430, 136],
                        backgroundColor: 'rgba(13,133,100,.75)',
                        borderRadius: 4
                    },
                    {
                        label: 'Outbound (unit)',
                        data: [250, 310, 380, 290, 350, 400, 98],
                        backgroundColor: 'rgba(59,130,246,.75)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        },
                        gridLines: {
                            color: '#f3f4f6'
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            display: false
                        }
                    }]
                }
            }
        });

        // ── 3. Pie Chart Status Order ───────────────────────────────────────────
        new Chart(document.getElementById('chartOrderStatus'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Processing', 'Pending', 'On Hold', 'Cancelled'],
                datasets: [{
                    data: [48, 32, 22, 8, 3],
                    backgroundColor: ['#0d8564', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                cutoutPercentage: 60,
                legend: {
                    display: false
                },
                animation: {
                    duration: 1000
                }
            }
        });

        // ── 4. Line Chart Tren Stok Bulanan ─────────────────────────────────────
        new Chart(document.getElementById('chartStockTrend'), {
            type: 'line',
            data: {
                labels: ['1 Jan', '5 Jan', '10 Jan', '15 Jan', '20 Jan', '25 Jan', '31 Jan',
                    '5 Feb', '10 Feb', '15 Feb', '20 Feb', '27 Feb'
                ],
                datasets: [{
                        label: 'Stok Masuk',
                        data: [1200, 980, 1500, 1100, 1300, 900, 1600, 1050, 1400, 1250, 1350, 1136],
                        borderColor: '#0d8564',
                        backgroundColor: 'rgba(13,133,100,.08)',
                        pointRadius: 3,
                        tension: .4,
                        fill: true
                    },
                    {
                        label: 'Stok Keluar',
                        data: [800, 1100, 1200, 950, 1150, 1050, 1300, 900, 1200, 1100, 1050, 980],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,.06)',
                        pointRadius: 3,
                        tension: .4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: false
                        },
                        gridLines: {
                            color: '#f3f4f6'
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            display: false
                        }
                    }]
                }
            }
        });
    </script>
@endpush
