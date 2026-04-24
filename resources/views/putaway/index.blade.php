@extends('layouts.adminlte')

@section('title', 'Put-Away — Penempatan Barang')

@section('content')
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-md-12">
                <h4 class="mt-2">
                    <i class="fas fa-dolly-flatbed mr-2 text-primary"></i>
                    Put-Away — Penempatan Barang
                </h4>
                <p class="text-muted mb-0">Daftar inbound order yang sudah disetujui GA dan siap di-put-away oleh operator.
                </p>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
         ANTRIAN AKTIF
    ══════════════════════════════════════════════════════ --}}
        @if ($orders->isEmpty())
            <div class="card mb-4">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                    <h5>Tidak ada order yang menunggu put-away</h5>
                    <p>Semua order sudah selesai, atau belum ada rekomendasi GA yang disetujui.</p>
                    <a href="{{ route('inbound.orders.index') }}" class="btn btn-primary">
                        <i class="fas fa-arrow-left mr-1"></i> Lihat Inbound Order
                    </a>
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
                        $cardBorder = $isPartial ? 'border-warning' : 'border-primary';
                        $badgeCls = $isPartial ? 'badge-warning' : 'badge-primary';
                        $badgeTxt = $isPartial ? 'Sedang Berjalan' : 'Siap Put-Away';
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
                                    <div class="col-6">
                                        <small class="text-muted">Gudang</small>
                                        <div class="font-weight-bold">{{ $order->warehouse?->name ?? '-' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Diterima Oleh</small>
                                        <div class="font-weight-bold">{{ $order->receivedBy?->name ?? '-' }}</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Progress Put-Away</small>
                                        <small class="font-weight-bold">{{ $done }} / {{ $total }}
                                            item</small>
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

        {{-- ══════════════════════════════════════════════════════
         RIWAYAT PUT-AWAY (Completed)
    ══════════════════════════════════════════════════════ --}}
        @if ($completedOrders->isNotEmpty())
            <div class="mt-2 mb-1">
                <h6 class="text-muted font-weight-bold" style="letter-spacing:.5px;font-size:12px">
                    <i class="fas fa-history mr-1"></i> RIWAYAT PUT-AWAY — SELESAI
                    <small class="font-weight-normal ml-1">({{ $completedOrders->total() }} order)</small>
                </h6>
                <hr class="mt-1 mb-3">
            </div>

            <div class="row">
                @foreach ($completedOrders as $order)
                    @php
                        $total = $order->items_count;
                        $done = $order->put_away_count;
                    @endphp
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-success shadow-sm h-100" style="opacity:.92">
                            <div class="card-header d-flex justify-content-between align-items-center py-2"
                                style="background:#f1fff5">
                                <span class="font-weight-bold">
                                    <i class="fas fa-file-alt mr-1 text-success"></i>
                                    <code>{{ $order->do_number }}</code>
                                </span>
                                <span class="badge badge-primary">
                                    <i class="fas fa-check-double mr-1"></i>Completed
                                </span>
                            </div>
                            <div class="card-body py-3">
                                <div class="row text-sm mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">Gudang</small>
                                        <div class="font-weight-bold">{{ $order->warehouse?->name ?? '-' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Diterima Oleh</small>
                                        <div class="font-weight-bold">{{ $order->receivedBy?->name ?? '-' }}</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Progress Put-Away</small>
                                        <small class="font-weight-bold text-success">{{ $done }} /
                                            {{ $total }} item</small>
                                    </div>
                                    <div class="progress" style="height:10px">
                                        <div class="progress-bar bg-success" role="progressbar" style="width:100%"></div>
                                    </div>
                                    <small class="text-muted">100% selesai</small>
                                </div>
                            </div>
                            <div class="card-footer py-2" style="background:#f1fff5">
                                <a href="{{ route('putaway.show', $order->id) }}"
                                    class="btn btn-primary btn-sm btn-block">
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
