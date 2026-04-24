@extends('layouts.adminlte')
@section('title', 'Mutasi Stok')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-exchange-alt text-info mr-2"></i>Mutasi Stok
        </h5>
        <small class="text-muted">Riwayat seluruh pergerakan barang — masuk, keluar, transfer</small>
    </div>
    <a href="{{ route('stock.index') }}" class="btn btn-sm btn-light border">
        <i class="fas fa-arrow-left mr-1"></i>Kembali ke Stok
    </a>
</div>

{{-- Summary Cards --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner">
                <h4>{{ number_format($summary['today_in']) }}</h4>
                <p>Qty Masuk Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-down"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-danger mb-0">
            <div class="inner">
                <h4>{{ number_format($summary['today_out']) }}</h4>
                <p>Qty Keluar Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-up"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner">
                <h4>{{ number_format($summary['today_trans']) }}</h4>
                <p>Transfer Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-exchange-alt"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-secondary mb-0">
            <div class="inner">
                <h4>{{ number_format($summary['total_today']) }}</h4>
                <p>Total Transaksi Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-list"></i></div>
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
            <div class="col-md-2 mb-2">
                <select class="form-control form-control-sm" id="filterType">
                    <option value="">Semua Tipe</option>
                    <option value="inbound">Masuk (Inbound)</option>
                    <option value="outbound">Keluar (Outbound)</option>
                    <option value="transfer">Transfer</option>
                    <option value="adjust">Penyesuaian</option>
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
            <div class="col-md-2 mb-2">
                <input type="date" class="form-control form-control-sm" id="filterDateFrom"
                    placeholder="Dari tanggal">
            </div>
            <div class="col-md-2 mb-2">
                <input type="date" class="form-control form-control-sm" id="filterDateTo"
                    placeholder="Sampai tanggal">
            </div>
            <div class="col-md-3 mb-2 d-flex" style="gap:6px;">
                <button class="btn btn-sm btn-primary flex-fill" id="btnFilter">
                    <i class="fas fa-search mr-1"></i>Filter
                </button>
                <button class="btn btn-sm btn-secondary" id="btnReset">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblMovements" class="table table-sm table-bordered table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th width="40" class="text-center">#</th>
                        <th width="140">Waktu</th>
                        <th width="220">Item</th>
                        <th class="text-center" width="110">Tipe</th>
                        <th width="180">Lokasi</th>
                        <th class="text-center" width="100">Qty</th>
                        <th width="140">Dilakukan Oleh</th>
                        <th>Catatan</th>
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
    table = $('#tblMovements').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("stock.movements") }}',
            data: function (d) {
                d.type         = $('#filterType').val();
                d.warehouse_id = $('#filterWarehouse').val();
                d.date_from    = $('#filterDateFrom').val();
                d.date_to      = $('#filterDateTo').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex',     orderable: false, searchable: false, className: 'text-center text-muted' },
            { data: 'date_display',    orderable: true  },
            { data: 'item_info',       orderable: true  },
            { data: 'type_badge',      orderable: false, className: 'text-center' },
            { data: 'location_display',orderable: false },
            { data: 'qty_display',     orderable: false, className: 'text-center' },
            { data: 'by_display',      orderable: false },
            { data: 'notes_display',   orderable: false },
        ],
        order: [[1, 'desc']],
        pageLength: 25,
        language: { url: '/vendor/datatables/i18n/id.json' },
    });

    $('#btnFilter').on('click', () => table.ajax.reload());
    $('#btnReset').on('click', function () {
        $('#filterType, #filterWarehouse').val('');
        $('#filterDateFrom, #filterDateTo').val('');
        table.ajax.reload();
    });
});
</script>
@endpush
