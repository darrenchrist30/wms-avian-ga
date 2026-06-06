@extends('layouts.adminlte')
@section('title', 'Detail Request ' . $obr->request_number)

@push('styles')
<style>
.signature-wrapper {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
    position: relative;
    overflow: hidden;
}
canvas#signatureCanvas {
    display: block;
    cursor: crosshair;
    background: #fff;
    width: 100%;
    height: 160px;
}
.signature-actions {
    padding: 6px 10px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 6px;
}
.status-timeline {
    position: relative;
    padding-left: 28px;
}
.status-timeline::before {
    content: '';
    position: absolute;
    left: 10px; top: 0; bottom: 0;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    padding-bottom: 14px;
}
.timeline-dot {
    position: absolute;
    left: -22px; top: 3px;
    width: 14px; height: 14px;
    border-radius: 50%;
    border: 2px solid #dee2e6;
    background: #fff;
}
.timeline-dot.active { background: #0d8564; border-color: #0d8564; }
.timeline-dot.done   { background: #28a745; border-color: #28a745; }
.timeline-dot.danger { background: #dc3545; border-color: #dc3545; }
</style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">
            <i class="fas fa-clipboard-list mr-2 text-primary"></i>Detail Permintaan Outbound
        </h4>
        <a href="{{ route('outbound.requests.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i>Kembali
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Informasi Request — full width tabel horizontal ────────── --}}
    <div class="card mb-3">
        <div class="card-header py-2">
            <strong><i class="fas fa-info-circle mr-1"></i> Informasi Request</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th class="font-weight-bold" style="white-space:nowrap;">No. Request</th>
                        <th class="font-weight-bold" style="white-space:nowrap;">Operator</th>
                        <th class="font-weight-bold" style="white-space:nowrap;">Gudang</th>
                        <th class="font-weight-bold" style="white-space:nowrap;">Waktu Pengajuan</th>
                        @if($obr->notes)
                        <th class="font-weight-bold" style="white-space:nowrap;">Catatan</th>
                        @endif
                        <th class="font-weight-bold text-center" style="white-space:nowrap;">Status</th>
                        @if($obr->isRejected())
                        <th class="font-weight-bold" style="white-space:nowrap;">Alasan Ditolak</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>{{ $obr->request_number }}</strong></td>
                        <td>{{ $obr->operator->name }}</td>
                        <td>{{ $obr->warehouse->name }}</td>
                        <td style="white-space:nowrap;">{{ $obr->created_at->locale('id')->isoFormat('dddd, D MMM Y HH:mm') }}</td>
                        @if($obr->notes)
                        <td>{{ $obr->notes }}</td>
                        @endif
                        <td class="text-center">
                            <span class="badge {{ $obr->status_badge_class }} px-2 py-1">{{ $obr->status_label }}</span>
                        </td>
                        @if($obr->isRejected())
                        <td><span class="text-danger">{{ $obr->reject_reason }}</span></td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Item yang Diminta — full width ─────────────────────────── --}}
    <div class="card mb-3">
        <div class="card-header py-2">
            <strong><i class="fas fa-boxes mr-1"></i> Item Outbound</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered table-striped mb-0">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center font-weight-bold" width="40">#</th>
                        <th class="font-weight-bold">Item / SKU</th>
                        <th class="text-center font-weight-bold" width="140" style="white-space:nowrap;">Qty Diminta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($obr->items as $i => $it)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>
                            <strong>{{ $it->item->name }}</strong>
                            <small class="text-muted d-block">{{ $it->item->sku }}</small>
                        </td>
                        <td class="text-center font-weight-bold">
                            {{ number_format($it->quantity_requested) }}
                            <small class="text-muted">{{ $it->item->unit?->code }}</small>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Status Proses — hanya tampil untuk operator ───────────── --}}
    @if(!auth()->user()->hasRole(['admin', 'supervisor']))
    <div class="card mb-3">
        <div class="card-header py-2">
            <strong><i class="fas fa-stream mr-1"></i> Status Proses</strong>
        </div>
        <div class="card-body py-3">
            <div class="d-flex" style="gap:48px;flex-wrap:wrap;">
                <div class="d-flex align-items-start" style="gap:10px;">
                    <div style="width:14px;height:14px;border-radius:50%;background:#28a745;border:2px solid #28a745;flex-shrink:0;margin-top:3px;"></div>
                    <div>
                        <div class="font-weight-bold" style="font-size:13px;">Request Diajukan</div>
                        <div class="text-muted" style="font-size:11px;">{{ $obr->created_at->format('d M Y, H:i') }}</div>
                    </div>
                </div>
                <div class="d-flex align-items-start" style="gap:10px;">
                    @php
                        $dot2 = $obr->isApproved() || $obr->isCompleted() ? '#28a745' : ($obr->isRejected() ? '#dc3545' : '#0d8564');
                    @endphp
                    <div style="width:14px;height:14px;border-radius:50%;background:{{ $dot2 }};border:2px solid {{ $dot2 }};flex-shrink:0;margin-top:3px;"></div>
                    <div>
                        <div class="font-weight-bold" style="font-size:13px;">
                            @if($obr->isApproved() || $obr->isCompleted()) Disetujui Supervisor
                            @elseif($obr->isRejected()) Ditolak Supervisor
                            @else Menunggu Persetujuan
                            @endif
                        </div>
                        @if($obr->approved_at)
                            <div class="text-muted" style="font-size:11px;">{{ $obr->approved_at->format('d M Y, H:i') }} · {{ $obr->approvedBy?->name }}</div>
                        @elseif($obr->rejected_at)
                            <div class="text-muted" style="font-size:11px;">{{ $obr->rejected_at->format('d M Y, H:i') }} · {{ $obr->rejectedBy?->name }}</div>
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-start" style="gap:10px;">
                    @php $dot3 = $obr->isCompleted() ? '#28a745' : '#dee2e6'; @endphp
                    <div style="width:14px;height:14px;border-radius:50%;background:{{ $dot3 }};border:2px solid {{ $dot3 }};flex-shrink:0;margin-top:3px;"></div>
                    <div>
                        <div class="font-weight-bold" style="font-size:13px;">
                            @if($obr->isCompleted()) Outbound Dilakukan
                            @else Outbound Belum Dilakukan
                            @endif
                        </div>
                        @if($obr->executed_at)
                            <div class="text-muted" style="font-size:11px;">{{ $obr->executed_at->format('d M Y, H:i') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Tanda Tangan Supervisor yang sudah approved ──────────────── --}}
    @if($obr->isApproved() && $obr->signature_path)
    <div class="card mb-3">
        <div class="card-header py-2">
            <strong><i class="fas fa-signature mr-1 text-success"></i> Tanda Tangan Supervisor</strong>
        </div>
        <div class="card-body text-center">
            <img src="{{ asset($obr->signature_path) }}" alt="Tanda Tangan"
                style="max-height:120px;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
            <div class="mt-2 text-muted" style="font-size:12px;">
                Disetujui oleh <strong>{{ $obr->approvedBy?->name }}</strong>
                pada {{ $obr->approved_at?->locale('id')->isoFormat('D MMMM Y, HH:mm') }}
            </div>
        </div>
    </div>
    @endif

    {{-- ── Aksi Operator: Lanjutkan Outbound ──────────────────────── --}}
    @if($obr->isApproved() && $obr->operator_id === auth()->id())
    <div class="card mb-3 border-success">
        <div class="card-body text-center py-3">
            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
            <div class="font-weight-bold mb-2">Request telah disetujui!</div>
            <a href="{{ route('outbound.create', ['request_id' => $obr->id]) }}"
               class="btn"
               style="background:#0d8564;color:#fff;border-color:#0d8564;transition:background .15s,border-color .15s;"
               onmouseenter="this.style.background='#0a6e52';this.style.borderColor='#0a6e52'"
               onmouseleave="this.style.background='#0d8564';this.style.borderColor='#0d8564'">
                <i class="fas fa-arrow-right mr-1"></i>Lanjutkan Proses Outbound
            </a>
        </div>
    </div>
    @endif

    {{-- ── Aksi Supervisor: Tanda Tangan di bawah ─────────────────── --}}
    @if($obr->isPending() && auth()->user()->hasRole(['admin', 'supervisor']))
    <div class="card mb-3 mx-auto" style="border:1.5px solid #ffc107;max-width:400px;">
        <div class="card-header py-2" style="background:#fff3cd;border-bottom:1px solid #ffc107;">
            <strong><i class="fas fa-signature mr-1"></i> Tanda Tangan Persetujuan</strong>
        </div>
        <div class="card-body p-3">
            <div class="signature-wrapper mb-2">
                <canvas id="signatureCanvas" width="600" height="200"></canvas>
                <div class="signature-actions">
                    <button type="button" id="btnClearSig" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eraser mr-1"></i>Hapus
                    </button>
                </div>
            </div>
            <div class="d-flex" style="gap:8px;">
                <button type="button" id="btnApprove" class="btn flex-fill"
                    style="background:#0d8564;color:#fff;border-color:#0d8564;transition:background .15s,border-color .15s;"
                    onmouseenter="this.style.background='#0a6e52';this.style.borderColor='#0a6e52'"
                    onmouseleave="this.style.background='#0d8564';this.style.borderColor='#0d8564'">
                    <i class="fas fa-check mr-1"></i>Setujui
                </button>
                <button type="button" id="btnReject" class="btn btn-outline-danger flex-fill">
                    <i class="fas fa-times mr-1"></i>Tolak
                </button>
            </div>
        </div>
    </div>
    @endif

</div>

{{-- Modal: Alasan Penolakan --}}
<div class="modal fade" id="modalReject" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title font-weight-bold text-danger">
                    <i class="fas fa-times-circle mr-1"></i>Tolak Request
                </h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                {{-- <p class="text-muted" style="font-size:13px;">
                    Request <strong>{{ $obr->request_number }}</strong> akan ditolak. Operator akan mendapat notifikasi WA.
                </p> --}}
                <div class="form-group mb-0">
                    <label class="font-weight-bold small">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="rejectReason" class="form-control" rows="3"
                        placeholder="Jelaskan alasan penolakan..."></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" id="btnConfirmReject" class="btn btn-sm btn-danger">
                    <i class="fas fa-times mr-1"></i>Tolak Request
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/signature_pad.min.js') }}"></script>
<script>
var csrfToken  = $('meta[name="csrf-token"]').attr('content');
var approveUrl = '{{ route("outbound.requests.approve", $obr->id) }}';
var rejectUrl  = '{{ route("outbound.requests.reject", $obr->id) }}';

@if($obr->isPending() && auth()->user()->hasRole(['admin', 'supervisor']))

var canvas       = document.getElementById('signatureCanvas');
var signaturePad = new SignaturePad(canvas, {
    backgroundColor: 'rgba(255,255,255,0)',
    penColor: 'rgb(0,0,0)',
    minWidth: 1, maxWidth: 3,
});

function resizeCanvas() {
    var ratio   = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width  = canvas.offsetWidth  * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    signaturePad.clear();
}
window.addEventListener('resize', resizeCanvas);
resizeCanvas();

@if($userSigUrl)
var userSigUrl = '{{ $userSigUrl }}';
var sigImg = new Image();
sigImg.onload = function() {
    var ctx = canvas.getContext('2d');
    var scale = window.devicePixelRatio || 1;
    ctx.drawImage(sigImg, 0, 0, canvas.offsetWidth, canvas.offsetHeight);
};
sigImg.src = userSigUrl + '?t=' + Date.now();
@endif

$('#btnClearSig').on('click', function () { signaturePad.clear(); });

$('#btnApprove').on('click', function () {
    if (signaturePad.isEmpty()) {
        Swal.fire('Tanda Tangan Kosong', 'Silakan tanda tangan terlebih dahulu.', 'warning');
        return;
    }
    Swal.fire({
        title: 'Setujui Request?',
        html: '',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d8564',
        confirmButtonText: '<i class="fas fa-check mr-1"></i>Ya, Setujui',
        cancelButtonText: 'Batal',
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $('#btnApprove').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menyetujui...');
        $.ajax({
            url: approveUrl, type: 'POST',
            data: { _token: csrfToken, signature: signaturePad.toDataURL('image/png') },
            success: function (res) {
                Swal.fire('Disetujui!', res.message, 'success').then(function () { location.reload(); });
            },
            error: function (xhr) {
                Swal.fire('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan.', 'error');
                $('#btnApprove').prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Setujui');
            }
        });
    });
});

$('#btnReject').on('click', function () {
    $('#rejectReason').val('');
    $('#modalReject').modal('show');
});

$('#btnConfirmReject').on('click', function () {
    var reason = $.trim($('#rejectReason').val());
    if (!reason) { $('#rejectReason').addClass('is-invalid'); return; }
    $('#rejectReason').removeClass('is-invalid');
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menolak...');
    $.ajax({
        url: rejectUrl, type: 'POST',
        data: { _token: csrfToken, reject_reason: reason },
        success: function (res) {
            $('#modalReject').modal('hide');
            Swal.fire('Ditolak', res.message, 'info').then(function () { location.reload(); });
        },
        error: function (xhr) {
            Swal.fire('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan.', 'error');
            $('#btnConfirmReject').prop('disabled', false).html('<i class="fas fa-times mr-1"></i>Tolak Request');
        }
    });
});

@endif
</script>
@endpush
