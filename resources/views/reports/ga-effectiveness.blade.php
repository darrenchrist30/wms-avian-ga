@extends('layouts.adminlte')
@section('title', 'Evaluasi Efektivitas GA')

@section('content')
<div class="container-fluid pb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:10px;">
        <div>
            <h4 class="mb-1 font-weight-bold">
                <i class="fas fa-dna text-success mr-2"></i>Evaluasi Efektivitas Genetic Algorithm
            </h4>
            <div class="text-muted">
                Perbandingan apple-to-apple untuk item inbound yang sama: Existing / Manual, Random Placement, dan GA Recommendation.
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <strong><i class="fas fa-filter mr-1 text-success"></i>Scope Pengujian</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.ga-effectiveness') }}">
                <div class="row">
                    <div class="col-12 col-lg-8 mb-3">
                        <label class="small text-muted mb-1">Inbound Order / DO</label>
                        <select name="order_ids[]" class="form-control" multiple size="8" required>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" {{ in_array($order->id, $selectedOrderIds ?? [], true) ? 'selected' : '' }}>
                                    {{ $order->do_number }} - {{ optional($order->do_date)->format('Y-m-d') }} - {{ $order->status }} - {{ $order->warehouse?->code ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Pilih satu atau beberapa DO. Semua skenario akan dihitung memakai item inbound yang sama dari DO ini.</small>
                    </div>
                    <div class="col-12 col-lg-4 mb-3">
                        <label class="small text-muted mb-1">Random Seed</label>
                        <input type="number" min="1" name="random_seed" class="form-control" value="{{ $randomSeed ?? 42 }}">
                        <small class="text-muted">Seed membuat random placement bisa diulang dengan hasil yang sama.</small>
                        <button class="btn btn-success btn-block mt-3">
                            <i class="fas fa-calculator mr-1"></i>Hitung Evaluasi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(!$result)
        <div class="alert alert-info">
            Pilih inbound order, lalu klik <strong>Hitung Evaluasi</strong>. Evaluasi hanya valid jika item yang dibandingkan berasal dari scope DO yang sama.
        </div>
    @elseif(!$result['has_result'])
        <div class="alert alert-warning">
            Tidak ada item diterima pada scope yang dipilih.
        </div>
    @else
        <div class="row mb-3">
            <div class="col-6 col-md-3 mb-2">
                <div class="small-box bg-info mb-0">
                    <div class="inner">
                        <h4>{{ count($result['scope']['do_numbers']) }}</h4>
                        <p>DO Dievaluasi</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-alt"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="small-box bg-success mb-0">
                    <div class="inner">
                        <h4>{{ number_format($result['scope']['item_line_count']) }}</h4>
                        <p>Item Line Sama</p>
                    </div>
                    <div class="icon"><i class="fas fa-list"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="small-box bg-primary mb-0">
                    <div class="inner">
                        <h4>{{ number_format($result['scope']['sku_count']) }}</h4>
                        <p>SKU Unik</p>
                    </div>
                    <div class="icon"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="small-box bg-secondary mb-0">
                    <div class="inner">
                        <h4>{{ number_format($result['scope']['total_qty']) }}</h4>
                        <p>Total Qty</p>
                    </div>
                    <div class="icon"><i class="fas fa-cubes"></i></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white">
                <strong><i class="fas fa-table mr-1 text-success"></i>Ringkasan Metrik Evaluasi</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Metrik Evaluasi</th>
                                <th class="text-right">Existing / Manual</th>
                                <th class="text-right">Random Placement</th>
                                <th class="text-right">Genetic Algorithm</th>
                                <th class="text-right">Perbaikan GA vs Existing</th>
                                <th class="text-right">Perbaikan GA vs Random</th>
                                <th>Interpretasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($result['summary_rows'] as $row)
                                <tr>
                                    <td class="font-weight-bold">{{ $row['label'] }}</td>
                                    <td class="text-right">{{ $row['existing'] }}</td>
                                    <td class="text-right">{{ $row['random'] }}</td>
                                    <td class="text-right font-weight-bold text-success">{{ $row['ga'] }}</td>
                                    <td class="text-right {{ $row['improvement_existing']['class'] }}">{{ $row['improvement_existing']['label'] }}</td>
                                    <td class="text-right {{ $row['improvement_random']['class'] }}">{{ $row['improvement_random']['label'] }}</td>
                                    <td style="min-width:260px;">{{ $row['interpretation'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white">
                <strong><i class="fas fa-box mr-1 text-success"></i>Detail Per Item</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" style="font-size:13px;">
                        <thead class="thead-light">
                            <tr>
                                <th>DO</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th>Kategori</th>
                                <th class="text-right">Qty</th>
                                <th>Satuan</th>
                                <th>Existing Cell</th>
                                <th>Random Cell</th>
                                <th>GA Cell</th>
                                <th class="text-right">Dist Existing</th>
                                <th class="text-right">Dist Random</th>
                                <th class="text-right">Dist GA</th>
                                <th class="text-right">Demand</th>
                                <th>Movement</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($result['detail_rows'] as $row)
                                <tr>
                                    <td>{{ $row['do_number'] }}</td>
                                    <td><code>{{ $row['sku'] }}</code></td>
                                    <td>{{ $row['item_name'] }}</td>
                                    <td>{{ $row['category'] }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($row['qty']) }}</td>
                                    <td>{{ $row['unit'] }}</td>
                                    <td>{{ $row['existing_cell'] }}</td>
                                    <td>{{ $row['random_cell'] }}</td>
                                    <td class="font-weight-bold text-success">{{ $row['ga_cell'] }}</td>
                                    <td class="text-right">{{ $row['distance_existing'] !== null ? number_format($row['distance_existing'], 2) : '-' }}</td>
                                    <td class="text-right">{{ $row['distance_random'] !== null ? number_format($row['distance_random'], 2) : '-' }}</td>
                                    <td class="text-right">{{ $row['distance_ga'] !== null ? number_format($row['distance_ga'], 2) : '-' }}</td>
                                    <td class="text-right">{{ number_format($row['capacity_demand']) }}</td>
                                    <td>{{ $row['movement_type'] }}</td>
                                    <td style="min-width:220px;">{{ $row['notes'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if(!empty($result['notes']))
            <div class="alert alert-warning">
                <strong>Catatan transparansi data:</strong>
                <ul class="mb-0 pl-3">
                    @foreach(array_slice($result['notes'], 0, 10) as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                    @if(count($result['notes']) > 10)
                        <li>Dan {{ count($result['notes']) - 10 }} catatan lain.</li>
                    @endif
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <strong><i class="fas fa-book mr-1 text-success"></i>Ringkasan Formal Bab 4</strong>
            </div>
            <div class="card-body">
                <p class="mb-0" style="line-height:1.7;">{{ $result['narrative'] }}</p>
            </div>
        </div>
    @endif
</div>
@endsection
