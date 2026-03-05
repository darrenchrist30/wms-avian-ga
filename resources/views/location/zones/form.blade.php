@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Zona Penyimpanan' : 'Edit Zona Penyimpanan')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Zona Penyimpanan' : 'Edit Zona Penyimpanan' }}</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                @if ($typeForm == 'create')
                                    <i class="fas fa-plus-circle mr-1"></i> Form Zona Baru
                                @else
                                    <i class="fas fa-edit mr-1"></i> Edit: {{ $data->name }}
                                @endif
                            </div>
                            <div class="btn-group">
                                <a href="{{ route('location.zones.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-arrow-left mr-2"></i>Back
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form-zona"
                            action="{{ $typeForm == 'create' ? route('location.zones.store') : route('location.zones.update', $data->id) }}"
                            method="POST">
                            @csrf
                            @if ($typeForm == 'edit')
                                @method('PUT')
                            @endif

                            <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-warning' }} card-outline">
                                <div class="card-body">

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Warehouse <span
                                                class="text-danger">*</span></label>
                                        <div class="col-sm-9">
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
                                            @error('warehouse_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Kode Zona <span
                                                class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="code"
                                                class="form-control @error('code') is-invalid @enderror"
                                                value="{{ old('code', $data->code ?? '') }}"
                                                placeholder="Contoh: A, B, C atau Z01" style="text-transform:uppercase"
                                                maxlength="10">
                                            <small class="text-muted">Kode unik per warehouse, maks 10 karakter.</small>
                                            @error('code')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Nama Zona <span
                                                class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="name"
                                                class="form-control @error('name') is-invalid @enderror"
                                                value="{{ old('name', $data->name ?? '') }}"
                                                placeholder="Contoh: Zone A - Fast Moving" maxlength="100">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Deskripsi</label>
                                        <div class="col-sm-9">
                                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                                                placeholder="Keterangan singkat tentang zona ini...">{{ old('description', $data->description ?? '') }}</textarea>
                                            @error('description')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Posisi X <small class="text-muted">(layout
                                                3D)</small></label>
                                        <div class="col-sm-9 col-md-4">
                                            <input type="number" name="pos_x" step="0.01"
                                                class="form-control @error('pos_x') is-invalid @enderror"
                                                value="{{ old('pos_x', $data->pos_x ?? 0) }}" placeholder="0">
                                            @error('pos_x')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Posisi Z <small class="text-muted">(layout
                                                3D)</small></label>
                                        <div class="col-sm-9 col-md-4">
                                            <input type="number" name="pos_z" step="0.01"
                                                class="form-control @error('pos_z') is-invalid @enderror"
                                                value="{{ old('pos_z', $data->pos_z ?? 0) }}" placeholder="0">
                                            @error('pos_z')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

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
                                            Zona ini memiliki <strong>{{ $data->racks_count ?? 0 }} rak</strong>.
                                            Dibuat: {{ $data->created_at->format('d M Y') }}
                                        </div>
                                    @endif

                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('location.zones.index') }}" class="btn btn-secondary mr-2">
                                <i class="fas fa-times mr-1"></i>Batal
                            </a>
                            @if ($typeForm == 'create')
                                <button type="submit" form="form-zona" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Simpan
                                </button>
                            @else
                                <button type="submit" form="form-zona" class="btn btn-warning text-white">
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
