@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Rak' : 'Edit Rak')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-{{ $typeForm == 'create' ? 'layer-group' : 'edit' }} mr-2 text-primary"></i>
                {{ $typeForm == 'create' ? 'Tambah Rak' : 'Edit Rak' }}
            </h5>
            <small class="text-muted">
                {{ $typeForm == 'create' ? 'Daftarkan rak baru dalam gudang' : 'Perbarui data rak: ' . ($data->code ?? '') }}
            </small>
        </div>
        <a href="{{ route('location.racks.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle mr-1"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card shadow-sm {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
        <div class="card-header py-2">
            <h3 class="card-title font-weight-bold">
                <i class="fas fa-{{ $typeForm == 'create' ? 'plus-circle' : 'edit' }} mr-1"></i>
                {{ $typeForm == 'create' ? 'Data Rak Baru' : 'Edit: ' . ($data->code ?? '') }}
            </h3>
        </div>

        <form id="form-rack"
            action="{{ $typeForm == 'create' ? route('location.racks.store') : route('location.racks.update', $data->id) }}"
            method="POST">
            @csrf
            @if ($typeForm == 'edit') @method('PUT') @endif

            <div class="card-body">

                {{-- Gudang --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Gudang <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <select name="warehouse_id"
                                class="form-control @error('warehouse_id') is-invalid @enderror">
                                <option value="">-- Pilih Gudang --</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}"
                                        {{ old('warehouse_id', $data->warehouse_id ?? '') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Kategori Dominan --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Kategori Dominan</label>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
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
                            @error('dominant_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Kategori sparepart yang diutamakan di rak ini. Dipakai GA untuk put-away optimal.</small>
                    </div>
                </div>

                {{-- Kode Rak --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Kode Rak <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="code"
                                class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code', $data->code ?? '') }}"
                                placeholder="Contoh: R-A01" style="text-transform:uppercase"
                                maxlength="20">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Unik per gudang, maks 20 karakter.</small>
                    </div>
                </div>

                {{-- Nama Rak --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Nama Rak</label>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $data->name ?? '') }}"
                                placeholder="Nama rak (opsional)" maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Total Level (create only) --}}
                @if ($typeForm == 'create')
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Total Level <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="number" name="total_levels"
                                class="form-control @error('total_levels') is-invalid @enderror"
                                value="{{ old('total_levels', 7) }}" min="1" max="26">
                            @error('total_levels')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Jumlah level rak (A, B, C, ...).</small>
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

                <hr class="my-3">

                {{-- Status --}}
                <div class="form-group row mb-0">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Status</label>
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
                    <div class="alert alert-light border small text-muted mt-3 mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Gudang: {{ $data->warehouse->name ?? '-' }} |
                        Level: {{ $data->total_levels }} (A–{{ chr(64 + $data->total_levels) }}) |
                        Dibuat: {{ $data->created_at->format('d M Y') }}
                    </div>
                @endif

            </div>{{-- /card-body --}}

            <div class="card-footer d-flex justify-content-end align-items-center">
                <a href="{{ route('location.racks.index') }}" class="btn btn-secondary mr-2">
                    <i class="fas fa-times mr-1"></i>Batal
                </a>
                <button type="submit" class="btn btn-{{ $typeForm == 'create' ? 'primary' : 'success' }}">
                    <i class="fas fa-save mr-1"></i>
                    {{ $typeForm == 'create' ? 'Simpan & Generate Sel' : 'Update' }}
                </button>
            </div>

        </form>
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
