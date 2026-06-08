@extends('layouts.adminlte')
@section('title', 'Detail Rak: ' . $rack->code)

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-layer-group mr-2 text-primary"></i>Detail Rak: {{ $rack->code }}
            </h5>
            <small class="text-muted">{{ $rack->warehouse->name ?? '-' }}</small>
        </div>
        <div>
            <a href="{{ route('location.racks.edit', $rack->id) }}" class="btn btn-sm btn-warning mr-1">
                <i class="fas fa-edit mr-1"></i> Edit Rak
            </a>
            <a href="{{ route('location.racks.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="row">

        {{-- ── Info Rak ───────────────────────────────────────────────────── --}}
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0 font-weight-bold">
                        <i class="fas fa-info-circle mr-1"></i> Informasi Rak
                    </h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted pl-3" style="width:45%;font-size:12px;">Kode</td>
                            <td class="font-weight-bold">{{ $rack->code }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Nama</td>
                            <td>{{ $rack->name ?: '<span class="text-muted">—</span>' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Gudang</td>
                            <td><small>{{ $rack->warehouse->name ?? '—' }}</small></td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Level</td>
                            <td>{{ $rack->total_levels }} level (A–{{ chr(64 + $rack->total_levels) }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Kolom</td>
                            <td>{{ $rack->total_columns }} kolom</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Total Sel</td>
                            <td><strong>{{ $rack->cells_count }}</strong> sel</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Posisi 3D</td>
                            <td><small class="text-muted">X:{{ $rack->pos_x }} Z:{{ $rack->pos_z }}</small></td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Status</td>
                            <td>
                                @if($rack->is_active)
                                    <span class="badge badge-success">Aktif</span>
                                @else
                                    <span class="badge badge-secondary">Nonaktif</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3" style="font-size:12px;">Dibuat</td>
                            <td><small class="text-muted">{{ $rack->created_at->format('d M Y') }}</small></td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- Legenda --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0 font-weight-bold">
                        <i class="fas fa-palette mr-1"></i> Legenda
                    </h6>
                </div>
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center mb-1" style="font-size:12px;">
                        <span class="cell-badge" style="background:#d4edda;border:1px solid #c3e6cb;"></span>
                        <span>Kosong (tersedia)</span>
                    </div>
                    <div class="d-flex align-items-center mb-1" style="font-size:12px;">
                        <span class="cell-badge" style="background:#fff3cd;border:1px solid #ffeeba;"></span>
                        <span>Sebagian terisi</span>
                    </div>
                    <div class="d-flex align-items-center mb-1" style="font-size:12px;">
                        <span class="cell-badge" style="background:#f8d7da;border:1px solid #f5c6cb;"></span>
                        <span>Penuh</span>
                    </div>
                    <div class="d-flex align-items-center" style="font-size:12px;">
                        <span class="cell-badge" style="background:#e2e3e5;border:1px solid #d6d8db;"></span>
                        <span>Diblokir / Nonaktif</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Grid Sel ───────────────────────────────────────────────────── --}}
        <div class="col-md-8 col-lg-9">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0 font-weight-bold">
                        <i class="fas fa-th mr-1"></i> Sel Rak
                        <span class="badge badge-secondary ml-1">{{ $rack->cells_count }} sel</span>
                    </h6>
                    <small class="text-muted">
                        Tampak depan rak - baris = level (A atas), kolom = kolom (kiri–kanan)
                    </small>
                </div>
                <div class="card-body p-2" style="overflow-x:auto;">

                    @if($rack->cells->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <div>Belum ada sel di rak ini.</div>
                        </div>
                    @else
                        <table class="table table-bordered table-sm mb-0" style="min-width:{{ max(300, count($columns) * 130) }}px;">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center text-muted" style="width:48px;font-size:11px;">Level</th>
                                    @foreach($columns as $col)
                                        <th class="text-center" style="font-size:11px;">
                                            Kolom {{ $col }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($levels as $level)
                                    @php $letter = chr(64 + $level); @endphp
                                    <tr>
                                        <td class="text-center font-weight-bold text-muted align-middle"
                                            style="background:#f8f9fa;font-size:13px;">{{ $letter }}</td>
                                        @foreach($columns as $col)
                                            @php
                                                $cell = $cellGrid[$level][$col] ?? null;
                                                if ($cell) {
                                                    if (!$cell->is_active || $cell->status === 'blocked') {
                                                        $bg = '#e2e3e5'; $border = '#d6d8db'; $textColor = '#6c757d';
                                                    } elseif ($cell->status === 'full') {
                                                        $bg = '#f8d7da'; $border = '#f5c6cb'; $textColor = '#721c24';
                                                    } elseif ($cell->capacity_used > 0) {
                                                        $bg = '#fff3cd'; $border = '#ffeeba'; $textColor = '#856404';
                                                    } else {
                                                        $bg = '#d4edda'; $border = '#c3e6cb'; $textColor = '#155724';
                                                    }
                                                    $pct = $cell->capacity_max > 0
                                                        ? min(100, round($cell->capacity_used / $cell->capacity_max * 100))
                                                        : 0;
                                                }
                                            @endphp
                                            <td class="p-1 align-middle"
                                                style="{{ $cell ? "background:$bg;border-color:$border;" : 'background:#f8f9fa;' }}">
                                                @if($cell)
                                                    <a href="{{ route('location.cells.stock', $cell->id) }}"
                                                       class="d-block text-decoration-none"
                                                       style="color:{{ $textColor }};" title="Lihat stok sel {{ $cell->code }}">
                                                        <div class="font-weight-bold" style="font-size:11px;">
                                                            {{ $cell->code }}
                                                        </div>
                                                        <div style="font-size:10px;margin-top:2px;">
                                                            {{ $cell->capacity_used }}/{{ $cell->capacity_max }}
                                                            @if(!$cell->is_active)
                                                                <span class="badge badge-secondary" style="font-size:9px;">off</span>
                                                            @elseif($cell->status === 'blocked')
                                                                <span class="badge badge-secondary" style="font-size:9px;">blokir</span>
                                                            @elseif($cell->status === 'full')
                                                                <span class="badge badge-danger" style="font-size:9px;">penuh</span>
                                                            @endif
                                                        </div>
                                                        {{-- Capacity bar --}}
                                                        <div style="height:3px;background:rgba(0,0,0,.1);border-radius:2px;margin-top:3px;">
                                                            <div style="height:3px;background:{{ $pct >= 80 ? '#dc3545' : ($pct >= 40 ? '#ffc107' : '#28a745') }};width:{{ $pct }}%;border-radius:2px;"></div>
                                                        </div>
                                                    </a>
                                                @else
                                                    <span class="text-muted" style="font-size:10px;">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
.cell-badge {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 3px;
    margin-right: 6px;
    flex-shrink: 0;
}
</style>
@endpush
