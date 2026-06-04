@extends('layouts.adminlte')
@section('title', 'Mode Operator — Put-Away')

@push('styles')
<link rel="stylesheet" href="{{ asset('adminlte/plugins/toastr/toastr.min.css') }}">
<style>
body { background: #f0f2f5; }

.op-header {
    background: linear-gradient(135deg, #0d8564, #004230);
    color: #fff;
    padding: 18px 20px 16px;
    border-radius: 10px;
    margin-bottom: 14px;
}
.op-header h2 { font-size: 22px; font-weight: 500; margin: 4px 0; }
.op-header small { font-size: 12px; opacity: .75; }

/* Location group card */
.loc-group {
    border: 2px solid #dee2e6;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 16px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    transition: opacity .3s ease, transform .3s ease;
}
.loc-group.removing {
    opacity: 0;
    transform: scale(.97);
    pointer-events: none;
}

.operator-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}
.operator-summary-card {
    background: #fff;
    border: 1px solid #dfe5ec;
    border-radius: 10px;
    padding: 12px 14px;
    min-height: 84px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.operator-summary-card .label {
    color: #6c757d;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.operator-summary-card .value {
    color: #13283f;
    font-size: 24px;
    font-weight: 500;
    line-height: 1;
}
.operator-summary-card .unit {
    color: #6c757d;
    font-size: 13px;
    font-weight: 400;
    margin-left: 3px;
}
.operator-summary-card .note {
    color: #6c757d;
    font-size: 12px;
    margin-top: 4px;
}
.operator-summary-card .icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: #e9f8f3;
    color: #0d8564;
    font-size: 18px;
}

.operator-table-card {
    background: #fff;
    border: 1px solid #dfe5ec;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
}
.operator-table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #e2e8f0;
    background: #f4f6f9;
}
.operator-search {
    max-width: 420px;
    flex: 1;
}
.operator-search .input-group-text {
    background: #f7fafc;
    border-color: #dfe5ec;
}
.operator-search input {
    border-color: #dfe5ec;
    font-weight: 400;
}
.operator-table-wrap {
    width: 100%;
    overflow-x: auto;
    border-radius: 0 0 12px 12px;
    overflow: hidden;
}
.operator-table {
    margin-bottom: 0;
    min-width: 0;
}
.operator-table th {
    background: #f4f6f9;
    color: #43566c;
    font-size: 12px;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: .35px;
    white-space: nowrap;
    border-top: 0;
    border-right: 1px solid #dee2e6;
    text-align: center;
}
.operator-table th:last-child { border-right: 0; }
.operator-table td {
    vertical-align: middle;
    border-right: 1px solid #f0f0f0;
}
.operator-table td:last-child { border-right: 0; }
.cell-row td {
    background: #eaf4f0;
    border-top: 1px solid #c8e6de;
    border-bottom: 1px solid #c8e6de;
    padding: 8px 14px;
}
.operator-row-item {
    font-size: 14px;
    font-weight: 400;
    color: #122238;
    line-height: 1.2;
}
.operator-row-meta {
    font-size: 12px;
    color: #6c757d;
}
.operator-do {
    color: #122238;
    font-weight: 400;
    font-size: 13px;
    letter-spacing: 0;
}
.operator-cell-badge {
    display: inline-block;
    background: #0d8564;
    color: #fff;
    border-radius: 5px;
    padding: 3px 9px;
    font-weight: 900;
    letter-spacing: .4px;
    white-space: nowrap;
}
.qty-inline {
    font-size: 14px;
    font-weight: 400;
    color: #122238;
}
.unit-inline {
    font-size: 12px;
    color: #6c757d;
    font-weight: 800;
    margin-left: 2px;
}
.no-filter-result {
    display: none;
    padding: 30px 16px;
    text-align: center;
    color: #6c757d;
}

/* Location header */
.loc-sub  { font-size: 12px; }
.cap-bar  { height: 4px; background: #e9ecef; border-radius: 3px; overflow: hidden; width: 70px; display:inline-block; vertical-align:middle; }
.cap-fill { height: 4px; border-radius: 3px; }

/* Item row */
.item-row td {
    padding: 12px 14px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}
.item-row:last-child td { border-bottom: 0; }
.item-name { font-size: 17px; font-weight: 700; line-height: 1.2; }
.item-sku  { font-size: 12px; color: #6c757d; margin-top: 3px; }
.item-do   { font-size: 12px; color: #fff; background: #6c757d; border-radius: 4px; padding: 1px 7px; margin-top: 4px; display: inline-block; font-weight: 600; letter-spacing: .3px; }
.qty-box {
    background: #0d8564;
    color: #fff;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 22px;
    font-weight: 800;
    white-space: nowrap;
    text-align: center;
    min-width: 88px;
    flex-shrink: 0;
}
.qty-unit { font-size: 11px; font-weight: 400; opacity: .85; margin-top: 1px; }

/* Fixed scan bar at bottom */
#scanBar {
    position: fixed;
    bottom: 0; left: 250px; right: 0;
    background: #fff;
    border-top: 2px solid #dee2e6;
    padding: 10px 16px;
    z-index: 1038;
    box-shadow: 0 -4px 16px rgba(0,0,0,.12);
    transition: left .3s ease;
}
.sidebar-collapse #scanBar { left: 70px; }
@media (max-width: 1199.98px) {
    #scanBar, .sidebar-collapse #scanBar { left: 0 !important; right: 0 !important; width: auto; }
}
.scan-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}
#btnCamera { width: 44px; height: 44px; border-radius: 8px; font-size: 16px; flex-shrink: 0; }
#scanInput { height: 44px; font-size: 16px; font-weight: 400; border-radius: 8px; flex: 1; min-width: 0; }
#btnScan   { height: 44px; font-size: 14px; font-weight: 500; border-radius: 8px; padding: 0 18px; flex-shrink: 0; }
.auto-toggle-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    gap: 2px;
}
.auto-toggle-wrap label {
    font-size: 10px;
    color: #6c757d;
    margin: 0;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .3px;
    white-space: nowrap;
}
#scanMsg { font-size: 11px; margin-top: 4px; text-align: center; min-height: 0; }

/* All done state */
.all-done-screen { text-align: center; padding: 70px 20px; }

/* Toastr custom size for operator */
#toast-container.toast-top-center { top: 20px; }
#toast-container.toast-top-center > .toast {
    font-size: 16px;
    padding: 14px 20px 14px 52px;
    min-width: 280px;
    max-width: 420px;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.25);
}
#toast-container .toast-success { background-color: #0d8564 !important; }
#toast-container .toast-title { font-size: 14px; font-weight: 700; }
#toast-container .toast-message { margin-top: 4px; line-height: 1.4; }

/* Filter toggle */
.btn-avian-filter {
    border: 1px solid #ced4da;
    color: #6c757d;
    background: #fff;
    font-weight: 400;
    border-radius: 6px;
    transition: background .15s, color .15s, border-color .15s;
}
.btn-avian-filter:hover,
.btn-avian-filter:focus {
    border-color: #0d8564;
    color: #0d8564;
    background: #f0faf7;
    text-decoration: none;
}
.btn-avian-filter.active {
    background: #0d8564;
    border-color: #0d8564;
    color: #fff;
    font-weight: 500;
}
.btn-group .btn-avian-filter.active:not(:last-child) {
    border-right-color: #0a6e52;
}

/* Counter bump animation */
@keyframes counterBump {
    0%   { transform: scale(1); }
    35%  { transform: scale(1.18); color: #a8e6cf; }
    100% { transform: scale(1); }
}
#itemCounter.bumping { animation: counterBump 0.5s ease; }

/* Quick confirm toggle button */
#scanBar.qc-active { border-top-color: #0d8564; }
#btnQuickConfirm:checked ~ .custom-control-label::before { background-color: #0d8564; border-color: #0d8564; }

/* ── Mobile (< 576px) ───────────────────────────────────────── */
@media (max-width: 575px) {
    .operator-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .operator-summary-card { padding: 10px; min-height: 76px; }
    .operator-summary-card .value { font-size: 24px; }
    .operator-table-toolbar { align-items: stretch; flex-direction: column; }
    .operator-search { max-width: none; width: 100%; }
    .op-header { padding: 14px 14px 12px; }
    .op-header h2 { font-size: 20px; }
    .loc-code { font-size: 28px; letter-spacing: 1px; }
    .loc-sub  { font-size: 12px; }
    .loc-header { padding: 10px 14px; }
    .item-row { padding: 10px 14px; gap: 8px; }
    .item-name { font-size: 15px; }
    .qty-box { font-size: 18px; min-width: 68px; padding: 6px 10px; }
    #scanInput { height: 48px; font-size: 15px; }
    #btnCamera { width: 40px; height: 40px; font-size: 14px; }
    #btnScan   { height: 40px; font-size: 13px; padding: 0 12px; }
    #scanInput { height: 40px; font-size: 14px; }
    #scanBar   { padding: 8px 12px; }
    #btnQuickConfirm { height: 48px; padding: 0 14px; font-size: 14px; }
    .container-fluid { padding-left: 8px !important; padding-right: 8px !important; }
    .cap-bar { width: 70px; }
}

/* ── Tablet portrait (576–767px) ────────────────────────────── */
@media (min-width: 576px) and (max-width: 767px) {
    .operator-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .operator-table-toolbar { align-items: stretch; flex-direction: column; }
    .operator-search { max-width: none; width: 100%; }
    .loc-code { font-size: 32px; }
    .item-name { font-size: 16px; }
    #scanBar { padding-left: 14px; padding-right: 14px; }
    .scan-controls { grid-template-columns: 52px minmax(0, 1fr) 88px; }
    #btnScan { padding-left: 14px; padding-right: 14px; }
}
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-3" id="mainContent">

    {{-- ── Nav + Filter ────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-3" style="gap:8px;flex-wrap:wrap;">

        {{-- Filter toggle kiri --}}
        <div class="btn-group" role="group">
            <a href="{{ route('putaway.operator') }}"
               class="btn btn-sm btn-avian-filter {{ !$allActive && !$hasDateFilter ? 'active' : '' }}">
                <i class="fas fa-calendar-day mr-1"></i>Hari Ini
                @if(!$allActive && !$hasDateFilter && $items->isNotEmpty())
                    <span class="badge ml-1" style="background:rgba(255,255,255,.3);color:#fff;font-size:11px;">{{ $items->count() }}</span>
                @endif
            </a>
            <a href="{{ route('putaway.operator', ['all_active' => 1]) }}"
               class="btn btn-sm btn-avian-filter {{ $allActive ? 'active' : '' }}">
                <i class="fas fa-list mr-1"></i>Semua SJ
                @if($allActive && $items->isNotEmpty())
                    <span class="badge ml-1" style="background:rgba(255,255,255,.3);color:#fff;font-size:11px;">{{ $items->count() }}</span>
                @endif
            </a>
            <button type="button" id="btnToggleDateFilter"
                    class="btn btn-sm btn-avian-filter {{ $hasDateFilter ? 'active' : '' }}">
                <i class="fas fa-calendar-alt mr-1"></i>Pilih Tanggal
                @if($hasDateFilter && $items->isNotEmpty())
                    <span class="badge ml-1" style="background:rgba(255,255,255,.3);color:#fff;font-size:11px;">{{ $items->count() }}</span>
                @endif
            </button>
        </div>

        {{-- Nav kanan --}}
        <div style="display:flex;gap:6px;flex-shrink:0;">
            <a href="{{ route('putaway.queue') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-list mr-1"></i><span class="d-none d-sm-inline">Tampilan Lengkap</span>
            </a>
            <a href="{{ route('putaway.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i><span class="d-none d-sm-inline">Kembali</span>
            </a>
        </div>
    </div>

    {{-- ── Date filter form (collapsible) ─────────────────────────────── --}}
    <div id="dateFilterForm" class="{{ $hasDateFilter ? '' : 'd-none' }} mb-3">
        <form method="GET" action="{{ route('putaway.operator') }}"
              class="d-flex align-items-center flex-wrap"
              style="gap:8px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:10px 14px;">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;flex:1;">
                <label class="mb-0 text-muted" style="font-size:13px;white-space:nowrap;">
                    <i class="fas fa-calendar mr-1"></i>Dari
                </label>
                <input type="date" name="start_date" class="form-control form-control-sm"
                       style="width:150px;" value="{{ $startDate }}">
                <label class="mb-0 text-muted" style="font-size:13px;white-space:nowrap;">s/d</label>
                <input type="date" name="end_date" class="form-control form-control-sm"
                       style="width:150px;" value="{{ $endDate }}">
            </div>
            <button type="submit" class="btn btn-sm" style="background:#0d8564;color:#fff;border-color:#0d8564;white-space:nowrap;">
                <i class="fas fa-filter mr-1"></i>Filter
            </button>
            <a href="{{ route('putaway.operator') }}" class="btn btn-sm btn-outline-secondary" style="white-space:nowrap;">
                <i class="fas fa-times mr-1"></i>Reset
            </a>
        </form>
    </div>

    <div class="operator-summary-grid">
        <div class="operator-summary-card">
            <div>
                <div class="label">SJ Aktif</div>
                <div><span class="value">{{ number_format($operatorSummary['sj_count'] ?? 0) }}</span><span class="unit">SJ</span></div>
            </div>
            <div class="icon"><i class="fas fa-file-alt"></i></div>
        </div>
        <div class="operator-summary-card">
            <div>
                <div class="label">Barang Masuk</div>
                <div><span class="value">{{ number_format($operatorSummary['total_lines'] ?? 0) }}</span><span class="unit">item</span></div>
                <div class="note">{{ number_format($operatorSummary['total_qty'] ?? 0) }} qty total</div>
            </div>
            <div class="icon"><i class="fas fa-boxes"></i></div>
        </div>
        <div class="operator-summary-card">
            <div>
                <div class="label">Sudah Put-Away</div>
                <div><span class="value">{{ number_format($operatorSummary['completed_lines'] ?? 0) }}</span><span class="unit">item</span></div>
            </div>
            <div class="icon"><i class="fas fa-check"></i></div>
        </div>
        <div class="operator-summary-card">
            <div>
                <div class="label">Belum Put-Away</div>
                <div><span class="value" id="summaryWaiting">{{ number_format($operatorSummary['waiting_lines'] ?? $items->count()) }}</span><span class="unit">item</span></div>
            </div>
            <div class="icon"><i class="fas fa-dolly"></i></div>
        </div>
    </div>

    @if($items->isEmpty())

        <div class="all-done-screen">
            @if($otherActiveCount > 0)
                {{-- Tidak ada SJ hari ini tapi ada SJ lain yang aktif --}}
                <i class="fas fa-calendar-times fa-4x text-secondary mb-3" style="display:block;"></i>
                <h3 class="font-weight-bold">Tidak ada SJ untuk hari ini</h3>
                <p class="text-muted">Tapi ada <strong>{{ $otherActiveCount }} item</strong> dari SJ tanggal lain yang belum selesai.</p>
                <a href="{{ route('putaway.operator', ['all_active' => 1]) }}"
                   class="btn btn-primary btn-lg mt-2 mr-2">
                    <i class="fas fa-list mr-1"></i> Tampilkan Semua SJ Aktif
                </a>
                <a href="{{ route('putaway.index') }}" class="btn btn-outline-secondary btn-lg mt-2">
                    <i class="fas fa-home mr-1"></i> Kembali
                </a>
            @else
                {{-- Benar-benar semua selesai --}}
                <i class="fas fa-check-circle fa-5x text-success mb-3" style="display:block;"></i>
                <h3 class="text-success font-weight-bold">Semua selesai!</h3>
                <p class="text-muted">Tidak ada item yang perlu di-put-away.</p>
                <a href="{{ route('putaway.index') }}" class="btn btn-primary btn-lg mt-2">
                    <i class="fas fa-home mr-1"></i> Beranda Put-Away
                </a>
            @endif
        </div>

    @else

        @if($allActive)
            <div class="alert alert-warning border-0 py-2 mb-3" style="font-size:13px;">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong>Semua SJ aktif</strong> termasuk SJ dari tanggal sebelumnya.
                <a href="{{ route('putaway.operator') }}" class="ml-2 alert-link">Tampilkan hari ini saja</a>
            </div>
        @endif

        {{-- Tabel ringkas per item, tetap dikelompokkan sesuai urutan lokasi --}}
        <div class="operator-table-card" id="itemList">
            <div class="operator-table-toolbar">
                <div class="operator-search" style="max-width:100%;">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="operatorSearchInput"
                               placeholder="Cari SJ / SKU / item / cell...">
                    </div>
                </div>
            </div>
            <div id="searchSpinner" style="display:none;padding:40px 0;text-align:center;">
                <i class="fas fa-spinner fa-spin" style="font-size:24px;color:#0d8564;"></i>
                <div style="font-size:12px;color:#6c757d;margin-top:8px;">Mencari...</div>
            </div>
            <div class="operator-table-wrap" id="tableWrap">
                <table class="table table-sm operator-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="width:220px;">SJ</th>
                            <th>Item / SKU</th>
                            <th style="width:90px;">Qty</th>
                        </tr>
                    </thead>
                    @php
                        $groups = $items->groupBy('cell_id');
                        $rowNo = 1;
                    @endphp

                    @foreach($groups as $cellId => $groupItems)
                        @php
                            $cell = $groupItems->first()->cell;
                            $rack = $cell?->rack;

                            if ($cell && !is_null($cell->blok)) {
                                $locCode = $cell->physical_code;
                                $parts   = array_filter([
                                    'Blok '  . $cell->blok,
                                    'Grup '  . strtoupper($cell->grup ?? ''),
                                    !is_null($cell->kolom) ? 'Kolom ' . $cell->kolom : null,
                                    !is_null($cell->baris) ? 'Baris ' . $cell->baris  : null,
                                ]);
                                $locSub  = implode(' - ', $parts);
                            } elseif ($rack) {
                                $lvLetter = $cell?->level ? chr(64 + $cell->level) : '?';
                                $lvCol    = $cell?->column ?? 1;
                                $locCode  = $rack->code . '-' . $lvLetter
                                          . ($rack->total_columns > 1 ? $lvCol : '');
                                $locSub   = 'Rak ' . $rack->code . ' - Level ' . $lvLetter
                                          . ($rack->total_columns > 1 ? ' - Kolom ' . $lvCol : '');
                            } else {
                                $locCode = $cell?->code ?? '-';
                                $locSub  = '';
                            }

                            $zone     = $cell?->zone_category ?? null;
                            $capUsed  = $cell?->capacity_used ?? 0;
                            $capMax   = $cell?->capacity_max  ?? 100;
                            $capPct   = $capMax > 0 ? min(100, round($capUsed / $capMax * 100)) : 0;
                            $capColor = $capPct >= 80 ? '#ff6b6b' : ($capPct >= 40 ? '#6c757d' : '#a8e6cf');
                            $statusLabel = match($cell?->status ?? 'available') {
                                'available' => 'Tersedia',
                                'partial'   => 'Sebagian terisi',
                                'full'      => 'Penuh',
                                default     => 'Diblokir',
                            };
                            $statusBadge = match($cell?->status ?? 'available') {
                                'available' => 'success',
                                'partial'   => 'warning',
                                'full'      => 'danger',
                                default     => 'secondary',
                            };
                            $groupSearch = strtolower(trim($locCode . ' ' . $locSub . ' ' . $statusLabel . ' ' . $zone));
                        @endphp

                        <tbody class="loc-group" id="group-{{ $cellId }}" data-cell-id="{{ $cellId }}" data-location-search="{{ e($groupSearch) }}">
                            <tr class="cell-row">
                                <td colspan="4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <span style="background:#0d8564;color:#fff;border-radius:5px;padding:3px 10px;font-size:15px;font-weight:700;letter-spacing:1px;">{{ $locCode }}</span>
                                            @if($locSub)
                                                <span style="color:#43566c;font-size:13px;margin-left:10px;">{{ $locSub }}</span>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center" style="gap:8px;">
                                            <span class="badge badge-{{ $statusBadge }}" style="font-size:11px;">{{ $statusLabel }}</span>
                                            <span style="font-size:12px;color:#6c757d;"><i class="fas fa-box mr-1"></i>{{ $groupItems->count() }} item</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            @foreach($groupItems as $item)
                                @php
                                    $itm  = $item->inboundOrderItem?->item;
                                    $qty  = $item->quantity;
                                    $unit = $itm?->unit?->code ?? 'pcs';
                                    $do   = $item->gaRecommendation?->inboundOrder?->do_number ?? '-';
                                    $searchText = strtolower(trim(implode(' ', [
                                        $do,
                                        $itm?->sku ?? '',
                                        $itm?->name ?? '',
                                        $locCode,
                                        $locSub,
                                        $unit,
                                    ])));
                                @endphp
                                <tr class="item-row" id="row-{{ $item->id }}" data-ga-id="{{ $item->id }}" data-search="{{ e($searchText) }}">
                                    <td class="text-center">{{ $rowNo++ }}</td>
                                    <td><span class="operator-do">{{ $do }}</span></td>
                                    <td>
                                        <div class="operator-row-item">{{ $itm?->name ?? '-' }}</div>
                                        <div class="operator-row-meta">SKU: {{ $itm?->sku ?? '-' }}</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="qty-inline">{{ $qty }}</span>
                                        <span class="unit-inline">{{ $unit }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <div class="no-filter-result" id="operatorNoFilterResult">
                <i class="fas fa-search fa-2x mb-2 d-block"></i>
                Tidak ada item yang cocok dengan pencarian.
            </div>
        </div>

        {{-- Spacer so the last card can scroll fully above the fixed scan bar --}}
        <div id="scrollSpacer" style="height:200px;"></div>

    @endif
</div>

{{-- ── Fixed scan bar ─────────────────────────────────────────────────── --}}
@if($items->isNotEmpty())
<div id="scanBar">
    <div class="scan-controls">
        <button type="button" id="btnCamera" class="btn btn-outline-secondary" title="Scan dengan kamera">
            <i class="fas fa-camera"></i>
        </button>
        <input type="text" id="scanInput" class="form-control"
            placeholder="Scan QR atau ketik kode cell..."
            autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
            inputmode="text">
        <button type="button" id="btnScan" class="btn" style="background:#0d8564;color:#fff;border-color:#0d8564;">
            <i class="fas fa-search mr-1"></i> Cari
        </button>
        <div class="auto-toggle-wrap" title="Auto konfirmasi: scan langsung simpan tanpa muncul modal">
            <label for="btnQuickConfirm">Auto</label>
            <div class="custom-control custom-switch" style="padding-left:2.5rem;">
                <input type="checkbox" class="custom-control-input" id="btnQuickConfirm">
                <label class="custom-control-label" for="btnQuickConfirm"></label>
            </div>
        </div>
    </div>
    <div id="scanMsg" class="text-muted"></div>
</div>
@endif

{{-- ── Confirm modal ───────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalConfirm" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header py-3" style="background:#0d8564;color:#fff;border:0;">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-check-circle mr-2"></i>Konfirmasi Put-Away
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;">&times;</button>
            </div>
            <div class="modal-body p-0" id="confirmBody">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                </div>
            </div>
            <div class="modal-footer py-2" style="border:0;background:#f8f9fa;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Batal
                </button>
                <button type="button" id="btnDoConfirm" class="btn" style="background:#0d8564;color:#fff;border-color:#0d8564;font-weight:700;" disabled>
                    <i class="fas fa-check mr-1"></i>
                    Konfirmasi (<span id="confirmCount">0</span> item)
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Camera modal ─────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCamera" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">
                    <i class="fas fa-camera mr-1"></i> Scan dengan Kamera
                    <small class="text-muted ml-1 d-none d-md-inline">QR & Barcode 1D/2D</small>
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div id="camReader" style="width:100%;"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('adminlte/plugins/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('adminlte/plugins/html5-qrcode/html5-qrcode.min.js') }}"></script>
<script>
var batchScanUrl    = '{{ route('putaway.batch-scan') }}';
var batchConfirmUrl = '{{ route('putaway.batch-confirm') }}';
var csrfToken       = $('meta[name="csrf-token"]').attr('content');
var pendingItems    = null;
var totalRemaining  = {{ $items->count() }};
var filterAllActive = {{ $allActive ? 1 : 0 }};
var filterStartDate = '{{ $startDate }}';
var filterEndDate   = '{{ $endDate }}';
var quickConfirm    = localStorage.getItem('putaway_quick_confirm') === '1';

function applyOperatorSearch() {
    var term = ($('#operatorSearchInput').val() || '').toLowerCase().trim();
    var visibleRows = 0;

    $('#itemList .loc-group').each(function() {
        var $group = $(this);
        var groupText = String($group.data('location-search') || '').toLowerCase();
        var groupMatches = !term || groupText.indexOf(term) !== -1;
        var groupVisibleRows = 0;

        $group.find('.item-row').each(function() {
            var rowText = String($(this).data('search') || '').toLowerCase();
            var rowVisible = groupMatches || rowText.indexOf(term) !== -1;
            $(this).toggle(rowVisible);
            if (rowVisible) groupVisibleRows++;
        });

        $group.find('.cell-row').toggle(groupVisibleRows > 0);
        visibleRows += groupVisibleRows;
    });

    $('#operatorNoFilterResult').toggle(term !== '' && visibleRows === 0);
}

var searchDebounce = null;
$('#operatorSearchInput').on('input', function() {
    clearTimeout(searchDebounce);
    $('#tableWrap').hide();
    $('#searchSpinner').show();
    searchDebounce = setTimeout(function() {
        applyOperatorSearch();
        $('#searchSpinner').hide();
        $('#tableWrap').show();
    }, 350);
});

// ── Helpers ───────────────────────────────────────────────────────────────

function setScanMsg(msg, cls) {
    $('#scanMsg').attr('class', 'mt-1 ' + (cls || 'text-muted')).html(msg);
}

function focusScan() {
    setTimeout(function() { $('#scanInput').val('').focus(); }, 300);
}

// ── Scan trigger ─────────────────────────────────────────────────────────

$('#scanInput').on('keydown', function(e) {
    if (e.key === 'Enter') doScan($(this).val().trim());
});
$('#btnScan').on('click', function() { doScan($('#scanInput').val().trim()); });

function resetScanMsg() {
    if (quickConfirm) {
        setScanMsg('Auto konfirmasi aktif', 'text-muted');
    } else {
        setScanMsg('', 'text-muted');
    }
}

function doScan(code) {
    if (!code) {
        setScanMsg('<i class="fas fa-exclamation-triangle mr-1"></i> Kode kosong.', 'text-warning');
        return;
    }
    // Strip full URL from camera scan (e.g. http://host/c/1-A-1 → 1-A-1)
    if (code.indexOf('/c/') !== -1) {
        code = code.split('/c/').pop().replace(/\/+$/, '').trim();
        $('#scanInput').val(code);
    }
    setScanMsg('<i class="fas fa-spinner fa-spin mr-1"></i> Mencari...', 'text-muted');
    $('#confirmBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#btnDoConfirm').prop('disabled', true);

    $.getJSON(batchScanUrl, { qr_code: code, override: 0, all_active: filterAllActive, start_date: filterStartDate, end_date: filterEndDate })
        .done(function(res) {
            if (res.status !== 'found' || !res.items || !res.items.length) {
                setScanMsg(
                    '<i class="fas fa-times-circle mr-1"></i> ' +
                    (res.message || 'Tidak ada item aktif di cell ini.'),
                    'text-danger'
                );
                return;
            }
            pendingItems = res.items;

            // Cek apakah ada item yang perlu split kapasitas
            var hasSplit = res.items.some(function(i) { return i.requires_split && i.split_quantity > 0; });

            if (quickConfirm && !hasSplit) {
                // Quick confirm: langsung POST tanpa tampilkan modal
                doQuickConfirm(res);
            } else {
                // Normal flow: tampilkan modal konfirmasi
                if (quickConfirm && hasSplit) {
                    // Ada split — wajib manual, beri tahu operator
                    setScanMsg('<i class="fas fa-exclamation-circle mr-1"></i> Ada item dengan kapasitas sebagian — perlu konfirmasi manual.', 'text-warning');
                } else {
                    resetScanMsg();
                }
                buildConfirmModal(res);
                $('#modalConfirm').modal('show');
            }
        })
        .fail(function(xhr) {
            var msg = xhr.responseJSON?.message || 'Gagal. Coba scan ulang.';
            setScanMsg('<i class="fas fa-times-circle mr-1"></i> ' + msg, 'text-danger');
        });
}

function buildConfirmModal(res) {
    var html = '<div class="px-3 pt-3 pb-1">';
    html += '<div class="alert alert-success py-2 mb-3">'
          + '<i class="fas fa-map-marker-alt mr-1"></i>'
          + '<strong>Cell: ' + (res.display_code || '-') + '</strong>'
          + (res.display_rack !== '-' ? ' &nbsp;|&nbsp; Rak: ' + res.display_rack : '')
          + '</div>';

    html += '<div class="font-weight-bold mb-2" style="font-size:14px;">'
          + res.items.length + ' item akan dikonfirmasi:</div>';
    html += '<ul class="list-group list-group-flush mb-2">';

    res.items.forEach(function(item) {
        var splitNote = item.requires_split && item.split_quantity > 0
            ? '<div class="text-warning small"><i class="fas fa-info-circle mr-1"></i>Kapasitas sebagian: ' + item.primary_quantity + ' dari ' + item.quantity + ' item</div>'
            : '';
        var cellCode  = item.cell_code || item.ga_cell_code || '';
        var barisNote = cellCode
            ? '<div style="margin-top:4px;">'
              + '<span style="background:#0d8564;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;">'
              + '<i class="fas fa-map-marker-alt mr-1"></i>' + cellCode
              + '</span></div>'
            : '';
        html += '<li class="list-group-item d-flex justify-content-between align-items-start py-2 px-0">'
              + '<div>'
              +   '<div style="font-size:15px;font-weight:700;">' + (item.item_name || '-') + '</div>'
              +   '<div class="text-muted small">' + (item.item_sku || '-') + '</div>'
              +   '<div class="text-muted" style="font-size:11px;">DO: ' + (item.do_number || '-') + '</div>'
              +   barisNote
              +   splitNote
              + '</div>'
              + '<span class="badge badge-success ml-2 flex-shrink-0" style="font-size:17px;padding:8px 14px;font-weight:800;background:#0d8564;">'
              +   item.primary_quantity + ' <span style="font-size:11px;font-weight:400;">' + item.unit + '</span>'
              + '</span>'
              + '</li>';
    });

    html += '</ul></div>';
    $('#confirmBody').html(html);
    $('#confirmCount').text(res.items.length);
    $('#btnDoConfirm').prop('disabled', false);
}

// ── Shared: handle sukses setelah konfirmasi (modal maupun quick) ─────────

function handleConfirmSuccess(serverRes, confirmedItems) {
    var cellIds    = [...new Set(confirmedItems.map(function(i) { return i.cell_id; }))];
    var cellIdStrs = cellIds.map(String);

    // Cari lokasi BERIKUTNYA sebelum menghapus group
    var nextLocation = null;
    $('#itemList .loc-group').each(function() {
        var cid = String($(this).data('cell-id'));
        if (cellIdStrs.indexOf(cid) === -1) {
            nextLocation = $(this).find('.loc-code').first().text().trim();
            return false; // break
        }
    });

    // Hapus baris item yang sudah dikonfirmasi
    confirmedItems.forEach(function(item) {
        $('#row-' + item.ga_detail_id).fadeOut(200, function() { $(this).remove(); });
    });

    // Hapus group yang sudah kosong setelah baris hilang
    setTimeout(function() {
        cellIds.forEach(function(cid) {
            var grp = $('#group-' + cid);
            if (grp.find('.item-row').length === 0) {
                grp.addClass('removing');
                setTimeout(function() { grp.remove(); }, 320);
            }
        });
    }, 250);

    // Update counter dengan animasi bump
    var confirmed = serverRes.confirmed_count || confirmedItems.length;
    totalRemaining -= confirmed;
    totalRemaining = Math.max(totalRemaining, 0);
    $('#summaryWaiting').text(totalRemaining.toLocaleString('id-ID'));
    var $ctr = $('#itemCounter');
    if (totalRemaining <= 0) {
        $ctr.text('0 item menunggu').addClass('bumping');
        setTimeout(function() {
            $ctr.removeClass('bumping');
            showAllDone();
        }, 900);
    } else {
        $ctr.text(totalRemaining + ' item menunggu').addClass('bumping');
        setTimeout(function() { $ctr.removeClass('bumping'); }, 600);
    }

    // Toast: sukses + lokasi berikutnya
    Swal.fire({
        icon:              'success',
        toast:             true,
        position:          'top-end',
        showConfirmButton: false,
        timer:             6000,
        timerProgressBar:  true,
        title:             'Put-Away Berhasil',
        html:              confirmed + ' item berhasil disimpan.'
                           + (nextLocation ? '<br><b>Lanjut ke ' + nextLocation + '</b>' : ''),
    });
    resetScanMsg();
    pendingItems = null;
    applyOperatorSearch();
    focusScan();
}

// ── Quick confirm: langsung POST tanpa modal ──────────────────────────────

function doQuickConfirm(scanRes) {
    setScanMsg('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...', 'text-muted');

    var payload = scanRes.items.map(function(item) {
        return {
            cell_id:      item.cell_id,
            order_id:     item.order_id,
            detail_id:    item.detail_id,
            ga_detail_id: item.ga_detail_id || null,
            quantity:     item.primary_quantity,
            is_override:  0,
        };
    });

    $.ajax({
        url:  batchConfirmUrl,
        type: 'POST',
        data: { _token: csrfToken, items: payload },
        success: function(res) {
            if (res.status === 'success') {
                handleConfirmSuccess(res, pendingItems);
            } else {
                Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                resetScanMsg();
                pendingItems = null;
            }
        },
        error: function(xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Gagal menyimpan. Coba lagi.', 'error');
            resetScanMsg();
            pendingItems = null;
        }
    });
}

// ── Quick confirm toggle ──────────────────────────────────────────────────

function applyQuickConfirmUI() {
    $('#btnQuickConfirm').prop('checked', quickConfirm);
    $('#scanBar').toggleClass('qc-active', quickConfirm);
    resetScanMsg();
}

$('#btnQuickConfirm').on('change', function() {
    quickConfirm = $(this).is(':checked');
    localStorage.setItem('putaway_quick_confirm', quickConfirm ? '1' : '0');
    applyQuickConfirmUI();
    focusScan();
});

// ── Confirm submit ────────────────────────────────────────────────────────

$('#btnDoConfirm').on('click', function() {
    if (!pendingItems) return;
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...');

    var payload = pendingItems.map(function(item) {
        return {
            cell_id:      item.cell_id,
            order_id:     item.order_id,
            detail_id:    item.detail_id,
            ga_detail_id: item.ga_detail_id || null,
            quantity:     item.primary_quantity,
            is_override:  0,
        };
    });

    $.ajax({
        url:  batchConfirmUrl,
        type: 'POST',
        data: { _token: csrfToken, items: payload },
        success: function(res) {
            $('#modalConfirm').modal('hide');

            if (res.status === 'success') {
                handleConfirmSuccess(res, pendingItems);
            } else {
                Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                btn.prop('disabled', false)
                   .html('<i class="fas fa-check mr-1"></i>Konfirmasi (<span id="confirmCount">' + (pendingItems ? pendingItems.length : 0) + '</span> item)');
            }
        },
        error: function(xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Gagal menyimpan. Coba lagi.', 'error');
            btn.prop('disabled', false)
               .html('<i class="fas fa-check mr-1"></i>Konfirmasi (<span id="confirmCount">' + (pendingItems ? pendingItems.length : 0) + '</span> item)');
        }
    });
});

// ── All done ──────────────────────────────────────────────────────────────

function showAllDone() {
    $('#itemList').html(
        '<div class="all-done-screen">' +
        '<i class="fas fa-check-circle fa-5x text-success mb-3" style="display:block;"></i>' +
        '<h3 class="text-success font-weight-bold">Semua selesai!</h3>' +
        '<p class="text-muted">Semua item berhasil di-put-away.</p>' +
        '<a href="{{ route('putaway.operator') }}" class="btn btn-primary btn-lg mt-2">' +
        '<i class="fas fa-sync-alt mr-1"></i> Muat Ulang</a>' +
        '</div>'
    );
    $('#itemCounter').text('Semua selesai!');
    $('#scanBar').fadeOut();
}

// ── Camera scanner ─────────────────────────────────────────────────────────

var html5QrCode  = null;
var cameraActive = false; // true only after .start() resolves

// Idempotent: safe to call multiple times or when camera was never started
function stopCameraAndClean() {
    var inst = html5QrCode;
    html5QrCode  = null;
    cameraActive = false;
    var p = (inst && cameraActive !== false)
        ? inst.stop().catch(function() {})
        : Promise.resolve();
    return p.finally(function() {
        var el = document.getElementById('camReader');
        if (el) el.innerHTML = '';
    });
}

$('#btnCamera').on('click', function() {
    $('#modalCamera').modal('show');
});

$('#modalCamera').on('shown.bs.modal', function() {
    html5QrCode = new Html5Qrcode('camReader');
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        function(decoded) {
            // QR decoded — stop camera then process
            var inst = html5QrCode;
            html5QrCode  = null;
            cameraActive = false;
            inst.stop().catch(function() {}).finally(function() {
                document.getElementById('camReader').innerHTML = '';
                $('#modalCamera').modal('hide');
                $('#scanInput').val(decoded);
                doScan(decoded);
            });
        }
    ).then(function() {
        cameraActive = true;
    }).catch(function() {
        // Camera access denied or device error
        html5QrCode  = null;
        cameraActive = false;
        document.getElementById('camReader').innerHTML = '';
        setScanMsg('<i class="fas fa-times-circle mr-1"></i>Kamera tidak dapat diakses.', 'text-danger');
        $('#modalCamera').modal('hide');
    });
});

// User closes modal manually (X button or backdrop click)
$('#modalCamera').on('hide.bs.modal', function() {
    stopCameraAndClean();
});

$('#modalConfirm').on('hide.bs.modal', function() {
    if (document.activeElement) document.activeElement.blur();
});
$('#modalConfirm').on('hidden.bs.modal', function() {
    resetScanMsg();
    focusScan();
});

// Size the spacer element to match the scan bar height so the last card
// is always fully scrollable above the fixed scan bar.
// Uses a real DOM element (not padding) so AdminLTE cannot reset it.
function adjustSpacer() {
    var bar    = document.getElementById('scanBar');
    var barH   = bar ? bar.offsetHeight : 0;
    var spacer = document.getElementById('scrollSpacer');
    if (spacer) spacer.style.height = Math.max(200, barH + 80) + 'px';
}

(function() {
    var bar = document.getElementById('scanBar');
    if (!bar) return;
    adjustSpacer();
    if (window.ResizeObserver) {
        new ResizeObserver(adjustSpacer).observe(bar);
    }
    window.addEventListener('resize', adjustSpacer);
    [100, 300, 700, 1500].forEach(function(t) { setTimeout(adjustSpacer, t); });
})();

$('#btnToggleDateFilter').on('click', function() {
    $('#dateFilterForm').toggleClass('d-none');
    if (!$('#dateFilterForm').hasClass('d-none')) {
        $('#dateFilterForm input[name="start_date"]').focus();
    }
});

$(document).ready(function() {
    applyQuickConfirmUI();
    $('#scanInput').focus();
});
</script>
@endpush
