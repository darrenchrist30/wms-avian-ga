@extends('layouts.adminlte')
@section('title', 'Laporan Penerimaan Barang')

@push('styles')
<style>
    .inbound-summary-card {
        min-height: 80px;
        height: 100%;
        display: flex;
        align-items: center;
    }

    .inbound-summary-card .inner {
        width: 100%;
        min-height: 80px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding-right: 72px;
    }

    .inbound-summary-card .inner h4 {
        line-height: 1;
        margin-bottom: 8px;
    }

    .inbound-summary-card .inner p {
        min-height: 34px;
        display: flex;
        align-items: center;
        margin-bottom: 0;
        line-height: 1.25;
    }

    .small-box.inbound-summary-card > .icon {
        font-size: 70px !important;
        opacity: 0.5 !important;
        top: -20px !important;
        bottom: auto !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-truck-loading text-success mr-2"></i>Laporan Penerimaan Barang
        </h5>
        {{-- <small class=”text-muted”>Analisis penerimaan inbound &mdash; tren dan status DO</small> --}}
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
        <div class="small-box inbound-summary-card bg-primary mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_orders']) }} <small>SJ</small></h4><p>Total SJ {{ $year }}</p></div>
            <div class="icon"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box inbound-summary-card bg-success mb-0">
            <div class="inner"><h4>{{ number_format($summary['processed_orders']) }} <small>SJ</small></h4><p>Selesai (Completed)</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box inbound-summary-card bg-warning mb-0">
            <div class="inner"><h4>{{ number_format($summary['received_orders']) }} <small>SJ</small></h4><p>Sedang Put-Away</p></div>
            <div class="icon"><i class="fas fa-box-open"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box inbound-summary-card bg-info mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_warehouses']) }} <small>Gudang</small></h4><p>Gudang Aktif</p></div>
            <div class="icon"><i class="fas fa-warehouse"></i></div>
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
                <strong><i class="fas fa-chart-pie mr-1"></i>Status SJ</strong>
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

<div class="row">
    {{-- Rata-rata Waktu Proses DO --}}
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-stopwatch mr-1"></i>Rata-rata Waktu Proses SJ (Jam) {{ $year }}</strong>
            </div>
            <div class="card-body">
                <div id="chartAvgProcessing" style="height:260px;"></div>
            </div>
        </div>
    </div>

    {{-- Top 5 SKU Terbanyak Diterima --}}
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-trophy mr-1"></i>Top 5 SKU Terbanyak Diterima {{ $year }}</strong>
            </div>
            <div class="card-body">
                @if($topSkus->isEmpty())
                    <div class="text-center text-muted py-4">Belum ada data.</div>
                @else
                    <div id="chartTopSkus" style="height:260px;"></div>
                @endif
            </div>
        </div>
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
            { title: { text: 'Jumlah SJ' }, min: 0 },
            { title: { text: 'Total Qty' }, opposite: true, min: 0 }
        ],
        tooltip: { shared: true },
        series: [
            {
                name: 'Jumlah SJ',
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
        tooltip: { pointFormat: '<b>{point.name}</b>: {point.y} SJ ({point.percentage:.1f}%)' },
        plotOptions: {
            pie: {
                allowPointSelect: true, cursor: 'pointer',
                dataLabels: { enabled: true, format: '{point.name}: {point.y}' }
            }
        },
        series: [{
            name: 'SJ',
            data: [
                @foreach($statusDist as $s)
                { name: '{{ $s->label }}', y: {{ $s->total }},
                  color: statusColors['{{ $s->status }}'] || '#007bff' },
                @endforeach
            ]
        }],
        credits: { enabled: false }
    });
    @endif

    // Rata-rata Waktu Proses SJ
    Highcharts.chart('chartAvgProcessing', {
        chart: { type: 'column', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: { categories: monthNames },
        yAxis: {
            title: { text: 'Jam' },
            min: 0,
            labels: { format: '{value} jam' }
        },
        tooltip: {
            formatter: function () {
                const bulan = monthNames[this.point.index];
                if (this.y === null || this.y === undefined) return '<b>' + bulan + '</b><br>Tidak ada data';
                return '<b>' + bulan + '</b><br>Rata-rata: <b>' + this.y + ' jam</b>';
            }
        },
        plotOptions: {
            column: { borderRadius: 3, color: '#17a2b8', dataLabels: { enabled: true, format: '{y}' } }
        },
        series: [{
            name: 'Avg Waktu Proses',
            data: {!! json_encode($chartAvgProcessing) !!}
        }],
        credits: { enabled: false }
    });

    @if(!$topSkus->isEmpty())
    // Top 5 SKU
    Highcharts.chart('chartTopSkus', {
        chart: { type: 'bar', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: {
            categories: {!! json_encode($topSkus->map(fn($s) => $s->sku . ' — ' . (mb_strlen($s->name) > 25 ? mb_substr($s->name, 0, 25) . '…' : $s->name))->values()) !!},
            labels: { style: { fontSize: '11px' } }
        },
        yAxis: { title: { text: 'Total Qty Diterima' }, min: 0, labels: { formatter: function() { return Highcharts.numberFormat(this.value, 0, '.', ','); } } },
        tooltip: {
            formatter: function () {
                return '<b>' + this.point.category + '</b><br>Qty: <b>' + Highcharts.numberFormat(this.y, 0) + '</b>';
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 3,
                colorByPoint: true,
                colors: ['#007bff','#28a745','#ffc107','#17a2b8','#6f42c1'],
                dataLabels: { enabled: true, formatter: function() { return Highcharts.numberFormat(this.y, 0, '.', ','); } }
            }
        },
        legend: { enabled: false },
        series: [{
            name: 'Qty Diterima',
            data: {!! json_encode($topSkus->pluck('total_qty')->map(fn($v) => (int)$v)->values()) !!}
        }],
        credits: { enabled: false }
    });
    @endif

});
</script>
@endpush
