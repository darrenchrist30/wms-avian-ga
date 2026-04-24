@extends('layouts.adminlte')
@section('title', 'Mendekati Kadaluarsa')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-calendar-times text-danger mr-2"></i>Mendekati Kadaluarsa
        </h5>
        <small class="text-muted">Barang yang akan atau sudah kadaluarsa — perlu tindakan segera</small>
    </div>
    <div class="d-flex align-items-center" style="gap:6px;">
        {{-- Filter periode --}}
        <div class="btn-group btn-group-sm">
            @foreach([7 => '7 Hari', 30 => '30 Hari', 60 => '60 Hari', 90 => '90 Hari'] as $d => $label)
            <a href="{{ route('stock.near-expiry', ['days' => $d]) }}"
               class="btn {{ $days == $d ? 'btn-danger' : 'btn-outline-danger' }}">
               {{ $label }}
            </a>
            @endforeach
        </div>
        <a href="{{ route('stock.index') }}" class="btn btn-sm btn-light border">
            <i class="fas fa-arrow-left mr-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Summary --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-dark mb-0">
            <div class="inner"><h4>{{ $summary['expired'] }}</h4><p>Sudah Kadaluarsa</p></div>
            <div class="icon"><i class="fas fa-skull-crossbones"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-danger mb-0">
            <div class="inner"><h4>{{ $summary['today'] }}</h4><p>Kadaluarsa Hari Ini</p></div>
            <div class="icon"><i class="fas fa-calendar-times"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-warning mb-0">
            <div class="inner"><h4>{{ $summary['this_week'] }}</h4><p>Dalam 7 Hari</p></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ $summary['this_month'] }}</h4><p>8–30 Hari Lagi</p></div>
            <div class="icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header py-2">
        <strong>
            <i class="fas fa-list mr-1"></i>
            Daftar Stok Mendekati Kadaluarsa
            <span class="badge badge-danger ml-1">dalam {{ $days }} hari ke depan</span>
        </strong>
    </div>
    <div class="card-body p-0">
        @if($stocks->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-2 d-block"></i>
            <strong>Tidak ada barang yang mendekati kadaluarsa</strong>
            <p class="mb-0">dalam {{ $days }} hari ke depan.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" id="tblNearExpiry">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="40">#</th>
                        <th>Item</th>
                        <th width="130">Kategori</th>
                        <th width="110">Cell / Lokasi</th>
                        <th class="text-center" width="90">Qty</th>
                        <th width="110">LPN</th>
                        <th class="text-center" width="120">Tgl Expired</th>
                        <th class="text-center" width="120">Sisa Hari</th>
                        <th class="text-center" width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $i => $s)
                    @php
                        $dr = $s->days_remaining;
                        if ($dr < 0)      { $rowCls = 'table-dark';    $badge = 'dark';    $badgeTxt = 'Sudah Expired'; }
                        elseif ($dr === 0){ $rowCls = 'table-danger';  $badge = 'danger';  $badgeTxt = 'Hari Ini'; }
                        elseif ($dr <= 7) { $rowCls = 'table-danger';  $badge = 'danger';  $badgeTxt = $dr . ' hari'; }
                        elseif ($dr <= 30){ $rowCls = 'table-warning'; $badge = 'warning'; $badgeTxt = $dr . ' hari'; }
                        else              { $rowCls = '';               $badge = 'info';    $badgeTxt = $dr . ' hari'; }
                    @endphp
                    <tr class="{{ $rowCls }}">
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="font-weight-bold">{{ $s->item?->name ?? '—' }}</div>
                            <small class="text-muted">{{ $s->item?->sku }}</small>
                        </td>
                        <td>
                            @if($s->item?->category)
                                <span class="badge px-2" style="background:{{ $s->item->category->color_code ?? '#6c757d' }};color:#fff;font-size:11px;">
                                    {{ $s->item->category->name }}
                                </span>
                            @else <span class="text-muted">—</span> @endif
                        </td>
                        <td>
                            <strong>{{ $s->cell?->code ?? '—' }}</strong>
                            @if($s->cell?->rack?->zone)
                                <br><small class="text-muted">{{ $s->cell->rack->zone->name }}</small>
                            @endif
                        </td>
                        <td class="text-center font-weight-bold">
                            {{ number_format($s->quantity) }}
                            <small class="text-muted font-weight-normal">{{ $s->item?->unit?->code }}</small>
                        </td>
                        <td><code class="small">{{ $s->lpn ?? '—' }}</code></td>
                        <td class="text-center font-weight-bold">
                            {{ $s->expiry_date?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="text-center">
                            <span class="badge badge-{{ $badge }} px-2">{{ $badgeTxt }}</span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('stock.show', $s->item_id) }}"
                               class="btn btn-xs btn-info" title="Detail Stok">
                                <i class="fas fa-search-location"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#tblNearExpiry').DataTable({
        pageLength: 25,
        order: [[6, 'asc']],
        language: { url: '/vendor/datatables/i18n/id.json' },
    });
});
</script>
@endpush
