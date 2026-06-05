@extends('layouts.adminlte')
@section('title', 'Stok Kritis')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-exclamation-triangle text-danger mr-2"></i>Stok Kritis
        </h5>
        {{-- <small class="text-muted">Item di bawah reorder point — perlu segera dilakukan pemesanan</small> --}}
    </div>
    <div class="d-flex" style="gap:6px;">
        <a href="{{ route('stock.index') }}" class="btn btn-sm btn-light border">
            <i class="fas fa-arrow-left mr-1"></i>Kembali
        </a>
    </div>
</div>

<div class="alert alert-light border mb-3 py-2">
    @if($type === 'empty')
        <strong>Filter:</strong> hanya item dengan stok available = 0.
    @elseif($type === 'low')
        <strong>Filter:</strong> hanya item yang masih punya stok, tetapi sudah di bawah minimum.
    @elseif($type === 'reorder')
        <strong>Filter:</strong> hanya item yang sudah masuk batas reorder point.
    @else
        <strong>Filter:</strong> semua item stok habis, di bawah minimum, dan perlu reorder.
    @endif
</div>

{{-- Summary --}}
<div class="row mb-3">
    <div class="col-4 mb-2">
        <a href="{{ route('stock.low-stock', ['type' => 'empty']) }}" class="text-decoration-none">
        <div class="small-box bg-dark mb-0">
            <div class="inner"><h4>{{ number_format($summary['empty']) }} <small>SKU</small></h4><p>Stok Habis</p></div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
        </div>
        </a>
    </div>
    <div class="col-4 mb-2">
        <a href="{{ route('stock.low-stock', ['type' => 'low']) }}" class="text-decoration-none">
        <div class="small-box bg-danger mb-0">
            <div class="inner"><h4>{{ number_format($summary['critical']) }} <small>SKU</small></h4><p>Di Bawah Minimum</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        </a>
    </div>
    <div class="col-4 mb-2">
        <a href="{{ route('stock.low-stock', ['type' => 'reorder']) }}" class="text-decoration-none">
        <div class="small-box bg-warning mb-0">
            <div class="inner"><h4>{{ number_format($summary['reorder']) }} <small>SKU</small></h4><p>Perlu Reorder</p></div>
            <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
        </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($items->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-2 d-block"></i>
            <strong>Semua stok dalam kondisi aman.</strong>
            <p class="mb-0">Tidak ada item di bawah reorder point.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" id="tblLowStock">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="40">#</th>
                        <th>SKU / Nama Item</th>
                        <th width="130">Kategori</th>
                        <th class="text-center" width="110">Stok Saat Ini</th>
                        <th class="text-center" width="110">Min Stock</th>
                        <th class="text-center" width="110">Reorder Point</th>
                        <th class="text-center" width="110">Kekurangan</th>
                        <th class="text-center" width="110">Status</th>
                        <th class="text-center" width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                    @php
                        $qty     = (int) $item->current_stock;
                        $min     = (int) $item->min_stock;
                        $reorder = (int) $item->reorder_point;
                        $deficit = max(0, $reorder - $qty);

                        if ($qty === 0)          { $statusCls = 'danger';  $statusTxt = 'Habis';   $rowCls = 'table-danger'; }
                        elseif ($qty <= $min)    { $statusCls = 'danger';  $statusTxt = 'Kritis';  $rowCls = 'table-danger'; }
                        else                     { $statusCls = 'warning'; $statusTxt = 'Reorder'; $rowCls = 'table-warning'; }
                    @endphp
                    <tr class="{{ $rowCls }}">
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="font-weight-bold">{{ $item->name }}</div>
                            <small class="text-muted">{{ $item->sku }}</small>
                        </td>
                        <td>
                            @if($item->category)
                                <span class="badge px-2" style="background:{{ $item->category->color_code ?? '#6c757d' }};color:#fff;font-size:11px;">
                                    {{ $item->category->name }}
                                </span>
                            @else <span class="text-muted">—</span> @endif
                        </td>
                        <td class="text-center font-weight-bold {{ $qty === 0 ? 'text-danger' : '' }}">
                            {{ number_format($qty) }}
                            <small class="text-muted font-weight-normal"> {{ $item->unit?->code }}</small>
                        </td>
                        <td class="text-center text-danger font-weight-bold">{{ number_format($min) }}</td>
                        <td class="text-center text-warning font-weight-bold">{{ number_format($reorder) }}</td>
                        <td class="text-center font-weight-bold text-danger">
                            {{ $deficit > 0 ? number_format($deficit) : '—' }}
                        </td>
                        <td class="text-center">
                            <span class="badge badge-{{ $statusCls }}">{{ $statusTxt }}</span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('stock.show', $item->id) }}"
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
    $('#tblLowStock').DataTable({
        pageLength: 25,
        order: [[3, 'asc']],
    });
});
</script>
@endpush
