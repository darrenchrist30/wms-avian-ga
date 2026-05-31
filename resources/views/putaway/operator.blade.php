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
.op-header h2 { font-size: 26px; font-weight: 800; margin: 4px 0; }
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

/* Location header */
.loc-header {
    background: linear-gradient(90deg, #0d8564, #0a6e52);
    color: #fff;
    padding: 14px 18px;
}
.loc-code {
    font-size: 38px;
    font-weight: 900;
    letter-spacing: 3px;
    line-height: 1;
    text-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.loc-sub  { font-size: 13px; opacity: .85; margin-top: 5px; }
.loc-cap  { font-size: 12px; opacity: .7;  margin-top: 3px; }
.cap-bar  { height: 5px; background: rgba(255,255,255,.25); border-radius: 3px; margin-top: 5px; overflow: hidden; width: 90px; }
.cap-fill { height: 5px; border-radius: 3px; }

/* Item row */
.item-row {
    padding: 14px 18px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.item-row:last-child { border-bottom: 0; }
.item-name { font-size: 17px; font-weight: 700; line-height: 1.2; }
.item-sku  { font-size: 12px; color: #6c757d; margin-top: 3px; }
.item-do   { font-size: 11px; color: #adb5bd; margin-top: 2px; }
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
    padding: 10px 16px 10px;
    z-index: 1038;
    box-shadow: 0 -4px 16px rgba(0,0,0,.12);
    transition: left .3s ease;
}
/* Sidebar collapsed (icon-only mode) */
.sidebar-collapse #scanBar { left: 70px; }
/* Mobile — sidebar jadi overlay, scan bar full width */
@media (max-width: 991px) {
    #scanBar { left: 0; }
}
#scanBar .inner { max-width: 100%; }
#scanInput { height: 54px; font-size: 20px; font-weight: 700; border-radius: 8px; }
#btnCamera { width: 54px; height: 54px; border-radius: 8px; font-size: 19px; flex-shrink: 0; }
#btnScan   { height: 54px; font-size: 16px; font-weight: 700; border-radius: 8px; padding: 0 20px; flex-shrink: 0; }
#scanMsg   { font-size: 12px; margin-top: 5px; min-height: 0; text-align: center; }

/* All done state */
.all-done-screen { text-align: center; padding: 70px 20px; }

/* Toastr custom size for operator */
#toast-container.toast-top-center { top: 20px; }
#toast-container.toast-top-center > .toast {
    font-size: 16px;
    padding: 16px 20px;
    min-width: 280px;
    max-width: 420px;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.25);
}
#toast-container .toast-success { background-color: #0d8564 !important; }
#toast-container .toast-title { font-size: 14px; font-weight: 700; }
#toast-container .toast-message { margin-top: 4px; line-height: 1.4; }

/* Counter bump animation */
@keyframes counterBump {
    0%   { transform: scale(1); }
    35%  { transform: scale(1.18); color: #a8e6cf; }
    100% { transform: scale(1); }
}
#itemCounter.bumping { animation: counterBump 0.5s ease; }

/* ── Mobile (< 576px) ───────────────────────────────────────── */
@media (max-width: 575px) {
    .op-header { padding: 14px 14px 12px; }
    .op-header h2 { font-size: 20px; }
    .loc-code { font-size: 28px; letter-spacing: 1px; }
    .loc-sub  { font-size: 12px; }
    .loc-header { padding: 10px 14px; }
    .item-row { padding: 10px 14px; gap: 8px; }
    .item-name { font-size: 15px; }
    .qty-box { font-size: 18px; min-width: 68px; padding: 6px 10px; }
    #scanInput { height: 48px; font-size: 15px; }
    #btnCamera { width: 48px; height: 48px; font-size: 16px; }
    #btnScan   { height: 48px; font-size: 14px; padding: 0 12px; }
    #scanBar   { padding: 8px 12px 8px; }
    .container-fluid { padding-left: 8px !important; padding-right: 8px !important; }
    .cap-bar { width: 70px; }
}

/* ── Tablet portrait (576–767px) ────────────────────────────── */
@media (min-width: 576px) and (max-width: 767px) {
    .loc-code { font-size: 32px; }
    .item-name { font-size: 16px; }
}
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-3" id="mainContent">

    {{-- ── Nav tombol ──────────────────────────────────────────────────── --}}
    <div class="d-flex justify-content-end mb-3" style="gap:8px;">
        <a href="{{ route('putaway.queue') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-list mr-1"></i> Tampilan Lengkap
        </a>
        <a href="{{ route('putaway.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="op-header">
        <div>
            <div style="font-size:12px;opacity:.8;text-transform:uppercase;letter-spacing:1px;">
                <i class="fas fa-hard-hat mr-1"></i> Mode Operator — Put-Away
            </div>
            <h2 id="itemCounter">
                @if($items->isEmpty())
                    Semua selesai!
                @else
                    {{ $items->count() }} item menunggu
                @endif
            </h2>
            <small>
                <i class="fas fa-info-circle mr-1"></i>
                Taruh barang → scan QR cell → konfirmasi
            </small>
        </div>
    </div>

    @if($items->isEmpty())

        <div class="all-done-screen">
            <i class="fas fa-check-circle fa-5x text-success mb-3" style="display:block;"></i>
            <h3 class="text-success font-weight-bold">Tidak ada item yang perlu di-put-away</h3>
            <p class="text-muted">Semua sudah dikonfirmasi atau belum ada DO aktif hari ini.</p>
            <a href="{{ route('putaway.index') }}" class="btn btn-primary btn-lg mt-2">
                <i class="fas fa-home mr-1"></i> Beranda Put-Away
            </a>
        </div>

    @else

        <div class="alert alert-info border-0 py-2 mb-3" style="font-size:13px;">
            <i class="fas fa-route mr-1"></i>
            Item diurutkan berdasarkan lokasi fisik — ikuti urutan ini untuk jalur tercepat di gudang.
        </div>

        {{-- ── Item groups per lokasi ──────────────────────────────────── --}}
        <div id="itemList">
            @php $groups = $items->groupBy('cell_id'); @endphp

            @foreach($groups as $cellId => $groupItems)
                @php
                    $cell = $groupItems->first()->cell;
                    $rack = $cell?->rack;

                    // Location code + breakdown
                    if ($cell && !is_null($cell->blok)) {
                        $locCode = $cell->physical_code;
                        $parts   = array_filter([
                            'Blok '  . $cell->blok,
                            'Grup '  . strtoupper($cell->grup ?? ''),
                            !is_null($cell->kolom) ? 'Kolom ' . $cell->kolom : null,
                            !is_null($cell->baris) ? 'Baris ' . $cell->baris  : null,
                        ]);
                        $locSub  = implode(' · ', $parts);
                    } elseif ($rack) {
                        $lvLetter = $cell?->level ? chr(64 + $cell->level) : '?';
                        $lvCol    = $cell?->column ?? 1;
                        $locCode  = $rack->code . '-' . $lvLetter
                                  . ($rack->total_columns > 1 ? $lvCol : '');
                        $locSub   = 'Rak ' . $rack->code . ' · Level ' . $lvLetter
                                  . ($rack->total_columns > 1 ? ' · Kolom ' . $lvCol : '');
                    } else {
                        $locCode = $cell?->code ?? '—';
                        $locSub  = '';
                    }

                    $zone     = $cell?->zone_category ?? null;
                    $capUsed  = $cell?->capacity_used ?? 0;
                    $capMax   = $cell?->capacity_max  ?? 100;
                    $capPct   = $capMax > 0 ? min(100, round($capUsed / $capMax * 100)) : 0;
                    $capColor = $capPct >= 80 ? '#ff6b6b' : ($capPct >= 40 ? '#ffc107' : '#a8e6cf');
                    $statusLabel = match($cell?->status ?? 'available') {
                        'available' => 'Tersedia',
                        'partial'   => 'Sebagian terisi',
                        'full'      => 'Penuh',
                        default     => 'Diblokir',
                    };
                @endphp

                <div class="loc-group" id="group-{{ $cellId }}" data-cell-id="{{ $cellId }}">

                    {{-- Location header --}}
                    <div class="loc-header d-flex align-items-start justify-content-between">
                        <div>
                            <div class="loc-code">{{ $locCode }}</div>
                            @if($locSub)
                                <div class="loc-sub">{{ $locSub }}</div>
                            @endif
                            @if($zone)
                                <div class="mt-2">
                                    <span style="background:rgba(255,255,255,.2);border-radius:4px;padding:3px 10px;font-size:12px;">
                                        <i class="fas fa-tag mr-1"></i>{{ $zone }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <div class="text-right" style="flex-shrink:0;margin-left:12px;">
                            <div style="font-size:13px;">{{ $statusLabel }}</div>
                            <div class="loc-cap">{{ $capUsed }}/{{ $capMax }}</div>
                            <div class="cap-bar">
                                <div class="cap-fill" style="background:{{ $capColor }};width:{{ $capPct }}%;"></div>
                            </div>
                            <div class="mt-2" style="font-size:13px;opacity:.85;">
                                <i class="fas fa-box mr-1"></i>{{ $groupItems->count() }} item
                            </div>
                        </div>
                    </div>

                    {{-- Item rows --}}
                    @foreach($groupItems as $item)
                        @php
                            $itm  = $item->inboundOrderItem?->item;
                            $qty  = $item->quantity;
                            $unit = $itm?->unit?->code ?? 'pcs';
                            $do   = $item->gaRecommendation?->inboundOrder?->do_number ?? '-';
                        @endphp
                        <div class="item-row" id="row-{{ $item->id }}" data-ga-id="{{ $item->id }}">
                            <div style="flex:1;min-width:0;">
                                <div class="item-name">{{ $itm?->name ?? '-' }}</div>
                                <div class="item-sku">SKU: {{ $itm?->sku ?? '-' }}</div>
                                <div class="item-do"><i class="fas fa-file-alt mr-1"></i>DO: {{ $do }}</div>
                            </div>
                            <div class="qty-box">
                                {{ $qty }}
                                <div class="qty-unit">{{ $unit }}</div>
                            </div>
                        </div>
                    @endforeach

                </div>
            @endforeach
        </div>

        {{-- Spacer so the last card can scroll fully above the fixed scan bar --}}
        <div id="scrollSpacer" style="height:200px;"></div>

    @endif
</div>

{{-- ── Fixed scan bar ─────────────────────────────────────────────────── --}}
@if($items->isNotEmpty())
<div id="scanBar">
    <div class="d-flex" style="gap:8px;">
        <button type="button" id="btnCamera" class="btn btn-outline-secondary" title="Scan dengan kamera">
            <i class="fas fa-camera"></i>
        </button>
        <input type="text" id="scanInput" class="form-control"
            placeholder="Scan / ketik kode QR cell..." autocomplete="off" inputmode="text"
            style="min-width:0;">
        <button type="button" id="btnScan" class="btn" style="background:#0d8564;color:#fff;border-color:#0d8564;">
            <i class="fas fa-search d-sm-none"></i>
            <span class="d-none d-sm-inline"><i class="fas fa-search mr-1"></i> Cari</span>
        </button>
    </div>
    <div id="scanMsg" class="text-muted" style="font-size:12px;margin-top:5px;text-align:center;">
        <i class="fas fa-qrcode mr-1"></i> Siap scan — taruh barang lalu scan QR label cell
    </div>
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
                <button type="button" id="btnDoConfirm" class="btn" style="background:#0d8564;color:#fff;border-color:#0d8564;font-weight:700;" disabled
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
    setScanMsg('<i class="fas fa-qrcode mr-1"></i> Siap scan — taruh barang lalu scan QR label cell', 'text-muted');
}

function doScan(code) {
    if (!code) {
        setScanMsg('<i class="fas fa-exclamation-triangle mr-1"></i>Masukkan kode terlebih dahulu.', 'text-warning');
        return;
    }
    // Strip full URL from camera scan (e.g. http://host/c/1-A-1 → 1-A-1)
    if (code.indexOf('/c/') !== -1) {
        code = code.split('/c/').pop().replace(/\/+$/, '').trim();
        $('#scanInput').val(code);
    }
    setScanMsg('<i class="fas fa-spinner fa-spin mr-1"></i>Mencari...', 'text-muted');
    $('#confirmBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#btnDoConfirm').prop('disabled', true);

    $.getJSON(batchScanUrl, { qr_code: code, override: 0, all_active: 1 })
        .done(function(res) {
            if (res.status !== 'found' || !res.items || !res.items.length) {
                setScanMsg(
                    '<i class="fas fa-times-circle mr-1 text-danger"></i>' +
                    (res.message || 'Tidak ada item untuk cell ini.'),
                    'text-danger'
                );
                return;
            }
            pendingItems = res.items;
            buildConfirmModal(res);
            $('#modalConfirm').modal('show');
            resetScanMsg();
        })
        .fail(function(xhr) {
            setScanMsg(
                '<i class="fas fa-times-circle mr-1"></i>' +
                (xhr.responseJSON?.message || 'Gagal memuat data. Coba lagi.'),
                'text-danger'
            );
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
                var cellIds = [...new Set(pendingItems.map(function(i) { return i.cell_id; }))];
                var cellIdStrs = cellIds.map(String);

                // Find NEXT location BEFORE removing groups
                var nextLocation = null;
                $('#itemList .loc-group').each(function() {
                    var cid = String($(this).data('cell-id'));
                    if (cellIdStrs.indexOf(cid) === -1) {
                        nextLocation = $(this).find('.loc-code').first().text().trim();
                        return false; // break
                    }
                });

                // Remove item rows
                pendingItems.forEach(function(item) {
                    $('#row-' + item.ga_detail_id).fadeOut(200, function() { $(this).remove(); });
                });

                // Remove empty groups after rows are gone
                setTimeout(function() {
                    cellIds.forEach(function(cid) {
                        var grp = $('#group-' + cid);
                        if (grp.find('.item-row').length === 0) {
                            grp.addClass('removing');
                            setTimeout(function() { grp.remove(); }, 320);
                        }
                    });
                }, 250);

                // Update counter with bump animation
                var confirmed = res.confirmed_count || pendingItems.length;
                totalRemaining -= confirmed;
                if (totalRemaining <= 0) {
                    setTimeout(showAllDone, 700);
                } else {
                    var $ctr = $('#itemCounter');
                    $ctr.text(totalRemaining + ' item menunggu')
                        .addClass('bumping');
                    setTimeout(function() { $ctr.removeClass('bumping'); }, 600);
                }

                // Toast: success + next location
                var toastMsg = confirmed + ' item berhasil disimpan.';
                if (nextLocation) {
                    toastMsg += '<br><strong style="font-size:18px;">→ ' + nextLocation + '</strong>';
                }
                toastr.success(toastMsg, 'Put-Away Berhasil', {
                    timeOut: 4000,
                    extendedTimeOut: 2000,
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-center',
                    enableHtml: true,
                    toastClass: 'toast',
                    titleClass: 'toast-title',
                    messageClass: 'toast-message',
                });
                resetScanMsg();
                pendingItems = null;
                focusScan();
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

var html5QrCode = null;

$('#btnCamera').on('click', function() {
    $('#modalCamera').modal('show');
});

$('#modalCamera').on('shown.bs.modal', function() {
    html5QrCode = new Html5Qrcode('camReader');
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        function(decoded) {
            html5QrCode.stop().then(function() {
                $('#modalCamera').modal('hide');
                $('#scanInput').val(decoded);
                doScan(decoded);
            }).catch(function() {});
        }
    ).catch(function(err) {
        setScanMsg('<i class="fas fa-times-circle mr-1"></i>Kamera tidak dapat diakses.', 'text-danger');
        $('#modalCamera').modal('hide');
    });
});

$('#modalCamera').on('hidden.bs.modal', function() {
    if (html5QrCode) {
        html5QrCode.stop().catch(function() {});
        html5QrCode = null;
        $('#camReader').empty();
    }
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
    var barH = document.getElementById('scanBar').offsetHeight || 0;
    var spacer = document.getElementById('scrollSpacer');
    if (spacer) spacer.style.height = Math.max(200, barH + 80) + 'px';
}

(function() {
    adjustSpacer();
    if (window.ResizeObserver) {
        new ResizeObserver(adjustSpacer).observe(document.getElementById('scanBar'));
    }
    window.addEventListener('resize', adjustSpacer);
    [100, 300, 700, 1500].forEach(function(t) { setTimeout(adjustSpacer, t); });
})();

$(document).ready(function() { $('#scanInput').focus(); });
</script>
@endpush
