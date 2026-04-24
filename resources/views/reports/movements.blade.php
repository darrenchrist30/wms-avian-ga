@extends('layouts.adminlte')
@section('title', 'Laporan Mutasi Stok')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-exchange-alt text-info mr-2"></i>Laporan Mutasi Stok
        </h5>
        <small class="text-muted">Riwayat dan analisis seluruh pergerakan barang</small>
    </div>
    <div class="d-flex align-items-center" style="gap:6px;">
        <form method="GET" class="d-flex align-items-center" style="gap:6px;">
            <select name="year" class="form-control form-control-sm" style="width:100px;" onchange="this.form.submit()">
                @foreach($years as $y)
                <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('stock.movements') }}" class="btn btn-sm btn-outline-info">
            <i class="fas fa-list mr-1"></i>Lihat Mutasi
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-secondary mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_txn']) }}</h4><p>Total Transaksi {{ $year }}</p></div>
            <div class="icon"><i class="fas fa-list"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_in']) }}</h4><p>Total Qty Masuk</p></div>
            <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-danger mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_out']) }}</h4><p>Total Qty Keluar</p></div>
            <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_trans']) }}</h4><p>Total Transfer</p></div>
            <div class="icon"><i class="fas fa-exchange-alt"></i></div>
        </div>
    </div>
</div>

{{-- Trend Chart --}}
<div class="card mb-3">
    <div class="card-header py-2">
        <strong><i class="fas fa-chart-area mr-1"></i>Tren Mutasi Bulanan {{ $year }}</strong>
    </div>
    <div class="card-body">
        <div id="chartMovements" style="height:300px;"></div>
    </div>
</div>

<div class="row">
    {{-- Top 10 Active Items --}}
    <div class="col-md-7 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-fire mr-1"></i>Top 10 Item Paling Aktif — {{ $year }}</strong>
            </div>
            <div class="card-body">
                @if($topActive->isEmpty())
                    <div class="text-center text-muted py-4">Belum ada data mutasi tahun ini.</div>
                @else
                    <div id="chartTopActive" style="height:260px;"></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Distribusi Tipe --}}
    <div class="col-md-5 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-pie mr-1"></i>Distribusi Tipe Mutasi</strong>
            </div>
            <div class="card-body">
                @if($typeDist->isEmpty())
                    <div class="text-center text-muted py-4">Belum ada data.</div>
                @else
                    <div id="chartTypeDist" style="height:260px;"></div>
                @endif
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
const monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$(function () {
    // Trend mutasi
    Highcharts.chart('chartMovements', {
        chart: { type: 'column', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: { categories: monthNames },
        yAxis: { title: { text: 'Total Qty' }, min: 0 },
        tooltip: { shared: true },
        plotOptions: { column: { stacking: null } },
        series: [
            { name: 'Masuk', color: '#28a745', data: {!! json_encode($chartIn) !!} },
            { name: 'Keluar', color: '#dc3545', data: {!! json_encode($chartOut) !!} },
            { name: 'Transfer', color: '#17a2b8', data: {!! json_encode($chartTrans) !!} }
        ],
        credits: { enabled: false }
    });

    @if(!$topActive->isEmpty())
    Highcharts.chart('chartTopActive', {
        chart: { type: 'bar', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: {
            categories: [
                @foreach($topActive as $item)
                '{{ addslashes(Str::limit($item->name, 25)) }}',
                @endforeach
            ],
            title: { text: null }
        },
        yAxis: { title: { text: 'Jumlah Transaksi' }, min: 0, allowDecimals: false },
        plotOptions: { bar: { dataLabels: { enabled: true } } },
        series: [{
            name: 'Transaksi',
            color: '#6f42c1',
            data: [
                @foreach($topActive as $item)
                {{ $item->txn_count }},
                @endforeach
            ]
        }],
        credits: { enabled: false },
        legend: { enabled: false }
    });
    @endif

    @if(!$typeDist->isEmpty())
    const typeColors = { inbound:'#28a745', outbound:'#dc3545', transfer:'#17a2b8', adjust:'#ffc107' };
    Highcharts.chart('chartTypeDist', {
        chart: { type: 'pie', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        tooltip: { pointFormat: '<b>{point.name}</b>: {point.y} txn ({point.percentage:.1f}%)' },
        plotOptions: {
            pie: {
                allowPointSelect: true, cursor: 'pointer',
                dataLabels: { enabled: true, format: '{point.name}<br>{point.percentage:.1f}%' }
            }
        },
        series: [{
            name: 'Transaksi',
            data: [
                @foreach($typeDist as $t)
                { name: '{{ ucfirst($t->movement_type) }}', y: {{ $t->total }},
                  color: typeColors['{{ $t->movement_type }}'] || '#6c757d' },
                @endforeach
            ]
        }],
        credits: { enabled: false }
    });
    @endif
});
</script>
@endpush
