@extends('layouts.adminlte')
@section('title', 'Efektivitas Algoritma GA')

@section('content')
<div class="container-fluid pb-4">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-dna text-purple mr-2" style="color:#6f42c1;"></i>Efektivitas Algoritma Genetika
        </h5>
        <small class="text-muted">Performa GA dalam mengoptimasi lokasi put-away — fitness, kecepatan, dan kepatuhan</small>
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
    <div class="col-6 col-md-2 mb-2">
        <div class="small-box bg-secondary mb-0">
            <div class="inner"><h4>{{ number_format($summary['total_ga']) }}</h4><p>Total Run GA</p></div>
            <div class="icon"><i class="fas fa-dna"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-2 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner"><h4>{{ $summary['avg_fitness'] }}</h4><p>Avg Fitness</p></div>
            <div class="icon"><i class="fas fa-star"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner"><h4>{{ $summary['best_fitness'] }}</h4><p>Best Fitness</p></div>
            <div class="icon"><i class="fas fa-trophy"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-2 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ number_format($summary['avg_exec_ms']) }} ms</h4><p>Avg Eksekusi</p></div>
            <div class="icon"><i class="fas fa-bolt"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="small-box {{ $summary['compliance_pct'] >= 80 ? 'bg-success' : ($summary['compliance_pct'] >= 50 ? 'bg-warning' : 'bg-danger') }} mb-0">
            <div class="inner"><h4>{{ $summary['compliance_pct'] }}%</h4><p>Tingkat Kepatuhan</p></div>
            <div class="icon"><i class="fas fa-check-double"></i></div>
        </div>
    </div>
</div>

@if($summary['total_ga'] == 0)
{{-- Empty state --}}
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-dna fa-4x mb-4 d-block" style="color:#6f42c1; opacity:.3;"></i>
        <h5 class="font-weight-bold">Belum Ada Data GA untuk Tahun {{ $year }}</h5>
        <p class="text-muted mb-3">
            Algoritma Genetika dijalankan secara otomatis saat proses inbound diterima.
            Data fitness, kecepatan, dan kepatuhan akan muncul di sini setelah GA dieksekusi.
        </p>
        <a href="{{ route('inbound.orders.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-truck mr-1"></i>Mulai dari Inbound
        </a>
    </div>
</div>
@else

<div class="row">
    {{-- Trend Fitness --}}
    <div class="col-md-8 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-line mr-1"></i>Tren Fitness Score per Bulan</strong>
            </div>
            <div class="card-body">
                <div id="chartFitnessTrend" style="height:280px;"></div>
            </div>
        </div>
    </div>

    {{-- Compliance Donut --}}
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-pie mr-1"></i>Kepatuhan Rekomendasi GA</strong>
            </div>
            <div class="card-body d-flex flex-column">
                @if(($compliance->total ?? 0) == 0)
                    <div class="text-center text-muted py-4 my-auto">
                        <i class="fas fa-question-circle fa-2x mb-2 d-block"></i>
                        Belum ada data konfirmasi put-away.
                    </div>
                @else
                    <div id="chartCompliance" style="height:200px;"></div>
                    <div class="mt-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="fas fa-circle text-success mr-1"></i>Diikuti</span>
                            <strong>{{ $compliance->followed ?? 0 }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="fas fa-circle text-danger mr-1"></i>Di-override</span>
                            <strong>{{ $compliance->overridden ?? 0 }}</strong>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Waktu Eksekusi --}}
<div class="card mb-3">
    <div class="card-header py-2">
        <strong><i class="fas fa-stopwatch mr-1"></i>Rata-rata Waktu Eksekusi GA per Bulan (ms)</strong>
    </div>
    <div class="card-body">
        <div id="chartExecTime" style="height:220px;"></div>
    </div>
</div>

{{-- Tabel riwayat GA --}}
<div class="card">
    <div class="card-header py-2">
        <strong><i class="fas fa-history mr-1"></i>Riwayat 50 Run GA Terakhir — {{ $year }}</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" id="tblGA">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="40">#</th>
                        <th>DO / Inbound</th>
                        <th class="text-center" width="130">Fitness Score</th>
                        <th class="text-center" width="100">Generasi</th>
                        <th class="text-center" width="110">Waktu (ms)</th>
                        <th class="text-center" width="100">Status</th>
                        <th width="140">Dijalankan Oleh</th>
                        <th class="text-center" width="130">Waktu Run</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gaRecords as $i => $ga)
                    @php
                        $fitColor = $ga->fitness_score >= 0.8 ? 'success'
                                  : ($ga->fitness_score >= 0.5 ? 'warning' : 'danger');
                    @endphp
                    <tr>
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            @if($ga->inboundOrder)
                                <a href="{{ route('inbound.orders.show', $ga->inbound_order_id) }}"
                                   class="font-weight-bold text-dark">
                                    {{ $ga->inboundOrder->do_number }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($ga->fitness_score)
                                <span class="badge badge-{{ $fitColor }} px-2" style="font-size:12px;">
                                    {{ number_format($ga->fitness_score, 4) }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">{{ number_format($ga->generations_run ?? 0) }}</td>
                        <td class="text-center">{{ number_format($ga->execution_time_ms ?? 0) }}</td>
                        <td class="text-center">
                            @php
                                $stColors = ['approved'=>'success','rejected'=>'danger','pending'=>'warning'];
                            @endphp
                            <span class="badge badge-{{ $stColors[$ga->status] ?? 'secondary' }}">
                                {{ ucfirst($ga->status) }}
                            </span>
                        </td>
                        <td>{{ $ga->generatedBy->name ?? '—' }}</td>
                        <td class="text-center">
                            <small>{{ $ga->generated_at?->format('d M Y H:i') ?? $ga->created_at->format('d M Y H:i') }}</small>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

</div>
@endsection

@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
const monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$(function () {
    @if($summary['total_ga'] > 0)

    // Trend fitness
    Highcharts.chart('chartFitnessTrend', {
        chart: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: { categories: monthNames },
        yAxis: { title: { text: 'Fitness Score' }, min: 0, max: 1 },
        tooltip: { shared: true, valueSuffix: '' },
        series: [
            {
                name: 'Avg Fitness',
                type: 'spline',
                color: '#28a745',
                data: {!! json_encode($chartAvgFit) !!},
                marker: { enabled: true }
            },
            {
                name: 'Max Fitness',
                type: 'spline',
                color: '#007bff',
                dashStyle: 'Dash',
                data: {!! json_encode($chartMaxFit) !!},
                marker: { enabled: false }
            }
        ],
        credits: { enabled: false }
    });

    // Exec time
    Highcharts.chart('chartExecTime', {
        chart: { type: 'column', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: { categories: monthNames },
        yAxis: { title: { text: 'ms' }, min: 0 },
        tooltip: { valueSuffix: ' ms' },
        series: [{
            name: 'Rata-rata Waktu Eksekusi',
            color: '#6f42c1',
            data: {!! json_encode($chartExecMs) !!}
        }],
        credits: { enabled: false },
        legend: { enabled: false }
    });

    @if(($compliance->total ?? 0) > 0)
    Highcharts.chart('chartCompliance', {
        chart: { type: 'pie', style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        tooltip: { pointFormat: '<b>{point.name}</b>: {point.y} ({point.percentage:.1f}%)' },
        plotOptions: {
            pie: {
                innerSize: '60%',
                dataLabels: { enabled: false },
                showInLegend: true
            }
        },
        series: [{
            name: 'Kepatuhan',
            data: [
                { name: 'Diikuti', y: {{ $compliance->followed ?? 0 }}, color: '#28a745' },
                { name: 'Di-override', y: {{ $compliance->overridden ?? 0 }}, color: '#dc3545' },
            ]
        }],
        credits: { enabled: false }
    });
    @endif

    // DataTable for GA records
    $('#tblGA').DataTable({
        pageLength: 25,
        order: [[7, 'desc']],
        language: { url: '/vendor/datatables/i18n/id.json' },
    });

    @endif
});
</script>
@endpush
