@extends('layouts.adminlte')
@section('title', 'Detail Stok: ' . $item->name)

@section('content')
@php
    $statusCfg = [
        'ok'       => ['success', 'Stok Aman',    'fas fa-check-circle'],
        'reorder'  => ['warning', 'Perlu Reorder','fas fa-exclamation-circle'],
        'critical' => ['danger',  'Stok Kritis',  'fas fa-exclamation-triangle'],
        'empty'    => ['secondary','Stok Habis',  'fas fa-times-circle'],
    ];
    [$sCls, $sLabel, $sIcon] = $statusCfg[$stockStatus];
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

{{-- Item Info Card --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold">
            <i class="fas fa-box mr-1 text-primary"></i>Informasi Item
        </span>
        <span class="badge badge-{{ $sCls }} px-3 py-1" style="font-size:12px;">
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
                <small class="text-muted text-uppercase" style="font-size:10.5px;letter-spacing:.5px;">Min / Reorder</small>
                <div class="mt-1">
                    <span class="text-danger font-weight-bold">{{ number_format($minStock) }}</span>
                    <span class="text-muted"> / </span>
                    <span class="text-warning font-weight-bold">{{ number_format($reorderPoint) }}</span>
                </div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <small class="text-muted text-uppercase" style="font-size:10.5px;letter-spacing:.5px;">FIFO Terlama Masuk</small>
                <div class="font-weight-bold mt-1">
                    {{ $oldestFifo ? $oldestFifo->format('d M Y') : '—' }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Summary mini cards --}}
<div class="row mb-3">
    <div class="col-4 mb-2">
        <div class="small-box bg-{{ $sCls }} mb-0">
            <div class="inner">
                <h4>{{ number_format($totalQty) }}</h4>
                <p>Total Qty Tersedia</p>
            </div>
            <div class="icon"><i class="fas fa-cubes"></i></div>
        </div>
    </div>
    <div class="col-4 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner">
                <h4>{{ $cellCount }}</h4>
                <p>Jumlah Lokasi (Cell)</p>
            </div>
            <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
        </div>
    </div>
    <div class="col-4 mb-2">
        <div class="small-box bg-secondary mb-0">
            <div class="inner">
                <h4>{{ $stocks->count() }}</h4>
                <p>Batch / LPN Aktif</p>
            </div>
            <div class="icon"><i class="fas fa-layer-group"></i></div>
        </div>
    </div>
</div>

{{-- Lokasi Stok per Cell (FIFO Order) --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold">
            <i class="fas fa-map-marker-alt mr-1 text-primary"></i>
            Posisi Stok per Cell
            <span class="badge badge-primary ml-1">FIFO</span>
        </span>
        <small class="text-muted">Urut dari tanggal masuk paling lama → yang harus diambil pertama</small>
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
                        <th class="text-center" width="40">FIFO</th>
                        <th width="120">Cell</th>
                        <th width="100">Rak</th>
                        <th width="100">Zona</th>
                        <th width="100">Gudang</th>
                        <th class="text-center" width="90">Qty</th>
                        <th width="120">LPN</th>
                        <th width="110">Tgl Masuk</th>
                        <th width="110">Tgl Expired</th>
                        <th class="text-center" width="90">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $i => $s)
                    @php
                        $isNearExpiry = $s->expiry_date && $s->expiry_date->diffInDays(now(), false) >= -30 && $s->expiry_date->isFuture();
                        $isExpired    = $s->expiry_date && $s->expiry_date->isPast();
                        $rowClass     = $isExpired ? 'table-danger' : ($isNearExpiry ? 'table-warning' : ($i === 0 ? 'table-success' : ''));
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="text-center">
                            @if($i === 0)
                                <span class="badge badge-success" title="Ambil ini duluan (FIFO)">
                                    <i class="fas fa-star"></i> 1st
                                </span>
                            @else
                                <span class="text-muted">{{ $i + 1 }}</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $s->cell?->code ?? '—' }}</strong>
                            @if($s->cell?->label)
                                <br><small class="text-muted">{{ $s->cell->label }}</small>
                            @endif
                        </td>
                        <td>{{ $s->cell?->rack?->code ?? '—' }}</td>
                        <td>
                            <span class="badge badge-light border">
                                {{ $s->cell?->rack?->zone?->name ?? '—' }}
                            </span>
                        </td>
                        <td><small>{{ $s->warehouse?->name ?? '—' }}</small></td>
                        <td class="text-center font-weight-bold">{{ number_format($s->quantity) }}</td>
                        <td><code class="small">{{ $s->lpn ?? '—' }}</code></td>
                        <td>
                            <small>{{ $s->inbound_date?->format('d M Y') ?? '—' }}</small>
                        </td>
                        <td>
                            @if($s->expiry_date)
                                <small class="{{ $isExpired ? 'text-danger font-weight-bold' : ($isNearExpiry ? 'text-warning font-weight-bold' : '') }}">
                                    {{ $s->expiry_date->format('d M Y') }}
                                    @if($isExpired)
                                        <i class="fas fa-exclamation-circle ml-1"></i>
                                    @elseif($isNearExpiry)
                                        <i class="fas fa-clock ml-1"></i>
                                    @endif
                                </small>
                            @else
                                <span class="text-muted">—</span>
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
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="font-weight-bold bg-light">
                    <tr>
                        <td colspan="5" class="text-right pr-2">Total:</td>
                        <td class="text-center">{{ number_format($totalQty) }}</td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
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
});
</script>
@endpush
