@extends('layouts.adminlte')
@section('title', 'Laporan Inventaris')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-boxes text-primary mr-2"></i>Laporan Inventaris
        </h5>
        <small class="text-muted">Kondisi stok real-time — distribusi, utilisasi, dan peringatan</small>
    </div>
    <div class="d-flex" style="gap:6px;">
        <a href="{{ route('stock.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-boxes mr-1"></i>Lihat Stok
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
            <div class="inner"><h4>{{ number_format($summary['total_skus']) }}</h4><p>Total SKU Aktif</p></div>
            <div class="icon"><i class="fas fa-list"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_qty']) }}</h4><p>Total Qty di Gudang</p></div>
            <div class="icon"><i class="fas fa-cubes"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box {{ $summary['below_min'] > 0 ? 'bg-danger' : 'bg-success' }} mb-0">
            <div class="inner"><h4>{{ $summary['below_min'] }}</h4><p>Di Bawah Minimum</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box {{ $summary['near_expiry'] > 0 ? 'bg-warning' : 'bg-secondary' }} mb-0">
            <div class="inner"><h4>{{ $summary['near_expiry'] }}</h4><p>Mendekati Kadaluarsa</p></div>
            <div class="icon"><i class="fas fa-calendar-times"></i></div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Stok per Kategori --}}
    <div class="col-md-5 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-pie mr-1"></i>Stok per Kategori</strong>
            </div>
            <div class="card-body">
                @if($stockByCategory->isEmpty())
                    <div class="text-center text-muted py-4">Belum ada data stok.</div>
                @else
                    <div id="chartCategoryPie" style="height:280px;"></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Top 10 Item --}}
    <div class="col-md-7 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-bar mr-1"></i>Top 10 Item — Stok Terbanyak</strong>
            </div>
            <div class="card-body">
                @if($topItems->isEmpty())
                    <div class="text-center text-muted py-4">Belum ada data stok.</div>
                @else
                    <div id="chartTopItems" style="height:280px;"></div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Utilisasi per Gudang --}}
<div class="card mb-3">
    <div class="card-header py-2">
        <strong><i class="fas fa-warehouse mr-1"></i>Utilisasi Cell per Gudang</strong>
    </div>
    <div class="card-body p-0">
        @if($warehouseUtil->isEmpty())
            <div class="text-center text-muted py-4">Belum ada data gudang.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Gudang</th>
                        <th class="text-center" width="120">Total Cell</th>
                        <th class="text-center" width="120">Cell Terpakai</th>
                        <th class="text-center" width="120">Utilisasi</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($warehouseUtil as $row)
                    @php
                        $color = $row->utilization >= 90 ? 'danger'
                               : ($row->utilization >= 70 ? 'warning' : 'success');
                    @endphp
                    <tr>
                        <td class="font-weight-bold">{{ $row->warehouse }}</td>
                        <td class="text-center">{{ number_format($row->total_cells) }}</td>
                        <td class="text-center">{{ number_format($row->used_cells) }}</td>
                        <td class="text-center">
                            <span class="badge badge-{{ $color }}">{{ $row->utilization }}%</span>
                        </td>
                        <td style="vertical-align:middle;">
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-{{ $color }}"
                                    style="width:{{ $row->utilization }}%"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Tabel stok per kategori --}}
<div class="card">
    <div class="card-header py-2">
        <strong><i class="fas fa-table mr-1"></i>Detail Stok per Kategori</strong>
    </div>
    <div class="card-body p-0">
        @if($stockByCategory->isEmpty())
            <div class="text-center text-muted py-4">Belum ada data stok tersedia.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th width="40" class="text-center">#</th>
                        <th>Kategori</th>
                        <th class="text-center" width="130">Jumlah SKU</th>
                        <th class="text-center" width="150">Total Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stockByCategory as $i => $row)
                    <tr>
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            <span class="badge px-2" style="background:{{ $row->color }};color:#fff;font-size:12px;">
                                {{ $row->name }}
                            </span>
                        </td>
                        <td class="text-center">{{ number_format($row->sku_count) }}</td>
                        <td class="text-center font-weight-bold">{{ number_format($row->total_qty) }}</td>
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
<script src="{{ asset('js/highcharts.min.js') }}"></script>
<script>
$(function () {
    @if(!$stockByCategory->isEmpty())
    // Pie: Stok per Kategori
    Highcharts.chart('chartCategoryPie', {
        chart: { type: 'pie', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        tooltip: {
            pointFormat: '<b>{point.name}</b><br>Qty: <b>{point.y:,.0f}</b> ({point.percentage:.1f}%)'
        },
        plotOptions: {
            pie: {
                allowPointSelect: true, cursor: 'pointer',
                dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%', style: { fontSize: '11px' } }
            }
        },
        series: [{
            name: 'Total Qty',
            data: [
                @foreach($stockByCategory as $row)
                { name: '{{ addslashes($row->name) }}', y: {{ $row->total_qty }}, color: '{{ $row->color }}' },
                @endforeach
            ]
        }],
        credits: { enabled: false }
    });
    @endif

    @if(!$topItems->isEmpty())
    // Bar: Top 10 Items
    Highcharts.chart('chartTopItems', {
        chart: { type: 'bar', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: {
            categories: [
                @foreach($topItems as $item)
                '{{ addslashes(Str::limit($item->name, 25)) }}',
                @endforeach
            ],
            title: { text: null }
        },
        yAxis: { title: { text: 'Total Qty' }, min: 0 },
        tooltip: { valueSuffix: ' pcs' },
        plotOptions: { bar: { dataLabels: { enabled: true } } },
        series: [{
            name: 'Total Qty',
            color: '#007bff',
            data: [
                @foreach($topItems as $item)
                {{ $item->total_qty }},
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
