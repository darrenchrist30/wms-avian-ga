@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Zona Penyimpanan' : 'Edit Zona Penyimpanan')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-{{ $typeForm == 'create' ? 'map-marker-alt' : 'edit' }} mr-2 text-primary"></i>
                {{ $typeForm == 'create' ? 'Tambah Zona Penyimpanan' : 'Edit Zona Penyimpanan' }}
            </h5>
            <small class="text-muted">
                {{ $typeForm == 'create' ? 'Buat zona baru dalam gudang' : 'Perbarui data zona: ' . ($data->name ?? '') }}
            </small>
        </div>
        <a href="{{ route('location.zones.index') }}" class="btn btn-sm btn-outline-secondary">
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
                {{ $typeForm == 'create' ? 'Data Zona Baru' : 'Edit: ' . ($data->name ?? '') }}
            </h3>
        </div>

        <form id="form-zona"
            action="{{ $typeForm == 'create' ? route('location.zones.store') : route('location.zones.update', $data->id) }}"
            method="POST">
            @csrf
            @if ($typeForm == 'edit') @method('PUT') @endif

            <div class="card-body">

                {{-- Warehouse --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Warehouse <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <select name="warehouse_id"
                                class="form-control @error('warehouse_id') is-invalid @enderror">
                                <option value="">-- Pilih Warehouse --</option>
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}"
                                        {{ old('warehouse_id', $data->warehouse_id ?? '') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Kode Zona --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Kode Zona <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="code"
                                class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code', $data->code ?? '') }}"
                                placeholder="Contoh: A, B, C atau Z01" style="text-transform:uppercase"
                                maxlength="10">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Kode unik per warehouse, maks 10 karakter.</small>
                    </div>
                </div>

                {{-- Nama Zona --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Nama Zona <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $data->name ?? '') }}"
                                placeholder="Contoh: Zone A - Fast Moving" maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Deskripsi --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Deskripsi</label>
                    <div class="col-sm-9">
                        <textarea name="description" rows="3"
                            class="form-control @error('description') is-invalid @enderror"
                            placeholder="Keterangan singkat tentang zona ini...">{{ old('description', $data->description ?? '') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Posisi X & Z --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Posisi X <small class="text-muted">(layout 3D)</small>
                    </label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="number" name="pos_x" step="0.01"
                                class="form-control @error('pos_x') is-invalid @enderror"
                                value="{{ old('pos_x', $data->pos_x ?? 0) }}" placeholder="0">
                            @error('pos_x')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Posisi Z <small class="text-muted">(layout 3D)</small>
                    </label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="number" name="pos_z" step="0.01"
                                class="form-control @error('pos_z') is-invalid @enderror"
                                value="{{ old('pos_z', $data->pos_z ?? 0) }}" placeholder="0">
                            @error('pos_z')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

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
                        Zona ini memiliki <strong>{{ $data->racks_count ?? 0 }} rak</strong>.
                        Dibuat: {{ $data->created_at->format('d M Y') }}
                    </div>
                @endif

            </div>{{-- /card-body --}}

            <div class="card-footer d-flex justify-content-end align-items-center">
                <a href="{{ route('location.zones.index') }}" class="btn btn-secondary mr-2">
                    <i class="fas fa-times mr-1"></i>Batal
                </a>
                <button type="submit" class="btn btn-{{ $typeForm == 'create' ? 'primary' : 'success' }}">
                    <i class="fas fa-save mr-1"></i>
                    {{ $typeForm == 'create' ? 'Simpan' : 'Update' }}
                </button>
            </div>

        </form>
    </div>

</div>
@endsection
