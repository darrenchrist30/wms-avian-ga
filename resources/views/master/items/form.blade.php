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
                                <div class="col-md-7 d-flex flex-column">
                                    <div class="card card-secondary card-outline flex-grow-1 mb-0">
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
                                                        placeholder="Nama sparepart" maxlength="200">
                                                    @error('name')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Merk</label>
                                                <div class="col-sm-8">
                                                    <input type="text" name="merk"
                                                        class="form-control @error('merk') is-invalid @enderror"
                                                        value="{{ old('merk', $data->merk ?? '') }}"
                                                        placeholder="Merk sparepart" maxlength="100">
                                                    @error('merk')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Kategori <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    @php
                                                        $selCatId   = old('category_id', $data->category_id ?? '');
                                                        $selCatText = $selCatId ? (\App\Models\ItemCategory::find($selCatId)?->name ?? '') : '';
                                                    @endphp
                                                    <select name="category_id" id="select-category"
                                                        class="form-control @error('category_id') is-invalid @enderror"
                                                        style="width:100%">
                                                        @if($selCatId)
                                                            <option value="{{ $selCatId }}" selected>{{ $selCatText }}</option>
                                                        @endif
                                                    </select>
                                                    @error('category_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Satuan <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    @php
                                                        $selUnitId   = old('unit_id', $data->unit_id ?? '');
                                                        $selUnit     = $selUnitId ? \App\Models\Unit::find($selUnitId) : null;
                                                        $selUnitText = $selUnit ? ($selUnit->code . ' - ' . $selUnit->name) : '';
                                                    @endphp
                                                    <select name="unit_id" id="select-unit"
                                                        class="form-control @error('unit_id') is-invalid @enderror"
                                                        style="width:100%">
                                                        @if($selUnitId)
                                                            <option value="{{ $selUnitId }}" selected>{{ $selUnitText }}</option>
                                                        @endif
                                                    </select>
                                                    @error('unit_id')
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
                                <div class="col-md-5 d-flex flex-column">

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
                                                    <small class="text-muted">Stok minimum sebelum reorder.</small>
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

                                    {{-- Card: Status --}}
                                    <div class="card card-secondary card-outline flex-grow-1 mb-0">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0"><i class="fas fa-toggle-on mr-1"></i> Status</h6>
                                        </div>
                                        <div class="card-body">

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

@push('styles')
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('adminlte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('#select-category').select2({
                theme: 'bootstrap4',
                placeholder: '-- Pilih Kategori --',
                allowClear: true,
                ajax: {
                    url: '{{ route("master.categories.select2") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data.results }; },
                    cache: true
                },
                minimumInputLength: 0
            });

            $('#select-unit').select2({
                theme: 'bootstrap4',
                placeholder: '-- Pilih Satuan --',
                allowClear: true,
                ajax: {
                    url: '{{ route("master.units.select2") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data.results }; },
                    cache: true
                },
                minimumInputLength: 0
            });
        });
    </script>
@endpush
