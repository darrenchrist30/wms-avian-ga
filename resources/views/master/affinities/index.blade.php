@extends('layouts.adminlte')
@section('title', 'Co-Occurrence Sparepart')

@section('content')
<div class="container-fluid pb-4">

{{-- ── Header ── --}}
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:8px;">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-project-diagram text-primary mr-2"></i>Analisis Co-Occurrence Sparepart
        </h5>
        <small class="text-muted">
            Pasangan sparepart yang sering datang bersamaan dalam satu inbound order —
            digunakan GA sebagai komponen <strong>FC_AFF</strong> (bobot afinitas, maks 20 poin).
        </small>
    </div>
    <div class="text-right text-muted" style="font-size:11px;line-height:1.6;">
        <div>
            <i class="fas fa-history mr-1"></i>
            @if ($lastUpdated)
                Diperbarui: <strong>{{ \Carbon\Carbon::parse($lastUpdated)->format('d M Y, H:i') }}</strong>
            @else
                Belum ada data
            @endif
        </div>
        <div>
            <i class="fas fa-truck mr-1"></i>
            Berdasarkan <strong>{{ number_format($ordersCompleted) }}</strong> inbound order selesai
        </div>
    </div>
</div>

{{-- ── Metodologi Panel ── --}}
<div class="card mb-3" style="border-left:4px solid #007bff;">
    <div class="card-header py-2 d-flex justify-content-between align-items-center"
         data-toggle="collapse" data-target="#panelMetodologi" style="cursor:pointer;">
        <span class="font-weight-bold small">
            <i class="fas fa-book-open mr-1 text-primary"></i>Sumber Data &amp; Cara Kerja
        </span>
        <i class="fas fa-angle-down text-muted" id="iconMetodologi"></i>
    </div>
    <div id="panelMetodologi" class="collapse show">
        <div class="card-body py-3">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex align-items-start">
                        <div class="mr-2 mt-1" style="width:28px;height:28px;border-radius:50%;background:#17a2b8;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-database text-white" style="font-size:11px;"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold small mb-1">Sumber Data</div>
                            <p class="text-muted mb-0" style="font-size:12px;line-height:1.5;">
                                Co-occurrence dihitung dari histori <strong>inbound order</strong> yang berstatus
                                <code>completed</code>. Setiap pasangan item yang muncul dalam order yang
                                sama akan menaikkan hitungan (<em>co_occurrence_count</em>) sebesar 1.
                                Dihitung ulang otomatis via <code>RecalculateAffinityJob</code>
                                setiap kali sebuah order selesai diproses.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex align-items-start">
                        <div class="mr-2 mt-1" style="width:28px;height:28px;border-radius:50%;background:#fd7e14;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-calculator text-white" style="font-size:11px;"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold small mb-1">Formula Normalisasi</div>
                            <div class="p-2 rounded mb-1" style="background:#f8f9fa;font-family:monospace;font-size:12px;">
                                affinity_score = co_count / max(co_count)
                            </div>
                            <p class="text-muted mb-0" style="font-size:12px;line-height:1.5;">
                                Skor dinormalisasi ke rentang <strong>0.0 – 1.0</strong>.
                                Pasangan dengan co-occurrence tertinggi mendapat skor <strong>1.0</strong>;
                                pasangan lain dihitung secara proporsional.
                                Normalisasi diulang setiap kali ada order baru selesai.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <div class="mr-2 mt-1" style="width:28px;height:28px;border-radius:50%;background:#28a745;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-dna text-white" style="font-size:11px;"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold small mb-1">Penggunaan di GA</div>
                            <p class="text-muted mb-0" style="font-size:12px;line-height:1.5;">
                                Skor afinitas dipakai GA pada komponen fitness <strong>FC_AFF</strong>
                                (kontribusi maks 20 poin dari total 100). Item dengan afinitas tinggi
                                akan cenderung ditempatkan di cell yang <strong>berdekatan</strong>,
                                sehingga operator tidak perlu berpindah-pindah area saat mengambil
                                sparepart yang sering dipakai bersama.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Summary Cards ── --}}
<div class="row mb-3">
    <div class="col-6 col-md-4 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner">
                <h4>{{ number_format($totalPairs) }}</h4>
                <p>Total Pasangan Item</p>
            </div>
            <div class="icon"><i class="fas fa-link"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-4 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner">
                <h4>{{ number_format($avgScore, 4) }}</h4>
                <p>Rata-rata Skor Afinitas</p>
            </div>
            <div class="icon"><i class="fas fa-star"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-4 mb-2">
        <div class="small-box bg-warning mb-0">
            <div class="inner">
                <h4>{{ number_format($maxCount) }}</h4>
                <p>Co-Occurrence Tertinggi</p>
            </div>
            <div class="icon"><i class="fas fa-fire"></i></div>
        </div>
    </div>
</div>

@if ($totalPairs === 0)
{{-- ── Empty State ── --}}
<div class="card mb-3">
    <div class="card-body text-center py-5">
        <i class="fas fa-project-diagram fa-4x mb-4 d-block text-muted" style="opacity:.3;"></i>
        <h5 class="font-weight-bold">Belum Ada Data Co-Occurrence</h5>
        <p class="text-muted mb-3">
            Data afinitas dihitung otomatis setelah inbound order selesai diproses.<br>
            Selesaikan minimal <strong>1 inbound order</strong> dengan 2 atau lebih jenis sparepart
            untuk memulai analisis co-occurrence.
        </p>
        <a href="{{ route('inbound.orders.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-truck mr-1"></i>Lihat Inbound Order
        </a>
    </div>
</div>
@else

{{-- ── Bar Chart Top 10 ── --}}
<div class="card mb-3">
    <div class="card-header py-2">
        <span class="font-weight-bold">
            <i class="fas fa-chart-bar mr-1 text-primary"></i>
            Top 10 Pasangan Sparepart — Co-Occurrence Tertinggi
        </span>
    </div>
    <div class="card-body">
        <div id="chartCoOccurrence" style="height:300px;"></div>
    </div>
    <div class="card-footer py-1">
        <small class="text-muted">
            <i class="fas fa-info-circle mr-1"></i>
            Batang biru = jumlah co-occurrence (sumbu kiri).
            Garis oranye = skor afinitas 0–1 (sumbu kanan).
            Skor tertinggi selalu 1.0 — dinormalisasi relatif terhadap pasangan paling sering bersama.
        </small>
    </div>
</div>

@endif

{{-- ── DataTable ── --}}
<div class="card">
    <div class="card-header py-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:6px;">
            <span class="font-weight-bold">
                <i class="fas fa-table mr-1 text-primary"></i>
                Daftar Pasangan Co-Occurrence
                <span class="badge badge-primary ml-1">{{ $totalPairs }}</span>
            </span>
            <div class="d-flex align-items-center" style="gap:6px;">
                <select id="filterCategory" class="form-control form-control-sm" style="width:180px;">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary" id="btnRefresh">
                    <i class="fas fa-redo mr-1"></i>Reset
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="datatable" class="table table-bordered table-sm table-hover mb-0 w-100">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" width="40">#</th>
                        <th>Sparepart A</th>
                        <th>Sparepart B</th>
                        <th class="text-center" width="130">Co-Occurrence</th>
                        <th width="220">Skor Afinitas</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    <div class="card-footer py-2">
        <div class="d-flex flex-wrap align-items-center" style="gap:12px;">
            <small class="text-muted font-weight-bold">Interpretasi skor:</small>
            <small>
                <span class="d-inline-block mr-1" style="width:10px;height:10px;border-radius:2px;background:#28a745;"></span>
                <strong style="color:#28a745;">≥ 0.70</strong> — Afinitas tinggi (sering bersama)
            </small>
            <small>
                <span class="d-inline-block mr-1" style="width:10px;height:10px;border-radius:2px;background:#fd7e14;"></span>
                <strong style="color:#fd7e14;">0.40 – 0.69</strong> — Afinitas sedang
            </small>
            <small>
                <span class="d-inline-block mr-1" style="width:10px;height:10px;border-radius:2px;background:#6c757d;"></span>
                <strong style="color:#6c757d;">&lt; 0.40</strong> — Afinitas rendah
            </small>
            <small class="text-muted ml-auto">
                <i class="fas fa-lightbulb text-warning mr-1"></i>
                Pasangan dengan skor tinggi diprioritaskan GA untuk cell berdekatan.
            </small>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/highcharts.min.js') }}"></script>
<script>
$(document).ready(function () {

    // Toggle icon for metodologi panel
    $('#panelMetodologi').on('show.bs.collapse', function () {
        $('#iconMetodologi').removeClass('fa-angle-right').addClass('fa-angle-down');
    }).on('hide.bs.collapse', function () {
        $('#iconMetodologi').removeClass('fa-angle-down').addClass('fa-angle-right');
    });

    // ── DataTable ──────────────────────────────────────────────────────────
    var table = $('#datatable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[3, 'desc']],
        ajax: {
            url: "{{ route('master.affinities.datatable') }}",
            data: function (d) {
                d.category_id = $('#filterCategory').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex',  name: 'DT_RowIndex',       orderable: false, searchable: false, className: 'text-center' },
            { data: 'item_a',       name: 'item.name',          orderable: false },
            { data: 'item_b',       name: 'relatedItem.name',   orderable: false },
            { data: 'cocount_html', name: 'co_occurrence_count', searchable: false },
            { data: 'score_bar',    name: 'affinity_score',     orderable: false, searchable: false },
        ]
    });

    $('#filterCategory').on('change', function () { table.ajax.reload(); });
    $('#btnRefresh').on('click', function () {
        $('#filterCategory').val('');
        table.ajax.reload();
    });

    // ── Highcharts Bar+Line ────────────────────────────────────────────────
    @if ($totalPairs > 0)
    var top10 = @json($top10);
    var labels = top10.map(function(d){ return d.label; });
    var counts = top10.map(function(d){ return d.count; });
    var scores = top10.map(function(d){ return d.score; });

    Highcharts.chart('chartCoOccurrence', {
        chart: { type: 'column', style: { fontFamily: 'inherit' } },
        title: { text: null },
        credits: { enabled: false },
        xAxis: {
            categories: labels,
            labels: { rotation: -25, style: { fontSize: '11px' } }
        },
        yAxis: [
            { title: { text: 'Jumlah Co-Occurrence' }, min: 0, allowDecimals: false },
            { title: { text: 'Skor Afinitas (0–1)' }, min: 0, max: 1, opposite: true }
        ],
        tooltip: {
            shared: true,
            formatter: function () {
                var s = '<b>' + this.x + '</b><br/>';
                this.points.forEach(function(p){
                    s += p.series.name + ': <b>' + p.y + '</b><br/>';
                });
                return s;
            }
        },
        legend: { enabled: true },
        series: [
            {
                name: 'Co-Occurrence',
                type: 'column',
                data: counts,
                color: '#007bff',
                yAxis: 0
            },
            {
                name: 'Skor Afinitas',
                type: 'line',
                data: scores,
                color: '#fd7e14',
                yAxis: 1,
                marker: { enabled: true, radius: 4 }
            }
        ]
    });
    @endif
});
</script>
@endpush
