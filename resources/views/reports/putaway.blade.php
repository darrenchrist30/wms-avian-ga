@extends('layouts.adminlte')
@section('title', 'Laporan Put-Away')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-map-marker-alt text-warning mr-2"></i>Laporan Put-Away
        </h5>
        <small class="text-muted">Analisis penempatan barang dan performa algoritma GA</small>
    </div>
    <div class="d-flex align-items-center" style="gap:6px;">
        <form method="GET" class="d-flex align-items-center" style="gap:6px;">
            <select name="year" class="form-control form-control-sm" style="width:100px;" onchange="this.form.submit()">
                @foreach($years as $y)
                <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-warning mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_ga']) }}</h4><p>Total Run GA</p></div>
            <div class="icon"><i class="fas fa-dna"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner"><h4>{{ $summary['avg_fitness'] }}</h4><p>Avg Fitness Score</p></div>
            <div class="icon"><i class="fas fa-star"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ number_format($summary['avg_exec_ms']) }} ms</h4><p>Avg Waktu Eksekusi</p></div>
            <div class="icon"><i class="fas fa-bolt"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box {{ $summary['approved_pct'] >= 80 ? 'bg-success' : 'bg-secondary' }} mb-0">
            <div class="inner"><h4>{{ $summary['approved_pct'] }}%</h4><p>GA Diterima</p></div>
            <div class="icon"><i class="fas fa-thumbs-up"></i></div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Trend Put-Away --}}
    <div class="col-md-8 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-line mr-1"></i>Tren Put-Away Bulanan {{ $year }}</strong>
            </div>
            <div class="card-body">
                <div id="chartPutAway" style="height:280px;"></div>
            </div>
        </div>
    </div>

    {{-- Per Kategori --}}
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-pie mr-1"></i>Distribusi per Kategori</strong>
            </div>
            <div class="card-body">
                @if($byCategory->isEmpty())
                    <div class="text-center text-muted py-4">Belum ada data.</div>
                @else
                    <div id="chartCategory" style="height:280px;"></div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- GA Stats Detail --}}
@if($summary['total_ga'] > 0)
<div class="card">
    <div class="card-header py-2">
        <strong><i class="fas fa-dna mr-1"></i>Statistik Algoritma GA â€” {{ $year }}</strong>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-6 col-md-3 mb-3">
                <div class="border rounded p-3">
                    <h4 class="text-success mb-1">{{ $summary['avg_fitness'] }}</h4>
                    <small class="text-muted">Rata-rata Fitness</small>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="border rounded p-3">
                    <h4 class="text-info mb-1">{{ number_format($summary['avg_exec_ms']) }} ms</h4>
                    <small class="text-muted">Rata-rata Eksekusi</small>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="border rounded p-3">
                    <h4 class="text-warning mb-1">{{ $gaStats->approved ?? 0 }} / {{ $summary['total_ga'] }}</h4>
                    <small class="text-muted">Diterima / Total</small>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="border rounded p-3">
                    <h4 class="text-danger mb-1">{{ $gaStats->rejected ?? 0 }}</h4>
                    <small class="text-muted">Ditolak / Rerun</small>
                </div>
            </div>
        </div>

        {{-- Komponen Fitness --}}
        <div class="mt-2">
            <p class="font-weight-bold mb-2"><i class="fas fa-info-circle text-primary mr-1"></i>Komponen Fungsi Fitness GA</p>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="font-weight-bold">FC_CAP â€” Kapasitas Cell</small>
                        <small class="text-muted">40 poin</small>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar bg-primary" style="width:100%"></div>
                    </div>
                    <small class="text-muted">Penempatan tidak melebihi kapasitas</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="font-weight-bold">FC_CAT â€” Kategori Sama</small>
                        <small class="text-muted">30 poin</small>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar bg-success" style="width:75%"></div>
                    </div>
                    <small class="text-muted">Barang kategori sama satu lokasi</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="font-weight-bold">FC_AFF â€” Item Affinity</small>
                        <small class="text-muted">20 poin</small>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar bg-warning" style="width:50%"></div>
                    </div>
                    <small class="text-muted">Item sering diambil bersama berdekatan</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="font-weight-bold">FC_SPLIT â€” Minimalkan Split</small>
                        <small class="text-muted">10 poin</small>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar bg-info" style="width:25%"></div>
                    </div>
                    <small class="text-muted">Satu item di satu cell (tidak split)</small>
                </div>
            </div>
        </div>
    </div>
</div>
@else
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="fas fa-dna fa-3x mb-3 d-block text-secondary"></i>
        <strong>Belum ada data GA untuk tahun {{ $year }}</strong>
        <p class="mb-0">Data akan muncul setelah GA dijalankan melalui proses inbound.</p>
    </div>
</div>
@endif

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/highcharts.min.js') }}"></script>
<script>
const monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$(function () {
    // Trend Put-Away
    Highcharts.chart('chartPutAway', {
        chart: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: { categories: monthNames },
        yAxis: [
            { title: { text: 'Jumlah Record' }, min: 0 },
            { title: { text: 'Total Qty' }, opposite: true, min: 0 }
        ],
        tooltip: { shared: true },
        series: [
            {
                name: 'Record Put-Away',
                type: 'column',
                color: '#ffc107',
                data: {!! json_encode($chartPutAway) !!}
            },
            {
                name: 'Total Qty',
                type: 'spline',
                color: '#28a745',
                yAxis: 1,
                data: {!! json_encode($chartQty) !!}
            }
        ],
        credits: { enabled: false }
    });

    @if(!$byCategory->isEmpty())
    // Category pie
    Highcharts.chart('chartCategory', {
        chart: { type: 'pie', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        tooltip: { pointFormat: '<b>{point.name}</b><br>Qty: {point.y:,.0f} ({point.percentage:.1f}%)' },
        plotOptions: {
            pie: {
                allowPointSelect: true, cursor: 'pointer',
                dataLabels: { enabled: true, format: '{point.percentage:.1f}%', style: { fontSize: '11px' } }
            }
        },
        series: [{
            name: 'Qty',
            data: [
                @foreach($byCategory as $cat)
                { name: '{{ addslashes($cat->category) }}', y: {{ $cat->qty }}, color: '{{ $cat->color }}' },
                @endforeach
            ]
        }],
        credits: { enabled: false }
    });
    @endif
});
</script>
@endpush
