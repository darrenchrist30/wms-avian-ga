@extends('layouts.adminlte')
@section('title', 'Deadstock')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-hourglass-half text-secondary mr-2"></i>Item Deadstock
        </h5>
        <small class="text-muted">Stok masih tersedia, tetapi tidak bergerak minimal {{ $days }} hari.</small>
    </div>
    <div class="d-flex" style="gap:6px;">
        <form method="GET" class="d-flex align-items-center" style="gap:6px;">
            <select name="days" class="form-control form-control-sm" style="width:120px;" onchange="this.form.submit()">
                @foreach([30, 60, 90, 180, 365] as $d)
                    <option value="{{ $d }}" {{ $days == $d ? 'selected' : '' }}>{{ $d }} hari</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-light border">
            <i class="fas fa-arrow-left mr-1"></i>Dashboard
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-4 mb-2">
        <div class="small-box bg-secondary mb-0">
            <div class="inner"><h4>{{ number_format($summary['sku_count']) }} <small>SKU</small></h4><p>SKU Deadstock</p></div>
            <div class="icon"><i class="fas fa-barcode"></i></div>
        </div>
    </div>
    <div class="col-4 mb-2">
        <div class="small-box bg-dark mb-0">
            <div class="inner"><h4>{{ number_format($summary['record_count']) }} <small>record</small></h4><p>Record Stok</p></div>
            <div class="icon"><i class="fas fa-layer-group"></i></div>
        </div>
    </div>
    <div class="col-4 mb-2">
        <div class="small-box bg-warning mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_qty']) }} <small>unit</small></h4><p>Total Qty</p></div>
            <div class="icon"><i class="fas fa-boxes"></i></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" id="tblDeadstock">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="40">#</th>
                        <th>SKU / Nama Item</th>
                        <th width="130">Kategori</th>
                        <th class="text-center" width="110">Qty</th>
                        <th width="120">Cell</th>
                        <th width="130">Gudang</th>
                        <th class="text-center" width="120">Terakhir Bergerak</th>
                        <th class="text-center" width="90">Umur</th>
                        <th class="text-center" width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $i => $stock)
                        @php
                            $lastActivity = $stock->last_moved_at ?: $stock->inbound_date;
                            $ageDays = $lastActivity ? \Carbon\Carbon::parse($lastActivity)->diffInDays(now()) : null;
                        @endphp
                        <tr>
                            <td class="text-center text-muted">{{ $i + 1 }}</td>
                            <td>
                                <div class="font-weight-bold">{{ $stock->item->name ?? '-' }}</div>
                                <small class="text-muted">{{ $stock->item->sku ?? '-' }}</small>
                            </td>
                            <td>
                                @if($stock->item?->category)
                                    <span class="badge px-2" style="background:{{ $stock->item->category->color_code ?? '#6c757d' }};color:#fff;font-size:11px;">
                                        {{ $stock->item->category->name }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center font-weight-bold">
                                {{ number_format($stock->quantity) }}
                                <small class="text-muted font-weight-normal">{{ $stock->item?->unit?->code }}</small>
                            </td>
                            <td>
                                <span class="badge badge-primary">{{ $stock->cell?->physical_code ?? $stock->cell?->code ?? '-' }}</span>
                            </td>
                            <td>{{ $stock->warehouse?->name ?? '-' }}</td>
                            <td class="text-center text-nowrap" data-order="{{ $lastActivity ? \Carbon\Carbon::parse($lastActivity)->timestamp : 0 }}">
                                {{ $lastActivity ? \Carbon\Carbon::parse($lastActivity)->format('d/m/Y') : '-' }}
                            </td>
                            <td class="text-center font-weight-bold text-danger">
                                {{ $ageDays !== null ? number_format($ageDays) . ' hari' : '-' }}
                            </td>
                            <td class="text-center">
                                @if($stock->item)
                                    <a href="{{ route('stock.show', $stock->item->id) }}" class="btn btn-xs btn-info" title="Detail Stok">
                                        <i class="fas fa-search-location"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#tblDeadstock').DataTable({
        pageLength: 25,
        order: [[6, 'asc']],
        language: { url: '/vendor/datatables/i18n/id.json' },
    });
});
</script>
@endpush
