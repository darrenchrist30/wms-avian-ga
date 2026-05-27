@extends('layouts.adminlte')
@section('title', 'Detail Stok: ' . $item->name)

@section('content')
@php
    $statusCfg = [
        'ok'       => ['#16a34a', 'Stok Aman',     'fas fa-check-circle'],
        'reorder'  => ['#b45309', 'Perlu Reorder', 'fas fa-exclamation-circle'],
        'critical' => ['#dc2626', 'Stok Kritis',   'fas fa-exclamation-triangle'],
        'empty'    => ['#6b7280', 'Stok Habis',    'fas fa-times-circle'],
    ];
    [$sCls, $sLabel, $sIcon] = $statusCfg[$stockStatus];
    $canTransferStock = auth()->user()->isAdmin()
        || auth()->user()->isSupervisor()
        || auth()->user()->isOperator();
@endphp
<div class="container-fluid pb-4">

{{-- Breadcrumb --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-search-location text-primary mr-2"></i>Detail Stok Item
        </h5>
        <small class="text-muted">
            <a href="{{ route('stock.index') }}">Stok Saat Ini</a>
            <i class="fas fa-chevron-right mx-1" style="font-size:9px;"></i>
            {{ $item->name }}
        </small>
    </div>
    <a href="{{ route('stock.index') }}" class="btn btn-sm btn-light border">
        <i class="fas fa-arrow-left mr-1"></i>Kembali
    </a>
</div>

{{-- Flash alert transfer --}}
<div id="transferAlert" class="alert d-none mb-3" role="alert"></div>

{{-- Item Info Card --}}
<div class="card mb-3">
    <div class="card-header py-2">
        <span class="font-weight-bold">
            <i class="fas fa-box mr-1 text-primary"></i>Informasi Item
        </span>
        <span class="ml-3" style="font-size:12px; font-weight:600; color:{{ $sCls }};">
            <i class="{{ $sIcon }} mr-1"></i>{{ $sLabel }}
        </span>
    </div>
    <div class="card-body py-3">
        <div class="row">
            <div class="col-6 col-md-3 mb-2">
                <small class="text-muted text-uppercase" style="font-size:10.5px;letter-spacing:.5px;">Nama Item</small>
                <div class="font-weight-bold mt-1">{{ $item->name }}</div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <small class="text-muted text-uppercase" style="font-size:10.5px;letter-spacing:.5px;">SKU</small>
                <div class="font-weight-bold mt-1">{{ $item->sku }}</div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <small class="text-muted text-uppercase" style="font-size:10.5px;letter-spacing:.5px;">Kategori</small>
                <div class="mt-1">
                    @if($item->category)
                        <span class="badge px-2" style="background:{{ $item->category->color_code ?? '#6c757d' }};color:#fff;">
                            {{ $item->category->name }}
                        </span>
                    @else —
                    @endif
                </div>
            </div>
            <div class="col-6 col-md-1 mb-2">
                <small class="text-muted text-uppercase" style="font-size:10.5px;letter-spacing:.5px;">Satuan</small>
                <div class="font-weight-bold mt-1">{{ $item->unit?->code ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <small class="text-muted text-uppercase" style="font-size:10.5px;letter-spacing:.5px;">Min / Max Stok</small>
                <div class="mt-1">
                    <span class="text-danger font-weight-bold">{{ number_format($minStock) }}</span>
                    <span class="text-muted"> / </span>
                    <span class="font-weight-bold">{{ number_format($maxStock) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Summary mini cards --}}
<div class="row mb-3">
    <div class="col-6 mb-2">
        <div class="small-box mb-0" style="background-color:{{ $sCls }}; color:#fff;">
            <div class="inner">
                <h4 style="color:#fff;">{{ number_format($totalQty) }}</h4>
                <p>Total Qty Tersedia</p>
            </div>
            <div class="icon"><i class="fas fa-cubes"></i></div>
        </div>
    </div>
    <div class="col-6 mb-2">
        <div class="small-box mb-0 {{ $cellCount > 1 ? 'bg-warning' : 'bg-info' }}">
            <div class="inner">
                <h4>{{ $cellCount }}</h4>
                <p>
                    Jumlah Lokasi (Cell)
                    @if($cellCount > 1)
                        <br><small style="font-size:10px;opacity:.9;">&#9888; Split Location</small>
                    @endif
                </p>
            </div>
            <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
        </div>
    </div>
</div>

{{-- Lokasi Stok per Cell (FIFO Order) --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold">
            <i class="fas fa-map-marker-alt mr-1 text-primary"></i>
            Posisi Stok per Cell
            @if($cellCount > 1)
                <span class="badge badge-warning ml-1 text-dark">
                    <i class="fas fa-exclamation-triangle mr-1"></i>Split Location: {{ $cellCount }} lokasi
                </span>
            @endif
        </span>
        {{-- <small class="text-muted">Urut dari tanggal masuk paling lama → yang harus diambil pertama</small> --}}
    </div>
    <div class="card-body p-0">
        @if($stocks->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-2 d-block"></i>
                Tidak ada stok tersedia untuk item ini.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="50">FIFO</th>
                        <th width="120">Cell</th>
                        <th width="80">Rak</th>
                        <th>Gudang</th>
                        <th class="text-center" width="70">Qty</th>
                        <th class="text-center" width="110">Tgl Masuk</th>
                        <th class="text-center" width="110">Tidak Gerak</th>
                        <th class="text-center" width="90">Status</th>
                        @if($canTransferStock)
                        <th class="text-center" width="80">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $i => $s)
                    @php
                        $deadDays = $s->inbound_date ? (int) now()->diffInDays($s->inbound_date) : null;
                    @endphp
                    <tr>
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            <strong>{{ $s->cell?->physical_code ?? '—' }}</strong>
                            @if($s->cell_id)
                                <br>
                                <button class="btn btn-xs btn-outline-secondary mt-1 btn-view3d"
                                    data-cell-id="{{ $s->cell_id }}"
                                    data-cell-code="{{ $s->cell?->physical_code }}"
                                    title="Lihat detail cell">
                                    <i class="fas fa-map-marker-alt mr-1"></i>Detail
                                </button>
                            @endif
                        </td>
                        <td>{{ $s->cell?->rack?->code ?? '—' }}</td>
                        <td>{{ $s->warehouse?->name ?? '—' }}</td>
                        <td class="text-center font-weight-bold">{{ number_format($s->quantity) }}</td>
                        <td class="text-center">{{ $s->inbound_date?->format('d M Y') ?? '—' }}</td>
                        <td class="text-center">
                            @if($deadDays === null)
                                <span class="text-muted">—</span>
                            @elseif($deadDays > 90)
                                <span class="text-danger font-weight-bold">{{ $deadDays }} hari</span>
                            @elseif($deadDays > 30)
                                <span class="text-warning font-weight-bold">{{ $deadDays }} hari</span>
                            @else
                                <span class="text-muted">{{ $deadDays }} hari</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @php
                                $statusMap = [
                                    'available'  => ['success', 'Tersedia'],
                                    'reserved'   => ['warning', 'Reserved'],
                                    'quarantine' => ['danger',  'Karantina'],
                                    'expired'    => ['dark',    'Expired'],
                                ];
                                [$sBadge, $sText] = $statusMap[$s->status] ?? ['secondary', $s->status];
                            @endphp
                            <span class="badge badge-{{ $sBadge }}">{{ $sText }}</span>
                        </td>
                        @if($canTransferStock)
                        <td class="text-center">
                            @if($s->status === 'available' && $s->quantity > 0)
                            <button class="btn btn-xs btn-outline-primary btn-transfer"
                                data-stock-id="{{ $s->id }}"
                                data-from-cell="{{ $s->cell?->code ?? '—' }}"
                                data-max-qty="{{ $s->quantity }}"
                                title="Transfer ke cell lain">
                                <i class="fas fa-exchange-alt"></i>
                            </button>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="font-weight-bold bg-light">
                    <tr>
                        <td colspan="4" class="text-right pr-2">Total:</td>
                        <td class="text-center">{{ number_format($totalQty) }}</td>
                        @if($canTransferStock)
                        <td colspan="4"></td>
                        @else
                        <td colspan="3"></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Modal Transfer Stok --}}
@if($canTransferStock)
<div class="modal fade" id="modalTransfer" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title font-weight-bold">
                    <i class="fas fa-exchange-alt mr-1 text-primary"></i>Transfer Stok
                </h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-2">
                    <label class="small font-weight-bold">Item</label>
                    <div class="form-control form-control-sm bg-light">{{ $item->name }}</div>
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold">Dari Cell</label>
                    <div class="form-control form-control-sm bg-light" id="tfFromCell"></div>
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold">Cell Tujuan <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="tfToCellCode" class="form-control"
                            placeholder="Ketik kode cell lalu Enter atau klik Cari">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="btnLookupCell">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>
                    <div id="tfCellInfo" class="mt-1 small d-none"></div>
                    <div id="tfBarisPicker" class="d-none mt-2">
                        <div class="small font-weight-bold mb-1 text-muted">Pilih baris (level):</div>
                        <div id="tfBarisOptions" class="d-flex flex-wrap" style="gap:6px;"></div>
                    </div>
                    <input type="hidden" id="tfToCellId">
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold">Jumlah <span class="text-danger">*</span></label>
                    <input type="number" id="tfQty" class="form-control form-control-sm"
                        min="1" placeholder="Masukkan jumlah">
                    <small class="text-muted">Maks: <span id="tfMaxQty"></span> unit tersedia di cell asal</small>
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Catatan</label>
                    <input type="text" id="tfNotes" class="form-control form-control-sm"
                        placeholder="Opsional" maxlength="255">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnDoTransfer">
                    <i class="fas fa-exchange-alt mr-1"></i>Transfer
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal Detail Cell 3D --}}
<div class="modal fade" id="modal3dCell" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#1a2332">
                <h6 class="modal-title text-white font-weight-bold mb-0">
                    <i class="fas fa-cube mr-1"></i>
                    Detail Cell: <span id="m3dCellCode">—</span>
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div id="m3dLoading" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-primary fa-2x mb-2 d-block"></i>
                    <small class="text-muted">Memuat data cell…</small>
                </div>
                <div id="m3dContent" style="display:none">
                    {{-- Info cell --}}
                    <div class="px-3 pt-3 pb-2 border-bottom" style="font-size:13px">
                        <div class="row">
                            <div class="col-6 mb-1">
                                <span class="text-muted d-block" style="font-size:10px;text-transform:uppercase">Rak / Zona / Gudang</span>
                                <span id="m3dLocation" class="font-weight-bold">—</span>
                            </div>
                            <div class="col-6 mb-1">
                                <span class="text-muted d-block" style="font-size:10px;text-transform:uppercase">Status Cell</span>
                                <span id="m3dStatus">—</span>
                            </div>
                            <div class="col-6 mb-1">
                                <span class="text-muted d-block" style="font-size:10px;text-transform:uppercase">Kapasitas</span>
                                <span id="m3dCapacity">—</span>
                            </div>
                            <div class="col-6 mb-1">
                                <span class="text-muted d-block" style="font-size:10px;text-transform:uppercase">Utilisasi</span>
                                <div class="d-flex align-items-center" style="gap:6px">
                                    <div style="flex:1;background:#e9ecef;border-radius:3px;height:8px;">
                                        <div id="m3dCapBar" style="height:100%;border-radius:3px;background:#28a745;width:0%"></div>
                                    </div>
                                    <small id="m3dCapPct" class="font-weight-bold">0%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Stok dalam cell ini --}}
                    <div class="px-3 py-2">
                        <small class="text-muted text-uppercase font-weight-bold" style="font-size:10px;letter-spacing:.4px">
                            Semua Item di Cell Ini
                        </small>
                        <div id="m3dStockList" class="mt-1"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <a id="m3dLink3D" href="{{ route('warehouse3d.index') }}" target="_blank"
                   class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-cube mr-1"></i>Buka Visualisasi 3D
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- Riwayat Mutasi Item Ini --}}
<div class="card">
    <div class="card-header py-2">
        <span class="font-weight-bold">
            <i class="fas fa-history mr-1 text-info"></i>Riwayat Mutasi Stok
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblMovements" class="table table-sm table-bordered table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th width="140">Waktu</th>
                        <th width="110" class="text-center">Tipe</th>
                        <th>Lokasi</th>
                        <th class="text-center" width="90">Qty</th>
                        <th width="140">Dilakukan Oleh</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
$(function () {
    // ── Riwayat mutasi DataTable ──────────────────────────────────────────
    $('#tblMovements').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("stock.show", $item->id) }}?type=movements',
        columns: [
            { data: 'date_display',     orderable: true  },
            { data: 'type_badge',       orderable: false, className: 'text-center' },
            { data: 'location_display', orderable: false },
            { data: 'qty_display',      orderable: false, className: 'text-center' },
            { data: 'by_display',       orderable: false },
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        language: { url: '/vendor/datatables/i18n/id.json' },
    });

    // ── Modal Detail Cell 3D ───────────────────────────────────────────────
    $(document).on('click', '.btn-view3d', function () {
        var cellCode = $(this).data('cell-code');
        if (cellCode && window.globalCellScan) {
            window.globalCellScan(cellCode);
        }
    });

    @if($canTransferStock)
    // ── Transfer modal ─────────────────────────────────────────────────────
    var currentStockId = null;

    $(document).on('click', '.btn-transfer', function () {
        currentStockId = $(this).data('stock-id');
        var fromCell   = $(this).data('from-cell');
        var maxQty     = $(this).data('max-qty');

        $('#tfFromCell').text(fromCell);
        $('#tfMaxQty').text(maxQty);
        $('#tfToCellCode').val('').trigger('input');
        $('#tfToCellId').val('');
        $('#tfCellInfo').addClass('d-none').text('');
        $('#tfQty').val('').attr('max', maxQty);
        $('#tfNotes').val('');
        $('#modalTransfer').modal('show');
        $('#modalTransfer').one('shown.bs.modal', function () {
            $('#tfToCellCode').focus();
        });
    });

    function resetBarisState() {
        $('#tfBarisPicker').addClass('d-none');
        $('#tfBarisOptions').empty();
        $('#tfToCellId').val('');
        $('#tfCellInfo').addClass('d-none').text('').removeClass('text-success text-danger');
    }

    // Lookup cell tujuan
    function doLookup() {
        var raw = $.trim($('#tfToCellCode').val());
        if (!raw) return;
        // Strip URL jika scan QR menghasilkan URL penuh: http://domain/c/CODE
        var code = raw.indexOf('/c/') !== -1
            ? raw.split('/c/').pop().split(/[/?# ]/)[0]
            : raw;
        $('#tfToCellCode').val(code);
        resetBarisState();

        $.get('{{ route("location.cells.lookup") }}', { code: code }, function (res) {
            // Kode kolom (tanpa baris) — tampilkan baris picker
            if (res.column_found) {
                var statusLabel = { available: 'Tersedia', partial: 'Sebagian', full: 'Penuh', blocked: 'Diblokir' };
                var statusColor = { available: '#0d8564', partial: '#f59e0b', full: '#ef4444', blocked: '#6b7280' };
                var html = '';
                $.each(res.column_cells, function (i, c) {
                    var color  = statusColor[c.status] || '#6b7280';
                    var label  = statusLabel[c.status] || c.status;
                    var sisa   = c.capacity_remaining + '/' + c.capacity_max;
                    html += '<button type="button" class="btn btn-sm btn-baris-pick" '
                          + 'data-cell-id="' + c.id + '" data-cell-code="' + c.code + '" '
                          + 'style="border:2px solid ' + color + ';color:' + color + ';background:#fff;border-radius:8px;padding:6px 12px;font-weight:600;">'
                          + 'Baris ' + c.baris
                          + '<br><small style="font-weight:400;font-size:10px;">' + label + ' · ' + sisa + '</small>'
                          + '</button>';
                });
                $('#tfBarisOptions').html(html);
                $('#tfBarisPicker').removeClass('d-none');
                $('#tfCellInfo').removeClass('d-none').addClass('text-muted')
                    .text('Kolom ' + res.column_code + ' ditemukan. Pilih baris di bawah.');
                return;
            }

            if (!res.found) {
                $('#tfCellInfo').removeClass('d-none text-success').addClass('text-danger').text(res.message);
                $('#tfToCellId').val('');
                return;
            }
            $('#tfToCellId').val(res.cell.id);
            var info = res.cell.code + ' — ' + res.cell.rack_zone + ' | Kapasitas sisa: ' + res.cell.capacity_remaining;
            $('#tfCellInfo').removeClass('d-none text-danger').addClass('text-success').text(info);
        });
    }

    $('#btnLookupCell').on('click', doLookup);
    $('#tfToCellCode').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doLookup(); } });

    // Klik tombol baris dari picker
    $(document).on('click', '.btn-baris-pick', function () {
        var cellId   = $(this).data('cell-id');
        var cellCode = $(this).data('cell-code');
        $('#tfToCellId').val(cellId);
        $('#tfToCellCode').val(cellCode);
        $('#tfBarisPicker').addClass('d-none');
        $('#tfCellInfo').removeClass('d-none text-danger text-muted').addClass('text-success')
            .text(cellCode + ' dipilih.');
    });

    // Eksekusi transfer
    $('#btnDoTransfer').on('click', function () {
        var toCellId = $('#tfToCellId').val();
        var qty      = parseInt($('#tfQty').val(), 10);
        var maxQty   = parseInt($('#tfQty').attr('max'), 10);

        if (!toCellId) { alert('Pilih cell tujuan terlebih dahulu.'); return; }
        if (!qty || qty < 1) { alert('Masukkan jumlah minimal 1.'); return; }
        if (qty > maxQty) { alert('Jumlah melebihi stok tersedia (' + maxQty + ').'); return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Proses...');

        $.ajax({
            url: '{{ route("stock.transfer") }}',
            method: 'POST',
            data: {
                _token:      '{{ csrf_token() }}',
                stock_id:    currentStockId,
                to_cell_id:  toCellId,
                quantity:    qty,
                notes:       $('#tfNotes').val(),
            },
            success: function (res) {
                $('#modalTransfer').modal('hide');
                showAlert('success', '<i class="fas fa-check-circle mr-1"></i>' + res.message);
                setTimeout(function () { location.reload(); }, 1500);
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                showAlert('danger', '<i class="fas fa-times-circle mr-1"></i>' + msg);
                $btn.prop('disabled', false).html('<i class="fas fa-exchange-alt mr-1"></i>Transfer');
            }
        });
    });

    function showAlert(type, html) {
        var $a = $('#transferAlert');
        $a.removeClass('d-none alert-success alert-danger')
          .addClass('alert-' + type)
          .html(html)
          .removeClass('d-none');
        $('html, body').animate({ scrollTop: 0 }, 300);
        setTimeout(function () { $a.addClass('d-none'); }, 5000);
    }
    @endif
});
</script>
@endpush
