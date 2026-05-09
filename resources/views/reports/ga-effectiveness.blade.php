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
    <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
        <a href="{{ route('reports.ga-effectiveness.export.pdf', ['year' => $year]) }}"
           class="btn btn-sm btn-danger" target="_blank">
            <i class="fas fa-file-pdf mr-1"></i> Export PDF
        </a>
        <a href="{{ route('reports.ga-effectiveness.export.excel', ['year' => $year]) }}"
           class="btn btn-sm btn-success">
            <i class="fas fa-file-excel mr-1"></i> Export Excel
        </a>
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
            <div class="inner">
                <h4>{{ $summary['compliance_pct'] }}%</h4>
                <p>Tingkat Kepatuhan
                    <i class="fas fa-info-circle ml-1" style="font-size:11px;opacity:0.8;cursor:pointer"
                       data-toggle="tooltip" data-placement="top"
                       title="Persentase konfirmasi put-away yang mengikuti rekomendasi GA tanpa override. Nilai di bawah 100% tidak berarti GA buruk — operator dapat melakukan override karena partial allocation, kapasitas berubah, atau testing."></i>
                </p>
            </div>
            <div class="icon"><i class="fas fa-check-double"></i></div>
        </div>
    </div>
</div>

{{-- ── Metrik Efektivitas Penempatan (selalu tampil) ────────────────────── --}}
<div class="d-flex align-items-center mb-2 mt-1">
    <span class="font-weight-bold text-dark" style="font-size:13px">
        <i class="fas fa-map-marker-alt mr-1" style="color:#6f42c1"></i>Metrik Efektivitas Penempatan
    </span>
    <span class="text-muted ml-2" style="font-size:11px">— kondisi saat ini (snapshot gudang)</span>
</div>
<div class="row mb-4">
    {{-- Split Location --}}
    <div class="col-6 col-md-3 mb-2">
        <div class="card mb-0" style="border-left:4px solid #fd7e14;">
            <div class="card-body py-2 px-3">
                <div class="text-uppercase text-muted mb-1" style="font-size:10px;letter-spacing:.5px">Split Location</div>
                <div class="h3 font-weight-bold mb-0" style="color:#fd7e14">{{ number_format($summary['split_location_count']) }}</div>
                <small class="text-muted">item tersimpan di &gt;1 cell</small>
                <div class="mt-1" style="font-size:10px;color:#6c757d">
                    <i class="fas fa-info-circle mr-1"></i>Idealnya mendekati 0 (tidak split)
                </div>
            </div>
        </div>
    </div>

    {{-- Avg Lokasi per SKU --}}
    <div class="col-6 col-md-3 mb-2">
        <div class="card mb-0" style="border-left:4px solid #17a2b8;">
            <div class="card-body py-2 px-3">
                <div class="text-uppercase text-muted mb-1" style="font-size:10px;letter-spacing:.5px">Rata-rata Lokasi / SKU</div>
                <div class="h3 font-weight-bold mb-0" style="color:#17a2b8">{{ $summary['avg_locations_per_sku'] }}</div>
                <small class="text-muted">cell per item (rata-rata)</small>
                <div class="mt-1" style="font-size:10px;color:#6c757d">
                    <i class="fas fa-info-circle mr-1"></i>GA mengoptimasi mendekati 1.0
                </div>
            </div>
        </div>
    </div>

    {{-- Utilisasi Kapasitas Rak --}}
    <div class="col-6 col-md-3 mb-2">
        <div class="card mb-0" style="border-left:4px solid #28a745;">
            <div class="card-body py-2 px-3">
                <div class="text-uppercase text-muted mb-1" style="font-size:10px;letter-spacing:.5px">Utilisasi Kapasitas Rak</div>
                <div class="h3 font-weight-bold mb-0" style="color:#28a745">{{ $summary['rack_utilization'] }}%</div>
                <small class="text-muted">dari total kapasitas rak aktif</small>
                <div class="progress mt-1" style="height:4px">
                    <div class="progress-bar bg-success" style="width:{{ $summary['rack_utilization'] }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Estimasi Waktu Put-Away --}}
    <div class="col-6 col-md-3 mb-2">
        <div class="card mb-0" style="border-left:4px solid #007bff;">
            <div class="card-body py-2 px-3">
                <div class="text-uppercase text-muted mb-1" style="font-size:10px;letter-spacing:.5px">Estimasi Waktu Put-Away</div>
                <div class="h3 font-weight-bold mb-0" style="color:#007bff">
                    @if($summary['avg_putaway_minutes'] >= 60)
                        {{ round($summary['avg_putaway_minutes'] / 60, 1) }} jam
                    @else
                        {{ $summary['avg_putaway_minutes'] }} mnt
                    @endif
                </div>
                <small class="text-muted">rata-rata order selesai (tahun {{ $year }})</small>
                <div class="mt-1" style="font-size:10px;color:#6c757d">
                    <i class="fas fa-info-circle mr-1"></i>Dari buat order → semua item put-away
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Perbandingan Skenario Pengujian ──────────────────────────────────── --}}
<div class="d-flex align-items-center mb-2 mt-1">
    <span class="font-weight-bold text-dark" style="font-size:13px">
        <i class="fas fa-table mr-1" style="color:#6f42c1"></i>Perbandingan Skenario Pengujian
    </span>
    <span class="text-muted ml-2" style="font-size:11px">— evaluasi tiga skenario penempatan barang</span>
</div>
<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="tblScenario" style="font-size:13px;">
                <thead class="thead-light">
                    <tr>
                        <th style="width:200px;">Skenario</th>
                        <th class="text-center" style="width:140px;">Split Location <br><small class="font-weight-normal text-muted">item di &gt;1 cell</small></th>
                        <th class="text-center" style="width:160px;">Rata-rata Lokasi/SKU <br><small class="font-weight-normal text-muted">cell per item</small></th>
                        <th class="text-center" style="width:150px;">Utilisasi Rak <br><small class="font-weight-normal text-muted">kapasitas terpakai</small></th>
                        <th class="text-center" style="width:170px;">Est. Waktu Put-Away <br><small class="font-weight-normal text-muted">avg. order selesai</small></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scenarioComparison as $sc)
                    @php
                        $isGa      = $sc['is_ga'];
                        $isSim     = $sc['is_simulated'];
                        $rowBg     = $isGa ? 'rgba(40,167,69,.06)' : ($isSim ? 'rgba(220,53,69,.04)' : '');
                        $noData    = !$isGa && !$isSim && $sc['putaway_min'] == 0 && $sc['split_count'] == 0;
                    @endphp
                    <tr style="background:{{ $rowBg }}">
                        <td class="align-middle">
                            <span class="badge badge-{{ $sc['badge'] }} mr-1">{{ $sc['label'] }}</span>
                            <div class="text-muted mt-1" style="font-size:11px;">{{ $sc['desc'] }}</div>
                            @if($isSim)
                            <div class="mt-1"><span class="badge badge-light border" style="font-size:10px;"><i class="fas fa-flask mr-1"></i>Simulasi Teori</span></div>
                            @endif
                        </td>
                        <td class="text-center align-middle">
                            @if($isGa && $sc['split_count'] == 0 && $sc['avg_loc'] == 0)
                                <span class="text-muted">—</span><br><small class="text-muted" style="font-size:10px;">Data belum cukup</small>
                            @else
                                @php
                                    $aktual = $scenarioComparison[0]['split_count'];
                                    $diff   = $aktual - $sc['split_count'];
                                @endphp
                                <span class="h5 font-weight-bold mb-0 d-block {{ $isGa ? 'text-success' : ($isSim ? 'text-danger' : 'text-secondary') }}">
                                    {{ number_format($sc['split_count']) }}
                                </span>
                                @if(!$isSim && $isGa && $diff > 0)
                                <small class="text-success"><i class="fas fa-arrow-down"></i> {{ $diff }} lebih sedikit</small>
                                @elseif($isSim && $diff < 0)
                                <small class="text-danger"><i class="fas fa-arrow-up"></i> {{ abs($diff) }} lebih banyak</small>
                                @endif
                            @endif
                        </td>
                        <td class="text-center align-middle">
                            @if($isGa && $sc['avg_loc'] == 0)
                                <span class="text-muted">—</span>
                            @else
                                <span class="h5 font-weight-bold mb-0 d-block {{ $isGa ? 'text-success' : ($isSim ? 'text-danger' : 'text-secondary') }}">
                                    {{ $sc['avg_loc'] }}
                                </span>
                                @if($isGa)<small class="text-muted">idealnya 1.00</small>@endif
                            @endif
                        </td>
                        <td class="text-center align-middle">
                            <span class="h5 font-weight-bold mb-0 d-block text-secondary">{{ $sc['utilization'] }}%</span>
                            <div class="progress mt-1" style="height:4px;width:80px;margin:0 auto;">
                                <div class="progress-bar bg-success" style="width:{{ $sc['utilization'] }}%"></div>
                            </div>
                            @if($isSim)<small class="text-muted" style="font-size:10px;">sama (input identik)</small>@endif
                        </td>
                        <td class="text-center align-middle">
                            @if($sc['putaway_min'] == 0)
                                <span class="text-muted">—</span><br>
                                <small class="text-muted" style="font-size:10px;">{{ $isGa ? 'Data belum cukup' : 'Belum ada order selesai' }}</small>
                            @else
                                @php $mnt = $sc['putaway_min']; @endphp
                                <span class="h5 font-weight-bold mb-0 d-block {{ $isGa ? 'text-success' : ($isSim ? 'text-danger' : 'text-secondary') }}">
                                    @if($mnt >= 60){{ round($mnt/60,1) }} jam
                                    @else{{ $mnt }} mnt
                                    @endif
                                </span>
                                @if($isSim)
                                <small class="text-muted" style="font-size:10px;">estimasi +20% vs aktual</small>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 border-top" style="background:#f8f9fa;font-size:11px;color:#6c757d;">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Kondisi Aktual</strong>: snapshot stok gudang saat ini ({{ number_format($itemPutCounts->count()) }} SKU aktif, {{ number_format($totalActiveCells) }} sel tersedia).&nbsp;
            <strong>Penempatan Acak</strong>: simulasi probabilistik (occupancy model) — T<sub>i</sub> record stok per SKU ditempatkan ke sel acak tanpa konsolidasi; angka mendekati aktual karena kondisi saat ini merefleksikan penempatan belum teroptimasi.&nbsp;
            <strong>Rekomendasi GA</strong>: dihitung dari {{ number_format($gaFollowedCount) }} konfirmasi put-away yang mengikuti saran GA (tahun {{ $year }}). Semakin banyak order diikuti, perbedaan akan semakin signifikan.
        </div>
    </div>
</div>

@if($summary['total_ga'] == 0)
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

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <strong><i class="fas fa-chart-pie mr-1"></i>Kepatuhan Rekomendasi GA</strong>
                <i class="fas fa-info-circle text-muted ml-1" style="font-size:11px;cursor:pointer"
                   data-toggle="tooltip" data-placement="top"
                   title="Persentase konfirmasi put-away yang mengikuti rekomendasi GA tanpa override. Nilai di bawah 100% tidak berarti GA buruk — operator dapat melakukan override karena partial allocation, kapasitas berubah, atau testing."></i>
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

<div class="card mb-3">
    <div class="card-header py-2">
        <strong><i class="fas fa-stopwatch mr-1"></i>Rata-rata Waktu Eksekusi GA per Bulan (ms)</strong>
    </div>
    <div class="card-body">
        <div id="chartExecTime" style="height:220px;"></div>
    </div>
</div>

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
                        $fitColor = $ga->fitness_score >= 75 ? 'success'
                                  : ($ga->fitness_score >= 50 ? 'warning' : 'danger');
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
                                $stColors = ['accepted'=>'success','rejected'=>'danger','pending'=>'warning'];
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

    Highcharts.chart('chartFitnessTrend', {
        chart: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
        title: { text: null },
        xAxis: { categories: monthNames },
        yAxis: { title: { text: 'Fitness Score' }, min: 0, max: 100 },
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

    $('#tblGA').DataTable({
        pageLength: 25,
        order: [[7, 'desc']],
        language: { url: '/vendor/datatables/i18n/id.json' },
    });

    @endif
});
</script>
@endpush
