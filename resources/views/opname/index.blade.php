@extends('layouts.adminlte')

@section('title', 'Stock Opname')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1a2332">
                <i class="fas fa-clipboard-check mr-2" style="color:#0d8564"></i>Stock Opname
            </h4>
            <small class="text-muted">Hitung stok fisik dan bandingkan dengan data sistem</small>
        </div>
        <a href="{{ route('opname.create') }}" class="btn btn-success btn-sm">
            <i class="fas fa-plus mr-1"></i> Buat Sesi Opname
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Tabel --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px">
                    <thead style="background:#1a2332;color:#fff">
                        <tr>
                            <th class="pl-3">No. Opname</th>
                            <th>Gudang</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th class="text-center">Progress</th>
                            <th>Dibuat Oleh</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($opnames as $op)
                        @php
                            $pct = $op->items_count > 0 ? round($op->counted_count / $op->items_count * 100) : 0;
                            $statusClass = match($op->status) {
                                'draft'       => 'secondary',
                                'in_progress' => 'warning',
                                'completed'   => 'success',
                                'cancelled'   => 'danger',
                                default       => 'secondary',
                            };
                            $statusLabel = match($op->status) {
                                'draft'       => 'Draft',
                                'in_progress' => 'Berjalan',
                                'completed'   => 'Selesai',
                                'cancelled'   => 'Dibatalkan',
                                default       => $op->status,
                            };
                        @endphp
                        <tr>
                            <td class="pl-3 font-weight-bold">{{ $op->opname_number }}</td>
                            <td>{{ $op->warehouse?->name ?? '-' }}</td>
                            <td>{{ $op->opname_date?->format('d/m/Y') }}</td>
                            <td><span class="badge badge-{{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="text-center" style="width:180px">
                                <div class="progress" style="height:8px">
                                    <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                                </div>
                                <small class="text-muted">{{ $op->counted_count }}/{{ $op->items_count }} item ({{ $pct }}%)</small>
                            </td>
                            <td>{{ $op->createdBy?->name ?? '-' }}</td>
                            <td class="text-center">
                                @if(in_array($op->status, ['draft', 'in_progress']))
                                    <a href="{{ route('opname.scan', $op->id) }}" class="btn btn-warning btn-xs">
                                        <i class="fas fa-barcode mr-1"></i> Scan
                                    </a>
                                @endif
                                <a href="{{ route('opname.show', $op->id) }}" class="btn btn-info btn-xs">
                                    <i class="fas fa-eye mr-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-clipboard fa-2x mb-2 d-block"></i>
                                Belum ada sesi opname. Klik "Buat Sesi Opname" untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($opnames->hasPages())
            <div class="card-footer">{{ $opnames->links() }}</div>
        @endif
    </div>

</div>
@endsection
