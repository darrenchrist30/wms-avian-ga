@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Rak' : 'Edit Rak')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Rak' : 'Edit Rak' }}</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                @if ($typeForm == 'create')
                                    <i class="fas fa-plus-circle mr-1"></i> Form Rak Baru
                                @else
                                    <i class="fas fa-edit mr-1"></i> Edit: {{ $data->code }}
                                @endif
                            </div>
                            <a href="{{ route('location.racks.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form-rack"
                            action="{{ $typeForm == 'create' ? route('location.racks.store') : route('location.racks.update', $data->id) }}"
                            method="POST">
                            @csrf
                            @if ($typeForm == 'edit')
                                @method('PUT')
                            @endif

                            <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                                <div class="card-body">

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Zona <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <select name="zone_id"
                                                class="form-control @error('zone_id') is-invalid @enderror">
                                                <option value="">-- Pilih Zona --</option>
                                                @foreach ($zones as $zone)
                                                    <option value="{{ $zone->id }}"
                                                        {{ old('zone_id', $data->zone_id ?? '') == $zone->id ? 'selected' : '' }}>
                                                        [{{ $zone->warehouse->name ?? '-' }}]
                                                        {{ $zone->code }} - {{ $zone->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('zone_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Kategori Dominan</label>
                                        <div class="col-sm-6">
                                            <select name="dominant_category_id"
                                                class="form-control @error('dominant_category_id') is-invalid @enderror">
                                                <option value="">-- Tidak Ditentukan --</option>
                                                @foreach ($categories as $cat)
                                                    <option value="{{ $cat->id }}"
                                                        {{ old('dominant_category_id', $data->dominant_category_id ?? '') == $cat->id ? 'selected' : '' }}>
                                                        {{ $cat->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Kategori sparepart yang diutamakan di rak ini. Dipakai GA untuk put-away optimal.</small>
                                            @error('dominant_category_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Kode Rak <span class="text-danger">*</span></label>
                                        <div class="col-sm-4">
                                            <input type="text" name="code"
                                                class="form-control @error('code') is-invalid @enderror"
                                                value="{{ old('code', $data->code ?? '') }}"
                                                placeholder="Contoh: R-A01" style="text-transform:uppercase"
                                                maxlength="20">
                                            @if ($typeForm == 'create')
                                                <small class="text-muted">Unik per zona, maks 20 karakter.</small>
                                            @endif
                                            @error('code')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Nama Rak</label>
                                        <div class="col-sm-6">
                                            <input type="text" name="name"
                                                class="form-control @error('name') is-invalid @enderror"
                                                value="{{ old('name', $data->name ?? '') }}"
                                                placeholder="Nama rak (opsional)" maxlength="100">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    @if ($typeForm == 'create')
                                        <div class="form-group row">
                                            <label class="col-sm-3 col-form-label">Total Level <span class="text-danger">*</span></label>
                                            <div class="col-sm-3">
                                                <input type="number" name="total_levels"
                                                    class="form-control @error('total_levels') is-invalid @enderror"
                                                    value="{{ old('total_levels', 7) }}" min="1" max="26">
                                                <small class="text-muted">Jumlah level rak (A, B, C, ...).</small>
                                                @error('total_levels')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="alert alert-info border small py-2">
                                            <i class="fas fa-magic mr-1"></i>
                                            Sel akan di-generate otomatis: <strong id="cellCount">7 sel</strong>
                                            — diberi label <strong id="cellRange">A – G</strong>
                                        </div>
                                    @else
                                        <div class="alert alert-light border small text-muted py-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Dimensi rak (level/kolom) tidak dapat diubah karena sel sudah ada.
                                            Rak ini memiliki <strong>{{ $data->cells_count ?? 0 }} sel</strong>.
                                        </div>
                                    @endif

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Status</label>
                                        <div class="col-sm-9 d-flex align-items-center">
                                            <div class="custom-control custom-switch">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" id="isActive"
                                                    class="custom-control-input"
                                                    {{ old('is_active', $data->is_active ?? 1) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="isActive">Aktif</label>
                                            </div>
                                        </div>
                                    </div>

                                    @if ($typeForm == 'edit')
                                        <div class="alert alert-light border small text-muted mt-2 mb-0">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Zona: {{ $data->zone->code ?? '-' }} |
                                            Level: {{ $data->total_levels }} (A–{{ chr(64 + $data->total_levels) }}) |
                                            Dibuat: {{ $data->created_at->format('d M Y') }}
                                        </div>
                                    @endif

                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('location.racks.index') }}" class="btn btn-secondary mr-2">
                                <i class="fas fa-times mr-1"></i>Batal
                            </a>
                            @if ($typeForm == 'create')
                                <button type="submit" form="form-rack" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Simpan & Generate Sel
                                </button>
                            @else
                                <button type="submit" form="form-rack" class="btn btn-success">
                                    <i class="fas fa-save mr-1"></i>Update
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        function updateCellCount() {
            var levels = parseInt($('[name=total_levels]').val()) || 0;
            $('#cellCount').text(levels + ' sel');
            if (levels > 0 && levels <= 26) {
                $('#cellRange').text('A – ' + letters[levels - 1]);
            }
        }
        $('[name=total_levels]').on('input', updateCellCount);
        updateCellCount();
    </script>
@endpush
