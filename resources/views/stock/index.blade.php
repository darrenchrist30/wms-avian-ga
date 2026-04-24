@extends('layouts.adminlte')
@section('title', 'Stok Saat Ini')

@section('content')
<div class="container-fluid pb-4">

{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-boxes text-primary mr-2"></i>Stok Saat Ini
        </h5>
        <small class="text-muted">Posisi barang real-time hasil put-away — per item, seluruh gudang</small>
    </div>
    <div class="d-flex" style="gap:6px;">
        <a href="{{ route('stock.movements') }}" class="btn btn-sm btn-outline-info">
            <i class="fas fa-exchange-alt mr-1"></i>Lihat Mutasi
        </a>
        <a href="{{ route('stock.low-stock') }}" class="btn btn-sm btn-outline-danger">
            <i class="fas fa-exclamation-triangle mr-1"></i>Stok Kritis
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_skus']) }}</h4><p>Total SKU</p></div>
            <div class="icon"><i class="fas fa-list"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_qty']) }}</h4><p>Total Qty</p></div>
            <div class="icon"><i class="fas fa-cubes"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_cells']) }}</h4><p>Cell Terpakai</p></div>
            <div class="icon"><i class="fas fa-th-large"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box {{ $summary['below_min'] > 0 ? 'bg-danger' : 'bg-secondary' }} mb-0">
            <div class="inner"><h4>{{ $summary['below_min'] }}</h4><p>Di Bawah Minimum</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
</div>

{{-- Filter + Table --}}
<div class="card">
    <div class="card-header py-2">
        <strong><i class="fas fa-filter mr-1"></i>Filter</strong>
    </div>
    <div class="card-body pb-1">
        <div class="row">
            <div class="col-md-3 mb-2">
                <select class="form-control form-control-sm" id="filterCategory">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <select class="form-control form-control-sm" id="filterWarehouse">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <select class="form-control form-control-sm" id="filterStatus">
                    <option value="">Semua Status Stok</option>
                    <option value="critical">Kritis (≤ Min Stock)</option>
                    <option value="reorder">Perlu Reorder</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-sm btn-secondary btn-block" id="btnReset">
                    <i class="fas fa-times mr-1"></i>Reset Filter
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblStock" class="table table-sm table-bordered table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th width="40" class="text-center">#</th>
                        <th>SKU / Nama Item</th>
                        <th width="130">Kategori</th>
                        <th class="text-center" width="130">Total Qty</th>
                        <th class="text-center" width="130">Min / Reorder</th>
                        <th class="text-center" width="110">Lokasi</th>
                        <th class="text-center" width="110">FIFO Terlama</th>
                        <th class="text-center" width="80">Aksi</th>
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
let table;
$(function () {
    table = $('#tblStock').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("stock.index") }}',
            data: function (d) {
                d.category_id    = $('#filterCategory').val();
                d.warehouse_id   = $('#filterWarehouse').val();
                d.status_filter  = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex',       orderable: false, searchable: false, className: 'text-center text-muted' },
            { data: 'item_info',         orderable: false, searchable: true  },
            { data: 'category_badge',    orderable: false, searchable: false, className: 'text-center' },
            { data: 'qty_display',       orderable: false, searchable: false, className: 'text-center' },
            { data: 'min_reorder',       orderable: false, searchable: false, className: 'text-center' },
            { data: 'locations_display', orderable: false, searchable: false, className: 'text-center' },
            { data: 'fifo_display',      orderable: false, searchable: false, className: 'text-center' },
            { data: 'action',            orderable: false, searchable: false, className: 'text-center' },
        ],
        order: [],
        pageLength: 25,
        language: { url: '/vendor/datatables/i18n/id.json' },
    });

    $('#filterCategory, #filterWarehouse, #filterStatus').on('change', () => table.ajax.reload());
    $('#btnReset').on('click', function () {
        $('#filterCategory, #filterWarehouse, #filterStatus').val('');
        table.ajax.reload();
    });
});
</script>
@endpush
