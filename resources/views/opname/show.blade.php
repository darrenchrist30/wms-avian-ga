@extends('layouts.adminlte')

@section('title', 'Detail Opname — ' . $opname->opname_number)

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1a2332">
                <i class="fas fa-clipboard-check mr-2" style="color:#0d8564"></i>
                {{ $opname->opname_number }}
            </h4>
            <small class="text-muted">
                {{ $opname->warehouse?->name }} · {{ $opname->opname_date?->format('d/m/Y') }}
                @if($opname->completed_at)
                    · Selesai: {{ $opname->completed_at->format('d/m/Y H:i') }}
                @endif
            </small>
        </div>
        <div class="d-flex align-items-center" style="gap:8px">
            @if($opname->status === 'in_progress')
                <a href="{{ route('opname.scan', $opname->id) }}" class="btn btn-warning btn-sm">
                    <i class="fas fa-barcode mr-1"></i> Lanjut Scan
                </a>
            @endif
            <a href="{{ route('opname.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="row mb-3">
        <div class="col-sm-3">
            <div class="info-box mb-0" style="background:#1a2332;color:#fff;border-radius:8px">
                <span class="info-box-icon" style="background:rgba(255,255,255,.1)">
                    <i class="fas fa-list-ol" style="color:#0d8564"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Item</span>
                    <span class="info-box-number">{{ $summary['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="info-box mb-0" style="background:#155724;color:#fff;border-radius:8px">
                <span class="info-box-icon" style="background:rgba(255,255,255,.1)">
                    <i class="fas fa-check-circle"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Sesuai</span>
                    <span class="info-box-number">{{ $summary['match'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="info-box mb-0" style="background:#721c24;color:#fff;border-radius:8px">
                <span class="info-box-icon" style="background:rgba(255,255,255,.1)">
                    <i class="fas fa-arrow-down"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Kekurangan</span>
                    <span class="info-box-number">{{ $summary['shortage'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="info-box mb-0" style="background:#856404;color:#fff;border-radius:8px">
                <span class="info-box-icon" style="background:rgba(255,255,255,.1)">
                    <i class="fas fa-arrow-up"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Kelebihan</span>
                    <span class="info-box-number">{{ $summary['surplus'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Detail --}}
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center py-2"
             style="background:#1a2332;color:#fff;font-size:13px;font-weight:600">
            <span><i class="fas fa-table mr-2" style="color:#0d8564"></i>Rincian Hasil Opname</span>
            <span class="badge badge-light text-dark">{{ $summary['total'] }} item</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px">
                    <thead style="background:#f8f9fa">
                        <tr>
                            <th class="pl-3">Item</th>
                            <th>Kategori</th>
                            <th class="text-center">Qty Sistem</th>
                            <th class="text-center">Qty Fisik</th>
                            <th class="text-center">Selisih</th>
                            <th>Lokasi</th>
                            <th>Dicatat Oleh</th>
                            <th class="text-center">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($items as $si)
                        @php
                            $diff = $si->difference;
                            $diffClass = $diff > 0 ? 'text-success font-weight-bold' :
                                        ($diff < 0 ? 'text-danger font-weight-bold' : 'text-muted');
                            $rowBg = $diff > 0 ? '#f0fff4' : ($diff < 0 ? '#fff5f5' : '');
                        @endphp
                        <tr style="background:{{ $rowBg }}">
                            <td class="pl-3">
                                <div class="font-weight-bold">{{ $si->item?->name }}</div>
                                <small class="text-muted">{{ $si->item?->sku }}</small>
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ $si->item?->category?->name ?? '—' }}</span>
                            </td>
                            <td class="text-center">{{ $si->system_qty }}</td>
                            <td class="text-center font-weight-bold">{{ $si->physical_qty }}</td>
                            <td class="text-center">
                                <span class="{{ $diffClass }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                    @if($diff > 0) <i class="fas fa-arrow-up ml-1"></i>
                                    @elseif($diff < 0) <i class="fas fa-arrow-down ml-1"></i>
                                    @else <i class="fas fa-check ml-1"></i>
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if($si->cell)
                                    <span class="badge badge-info">{{ $si->cell->code }}</span>
                                    <small class="text-muted">{{ $si->cell->rack?->zone?->name }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $si->scannedBy?->name ?? '—' }}</td>
                            <td class="text-center text-muted">{{ $si->scanned_at?->format('H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                Belum ada item yang tercatat dalam sesi opname ini.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
