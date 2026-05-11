@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Sparepart' : 'Edit Sparepart')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Sparepart' : 'Edit Sparepart: ' . ($data->sku ?? '') }}</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                @if ($typeForm == 'create')
                                    <i class="fas fa-plus-circle mr-1"></i> Form Sparepart Baru
                                @else
                                    <i class="fas fa-edit mr-1"></i> Edit Sparepart
                                @endif
                            </div>
                            <a href="{{ route('master.items.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form-item"
                            action="{{ $typeForm == 'create' ? route('master.items.store') : route('master.items.update', $data->id) }}"
                            method="POST">
                            @csrf
                            @if ($typeForm == 'edit')
                                @method('PUT')
                            @endif

                            <div class="row">

                                {{-- Kolom Kiri: Informasi Dasar --}}
                                <div class="col-md-7">
                                    <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0"><i class="fas fa-info-circle mr-1"></i> Informasi Dasar</h6>
                                        </div>
                                        <div class="card-body">

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">SKU <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    <input type="text" name="sku"
                                                        class="form-control @error('sku') is-invalid @enderror"
                                                        value="{{ old('sku', $data->sku ?? '') }}"
                                                        placeholder="Contoh: SKU-001" style="text-transform:uppercase" maxlength="50">
                                                    <small class="text-muted">Kode unik sparepart, maks 50 karakter.</small>
                                                    @error('sku')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Nama Sparepart <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    <input type="text" name="name"
                                                        class="form-control @error('name') is-invalid @enderror"
                                                        value="{{ old('name', $data->name ?? '') }}"
                                                        placeholder="Nama lengkap sparepart" maxlength="200">
                                                    @error('name')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Kategori <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    <select name="category_id" class="form-control @error('category_id') is-invalid @enderror">
                                                        <option value="">-- Pilih Kategori --</option>
                                                        @foreach ($categories as $cat)
                                                            <option value="{{ $cat->id }}"
                                                                {{ old('category_id', $data->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                                                                {{ $cat->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('category_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Satuan <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    <select name="unit_id" class="form-control @error('unit_id') is-invalid @enderror">
                                                        <option value="">-- Pilih Satuan --</option>
                                                        @foreach ($units as $unit)
                                                            <option value="{{ $unit->id }}"
                                                                {{ old('unit_id', $data->unit_id ?? '') == $unit->id ? 'selected' : '' }}>
                                                                {{ $unit->code }} - {{ $unit->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('unit_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">ERP Item Code</label>
                                                <div class="col-sm-8">
                                                    <input type="text" name="erp_item_code"
                                                        class="form-control @error('erp_item_code') is-invalid @enderror"
                                                        value="{{ old('erp_item_code', $data->erp_item_code ?? '') }}"
                                                        placeholder="Kode item di sistem ERP" maxlength="50">
                                                    <small class="text-muted">Opsional. Untuk sinkronisasi ERP.</small>
                                                    @error('erp_item_code')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Deskripsi</label>
                                                <div class="col-sm-8">
                                                    <textarea name="description" rows="3"
                                                        class="form-control @error('description') is-invalid @enderror"
                                                        placeholder="Keterangan tambahan sparepart...">{{ old('description', $data->description ?? '') }}</textarea>
                                                    @error('description')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                {{-- Kolom Kanan: Stok & Dimensi --}}
                                <div class="col-md-5">

                                    {{-- Card: Stok & Reorder --}}
                                    <div class="card card-secondary card-outline">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0"><i class="fas fa-cubes mr-1"></i> Stok & Reorder</h6>
                                        </div>
                                        <div class="card-body">

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Min. Stok <span class="text-danger">*</span></label>
                                                <div class="col-sm-6">
                                                    <input type="number" name="min_stock"
                                                        class="form-control @error('min_stock') is-invalid @enderror"
                                                        value="{{ old('min_stock', $data->min_stock ?? 0) }}" min="0">
                                                    @error('min_stock')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Maks. Stok <span class="text-danger">*</span></label>
                                                <div class="col-sm-6">
                                                    <input type="number" name="max_stock"
                                                        class="form-control @error('max_stock') is-invalid @enderror"
                                                        value="{{ old('max_stock', $data->max_stock ?? 0) }}" min="0">
                                                    @error('max_stock')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Reorder Point <span class="text-danger">*</span></label>
                                                <div class="col-sm-6">
                                                    <input type="number" name="reorder_point"
                                                        class="form-control @error('reorder_point') is-invalid @enderror"
                                                        value="{{ old('reorder_point', $data->reorder_point ?? 0) }}" min="0">
                                                    <small class="text-muted">Titik pemesanan ulang.</small>
                                                    @error('reorder_point')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Deadstock (hari) <span class="text-danger">*</span></label>
                                                <div class="col-sm-6">
                                                    <input type="number" name="deadstock_threshold_days"
                                                        class="form-control @error('deadstock_threshold_days') is-invalid @enderror"
                                                        value="{{ old('deadstock_threshold_days', $data->deadstock_threshold_days ?? 90) }}" min="1">
                                                    <small class="text-muted">Batas hari tidak bergerak.</small>
                                                    @error('deadstock_threshold_days')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    {{-- Card: Dimensi & Barcode --}}
                                    <div class="card card-secondary card-outline">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0"><i class="fas fa-weight mr-1"></i> Dimensi & Barcode</h6>
                                        </div>
                                        <div class="card-body">

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Berat (kg)</label>
                                                <div class="col-sm-6">
                                                    <input type="number" name="weight_kg" step="0.001"
                                                        class="form-control @error('weight_kg') is-invalid @enderror"
                                                        value="{{ old('weight_kg', $data->weight_kg ?? '') }}"
                                                        placeholder="0.000" min="0">
                                                    @error('weight_kg')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Volume (m³)</label>
                                                <div class="col-sm-6">
                                                    <input type="number" name="volume_m3" step="0.000001"
                                                        class="form-control @error('volume_m3') is-invalid @enderror"
                                                        value="{{ old('volume_m3', $data->volume_m3 ?? '') }}"
                                                        placeholder="0.000000" min="0">
                                                    @error('volume_m3')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Barcode</label>
                                                <div class="col-sm-6">
                                                    <input type="text" name="barcode"
                                                        class="form-control @error('barcode') is-invalid @enderror"
                                                        value="{{ old('barcode', $data->barcode ?? '') }}"
                                                        placeholder="Nomor barcode" maxlength="100">
                                                    <small class="text-muted">Opsional. Harus unik.</small>
                                                    @error('barcode')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-6 col-form-label">Status Aktif</label>
                                                <div class="col-sm-6 d-flex align-items-center">
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
                                                <div class="alert alert-light border small text-muted mb-0">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Dibuat: {{ $data->created_at->format('d M Y') }}
                                                </div>
                                            @endif

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('master.items.index') }}" class="btn btn-secondary mr-2">
                                <i class="fas fa-times mr-1"></i>Batal
                            </a>
                            @if ($typeForm == 'create')
                                <button type="submit" form="form-item" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Simpan
                                </button>
                            @else
                                <button type="submit" form="form-item" class="btn btn-success">
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
