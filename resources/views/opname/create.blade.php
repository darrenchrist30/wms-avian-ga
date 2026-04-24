@extends('layouts.adminlte')

@section('title', 'Buat Sesi Opname')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1a2332">
                <i class="fas fa-clipboard-check mr-2" style="color:#0d8564"></i>Buat Sesi Opname Baru
            </h4>
            <small class="text-muted">Isi form berikut untuk memulai sesi hitung stok fisik</small>
        </div>
        <a href="{{ route('opname.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2" style="background:#1a2332;color:#fff;font-size:13px;font-weight:600">
            <i class="fas fa-clipboard-list mr-2" style="color:#0d8564"></i>Detail Sesi Opname
        </div>
        <div class="card-body">
            <form action="{{ route('opname.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="font-weight-bold" style="font-size:13px">
                                Gudang <span class="text-danger">*</span>
                            </label>
                            <select name="warehouse_id"
                                    class="form-control @error('warehouse_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}"
                                        {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->code }} — {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('warehouse_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="font-weight-bold" style="font-size:13px">
                                Tanggal Opname <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="opname_date"
                                   class="form-control @error('opname_date') is-invalid @enderror"
                                   value="{{ old('opname_date', date('Y-m-d')) }}" required>
                            @error('opname_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="form-group">
                            <label class="font-weight-bold" style="font-size:13px">Catatan</label>
                            <input type="text" name="notes" class="form-control"
                                   placeholder="Opsional — keterangan sesi opname ini"
                                   value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end" style="gap:8px">
                    <a href="{{ route('opname.index') }}" class="btn btn-light btn-sm">Batal</a>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-play mr-1"></i> Buat & Mulai Scan
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>
@endsection
