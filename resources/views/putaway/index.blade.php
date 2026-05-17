@extends('layouts.adminlte')

@section('title', 'Put-Away — Penempatan Barang')

@section('content')
    <div class="container-fluid">

        <div class="row mb-2 align-items-center">
            <div class="col">
                <h4 class="mt-2 mb-0">
                    <i class="fas fa-dolly-flatbed mr-2 text-primary"></i>
                    Put-Away — Penempatan Barang
                </h4>
                <p class="text-muted mb-0 mt-1">Daftar inbound order yang sudah disetujui GA dan siap di-put-away oleh operator.</p>
            </div>
            <div class="col-auto mt-2">
                <a href="{{ route('putaway.queue') }}" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fas fa-stream mr-1"></i> Put-Away Queue
                </a>
            </div>
        </div>

        {{-- ── Filter Bar ───────────────────────────────────────────────────────── --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-body py-2 px-3">
                <form method="GET" action="{{ route('putaway.index') }}" id="filterForm">
                    <div class="row align-items-end">

                        <div class="col-12 col-md-4 mb-2 mb-md-0">
                            <label class="mb-1" style="font-size:12px;font-weight:600;color:#555">
                                <i class="fas fa-search mr-1"></i> Cari DO Number
                            </label>
                            <input type="text" name="search" id="searchInput"
                                class="form-control form-control-sm"
                                placeholder="Ketik nomor DO, tekan Enter…"
                                value="{{ request('search') }}">
                        </div>

                        <div class="col-6 col-md-3 mb-2 mb-md-0">
                            <label class="mb-1" style="font-size:12px;font-weight:600;color:#555">
                                <i class="fas fa-tasks mr-1"></i> Status
                            </label>
                            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="put_away" {{ request('status', 'put_away') === 'put_away' ? 'selected' : '' }}>
                                    Sedang Berjalan
                                </option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>
                                    Completed
                                </option>
                            </select>
                        </div>

                        @if ($warehouses->count() > 1)
                        <div class="col-6 col-md-3 mb-2 mb-md-0">
                            <label class="mb-1" style="font-size:12px;font-weight:600;color:#555">
                                <i class="fas fa-warehouse mr-1"></i> Gudang
                            </label>
                            <select name="warehouse_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="">Semua Gudang</option>
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        @if (request()->hasAny(['search', 'status', 'warehouse_id']))
                        <div class="col-auto mb-2 mb-md-0">
                            <label class="mb-1 d-block" style="font-size:12px">&nbsp;</label>
                            <a href="{{ route('putaway.index') }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-times mr-1"></i> Reset
                            </a>
                        </div>
                        @endif

                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
         ANTRIAN AKTIF — disembunyikan kalau filter = completed
    ══════════════════════════════════════════════════════ --}}
        @if (request('status') !== 'completed')

            @php $filterLabel = match(request('status')) {
                'put_away' => 'Sedang Berjalan',
                default    => 'Aktif',
            }; @endphp

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 text-muted font-weight-bold" style="font-size:12px;letter-spacing:.5px">
                    <i class="fas fa-stream mr-1"></i> ANTRIAN PUT-AWAY
                    @if (request('status'))
                        — <span class="text-primary">{{ $filterLabel }}</span>
                    @endif
                    @if ($orders->total() > 0)
                        <small class="font-weight-normal ml-1">({{ $orders->total() }} order)</small>
                    @endif
                </h6>
                @if (request()->hasAny(['search','status','warehouse_id']))
                    <span class="badge badge-info">Difilter</span>
                @endif
            </div>

            @if ($orders->isEmpty())
                <div class="card mb-4">
                    <div class="card-body text-center py-5 text-muted">
                        @if (request()->hasAny(['search', 'status', 'warehouse_id']))
                            <i class="fas fa-filter fa-4x mb-3 text-secondary"></i>
                            <h5>Tidak ada order yang cocok dengan filter</h5>
                            <p>Coba ubah atau <a href="{{ route('putaway.index') }}">reset filter</a>.</p>
                        @else
                            <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                            <h5>Tidak ada order yang menunggu put-away</h5>
                            <p>Semua order sudah selesai, atau belum ada rekomendasi GA yang disetujui.</p>
                            <a href="{{ route('inbound.orders.index') }}" class="btn btn-primary">
                                <i class="fas fa-arrow-left mr-1"></i> Lihat Inbound Order
                            </a>
                        @endif
                    </div>
                </div>
            @else
                <div class="row">
                    @foreach ($orders as $order)
                        @php
                            $done = $order->put_away_count;
                            $total = $order->items_count;
                            $pct = $total > 0 ? round(($done / $total) * 100) : 0;
                            $isPartial = $order->status === 'put_away';
                            $cardBorder = 'border-warning';
                            $badgeCls = 'badge-warning text-dark';
                            $badgeTxt = 'Sedang Berjalan';
                        @endphp
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card {{ $cardBorder }} shadow-sm h-100">
                                <div class="card-header d-flex justify-content-between align-items-center py-2">
                                    <span class="font-weight-bold">
                                        <i class="fas fa-file-alt mr-1"></i>
                                        <code>{{ $order->do_number }}</code>
                                    </span>
                                    <span class="badge {{ $badgeCls }}">{{ $badgeTxt }}</span>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row text-sm mb-2">
                                        <div class="col-12">
                                            <small class="text-muted">Gudang</small>
                                            <div class="font-weight-bold">{{ $order->warehouse?->name ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="text-muted">Progress Put-Away</small>
                                            <small class="font-weight-bold">{{ $done }} / {{ $total }} item</small>
                                        </div>
                                        <div class="progress" style="height:10px">
                                            <div class="progress-bar {{ $pct === 100 ? 'bg-success' : 'bg-primary' }} progress-bar-striped"
                                                role="progressbar" style="width:{{ $pct }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $pct }}% selesai</small>
                                    </div>
                                </div>
                                <div class="card-footer py-2">
                                    <a href="{{ route('putaway.show', $order->id) }}" class="btn btn-primary btn-sm btn-block">
                                        <i class="fas fa-dolly mr-1"></i>
                                        {{ $isPartial ? 'Lanjutkan Put-Away' : 'Mulai Put-Away' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-center mt-2">
                    {{ $orders->links() }}
                </div>
            @endif

        @endif {{-- end antrian aktif --}}

        {{-- ══════════════════════════════════════════════════════
         RIWAYAT PUT-AWAY (Completed)
         Disembunyikan kalau filter = recommended atau put_away
    ══════════════════════════════════════════════════════ --}}
        @if ($completedOrders->isNotEmpty())
            <div class="mt-3 mb-1">
                <h6 class="text-muted font-weight-bold" style="letter-spacing:.5px;font-size:12px">
                    <i class="fas fa-history mr-1"></i> RIWAYAT PUT-AWAY — COMPLETED
                    <small class="font-weight-normal ml-1">({{ $completedOrders->total() }} order)</small>
                </h6>
                <hr class="mt-1 mb-3">
            </div>

            <div class="row">
                @foreach ($completedOrders as $order)
                    @php
                        $total = $order->items_count;
                        $done  = $order->put_away_count;
                    @endphp
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-success shadow-sm h-100" style="opacity:.92">
                            <div class="card-header d-flex justify-content-between align-items-center py-2"
                                style="background:#f1fff5">
                                <span class="font-weight-bold">
                                    <i class="fas fa-file-alt mr-1 text-success"></i>
                                    <code>{{ $order->do_number }}</code>
                                </span>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-double mr-1"></i>Completed
                                </span>
                            </div>
                            <div class="card-body py-3">
                                <div class="row text-sm mb-2">
                                    <div class="col-12">
                                        <small class="text-muted">Gudang</small>
                                        <div class="font-weight-bold">{{ $order->warehouse?->name ?? '-' }}</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Progress Put-Away</small>
                                        <small class="font-weight-bold text-success">{{ $done }} / {{ $total }} item</small>
                                    </div>
                                    <div class="progress" style="height:10px">
                                        <div class="progress-bar bg-success" role="progressbar" style="width:100%"></div>
                                    </div>
                                    <small class="text-muted">100% selesai</small>
                                </div>
                            </div>
                            <div class="card-footer py-2" style="background:#f1fff5">
                                <a href="{{ route('putaway.show', $order->id) }}"
                                    class="btn btn-outline-success btn-sm btn-block">
                                    <i class="fas fa-eye mr-1"></i> Lihat Detail
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-center mt-2">
                {{ $completedOrders->links() }}
            </div>
        @endif

    </div>
@endsection

@push('scripts')
<script>
    // Auto-submit search saat Enter (tanpa tombol submit)
    document.getElementById('searchInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('filterForm').submit();
        }
    });
</script>
@endpush
