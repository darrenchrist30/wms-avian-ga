@extends('layouts.adminlte')

@section('title', 'Put-Away Queue')

@push('styles')
<style>
    /* ── Camera scanner ── */
    @keyframes scanMove {
        0%   { top: 8%;  opacity: 1;  }
        45%  { top: 82%; opacity: .7; }
        55%  { top: 82%; opacity: .7; }
        100% { top: 8%;  opacity: 1;  }
    }
    @keyframes scanPulse {
        0%, 100% { box-shadow: 0 0 4px #0d8564, 0 0 12px #0d856440; }
        50%      { box-shadow: 0 0 8px #0d8564, 0 0 24px #0d856480; }
    }
    #cameraViewport {
        position: relative; border-radius: 10px;
        overflow: hidden; background: #000; min-height: 200px;
    }
    #qrCameraReader video { width: 100% !important; height: auto !important; display: block; }
    #qrCameraReader img   { display: none; }
    #scanLine {
        position: absolute; left: 8%; width: 84%; height: 2px;
        background: linear-gradient(90deg, transparent, #0d8564 40%, #38c172, #0d8564 60%, transparent);
        animation: scanMove 2s ease-in-out infinite, scanPulse 2s ease-in-out infinite;
        z-index: 10; pointer-events: none;
    }
    .cam-corner {
        position: absolute; width: 28px; height: 28px;
        border-color: #0d8564; border-style: solid; z-index: 9; pointer-events: none;
    }
    #camCornTL { top: 10%; left: 8%;   border-width: 3px 0 0 3px; border-radius: 3px 0 0 0; }
    #camCornTR { top: 10%; right: 8%;  border-width: 3px 3px 0 0; border-radius: 0 3px 0 0; }
    #camCornBL { bottom: 10%; left: 8%;  border-width: 0 0 3px 3px; border-radius: 0 0 0 3px; }
    #camCornBR { bottom: 10%; right: 8%; border-width: 0 3px 3px 0; border-radius: 0 0 3px 0; }
    #cameraScanSuccess {
        display: none; position: absolute; inset: 0;
        background: rgba(13, 133, 100, .38);
        align-items: center; justify-content: center; z-index: 12; border-radius: 10px;
    }
    #cameraScanSuccess.visible { display: flex; }

    /* ── Cap bar ── */
    .cap-bar-wrap  { height: 5px; background: #e0e0e0; border-radius: 3px; overflow: hidden; }
    .cap-bar-fill  { height: 100%; border-radius: 3px; }

    /* ── Row flash ── */
    @keyframes rowSaveFlash {
        0%   { background: #b8f0ca; }
        70%  { background: #c6f8d5; }
        100% { background: #f1fff5; }
    }
    tr.row-save-flash td { animation: rowSaveFlash 1s ease-out forwards; }

    /* ── Modal saving overlay ── */
    #modalSavingOverlay {
        position: absolute; inset: 0;
        background: rgba(255,255,255,.90); z-index: 200;
        display: flex; align-items: center; justify-content: center;
        flex-direction: column; gap: 6px; border-radius: 12px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mt-2 mb-0">
                <i class="fas fa-stream mr-2 text-warning"></i>
                Put-Away Queue
            </h4>
            <p class="text-muted mb-0 mt-1">
                Semua item dari {{ $totalOrders }} DO yang siap ditempatkan — diurutkan per lokasi sel.
            </p>
        </div>
        <div class="col-auto">
            <a href="{{ route('putaway.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-list mr-1"></i> Lihat per DO
            </a>
        </div>
    </div>

    {{-- ── Summary Cards ────────────────────────────────────────────────────── --}}
    <div class="row mb-3" id="summaryRow">
        <div class="col-6 col-md-3">
            <div class="info-box shadow-sm mb-2">
                <span class="info-box-icon bg-warning"><i class="fas fa-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Item</span>
                    <span class="info-box-number" id="statTotal">{{ $items->count() }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-box shadow-sm mb-2">
                <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Selesai</span>
                    <span class="info-box-number" id="statDone">0</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-box shadow-sm mb-2">
                <span class="info-box-icon bg-primary"><i class="fas fa-truck-loading"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">DO Terlibat</span>
                    <span class="info-box-number">{{ $totalOrders }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-box shadow-sm mb-2">
                <span class="info-box-icon bg-info"><i class="fas fa-percent"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Progress</span>
                    <span class="info-box-number" id="statPct">0%</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Progress Bar ─────────────────────────────────────────────────────── --}}
    <div class="mb-3">
        <div class="progress" style="height:10px;border-radius:6px;">
            <div id="progressBar" class="progress-bar bg-success progress-bar-striped progress-bar-animated"
                 style="width:0%;transition:width .4s ease;"></div>
        </div>
    </div>

    {{-- ── Queue Table ──────────────────────────────────────────────────────── --}}
    @if($items->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                <p class="mb-0 font-weight-bold">Semua item sudah di-put-away!</p>
                <p class="small">Tidak ada item yang menunggu penempatan.</p>
                <a href="{{ route('putaway.index') }}" class="btn btn-sm btn-outline-primary mt-2">Lihat Riwayat</a>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="queueTable">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" width="36">#</th>
                                <th width="130">No. Surat Jalan</th>
                                <th>Item / SKU</th>
                                <th width="60" class="text-center">QTY</th>
                                <th width="55" class="text-center">Satuan</th>
                                <th width="110" class="text-center">Sel GA</th>
                                <th width="120">Rak / Zona</th>
                                <th width="85" class="text-center">Status Sel</th>
                                <th width="130" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $i => $detail)
                            @php
                                $order  = $detail->gaRecommendation->inboundOrder;
                                $item   = $detail->inboundOrderItem->item;
                                $cell   = $detail->cell;
                                $rack   = $cell?->rack;
                                $zone   = $rack?->zone;
                                $statusMap = [
                                    'available' => ['success', 'Tersedia'],
                                    'partial'   => ['warning', 'Sebagian'],
                                    'full'      => ['danger',  'Penuh'],
                                    'blocked'   => ['secondary','Blokir'],
                                ];
                                [$statusColor, $statusLabel] = $statusMap[$cell?->status ?? ''] ?? ['secondary', $cell?->status ?? '—'];
                            @endphp
                            <tr id="row-{{ $detail->inboundOrderItem->id }}">
                                <td class="text-center text-muted small align-middle">{{ $i + 1 }}</td>
                                <td class="align-middle">
                                    <a href="{{ route('putaway.show', $order->id) }}"
                                       class="font-weight-bold text-primary" style="font-size:12px;"
                                       target="_blank" title="Buka detail DO">
                                        {{ $order->do_number }}
                                    </a>
                                    @if($order->notes)
                                        <br><small class="text-muted" style="font-size:10px;">{{ Str::limit($order->notes, 35) }}</small>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <div class="font-weight-bold" style="font-size:13px;line-height:1.3;">{{ $item->name }}</div>
                                    <small class="text-muted">{{ $item->sku }}</small>
                                    @if($item->category)
                                        &nbsp;<span class="badge badge-light border" style="font-size:10px;">{{ $item->category->name }}</span>
                                    @endif
                                </td>
                                <td class="text-center font-weight-bold align-middle" style="font-size:14px;">
                                    {{ $detail->quantity }}
                                </td>
                                <td class="text-center align-middle">
                                    <small class="text-muted">{{ $item->unit?->symbol ?? '—' }}</small>
                                </td>
                                <td class="text-center align-middle">
                                    @if($cell)
                                        <span class="badge badge-primary px-2" style="font-size:11px;letter-spacing:.3px;">
                                            {{ $cell->code }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="align-middle" style="font-size:12px;">
                                    @if($rack)
                                        <span class="font-weight-bold">{{ $rack->code }}</span>
                                        @if($rack->name && $rack->name !== $rack->code)
                                            <br><small class="text-muted">{{ $rack->name }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    @if($zone)
                                        <br><small class="text-muted">Zona {{ $zone->code ?? $zone->name }}</small>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge badge-{{ $statusColor }}" style="font-size:10px;">
                                        {{ $statusLabel }}
                                    </span>
                                    @if($cell)
                                        <br><small class="text-muted" style="font-size:10px;">
                                            {{ $cell->capacity_used }}/{{ $cell->capacity_max }}
                                        </small>
                                    @endif
                                </td>
                                <td class="text-center align-middle" style="white-space:nowrap">
                                    <button class="btn btn-xs btn-success btnConfirm"
                                            data-order-id="{{ $order->id }}"
                                            data-detail-id="{{ $detail->inboundOrderItem->id }}"
                                            data-ga-detail-id="{{ $detail->id }}"
                                            data-item-name="{{ $item->name }}"
                                            data-qty="{{ $detail->quantity }}"
                                            data-ga-cell="{{ $cell?->code ?? '' }}"
                                            data-ga-cell-id="{{ $cell?->id ?? '' }}"
                                            data-cap-remaining="{{ $cell?->capacity_remaining ?? 0 }}"
                                            data-cap-max="{{ $cell?->capacity_max ?? 0 }}"
                                            title="Konfirmasi penempatan ke {{ $cell?->code ?? 'sel GA' }}">
                                        <i class="fas fa-check mr-1"></i>Konfirmasi
                                    </button>
                                    <button class="btn btn-xs btn-warning text-dark btnOverride"
                                            data-order-id="{{ $order->id }}"
                                            data-detail-id="{{ $detail->inboundOrderItem->id }}"
                                            data-ga-detail-id="{{ $detail->id }}"
                                            data-item-name="{{ $item->name }}"
                                            data-qty="{{ $detail->quantity }}"
                                            data-ga-cell="{{ $cell?->code ?? '' }}"
                                            data-ga-cell-id="{{ $cell?->id ?? '' }}"
                                            data-cap-remaining="{{ $cell?->capacity_remaining ?? 0 }}"
                                            data-cap-max="{{ $cell?->capacity_max ?? 0 }}"
                                            title="Override — tempatkan di luar rekomendasi GA">
                                        <i class="fas fa-map-marker-alt mr-1"></i>Override
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         MODAL KONFIRMASI — 2-PHASE (sama persis dengan show.blade.php)
    ══════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalConfirm" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;position:relative">

                {{-- ── Saving overlay ── --}}
                <div id="modalSavingOverlay" style="display:none">
                    <div style="background:#fff;border-radius:10px;
                                padding:2.2em 3em;text-align:center;
                                box-shadow:0 0 0 1px rgba(0,0,0,.06),0 8px 28px rgba(0,0,0,.18);
                                min-width:260px">
                        <div style="font-size:1.5em;font-weight:600;color:#545454;margin-bottom:16px">Menyimpan…</div>
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

                        {{-- ── CAMERA VIEWPORT ── --}}
                        <div id="cameraSection" style="display:none" class="mb-3">
                            <div id="cameraViewport">
                                <div id="qrCameraReader"></div>
                                <div id="scanLine"></div>
                                <div class="cam-corner" id="camCornTL"></div>
                                <div class="cam-corner" id="camCornTR"></div>
                                <div class="cam-corner" id="camCornBL"></div>
                                <div class="cam-corner" id="camCornBR"></div>
                                <div id="cameraScanSuccess">
                                    <i class="fas fa-check-circle"
                                        style="color:#fff;font-size:52px;
                                              text-shadow:0 2px 12px rgba(0,0,0,.4);
                                              animation:none"></i>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mt-2" style="gap:6px">
                                <select id="cameraSelect" class="form-control form-control-sm"
                                    style="flex:1;font-size:12px"></select>
                                <button type="button" id="btnTorch" class="btn btn-sm btn-outline-secondary"
                                    style="display:none;flex-shrink:0" title="Flash/Torch">
                                    <i class="fas fa-bolt"></i>
                                </button>
                                <button type="button" id="btnCloseCamera" class="btn btn-sm btn-outline-danger"
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

                        {{-- ── INPUT MANUAL ── --}}
                        <div class="input-group mb-2" style="box-shadow:0 2px 8px rgba(0,0,0,.08)">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#1a2332;border-color:#1a2332">
                                    <i class="fas fa-qrcode text-white" style="font-size:16px"></i>
                                </span>
                            </div>
                            <input type="text" id="modalQrInput" class="form-control"
                                placeholder="Scan pistol / ketik kode cell…" autocomplete="off"
                                style="font-size:16px;font-weight:600;letter-spacing:.5px;border-color:#dee2e6">
                            <div class="input-group-append">
                                <button class="btn" type="button" id="btnModalScanQr"
                                    style="background:#1a2332;color:#fff;border-color:#1a2332;font-size:13px">
                                    <i class="fas fa-search mr-1"></i>Cari
                                </button>
                            </div>
                        </div>

                        <div id="scanLoading" class="text-center py-2" style="display:none">
                            <i class="fas fa-spinner fa-spin text-primary mr-1"></i>
                            <small class="text-muted">Mengidentifikasi cell…</small>
                        </div>

                        <div id="autoConfirmStatus" class="text-center py-2" style="display:none"></div>

                        <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="border-radius:6px;font-size:12px">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Wajib scan QR di rak fisik</strong> sebelum konfirmasi penempatan.
                        </div>
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
                            <div id="resultCapBar" class="mt-2" style="display:none">
                                <div class="cap-bar-wrap">
                                    <div class="cap-bar-fill bg-secondary" id="resultCapBarUsed" style="width:0%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Perbandingan GA vs Cell Dipilih --}}
                        <div id="gaComparePanel" class="mx-3 mt-2 mb-0 p-2 rounded"
                            style="display:none;background:#f8f9fa;border:1px solid #dee2e6;font-size:12px">
                            <div class="row no-gutters mb-1">
                                <div class="col-6 pr-1">
                                    <span class="text-muted d-block"
                                        style="font-size:10px;text-transform:uppercase;letter-spacing:.4px">Rekomendasi GA</span>
                                    <span class="font-weight-bold" id="gaCompareGaCell"
                                        style="font-size:15px;color:#0056b3">—</span>
                                </div>
                                <div class="col-6 pl-1">
                                    <span class="text-muted d-block"
                                        style="font-size:10px;text-transform:uppercase;letter-spacing:.4px">Cell Dipilih</span>
                                    <span class="font-weight-bold" id="gaCompareScannedCell"
                                        style="font-size:15px;color:#155724">—</span>
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

                        {{-- Qty --}}
                        <div class="px-3 pt-3 pb-1">
                            <div class="mb-1">
                                <label class="text-muted mb-0" style="font-size:12px">
                                    <i class="fas fa-boxes mr-1"></i>Qty yang Ditempatkan
                                    <small class="text-info ml-1">(harus = qty diterima di dock)</small>
                                </label>
                            </div>
                            <div id="qtyDisplay"
                                style="font-size:32px;font-weight:800;text-align:center;
                                        color:#0d8564;line-height:1.1;padding:6px 0">-</div>
                            <input type="number" id="confirmQty" class="form-control" min="1"
                                style="display:none;font-size:22px;font-weight:700;
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
                    <button type="button" class="btn btn-success" id="btnDoConfirm"
                        style="display:none;font-size:15px;padding:8px 28px;font-weight:700;
                                   box-shadow:0 3px 10px rgba(40,167,69,.35)">
                        <i class="fas fa-check-circle mr-2"></i>Konfirmasi Sekarang
                    </button>
                </div>

            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const TOTAL      = {{ $items->count() }};
const scanQrUrl  = "{{ route('putaway.scan-qr') }}";
const csrfToken  = $('meta[name="csrf-token"]').attr('content');
let   done       = 0;

// ── State ─────────────────────────────────────────────────────────────────────
let currentOrderId   = null;
let currentDetailId  = null;   // InboundOrderItem id (for URL)
let currentGaDetailId = null;  // GaRecommendationDetail id (for ga_detail_id param)
let isOverride       = false;
let modalCell        = null;
let modalGaCell      = null;
let modalQty         = 0;
let qtyEditing       = false;

function updateStats() {
    $('#statDone').text(done);
    const pct = TOTAL > 0 ? Math.round(done / TOTAL * 100) : 0;
    $('#statPct').text(pct + '%');
    $('#progressBar').css('width', pct + '%');
}

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

    const isMatch  = modalGaCell && cell.id === modalGaCell.id;
    const isDiff   = modalGaCell && cell.id !== modalGaCell.id;
    const isGaSkip = cell.source === 'ga';

    let cardBorder = '#28a745', cardBg = '#f0fff4';
    if (isGaSkip)  { cardBorder = '#007bff'; cardBg = '#f0f7ff'; }
    else if (isDiff) { cardBorder = '#fd7e14'; cardBg = '#fff8f0'; }

    $('#cellResultCard').css({ borderColor: cardBorder, background: cardBg });
    $('#resultCellCode').text(cell.code);
    $('#resultCellMeta').text(
        (cell.zone_category ? 'Zone ' + cell.zone_category + ' · ' : '') +
        (cell.rack_code ? 'Rack ' + cell.rack_code : '')
    );

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
                'Kapasitas tidak cukup: butuh <strong>' + modalQty + '</strong> unit, tersedia <strong>' + rem +
                '</strong> unit. Scan atau pilih cell lain yang muat.'
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

    qtyEditing = false;
    $('#confirmQty').val(modalQty).hide();
    $('#qtyDisplay').text(modalQty);
    $('#qtyUnitLabel').text('unit yang akan ditempatkan');
    $('#confirmNotes').val(isOverride ? '[OVERRIDE] ' : '');

    $('#phaseScan').hide();
    $('#phaseConfirm').show();

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

// ── Simpan ke DB ─────────────────────────────────────────────────────────────
const MIN_LOADER_MS = 800;

function doSaveConfirm(cellId, qty, notes, cellCode) {
    const url = isOverride
        ? `/putaway/${currentOrderId}/items/${currentDetailId}/override`
        : `/putaway/${currentOrderId}/items/${currentDetailId}/confirm`;

    const overlayStart = Date.now();
    $('#modalSavingOverlay').show();
    $('#btnDoConfirm').prop('disabled', true)
        .html('<i class="fas fa-circle-notch fa-spin mr-2"></i>Menyimpan…');

    function afterMinLoader(fn) {
        const elapsed  = Date.now() - overlayStart;
        const waitMore = Math.max(0, MIN_LOADER_MS - elapsed);
        setTimeout(fn, waitMore);
    }

    $.ajax({
        url,
        method: 'POST',
        data: {
            _token:          csrfToken,
            cell_id:         cellId,
            quantity_stored: qty,
            ga_detail_id:    isOverride ? null : (currentGaDetailId || null),
            notes:           notes || ''
        },
        success: function(res) {
            if (res.status !== 'success') return;

            afterMinLoader(function() {
                $('#modalConfirm').modal('hide');

                // Hapus baris dari antrian
                const $row = $('#row-' + currentDetailId);
                $row.addClass('row-save-flash');
                setTimeout(function() {
                    $row.fadeOut(400, function() { $(this).remove(); });
                }, 800);

                done++;
                updateStats();

                if (done === TOTAL) {
                    setTimeout(function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Semua item selesai!',
                            text: 'Seluruh item dalam queue sudah di-put-away.',
                            confirmButtonText: 'Lihat Riwayat',
                        }).then(() => window.location.href = '{{ route("putaway.index") }}');
                    }, 1000);
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message || 'Item berhasil di-put-away.',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                    });
                }
            });
        },
        error: function(xhr) {
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
            afterMinLoader(function() {
                $('#btnDoConfirm').prop('disabled', false)
                    .html('<i class="fas fa-check-circle mr-2"></i>Konfirmasi Sekarang');
            });
        }
    });
}

// ── Scan QR di dalam modal — Smart routing ───────────────────────────────────
function doModalScanQr(code) {
    if (!code) return;
    $('#modalQrInput').val('').prop('disabled', true);
    $('#autoConfirmStatus').hide();
    $('#scanLoading').show();

    $.ajax({
        url: scanQrUrl,
        method: 'POST',
        data: { _token: csrfToken, qr_code: code },
        success: function(res) {
            const c    = res.cell;
            const cell = {
                id:                 c.id,
                code:               c.code,
                zone_category:      c.zone_category,
                rack_code:          c.rack_code,
                capacity_remaining: c.capacity_remaining,
                capacity_max:       c.capacity_max,
                source:             'scan',
            };

            const matchesGa = !isOverride && modalGaCell && (cell.id == modalGaCell.id);
            const diffFromGa = !isOverride && modalGaCell && (cell.id != modalGaCell.id);
            const capOk      = cell.capacity_remaining >= modalQty && modalQty > 0;

            if (diffFromGa) {
                $('#scanLoading').hide();
                $('#modalQrInput').prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Sel Tidak Sesuai Rekomendasi GA',
                    html:
                        '<p>Anda memindai <strong>' + cell.code + '</strong>, sedangkan GA merekomendasikan ' +
                        '<strong>' + modalGaCell.code + '</strong>.</p>' +
                        '<p class="mb-0 text-muted" style="font-size:13px">Scan ulang QR cell <strong>' +
                        modalGaCell.code + '</strong>, atau gunakan <strong>Override Lokasi</strong> ' +
                        'jika penempatan di luar rekomendasi benar-benar diperlukan.</p>',
                    confirmButtonText: 'Scan Ulang',
                    confirmButtonColor: '#dc3545',
                }).then(() => { $('#modalQrInput').val('').focus(); });

            } else if (matchesGa && capOk) {
                $('#scanLoading').hide();
                $('#modalQrInput').prop('disabled', false);
                Swal.fire({
                    icon: 'success',
                    title: 'Konfirmasi Penempatan',
                    html:
                        '<p>Cell <strong>' + cell.code + '</strong> ' +
                        '<span class="badge badge-success" style="font-size:12px">' +
                        '<i class="fas fa-check-circle mr-1"></i>Sesuai rekomendasi GA</span></p>' +
                        '<p>Qty: <strong>' + modalQty + ' unit</strong></p>' +
                        '<p class="mb-0 text-muted" style="font-size:13px">Sisa kapasitas: ' +
                        cell.capacity_remaining + ' / ' + cell.capacity_max + ' unit</p>',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check-circle mr-1"></i>Ya, Simpan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        modalCell = cell;
                        $('#modalConfirm').data('cell-id', cell.id);
                        doSaveConfirm(cell.id, modalQty, '', cell.code);
                    } else {
                        $('#modalQrInput').val('').focus();
                    }
                });

            } else {
                modalCell = cell;
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

$('#btnRescan').on('click', showScanPhase);

$('#modalConfirm').on('shown.bs.modal', function() { $('#modalQrInput').focus(); });

// ── Buka modal ────────────────────────────────────────────────────────────────
function openConfirmModal(orderId, detailId, gaDetailId, itemName, qty, gaCell, gaCellId,
                          gaCapRemain, gaCapMax, overrideMode) {
    currentOrderId    = orderId;
    currentDetailId   = detailId;
    currentGaDetailId = overrideMode ? null : (gaDetailId || null);
    isOverride        = !!overrideMode;
    modalQty          = qty;
    modalCell         = null;

    modalGaCell = gaCellId ? {
        id:                 gaCellId,
        code:               gaCell,
        zone_category:      '',
        rack_code:          '',
        capacity_remaining: gaCapRemain,
        capacity_max:       gaCapMax,
        source:             'ga'
    } : null;

    const headerBg = isOverride ? '#856404' : '#28a745';
    $('#confirmModalHeader').css('background', headerBg);
    $('#confirmModalTitle').text(isOverride ? 'Override Lokasi' : 'Konfirmasi Put-Away');
    $('#confirmModalSubtitle').text(itemName);
    $('#confirmItemName').text(itemName);
    $('#confirmItemQty').text(qty);

    showScanPhase();
    $('#modalConfirm').modal('show');
}

$(document).on('click', '.btnConfirm', function() {
    const b = $(this);
    openConfirmModal(
        b.data('order-id'),
        b.data('detail-id'),
        b.data('ga-detail-id'),
        b.data('item-name'),
        parseInt(b.data('qty')),
        b.data('ga-cell'),
        b.data('ga-cell-id'),
        parseInt(b.data('cap-remaining')) || 0,
        parseInt(b.data('cap-max')) || 0,
        false
    );
});

$(document).on('click', '.btnOverride', function() {
    const b = $(this);
    openConfirmModal(
        b.data('order-id'),
        b.data('detail-id'),
        b.data('ga-detail-id'),
        b.data('item-name'),
        parseInt(b.data('qty')),
        b.data('ga-cell')    || '',
        b.data('ga-cell-id') || '',
        parseInt(b.data('cap-remaining')) || 0,
        parseInt(b.data('cap-max')) || 0,
        true
    );
});

// ── Tombol "Konfirmasi Sekarang" (Phase 2) ───────────────────────────────────
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

    const cellCode = modalCell?.code || cellId;
    Swal.fire({
        title: 'Konfirmasi Penempatan?',
        html:  'Simpan item ke cell <strong>' + cellCode + '</strong><br>Jumlah: <strong>' + qty + ' unit</strong>',
        icon:  'question',
        showCancelButton:   true,
        confirmButtonColor: '#28a745',
        cancelButtonColor:  '#6c757d',
        confirmButtonText:  '<i class="fas fa-check-circle mr-1"></i> Ya, Simpan',
        cancelButtonText:   'Batal',
        reverseButtons:     true
    }).then(function(result) {
        if (result.isConfirmed) doSaveConfirm(cellId, qty, notes, cellCode);
    });
});

// ══════════════════════════════════════════════════════════════════════
//  CAMERA SCANNER — html5-qrcode
// ══════════════════════════════════════════════════════════════════════
let qrScanner    = null;
let cameraActive = false;
let torchOn      = false;

const CAM_MODES = [
    { label: 'Kamera Belakang / Default', constraint: { facingMode: 'environment' } },
    { label: 'Kamera Depan',              constraint: { facingMode: 'user'        } },
];
let activeModeIdx = 0;

function playBeep() {
    try {
        const ctx  = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = 1800; osc.type = 'sine';
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.13);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.13);
    } catch(e) {}
}

function onCameraSuccess(decodedText) {
    playBeep();
    const flash = document.getElementById('cameraScanSuccess');
    if (flash) { flash.classList.add('visible'); setTimeout(() => flash.classList.remove('visible'), 650); }
    stopCamera().then(() => doModalScanQr(decodedText.trim()));
}

function getSupportedFormats() {
    try {
        const F = Html5QrcodeSupportedFormats;
        return [F.QR_CODE, F.CODE_128, F.CODE_39, F.CODE_93,
                F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E,
                F.DATA_MATRIX, F.AZTEC, F.PDF_417, F.ITF, F.CODABAR]
            .filter(f => f !== undefined);
    } catch(e) { return []; }
}

async function startCamera(modeIdx) {
    activeModeIdx = modeIdx;
    const mode = CAM_MODES[modeIdx];

    if (!qrScanner) {
        qrScanner = new Html5Qrcode('qrCameraReader', { verbose: false });
    }

    setStatus('<i class="fas fa-circle-notch fa-spin mr-1"></i>Mengaktifkan kamera…', '#6c757d');
    $('#cameraSelect').val(String(modeIdx));

    const formats = getSupportedFormats();
    const config  = {
        fps: 15,
        qrbox: function(w, h) { const s = Math.round(Math.min(w, h) * 0.72); return { width: s, height: s }; },
        aspectRatio: 1.1,
        ...(formats.length ? { formatsToSupport: formats } : {}),
    };

    try {
        await qrScanner.start(mode.constraint, config, onCameraSuccess, () => {});
        cameraActive = true;
        try {
            const caps = qrScanner.getRunningTrackCapabilities();
            if (caps && caps.torch) $('#btnTorch').show();
        } catch(e) {}
        setStatus(
            '<i class="fas fa-circle mr-1" style="font-size:8px;color:#0d8564"></i>Kamera aktif — arahkan ke QR / barcode',
            '#0d8564'
        );
    } catch(err) {
        cameraActive = false;
        if (modeIdx === 0) {
            setStatus('<i class="fas fa-info-circle mr-1"></i>Kamera belakang tidak ada, mencoba kamera depan…', '#f6993f');
            setTimeout(() => startCamera(1), 400);
            return;
        }
        await stopCamera();
        Swal.fire({ icon: 'error', toast: true, position: 'top-end', showConfirmButton: false,
            timer: 5000, title: 'Gagal membuka kamera', text: err.message || String(err) });
    }
}

async function stopCamera() {
    if (qrScanner) {
        if (cameraActive) { try { await qrScanner.stop(); } catch(e) {} }
        try { await qrScanner.clear(); } catch(e) {}
        qrScanner = null;
    }
    cameraActive = false; torchOn = false;
    $('#btnTorch').removeClass('btn-warning').addClass('btn-outline-secondary').hide();
    $('#cameraSection').hide();
    $('#btnOpenCamera').show();
}

function setStatus(html, color) {
    $('#cameraStatus').html(html).css('color', color || '#6c757d').show();
}

$('#btnOpenCamera').on('click', function() {
    $(this).hide();
    const $sel = $('#cameraSelect').empty();
    CAM_MODES.forEach((m, i) => $sel.append(`<option value="${i}">${m.label}</option>`));
    $('#cameraSection').show();
    startCamera(0);
});

$('#btnCloseCamera').on('click', stopCamera);

$('#cameraSelect').on('change', async function() {
    if (qrScanner && cameraActive) {
        try { await qrScanner.stop(); } catch(e) {}
        try { await qrScanner.clear(); } catch(e) {}
        qrScanner = null; cameraActive = false;
    }
    startCamera(parseInt($(this).val()));
});

$('#btnTorch').on('click', async function() {
    if (!qrScanner || !cameraActive) return;
    torchOn = !torchOn;
    try {
        await qrScanner.applyVideoConstraints({ advanced: [{ torch: torchOn }] });
        $(this).toggleClass('btn-outline-secondary btn-warning');
    } catch(e) {
        torchOn = !torchOn;
        Swal.fire({ icon: 'info', toast: true, position: 'top-end', showConfirmButton: false,
            timer: 2000, title: 'Flash tidak didukung perangkat ini' });
    }
});

$('#modalConfirm').on('hide.bs.modal', function() {
    stopCamera();
    $('#modalSavingOverlay').hide();
});
</script>
@endpush
