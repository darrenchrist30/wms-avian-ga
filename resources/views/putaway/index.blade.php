@extends('layouts.adminlte')

@section('title', 'Put-Away (Penempatan Barang)')

@push('styles')
<style>
#tblOrders thead th,
#tblCompleted thead th {
    background-color: #1a3c2e;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .4px;
    border-color: #155230;
    border-right: 1px solid #2e5c44 !important;
}
#tblOrders thead th:last-child,
#tblCompleted thead th:last-child {
    border-right: none !important;
}
#tblOrders tbody td,
#tblCompleted tbody td {
    border-right: 1px solid #e9ecef;
}
#tblOrders tbody td:last-child,
#tblCompleted tbody td:last-child {
    border-right: none;
}
#tblOrders.table-striped tbody tr:nth-of-type(odd),
#tblCompleted.table-striped tbody tr:nth-of-type(odd) {
    background-color: #f4f9f7;
}
#tblOrders.table-striped tbody tr:nth-of-type(even),
#tblCompleted.table-striped tbody tr:nth-of-type(even) {
    background-color: #fff;
}
</style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="row mb-3 align-items-center">
            <div class="col">
                <h4 class="mt-2 mb-0">
                    <i class="fas fa-dolly-flatbed mr-2 text-primary"></i>
                    Put-Away (Penempatan Barang)
                </h4>
                {{-- <p class="text-muted mb-0 mt-1">Daftar inbound order yang sudah disetujui GA dan siap di-put-away oleh operator.</p> --}}
            </div>
            <div class="col-auto mt-2 d-flex" style="gap:6px;">
                <a href="{{ route('putaway.operator') }}" class="btn btn-success btn-sm shadow-sm">
                    <i class="fas fa-barcode mr-1"></i> Scan Put-Away
                </a>
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

                        <div class="col-6 col-md-2 mb-2 mb-md-0">
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

                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <label class="mb-1" style="font-size:12px;font-weight:600;color:#555">
                                <i class="fas fa-calendar-alt mr-1"></i> Start Date
                            </label>
                            <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control form-control-sm">
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <label class="mb-1" style="font-size:12px;font-weight:600;color:#555">
                                <i class="fas fa-calendar-check mr-1"></i> End Date
                            </label>
                            <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control form-control-sm">
                        </div>

                        <div class="col-auto mb-2 mb-md-0">
                            <label class="mb-1 d-block" style="font-size:12px">&nbsp;</label>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                        </div>

                        @if (request()->hasAny(['status', 'warehouse_id', 'start_date', 'end_date']))
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
                        (<span class="text-primary">{{ $filterLabel }}</span>)
                    @endif
                    @if ($orders->count() > 0)
                        <small class="font-weight-normal ml-1">({{ $orders->count() }} order)</small>
                    @endif
                </h6>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <table id="tblOrders" class="table table-hover table-striped mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>No SJ</th>
                                <th>Tanggal SJ</th>
                                <th>Gudang</th>
                                <th>Progress</th>
                                <th class="text-center" style="width:160px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                @php
                                    $done  = $order->put_away_count;
                                    $total = $order->items_count;
                                    $pct   = $total > 0 ? round(($done / $total) * 100) : 0;
                                @endphp
                                <tr>
                                    <td>
                                        <span style="font-weight:600;color:#13283f;">{{ $order->do_number }}</span>
                                    </td>
                                    <td>{{ $order->do_date ? $order->do_date->format('d M Y') : $order->created_at->format('d M Y') }}</td>
                                    <td>{{ $order->warehouse?->name ?? '-' }}</td>
                                    <td style="min-width:140px;">
                                        <div class="d-flex align-items-center" style="gap:8px;">
                                            <div class="progress flex-grow-1" style="height:8px;border-radius:4px;">
                                                <div class="progress-bar" role="progressbar"
                                                    style="width:{{ $pct }}%;background-color:#0d8564;"></div>
                                            </div>
                                            <small style="white-space:nowrap;font-size:11px;color:#6c757d;">{{ $done }}/{{ $total }}</small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('putaway.show', $order->id) }}"
                                           class="btn btn-sm btn-primary" style="white-space:nowrap;">
                                            <i class="fas fa-dolly mr-1"></i>
                                            {{ $done > 0 ? 'Lanjutkan' : 'Mulai' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        @endif {{-- end antrian aktif --}}

        {{-- ══════════════════════════════════════════════════════
         RIWAYAT PUT-AWAY (Completed)
         Disembunyikan kalau filter = recommended atau put_away
    ══════════════════════════════════════════════════════ --}}
        @if ($completedOrders->isNotEmpty())
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <table id="tblCompleted" class="table table-hover table-striped mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>No SJ</th>
                                <th>Tanggal Selesai</th>
                                <th>Gudang</th>
                                <th>Item</th>
                                <th class="text-center" style="width:120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($completedOrders as $order)
                                <tr>
                                    <td><span style="font-weight:600;color:#13283f;">{{ $order->do_number }}</span></td>
                                    <td>{{ $order->processed_at ? $order->processed_at->format('d M Y') : $order->updated_at->format('d M Y') }}</td>
                                    <td>{{ $order->warehouse?->name ?? '-' }}</td>
                                    <td>{{ $order->put_away_count }} / {{ $order->items_count }} item</td>
                                    <td class="text-center">
                                        <a href="{{ route('putaway.show', $order->id) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
@endsection

@push('scripts')
<script>
$(function() {
    @if (request('status') !== 'completed')
    $('#tblOrders').DataTable({
        order: [],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: 4 }]
    });
    @endif
    @if ($completedOrders->isNotEmpty())
    $('#tblCompleted').DataTable({
        order: [],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: 4 }]
    });
    @endif
});
</script>
@endpush
