@extends('layouts.adminlte')

@section('title', 'Stok Sel: ' . $cell->code)

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Detail Stok Sel</h3>
            </div>
        </div>

        {{-- Info Sel --}}
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card card-secondary card-outline">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                Sel: <code>{{ $cell->code }}</code>
                            </div>
                            <a href="{{ route('location.cells.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">Warehouse</small>
                                <div class="font-weight-bold">{{ $cell->rack->zone->warehouse->name ?? '-' }}</div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Zona</small>
                                <div class="font-weight-bold">{{ $cell->rack->zone->code ?? '-' }} — {{ $cell->rack->zone->name ?? '' }}</div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Rak</small>
                                <div class="font-weight-bold">{{ $cell->rack->code ?? '-' }}</div>
                            </div>
                            <div class="col-md-1">
                                <small class="text-muted">Level</small>
                                <div class="font-weight-bold">{{ chr(64 + $cell->level) }}</div>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Kapasitas</small>
                                <div>
                                    <span class="font-weight-bold">{{ $cell->capacity_used }}</span>
                                    <span class="text-muted">/ {{ $cell->capacity_max }}</span>
                                    @php $pct = $cell->capacity_max > 0 ? round(($cell->capacity_used / $cell->capacity_max) * 100) : 0; @endphp
                                    <span class="badge badge-{{ $pct >= 100 ? 'danger' : ($pct >= 75 ? 'warning' : 'success') }} ml-1">
                                        {{ $pct }}%
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <small class="text-muted">Status</small>
                                <div>
                                    @php
                                        $statusClass = match($cell->status) {
                                            'available' => 'badge-success',
                                            'partial'   => 'badge-warning',
                                            'full'      => 'badge-danger',
                                            'blocked'   => 'badge-dark',
                                            'reserved'  => 'badge-info',
                                            default     => 'badge-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }}">{{ ucfirst($cell->status) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stok Table --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="font-weight-bold">
                            <i class="fas fa-boxes mr-1"></i> Stok di Sel Ini
                            <span class="badge badge-primary ml-1">{{ $cell->stocks->count() }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($cell->stocks->isEmpty())
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-2"></i>
                                <p>Tidak ada stok di sel ini.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th width="40">#</th>
                                            <th>SKU / Nama Sparepart</th>
                                            <th width="100">Kategori</th>
                                            <th width="80" class="text-center">Qty</th>
                                            <th width="80" class="text-center">Satuan</th>
                                            <th width="100">LPN</th>
                                            <th width="100">Batch No</th>
                                            <th width="100" class="text-center">Tgl Masuk</th>
                                            <th width="100" class="text-center">Tgl Kadaluarsa</th>
                                            <th width="90" class="text-center">Status Stok</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cell->stocks as $i => $stock)
                                            @php
                                                $stockStatusClass = match($stock->status) {
                                                    'available'  => 'badge-success',
                                                    'reserved'   => 'badge-info',
                                                    'quarantine' => 'badge-warning',
                                                    'expired'    => 'badge-danger',
                                                    default      => 'badge-secondary',
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>
                                                    <div class="font-weight-bold">{{ $stock->item->name ?? '-' }}</div>
                                                    <small class="text-muted">{{ $stock->item->sku ?? '' }}</small>
                                                </td>
                                                <td>
                                                    @if ($stock->item?->category)
                                                        <span class="badge"
                                                            style="background:{{ $stock->item->category->color_code ?? '#6c757d' }};color:#fff;">
                                                            {{ $stock->item->category->name }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center font-weight-bold">{{ $stock->quantity }}</td>
                                                <td class="text-center">{{ $stock->item?->unit->code ?? '-' }}</td>
                                                <td><code>{{ $stock->lpn ?? '-' }}</code></td>
                                                <td>{{ $stock->batch_no ?? '-' }}</td>
                                                <td class="text-center">
                                                    {{ $stock->inbound_date ? \Carbon\Carbon::parse($stock->inbound_date)->format('d/m/Y') : '-' }}
                                                </td>
                                                <td class="text-center">
                                                    @if ($stock->expiry_date)
                                                        @php $daysLeft = now()->diffInDays($stock->expiry_date, false); @endphp
                                                        <span class="{{ $daysLeft <= 30 ? 'text-danger font-weight-bold' : '' }}">
                                                            {{ \Carbon\Carbon::parse($stock->expiry_date)->format('d/m/Y') }}
                                                        </span>
                                                        @if ($daysLeft <= 30)
                                                            <br><small class="text-danger">{{ $daysLeft }} hari lagi</small>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge {{ $stockStatusClass }}">
                                                        {{ ucfirst($stock->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
