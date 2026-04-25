@extends('layouts.adminlte')

@section('title', 'Detail DO: ' . $order->do_number)

@push('styles')
<style>
/* ── Stepper ── */
.wms-stepper { display:flex; align-items:flex-start; margin-bottom:0; padding:0; list-style:none; }
.wms-stepper li { flex:1; text-align:center; position:relative; }
.wms-stepper li:not(:last-child)::after {
    content:''; position:absolute; top:18px; left:50%; width:100%; height:3px;
    background:#dee2e6; z-index:0;
}
.wms-stepper li.done::after  { background:#28a745; }
.wms-stepper li.active::after{ background:#dee2e6; }
.step-circle {
    width:36px; height:36px; border-radius:50%; display:inline-flex;
    align-items:center; justify-content:center; font-size:13px; font-weight:700;
    position:relative; z-index:1; border:3px solid #dee2e6;
    background:#fff; color:#adb5bd;
}
.wms-stepper li.done  .step-circle { background:#28a745; border-color:#28a745; color:#fff; }
.wms-stepper li.active .step-circle{ background:#007bff; border-color:#007bff; color:#fff; }
.step-label { font-size:10.5px; margin-top:5px; color:#6c757d; font-weight:500; }
.wms-stepper li.done  .step-label  { color:#28a745; }
.wms-stepper li.active .step-label { color:#007bff; font-weight:700; }

/* ── Info grid ── */
.info-block small { font-size:10.5px; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; }
.info-block .val  { font-size:14px; font-weight:600; color:#212529; margin-top:2px; }

/* ── GA Fitness bars ── */
.fitness-bar-wrap { height:8px; background:#e9ecef; border-radius:4px; overflow:hidden; margin-top:3px; }
.fitness-bar       { height:100%; border-radius:4px; }

/* ── Qty diff badge ── */
.diff-badge { font-size:10px; padding:1px 5px; border-radius:10px; }

/* ── Action btn group ── */
.action-toolbar .btn { font-size:13px; padding:5px 14px; }
</style>
@endpush

@section('content')
@php
    /* ─── Status meta ─── */
    $statusMeta = [
        'draft'       => ['secondary', 'Draft',                  'fas fa-inbox'],
        'processing'  => ['warning',   'Processing GA',          'fas fa-spinner fa-spin'],
        'recommended' => ['info',      'Rekomendasi GA Selesai', 'fas fa-lightbulb'],
        'put_away'    => ['primary',   'Sedang Put-Away',        'fas fa-dolly'],
        'completed'   => ['success',   'Selesai',                'fas fa-check-circle'],
        'cancelled'   => ['danger',    'Dibatalkan',             'fas fa-ban'],
    ];
    [$sCls, $sLabel, $sIcon] = $statusMeta[$order->status] ?? ['secondary', ucfirst($order->status), 'fas fa-circle'];

    /* ─── Stepper mapping ─── */
    $steps = ['draft','recommended','put_away','completed'];
    $stepIdx = array_search($order->status, $steps);
    if ($order->status === 'processing') $stepIdx = 1;
    $stepIdx = $stepIdx === false ? 0 : $stepIdx;

    /* ─── Supervisor/admin check ─── */
    $isSupervisor = auth()->user()->isAdmin() || auth()->user()->isSupervisor();

    /* ─── Item stats ─── */
    $totalOrdered  = $order->items->sum('quantity_ordered');
    $totalReceived = $order->items->sum('quantity_received');
    $totalPutAway  = $order->items->where('status','put_away')->count();
    $totalItems    = $order->items->count();
    $progressPct   = $totalItems > 0 ? round($totalPutAway / $totalItems * 100) : 0;
@endphp

<div class="container-fluid pb-4">

{{-- ══════════════════════════════════════════════════════
     1. BREADCRUMB + TOOLBAR
══════════════════════════════════════════════════════ --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-file-alt text-primary mr-2"></i>
            Detail Inbound Order
        </h5>
        <small class="text-muted">
            <a href="{{ route('inbound.orders.index') }}">Penerimaan Barang</a>
            <i class="fas fa-chevron-right mx-1" style="font-size:9px;"></i>
            {{ $order->do_number }}
        </small>
    </div>

    {{-- ACTION TOOLBAR --}}
    <div class="action-toolbar d-flex flex-wrap" style="gap:6px;">

        {{-- DRAFT: Edit --}}
        @if ($order->status === 'draft')
        <a href="{{ route('inbound.orders.edit', $order->id) }}"
           class="btn btn-sm btn-outline-warning" title="Edit header order">
            <i class="fas fa-edit mr-1"></i>Edit
        </a>
        @endif

        {{-- DRAFT: Konfirmasi Qty Fisik --}}
        @if ($order->status === 'draft')
        <button class="btn btn-sm btn-secondary" id="btnConfirmQty"
            data-url="{{ route('inbound.orders.confirm-qty', $order->id) }}">
            <i class="fas fa-clipboard-check mr-1"></i>Konfirmasi Qty Fisik
        </button>
        @endif

        {{-- DRAFT: Jalankan GA (operator/supervisor/admin — role middleware handles access) --}}
        @if ($order->status === 'draft')
        <button class="btn btn-sm btn-primary" id="btnProcessGA"
            data-url="{{ route('inbound.orders.process-ga', $order->id) }}">
            <i class="fas fa-dna mr-1"></i>Jalankan GA
        </button>
        @endif

        {{-- RECOMMENDED + pending_review: Supervisor accept / reject --}}
        @if ($order->status === 'recommended' && $latestGa && $latestGa->status === 'pending_review' && $isSupervisor)
        <button class="btn btn-sm btn-success" id="btnAcceptGa"
            data-url="{{ route('inbound.orders.ga.accept', [$order->id, $latestGa->id]) }}">
            <i class="fas fa-check-circle mr-1"></i>Setujui Rekomendasi
        </button>
        <button class="btn btn-sm btn-outline-danger" id="btnRejectGa"
            data-url="{{ route('inbound.orders.ga.reject', [$order->id, $latestGa->id]) }}">
            <i class="fas fa-redo mr-1"></i>Tolak &amp; Ulangi GA
        </button>
        @endif

        {{-- PUT_AWAY: Lanjutkan --}}
        @if ($order->status === 'put_away' ||
             ($order->status === 'recommended' && $latestGa && $latestGa->status === 'accepted'))
        <a href="{{ route('putaway.show', $order->id) }}" class="btn btn-sm btn-primary">
            <i class="fas fa-dolly mr-1"></i>
            {{ $order->status === 'put_away' ? 'Lanjutkan Put-Away' : 'Mulai Put-Away' }}
        </a>
        @endif

        {{-- PRINT --}}
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Print halaman ini">
            <i class="fas fa-print mr-1"></i>Print
        </button>

        <a href="{{ route('inbound.orders.index') }}" class="btn btn-sm btn-light border">
            <i class="fas fa-arrow-left mr-1"></i>Kembali
        </a>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     2. STATUS STEPPER
══════════════════════════════════════════════════════ --}}
<div class="card mb-3">
    <div class="card-body py-3 px-4">
        <ul class="wms-stepper">
            @php
                $stepDefs = [
                    ['label'=>'DO Diterima',    'icon'=>'fas fa-inbox'],
                    ['label'=>'Review GA',      'icon'=>'fas fa-dna'],
                    ['label'=>'Put-Away',       'icon'=>'fas fa-dolly'],
                    ['label'=>'Selesai',        'icon'=>'fas fa-check-circle'],
                ];
            @endphp
            @foreach ($stepDefs as $si => $step)
            @php
                $cls = $si < $stepIdx ? 'done' : ($si === $stepIdx ? 'active' : '');
            @endphp
            <li class="{{ $cls }}">
                <div class="step-circle"><i class="{{ $step['icon'] }}"></i></div>
                <div class="step-label">{{ $step['label'] }}</div>
            </li>
            @endforeach
        </ul>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     3. INFO HEADER
══════════════════════════════════════════════════════ --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold">
            <i class="fas fa-info-circle mr-1 text-primary"></i>
            Informasi Delivery Order
        </span>
        <span class="badge badge-{{ $sCls }} px-3 py-1" style="font-size:12px;">
            <i class="{{ $sIcon }} mr-1"></i>{{ $sLabel }}
        </span>
    </div>
    <div class="card-body py-3">
        <div class="row">
            <div class="col-6 col-md-3 info-block mb-3">
                <small>No. Surat Jalan (DO)</small>
                <div class="val">{{ $order->do_number }}</div>
            </div>
            <div class="col-6 col-md-2 info-block mb-3">
                <small>Tgl Surat Jalan</small>
                <div class="val">{{ $order->do_date?->format('d M Y') ?? '-' }}</div>
            </div>
            <div class="col-6 col-md-2 info-block mb-3">
                <small>Diterima Tgl</small>
                <div class="val">{{ $order->received_at?->format('d M Y, H:i') ?? '-' }}</div>
            </div>
            <div class="col-6 col-md-2 info-block mb-3">
                <small>Diterima Oleh</small>
                <div class="val">{{ $order->receivedBy->name ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3 info-block mb-3">
                <small>Gudang Tujuan</small>
                <div class="val">
                    <i class="fas fa-warehouse text-secondary mr-1"></i>
                    {{ $order->warehouse->name ?? '-' }}
                </div>
            </div>

            @if ($order->supplier)
            <div class="col-6 col-md-3 info-block mb-3">
                <small>Supplier</small>
                <div class="val">
                    <i class="fas fa-industry text-secondary mr-1"></i>
                    {{ $order->supplier->name }}
                </div>
            </div>
            @endif

            @if ($order->erp_reference)
            <div class="col-6 col-md-2 info-block mb-3">
                <small>Referensi ERP</small>
                <div class="val">{{ $order->erp_reference }}</div>
            </div>
            @endif

            @if ($order->ref_doc_spk)
            <div class="col-6 col-md-2 info-block mb-3">
                <small>No. SPK</small>
                <div class="val">{{ $order->ref_doc_spk }}</div>
            </div>
            @endif

            @if ($order->batch_header)
            <div class="col-6 col-md-2 info-block mb-3">
                <small>Batch Header</small>
                <div class="val">{{ $order->batch_header }}</div>
            </div>
            @endif
        </div>

        @if ($order->notes)
        <div class="alert alert-light border mb-0 py-2">
            <i class="fas fa-sticky-note text-warning mr-1"></i>
            <strong>Catatan:</strong> {{ $order->notes }}
        </div>
        @endif
    </div>

    {{-- Progress bar put-away (tampil jika sudah masuk put_away/completed) --}}
    @if (in_array($order->status, ['put_away','completed']))
    <div class="card-footer py-2">
        <div class="d-flex justify-content-between mb-1">
            <small class="text-muted">Progress Put-Away</small>
            <small class="font-weight-bold">{{ $totalPutAway }} / {{ $totalItems }} item</small>
        </div>
        <div class="progress" style="height:8px;">
            <div class="progress-bar {{ $progressPct == 100 ? 'bg-success' : 'bg-primary' }} progress-bar-striped"
                style="width:{{ $progressPct }}%"></div>
        </div>
        <small class="text-muted">{{ $progressPct }}% selesai</small>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════
     4. SUMMARY STATS (cards kecil)
══════════════════════════════════════════════════════ --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner">
                <h4>{{ $totalItems }}</h4>
                <p>Total SKU</p>
            </div>
            <div class="icon"><i class="fas fa-list"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-warning mb-0">
            <div class="inner">
                <h4>{{ $totalOrdered }}</h4>
                <p>Qty Dipesan</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner">
                <h4>{{ $totalReceived }}</h4>
                <p>Qty Diterima Fisik</p>
            </div>
            <div class="icon"><i class="fas fa-boxes"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box {{ $totalPutAway == $totalItems && $totalItems > 0 ? 'bg-success' : 'bg-secondary' }} mb-0">
            <div class="inner">
                <h4>{{ $totalPutAway }}</h4>
                <p>Item Sudah Put-Away</p>
            </div>
            <div class="icon"><i class="fas fa-dolly-flatbed"></i></div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     5. TABEL ITEM
══════════════════════════════════════════════════════ --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold">
            <i class="fas fa-boxes mr-1 text-primary"></i>
            Detail Item
            <span class="badge badge-primary ml-1">{{ $totalItems }}</span>
        </span>
        @if ($order->status === 'draft')
        <span class="badge badge-warning">
            <i class="fas fa-pencil-alt mr-1"></i>Mode Input Qty — isi jumlah barang yang diterima secara fisik
        </span>
        @endif
    </div>
    <div class="card-body p-0">
        @if ($order->items->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-2 d-block"></i>
                Tidak ada item dalam order ini.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm mb-0" id="tblItems">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="36">#</th>
                        <th width="220">SKU / Nama Item</th>
                        <th width="130">Kategori</th>
                        <th class="text-center" width="60">Satuan</th>
                        <th width="130">LPN / Barcode</th>
                        <th class="text-center" width="90">
                            <i class="fas fa-file-invoice mr-1 text-muted" title="Qty dari surat jalan"></i>
                            Qty DO
                        </th>
                        <th class="text-center" width="120">
                            <i class="fas fa-hand-paper mr-1 text-warning" title="Qty fisik di dock"></i>
                            Qty Terima
                            @if ($order->status === 'draft')
                            <i class="fas fa-edit text-warning ml-1" title="Bisa diubah"></i>
                            @endif
                        </th>
                        <th class="text-center" width="70">Selisih</th>
                        <th class="text-center" width="110">Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $i => $itm)
                    @php
                        $iStatusMap = [
                            'pending'     => ['secondary', 'Pending'],
                            'recommended' => ['info',      'Direkomendasi GA'],
                            'put_away'    => ['success',   'Put Away'],
                            'cancelled'   => ['danger',    'Dibatalkan'],
                        ];
                        [$iCls, $iLabel] = $iStatusMap[$itm->status] ?? ['secondary', ucfirst($itm->status)];
                        $diff = $itm->quantity_received - $itm->quantity_ordered;
                    @endphp
                    <tr class="{{ $itm->status === 'put_away' ? 'table-success' : '' }}">
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="font-weight-bold" style="font-size:13px;">{{ $itm->item->name ?? '-' }}</div>
                            <small class="text-muted font-monospace">{{ $itm->item->sku ?? '' }}</small>
                        </td>
                        <td>
                            @if ($itm->item?->category)
                                <span class="badge px-2"
                                    style="background:{{ $itm->item->category->color_code ?? '#6c757d' }};color:#fff;font-size:11px;">
                                    {{ $itm->item->category->name }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light border">{{ $itm->item?->unit->code ?? '-' }}</span>
                        </td>
                        <td>
                            @if ($itm->lpn)
                                <code class="small">{{ $itm->lpn }}</code>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-center font-weight-bold">{{ number_format($itm->quantity_ordered) }}</td>
                        <td class="text-center">
                            @if ($order->status === 'draft')
                                <input type="number"
                                    class="form-control form-control-sm text-center qty-received-input"
                                    data-detail-id="{{ $itm->id }}"
                                    value="{{ $itm->quantity_received }}"
                                    min="0"
                                    max="{{ $itm->quantity_ordered * 2 }}"
                                    style="width:85px;margin:auto;">
                            @else
                                <span class="font-weight-bold">{{ number_format($itm->quantity_received) }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($itm->quantity_received == 0)
                                <span class="text-muted">—</span>
                            @elseif ($diff == 0)
                                <span class="badge badge-success diff-badge">Sesuai</span>
                            @elseif ($diff > 0)
                                <span class="badge badge-info diff-badge">+{{ number_format($diff) }}</span>
                            @else
                                <span class="badge badge-danger diff-badge">{{ number_format($diff) }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge badge-{{ $iCls }}" style="font-size:11px;">
                                @if ($itm->status === 'put_away')
                                    <i class="fas fa-check mr-1"></i>
                                @elseif ($itm->status === 'recommended')
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                @endif
                                {{ $iLabel }}
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">{{ $itm->notes ?? '—' }}</small>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="font-weight-bold bg-light">
                    <tr>
                        <td colspan="5" class="text-right pr-3">Total:</td>
                        <td class="text-center">{{ number_format($totalOrdered) }}</td>
                        <td class="text-center">{{ number_format($totalReceived) }}</td>
                        <td class="text-center">
                            @php $totalDiff = $totalReceived - $totalOrdered; @endphp
                            @if ($totalDiff != 0)
                                <span class="{{ $totalDiff > 0 ? 'text-info' : 'text-danger' }}">
                                    {{ $totalDiff > 0 ? '+' : '' }}{{ number_format($totalDiff) }}
                                </span>
                            @else
                                <span class="text-success">Sesuai</span>
                            @endif
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- PANDUAN QTY (hanya draft) --}}
        @if ($order->status === 'draft')
        <div class="px-3 py-2 bg-light border-top">
            <small class="text-muted">
                <i class="fas fa-info-circle text-primary mr-1"></i>
                Isi kolom <strong>Qty Terima</strong> sesuai jumlah fisik barang yang ada di dock.
                Jika ada barang yang tidak datang, isi <strong>0</strong>.
                Setelah selesai, klik <strong>"Konfirmasi Qty Fisik"</strong>.
            </small>
        </div>
        @endif
    </div>
</div>
        @endif

{{-- ══════════════════════════════════════════════════════
     6. GA RESULT CARD
══════════════════════════════════════════════════════ --}}
@if ($latestGa)
@php
    $gaStatusCfg = [
        'accepted'       => ['success',  'Diterima (Auto)',    'fas fa-check-circle'],
        'rejected'       => ['danger',   'Ditolak',            'fas fa-times-circle'],
        'pending'        => ['info',     'Menunggu Review',    'fas fa-hourglass-half'],
        'pending_review' => ['warning',  'Perlu Review SPV',   'fas fa-exclamation-triangle'],
    ];
    [$gaCl, $gaLbl, $gaIco] = $gaStatusCfg[$latestGa->status] ?? ['secondary','—','fas fa-circle'];

    $fcDefs = [
        'fc_cap_score'   => ['FC_CAP',   'Kapasitas Cell',           40, '#3b82f6'],
        'fc_cat_score'   => ['FC_CAT',   'Kesesuaian Kategori/Zona', 30, '#10b981'],
        'fc_aff_score'   => ['FC_AFF',   'Afinitas Co-occurrence',   20, '#f59e0b'],
        'fc_split_score' => ['FC_SPLIT', 'Minimasi Pemisahan SKU',   10, '#8b5cf6'],
    ];

    $avgFc = [];
    if ($latestGa->details->isNotEmpty()) {
        foreach ($fcDefs as $col => [$key]) {
            $avgFc[$col] = round((float) $latestGa->details->avg($col), 2);
        }
    }

    $fitnessScore = round((float) $latestGa->fitness_score, 1);
    $fitnessColor = $fitnessScore >= 75 ? '#10b981' : ($fitnessScore >= 50 ? '#f59e0b' : '#ef4444');
    $fitnessLabel = $fitnessScore >= 75 ? 'Sangat Baik' : ($fitnessScore >= 50 ? 'Cukup Baik' : 'Perlu Evaluasi');
@endphp

<div class="card mb-3 card-{{ $gaCl }} card-outline">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold">
            <i class="fas fa-dna mr-1 text-{{ $gaCl }}"></i>
            Hasil Rekomendasi Genetic Algorithm
        </span>
        <span class="badge badge-{{ $gaCl }} px-3 py-1" style="font-size:12px;">
            <i class="{{ $gaIco }} mr-1"></i>{{ $gaLbl }}
        </span>
    </div>
    <div class="card-body">

        {{-- ── Row 1: Fitness Gauge + FC Breakdown ── --}}
        <div class="row mb-3">

            {{-- Fitness Score Gauge --}}
            <div class="col-md-4 text-center">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:4px;">
                    Fitness Score
                </div>
                <div id="gaugeGA" style="height:190px;"></div>
                <div style="margin-top:-8px;">
                    <div style="font-size:36px;font-weight:800;color:{{ $fitnessColor }};line-height:1;">{{ $fitnessScore }}</div>
                    <div style="font-size:12px;color:#9ca3af;margin-bottom:10px;">
                        dari 100 &nbsp;—&nbsp;
                        <strong style="color:{{ $fitnessColor }};">{{ $fitnessLabel }}</strong>
                    </div>
                </div>
                <div class="row text-center" style="border-top:1px solid #f0f2f5;padding-top:10px;margin:0 4px;">
                    <div class="col-6" style="border-right:1px solid #f0f2f5;">
                        <div style="font-size:22px;font-weight:700;color:#374151;">{{ $latestGa->generations_run ?? '—' }}</div>
                        <div style="font-size:10px;color:#9ca3af;">Generasi</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:22px;font-weight:700;color:#374151;">{{ number_format($latestGa->execution_time_ms ?? 0) }}</div>
                        <div style="font-size:10px;color:#9ca3af;">ms</div>
                    </div>
                </div>
                <div style="font-size:11px;color:#9ca3af;margin-top:10px;line-height:1.6;">
                    <i class="fas fa-user-circle mr-1"></i>{{ $latestGa->generatedBy->name ?? '—' }}<br>
                    <i class="fas fa-clock mr-1"></i>{{ $latestGa->generated_at?->format('d M Y, H:i') ?? '' }}
                </div>
            </div>

            {{-- FC Breakdown Bars --}}
            <div class="col-md-8">
                <div style="font-size:13px;font-weight:700;color:#1f2937;margin-bottom:16px;">
                    <i class="fas fa-chart-bar mr-1 text-primary"></i>
                    Komponen Fitness Function
                </div>

                @foreach ($fcDefs as $col => [$key, $desc, $maxPt, $color])
                @php
                    $val = $avgFc[$col] ?? 0;
                    $pct = $maxPt > 0 ? min(100, round($val / $maxPt * 100)) : 0;
                @endphp
                <div style="margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                        <div>
                            <span style="font-size:13px;font-weight:700;font-family:monospace;color:{{ $color }};">{{ $key }}</span>
                            <span style="font-size:11px;color:#6b7280;margin-left:8px;">{{ $desc }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;white-space:nowrap;">
                            <span style="font-size:15px;font-weight:700;color:#1f2937;">{{ number_format($val, 1) }}</span>
                            <span style="font-size:11px;color:#9ca3af;">/ {{ $maxPt }} pt</span>
                            <span style="font-size:10px;padding:2px 7px;border-radius:10px;background:{{ $color }}22;color:{{ $color }};font-weight:600;">{{ $pct }}%</span>
                        </div>
                    </div>
                    <div style="height:14px;background:#f3f4f6;border-radius:99px;overflow:hidden;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $color }};border-radius:99px;"></div>
                    </div>
                </div>
                @endforeach

                {{-- Formula Box --}}
                <div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:10px 14px;margin-top:4px;">
                    <div style="font-size:11px;color:#6b7280;font-weight:700;margin-bottom:5px;">
                        <i class="fas fa-function mr-1"></i>Formula Fitness Function:
                    </div>
                    <div style="font-size:12px;font-family:monospace;color:#374151;line-height:2;">
                        <span style="color:#3b82f6;font-weight:700;">FC_CAP</span>(40) +
                        <span style="color:#10b981;font-weight:700;">FC_CAT</span>(30) +
                        <span style="color:#f59e0b;font-weight:700;">FC_AFF</span>(20) +
                        <span style="color:#8b5cf6;font-weight:700;">FC_SPLIT</span>(10) = maks <strong>100</strong>
                    </div>
                    <div style="font-size:10px;color:#9ca3af;margin-top:3px;">
                        Nilai rata-rata fitness seluruh gen dalam kromosom terbaik (generasi {{ $latestGa->generations_run ?? '—' }})
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Row 2: Per-item Recommendation Table ── --}}
        @if ($latestGa->details->isNotEmpty())
        <hr class="my-3" style="border-color:#f0f2f5;">
        <div style="font-size:13px;font-weight:700;color:#1f2937;margin-bottom:10px;">
            <i class="fas fa-map-marker-alt mr-1 text-primary"></i>
            Rekomendasi Penempatan per Item
            <span class="badge badge-primary ml-1" style="font-size:11px;">{{ $latestGa->details->count() }} item</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="30">#</th>
                        <th>Item (SKU)</th>
                        <th class="text-center" width="60">Qty</th>
                        <th class="text-center" width="100">Cell</th>
                        <th class="text-center" width="120">Zona</th>
                        <th class="text-center" width="80">Rak</th>
                        <th class="text-center" width="80" style="border-left:2px solid #dee2e6;">
                            Fitness<br><small style="font-size:9px;color:#9ca3af;font-weight:400;">/100</small>
                        </th>
                        <th class="text-center" width="72">
                            <span style="color:#3b82f6;font-weight:700;">CAP</span><br>
                            <small style="font-size:9px;color:#9ca3af;font-weight:400;">/40</small>
                        </th>
                        <th class="text-center" width="72">
                            <span style="color:#10b981;font-weight:700;">CAT</span><br>
                            <small style="font-size:9px;color:#9ca3af;font-weight:400;">/30</small>
                        </th>
                        <th class="text-center" width="72">
                            <span style="color:#f59e0b;font-weight:700;">AFF</span><br>
                            <small style="font-size:9px;color:#9ca3af;font-weight:400;">/20</small>
                        </th>
                        <th class="text-center" width="72">
                            <span style="color:#8b5cf6;font-weight:700;">SPL</span><br>
                            <small style="font-size:9px;color:#9ca3af;font-weight:400;">/10</small>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($latestGa->details as $di => $det)
                    @php
                        $gf      = round((float) $det->gene_fitness, 1);
                        $gfColor = $gf >= 75 ? '#10b981' : ($gf >= 50 ? '#f59e0b' : '#ef4444');
                        $gfBg    = $gf >= 75 ? '#f0fdf4' : ($gf >= 50 ? '#fffbeb' : '#fef2f2');
                        $capPct  = min(100, round((float) $det->fc_cap_score   / 40 * 100));
                        $catPct  = min(100, round((float) $det->fc_cat_score   / 30 * 100));
                        $affPct  = min(100, round((float) $det->fc_aff_score   / 20 * 100));
                        $splPct  = min(100, round((float) $det->fc_split_score / 10 * 100));
                        $zoneName = $det->cell->rack->zone->name ?? '—';
                        $zoneCode = $det->cell->rack->zone->code ?? '';
                    @endphp
                    <tr>
                        <td class="text-center text-muted">{{ $di + 1 }}</td>
                        <td>
                            <div style="font-weight:600;color:#1f2937;font-size:13px;">{{ $det->inboundOrderItem->item->name ?? '—' }}</div>
                            <code style="font-size:10px;color:#6b7280;">{{ $det->inboundOrderItem->item->sku ?? '' }}</code>
                        </td>
                        <td class="text-center"><strong>{{ number_format($det->quantity) }}</strong></td>
                        <td class="text-center">
                            <span class="badge badge-primary" style="font-size:11px;padding:3px 8px;">
                                {{ $det->cell->code ?? '—' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light border" style="font-size:10px;">
                                {{ $zoneCode ? "[$zoneCode] " : '' }}{{ $zoneName }}
                            </span>
                        </td>
                        <td class="text-center" style="font-size:11px;color:#374151;">{{ $det->cell->rack->code ?? '—' }}</td>

                        {{-- Gene Fitness (color-coded) --}}
                        <td class="text-center" style="border-left:2px solid #dee2e6;">
                            <span style="font-size:16px;font-weight:800;color:{{ $gfColor }};">{{ $gf }}</span>
                        </td>

                        {{-- FC_CAP --}}
                        <td class="text-center">
                            <div style="font-weight:600;">{{ number_format((float)$det->fc_cap_score, 1) }}</div>
                            <div style="height:5px;background:#e9ecef;border-radius:3px;margin-top:2px;">
                                <div style="height:5px;width:{{ $capPct }}%;background:#3b82f6;border-radius:3px;"></div>
                            </div>
                        </td>

                        {{-- FC_CAT --}}
                        <td class="text-center">
                            <div style="font-weight:600;">{{ number_format((float)$det->fc_cat_score, 1) }}</div>
                            <div style="height:5px;background:#e9ecef;border-radius:3px;margin-top:2px;">
                                <div style="height:5px;width:{{ $catPct }}%;background:#10b981;border-radius:3px;"></div>
                            </div>
                        </td>

                        {{-- FC_AFF --}}
                        <td class="text-center">
                            <div style="font-weight:600;">{{ number_format((float)$det->fc_aff_score, 1) }}</div>
                            <div style="height:5px;background:#e9ecef;border-radius:3px;margin-top:2px;">
                                <div style="height:5px;width:{{ $affPct }}%;background:#f59e0b;border-radius:3px;"></div>
                            </div>
                        </td>

                        {{-- FC_SPLIT --}}
                        <td class="text-center">
                            <div style="font-weight:600;">{{ number_format((float)$det->fc_split_score, 1) }}</div>
                            <div style="height:5px;background:#e9ecef;border-radius:3px;margin-top:2px;">
                                <div style="height:5px;width:{{ $splPct }}%;background:#8b5cf6;border-radius:3px;"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:8px;font-size:11px;color:#9ca3af;">
            <i class="fas fa-circle" style="color:#10b981;"></i> ≥ 75 Sangat Baik &ensp;
            <i class="fas fa-circle" style="color:#f59e0b;"></i> 50–74 Cukup Baik &ensp;
            <i class="fas fa-circle" style="color:#ef4444;"></i> &lt; 50 Perlu Evaluasi &ensp;
            <span class="ml-2"><strong style="color:#3b82f6;">CAP</strong>=Kapasitas &ensp;
            <strong style="color:#10b981;">CAT</strong>=Kategori/Zona &ensp;
            <strong style="color:#f59e0b;">AFF</strong>=Afinitas &ensp;
            <strong style="color:#8b5cf6;">SPL</strong>=Anti-Split</span>
        </div>
        @endif

        {{-- Alert: pending_review — perlu persetujuan Supervisor --}}
        @if ($latestGa->status === 'pending_review')
        <div class="alert alert-warning mt-3 mb-0 py-2 d-flex align-items-start">
            <i class="fas fa-exclamation-triangle mr-2 mt-1" style="font-size:18px;"></i>
            <div>
                <strong>Rekomendasi GA memerlukan persetujuan Supervisor.</strong><br>
                <span class="text-muted" style="font-size:12px;">
                    Alasan: {{ $latestGa->review_reason ?? '—' }}
                </span><br>
                @if ($isSupervisor)
                <small class="text-dark">Gunakan tombol <strong>Setujui</strong> atau <strong>Tolak</strong> di atas untuk menangani.</small>
                @else
                <small class="text-dark">Supervisor akan segera mereview rekomendasi ini. Silakan tunggu notifikasi.</small>
                @endif
            </div>
        </div>
        @endif

        {{-- Alert: accepted (auto atau manual) --}}
        @if ($latestGa->status === 'accepted')
        <div class="alert alert-success mt-3 mb-0 py-2">
            <i class="fas fa-check-circle mr-1"></i>
            Rekomendasi GA <strong>diterima</strong> oleh
            {{ $latestGa->acceptedBy->name ?? 'sistem' }}
            pada {{ $latestGa->accepted_at?->format('d M Y, H:i') ?? '' }}.
            Operator dapat langsung melakukan <a href="{{ route('putaway.show', $order->id) }}"><strong>Put-Away</strong></a>.
        </div>
        @endif

        {{-- Alert: rejected --}}
        @if ($latestGa->status === 'rejected')
        <div class="alert alert-danger mt-3 mb-0 py-2">
            <i class="fas fa-times-circle mr-1"></i>
            Rekomendasi ini telah <strong>ditolak</strong>
            oleh {{ $latestGa->rejectedBy->name ?? '—' }}
            pada {{ $latestGa->rejected_at?->format('d M Y, H:i') ?? '' }}.
            @if ($latestGa->rejection_reason)
            <br><small class="text-muted">Alasan: {{ $latestGa->rejection_reason }}</small>
            @endif
            <br>Status order dikembalikan ke Draft. Jalankan GA ulang dengan klik <strong>"Jalankan GA"</strong>.
        </div>
        @endif

    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     7. PANDUAN ALUR (tampil jika draft, belum ada tindakan)
══════════════════════════════════════════════════════ --}}
@if ($order->status === 'draft' && !$latestGa)
<div class="card mb-3 card-secondary card-outline">
    <div class="card-header py-2">
        <span class="font-weight-bold">
            <i class="fas fa-route mr-1"></i> Langkah Selanjutnya
        </span>
    </div>
    <div class="card-body py-3">
        <div class="row text-center">
            <div class="col-md-4 mb-3">
                <div class="rounded border p-3 h-100">
                    <i class="fas fa-clipboard-check fa-2x text-secondary mb-2 d-block"></i>
                    <strong>Step 1 — Operator</strong>
                    <p class="small text-muted mt-1 mb-0">
                        Isi kolom <em>Qty Terima</em> pada tabel item di atas sesuai jumlah fisik,
                        lalu klik <strong>Konfirmasi Qty Fisik</strong>.
                    </p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="rounded border border-primary p-3 h-100">
                    <i class="fas fa-dna fa-2x text-primary mb-2 d-block"></i>
                    <strong>Step 2 — Jalankan GA</strong>
                    <p class="small text-muted mt-1 mb-0">
                        Klik <strong>Jalankan GA</strong> untuk mendapatkan rekomendasi penempatan optimal.
                        Jika hasil valid, sistem otomatis menerima dan langsung siap <em>put-away</em>.
                        Jika fitness rendah, Supervisor akan dinotifikasi untuk review.
                    </p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="rounded border p-3 h-100">
                    <i class="fas fa-dolly fa-2x text-success mb-2 d-block"></i>
                    <strong>Step 3 — Put-Away</strong>
                    <p class="small text-muted mt-1 mb-0">
                        Operator put-away barang ke cell yang direkomendasikan GA dengan scan QR.
                        Jika ada exception, Supervisor dapat meng-override lokasi.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

</div>{{-- /container-fluid --}}
@endsection

@if ($latestGa)
@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<script>
(function () {
    const score = {{ round((float)($latestGa->fitness_score ?? 0), 1) }};
    const color = score >= 75 ? '#10b981' : (score >= 50 ? '#f59e0b' : '#ef4444');

    Highcharts.chart('gaugeGA', {
        chart: {
            type: 'solidgauge',
            margin: [0,0,0,0], spacing: [0,0,0,0],
            backgroundColor: 'transparent', height: 190,
            style: { fontFamily: "'Source Sans Pro','Roboto',sans-serif" }
        },
        credits: { enabled: false },
        title: { text: null },
        exporting: { enabled: false },
        pane: {
            startAngle: -150, endAngle: 150,
            background: [{
                outerRadius: '100%', innerRadius: '80%',
                backgroundColor: Highcharts.color(color).setOpacity(.12).get(),
                borderWidth: 0
            }]
        },
        yAxis: {
            min: 0, max: 100, lineWidth: 0, tickWidth: 0,
            minorTickInterval: null,
            labels: { enabled: false },
            stops: [
                [0.50, '#ef4444'],
                [0.75, '#f59e0b'],
                [1.00, '#10b981']
            ]
        },
        tooltip: { enabled: false },
        plotOptions: {
            solidgauge: {
                rounded: true,
                dataLabels: { enabled: false }
            }
        },
        series: [{
            name: 'Fitness',
            data: [score],
            radius: '100%',
            innerRadius: '80%'
        }]
    });
}());
</script>
@endpush
@endif

@push('scripts')
<script>
const csrfToken = $('meta[name="csrf-token"]').attr('content');

// ── Helper: tampilkan loading spinner lalu navigasi ──────────────────────────
function showNavLoader(navigateFn) {
    Swal.fire({
        title: 'Memuat…',
        html: '<div class="my-2"><i class="fas fa-circle-notch fa-spin fa-2x" style="color:#0d8564"></i></div>',
        allowOutsideClick: false,
        showConfirmButton: false,
        showCancelButton: false,
    });
    setTimeout(navigateFn, 250);
}

// ── KONFIRMASI QTY FISIK ─────────────────────────────────────────────────────
$('#btnConfirmQty').on('click', function () {
    const url = $(this).data('url');
    const quantities = {};
    $('.qty-received-input').each(function () {
        quantities[$(this).data('detail-id')] = parseInt($(this).val()) || 0;
    });

    // Validasi: minimal 1 item qty > 0
    const allZero = Object.values(quantities).every(v => v === 0);

    Swal.fire({
        title: 'Konfirmasi Penerimaan Fisik?',
        html: allZero
            ? '<div class="alert alert-warning">Semua qty = 0. Order akan <strong>dibatalkan</strong> otomatis.</div>'
            : 'Pastikan jumlah sudah sesuai kondisi fisik barang di dock.',
        icon: allZero ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: allZero ? '#dc3545' : '#6c757d',
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url, method: 'POST',
            data: { _token: csrfToken, quantities },
            success(res) {
                if (res.status === 'warning') {
                    Swal.fire('Order Dibatalkan', res.message, 'warning')
                        .then(() => showNavLoader(() => { window.location.href = res.redirect; }));
                } else {
                    Swal.fire('Tersimpan!', res.message, 'success')
                        .then(() => showNavLoader(() => location.reload()));
                }
            },
            error(xhr) {
                Swal.fire('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan.', 'error');
            }
        });
    });
});

// ── JALANKAN GA ──────────────────────────────────────────────────────────────
$('#btnProcessGA').on('click', function () {
    const url = $(this).data('url');
    Swal.fire({
        title: 'Jalankan Genetic Algorithm?',
        html: 'Sistem akan menghitung rekomendasi penempatan optimal.<br>'
            + '<small class="text-muted">Proses bisa memakan waktu 10–120 detik.</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#007bff',
        confirmButtonText: '<i class="fas fa-dna mr-1"></i>Ya, Jalankan!',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'GA Sedang Berjalan...',
            html: '<div class="my-2"><i class="fas fa-dna fa-spin fa-2x text-primary"></i></div>'
                + 'Mohon tunggu, proses optimasi sedang berlangsung.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url, method: 'POST',
            data: { _token: csrfToken },
            timeout: 180000,
            success(res) {
                if (res.status === 'success' && res.data?.auto_accepted) {
                    // GA valid & auto-accepted — langsung ke put-away
                    Swal.fire({
                        icon: 'success',
                        title: 'GA Selesai & Diterima Otomatis!',
                        html: res.message + '<br><small class="text-muted">Mengalihkan ke halaman put-away…</small>',
                        timer: 2500,
                        showConfirmButton: false,
                    }).then(() => showNavLoader(() => { window.location.href = res.data.redirect || location.href; }));
                } else if (res.status === 'warning') {
                    // GA selesai tapi fitness rendah — perlu review supervisor
                    Swal.fire({
                        icon: 'warning',
                        title: 'GA Selesai — Perlu Review Supervisor',
                        html: res.message + '<br><small class="text-muted">Supervisor telah dinotifikasi.</small>',
                    }).then(() => showNavLoader(() => location.reload()));
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'GA Selesai!',
                        text: res.message,
                    }).then(() => showNavLoader(() => location.reload()));
                }
            },
            error(xhr) {
                Swal.fire('GA Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan saat menjalankan GA.', 'error')
                    .then(() => showNavLoader(() => location.reload()));
            }
        });
    });
});

// ── ACCEPT GA ────────────────────────────────────────────────────────────────
$('#btnAcceptGa').on('click', function () {
    const url = $(this).data('url');
    Swal.fire({
        title: 'Terima Rekomendasi GA?',
        html: 'Rekomendasi penempatan akan diterima dan operator dapat memulai proses <strong>put-away</strong>.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: '<i class="fas fa-check mr-1"></i>Ya, Terima',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url, method: 'POST',
            data: { _token: csrfToken },
            success(res) {
                Swal.fire('Diterima!', res.message, 'success')
                    .then(() => showNavLoader(() => { window.location.href = res.redirect || location.href; }));
            },
            error(xhr) {
                Swal.fire('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan.', 'error');
            }
        });
    });
});

// ── REJECT GA ────────────────────────────────────────────────────────────────
$('#btnRejectGa').on('click', function () {
    const url = $(this).data('url');
    Swal.fire({
        title: 'Tolak Rekomendasi GA?',
        html: '<p>Rekomendasi akan ditolak dan status order dikembalikan ke <strong>Draft</strong>.</p>'
            + '<p class="text-muted small">Supervisor dapat menjalankan GA ulang setelahnya.</p>',
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'Alasan penolakan (opsional)...',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Tolak',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url, method: 'POST',
            data: { _token: csrfToken, reason: result.value },
            success(res) {
                Swal.fire('Ditolak', res.message, 'info')
                    .then(() => showNavLoader(() => location.reload()));
            },
            error(xhr) {
                Swal.fire('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan.', 'error');
            }
        });
    });
});
</script>
@endpush
