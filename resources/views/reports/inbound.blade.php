@extends('layouts.adminlte')
@section('title', 'Laporan Penerimaan Barang')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-truck-loading text-success mr-2"></i>Laporan Penerimaan Barang
        </h5>
        <small class="text-muted">Analisis penerimaan inbound â€” tren, supplier, dan status DO</small>
    </div>
    <div class="d-flex align-items-center" style="gap:6px;">
        {{-- Filter Tahun --}}
        <form method="GET" class="d-flex align-items-center" style="gap:6px;">
            <select name="year" class="form-control form-control-sm" style="width:100px;" onchange="this.form.submit()">
                @foreach($years as $y)
                <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('inbound.orders.index') }}" class="btn btn-sm btn-outline-success">
            <i class="fas fa-truck mr-1"></i>Lihat Inbound
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_orders']) }}</h4><p>Total DO {{ $year }}</p></div>
            <div class="icon"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner"><h4>{{ number_format($summary['processed_orders']) }}</h4><p>Selesai (Completed)</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-warning mb-0">
            <div class="inner"><h4>{{ number_format($summary['received_orders']) }}</h4><p>Rekomendasi / Put-Away</p></div>
            <div class="icon"><i class="fas fa-box-open"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_suppliers']) }}</h4><p>Supplier Aktif</p></div>
            <div class="icon"><i class="fas fa-handshake"></i></div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Trend Bulanan --}}
    <div class="col-md-8 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-line mr-1"></i>Tren Penerimaan Bulanan {{ $year }}</strong>
            </div>
            <div class="card-body">
                <div id="chartMonthlyTrend" style="height:280px;"></div>
            </div>
        </div>
    </div>

    {{-- Distribusi Status --}}
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-pie mr-1"></i>Status DO</strong>
            </div>
            <div class="card-body">
                @if($statusDist->isEmpty())
                    <div class="text-center text-muted py-4">Belum ada data.</div>
                @else
                    <div id="chartStatusPie" style="height:280px;"></div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Top Supplier --}}
<div class="card">
    <div class="card-header py-2">
        <strong><i class="fas fa-chart-bar mr-1"></i>Top Supplier â€” Jumlah DO {{ $year }}</strong>
    </div>
    <div class="card-body">
        @if($bySupplier->isEmpty())
            <div class="text-center text-muted py-4">Belum ada data penerimaan tahun ini.</div>
        @else
            <div id="chartSupplier" style="height:260px;"></div>
        @endif
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/highcharts.min.js') }}"></script>
<script>
const monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$(function () {
    // Trend bulanan
    Highcharts.chart('chartMonthlyTrend', {
        chart: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: { categories: monthNames },
        yAxis: [
            { title: { text: 'Jumlah DO' }, min: 0 },
            { title: { text: 'Total Qty' }, opposite: true, min: 0 }
        ],
        tooltip: { shared: true },
        series: [
            {
                name: 'Jumlah DO',
                type: 'column',
                color: '#28a745',
                data: {!! json_encode($chartOrders) !!}
            },
            {
                name: 'Total Qty Diterima',
                type: 'spline',
                color: '#007bff',
                yAxis: 1,
                data: {!! json_encode($chartQty) !!}
            }
        ],
        credits: { enabled: false }
    });

    @if(!$statusDist->isEmpty())
    // Status Pie
    const statusColors = { draft:'#6c757d', processing:'#17a2b8', recommended:'#ffc107', put_away:'#007bff', completed:'#28a745', cancelled:'#dc3545' };
    Highcharts.chart('chartStatusPie', {
        chart: { type: 'pie', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        tooltip: { pointFormat: '<b>{point.name}</b>: {point.y} DO ({point.percentage:.1f}%)' },
        plotOptions: {
            pie: {
                allowPointSelect: true, cursor: 'pointer',
                dataLabels: { enabled: true, format: '{point.name}: {point.y}' }
            }
        },
        series: [{
            name: 'DO',
            data: [
                @foreach($statusDist as $s)
                { name: '{{ ucfirst($s->status) }}', y: {{ $s->total }},
                  color: statusColors['{{ $s->status }}'] || '#007bff' },
                @endforeach
            ]
        }],
        credits: { enabled: false }
    });
    @endif

    @if(!$bySupplier->isEmpty())
    // Supplier bar
    Highcharts.chart('chartSupplier', {
        chart: { type: 'bar', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: {
            categories: [
                @foreach($bySupplier as $s)
                '{{ addslashes(Str::limit($s->supplier, 30)) }}',
                @endforeach
            ],
            title: { text: null }
        },
        yAxis: { title: { text: 'Jumlah DO' }, min: 0, allowDecimals: false },
        plotOptions: { bar: { dataLabels: { enabled: true } } },
        series: [{
            name: 'Jumlah DO',
            color: '#17a2b8',
            data: [
                @foreach($bySupplier as $s)
                {{ $s->order_count }},
                @endforeach
            ]
        }],
        credits: { enabled: false },
        legend: { enabled: false }
    });
    @endif
});
</script>
@endpush
