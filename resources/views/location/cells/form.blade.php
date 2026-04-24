@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Sel' : 'Edit Sel')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Sel' : 'Edit Sel: ' . ($data->code ?? '') }}</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                @if ($typeForm == 'create')
                                    <i class="fas fa-plus-circle mr-1"></i> Form Sel Baru
                                @else
                                    <i class="fas fa-edit mr-1"></i> Edit Sel
                                @endif
                            </div>
                            <a href="{{ route('location.cells.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form-cell"
                            action="{{ $typeForm == 'create' ? route('location.cells.store') : route('location.cells.update', $data->id) }}"
                            method="POST">
                            @csrf
                            @if ($typeForm == 'edit')
                                @method('PUT')
                            @endif

                            <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                                <div class="card-body">

                                    @if ($typeForm == 'create')
                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label">Rak <span class="text-danger">*</span></label>
                                            <div class="col-sm-8">
                                                <select name="rack_id"
                                                    class="form-control @error('rack_id') is-invalid @enderror">
                                                    <option value="">-- Pilih Rak --</option>
                                                    @foreach ($racks as $rack)
                                                        <option value="{{ $rack->id }}"
                                                            {{ old('rack_id') == $rack->id ? 'selected' : '' }}>
                                                            {{ $rack->code }}
                                                            ({{ $rack->zone->code ?? '-' }} /
                                                            {{ $rack->zone->warehouse->name ?? '-' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('rack_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label">Level <span class="text-danger">*</span></label>
                                            <div class="col-sm-4">
                                                <select name="level"
                                                    class="form-control @error('level') is-invalid @enderror">
                                                    @foreach (['A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5,'F'=>6,'G'=>7] as $lbl => $val)
                                                        <option value="{{ $val }}"
                                                            {{ old('level', 1) == $val ? 'selected' : '' }}>
                                                            Level {{ $lbl }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('level')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    @else
                                        <div class="alert alert-light border small mb-3">
                                            <div class="row">
                                                <div class="col-6">
                                                    <strong>Kode Sel:</strong> <code>{{ $data->code }}</code><br>
                                                    <strong>Rak:</strong> {{ $data->rack->code ?? '-' }}<br>
                                                    <strong>Zona:</strong> {{ $data->rack->zone->code ?? '-' }}
                                                </div>
                                                <div class="col-6">
                                                    <strong>Level:</strong> {{ chr(64 + $data->level) }}<br>
                                                    <strong>Terpakai:</strong> {{ $data->capacity_used }}
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label">Kapasitas Maks <span class="text-danger">*</span></label>
                                        <div class="col-sm-4">
                                            <input type="number" name="capacity_max"
                                                class="form-control @error('capacity_max') is-invalid @enderror"
                                                value="{{ old('capacity_max', $data->capacity_max ?? 100) }}" min="1">
                                            @error('capacity_max')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label">Status Sel <span class="text-danger">*</span></label>
                                        <div class="col-sm-6">
                                            <select name="status"
                                                class="form-control @error('status') is-invalid @enderror">
                                                @foreach (['available' => 'Available', 'partial' => 'Partial', 'full' => 'Full', 'blocked' => 'Blocked', 'reserved' => 'Reserved'] as $val => $label)
                                                    <option value="{{ $val }}"
                                                        {{ old('status', $data->status ?? 'available') == $val ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('status')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label">Kategori Dominan</label>
                                        <div class="col-sm-8">
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
                                            <small class="text-muted">Opsional. Kategori sparepart yang mendominasi sel ini.</small>
                                            @error('dominant_category_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label">Status Aktif</label>
                                        <div class="col-sm-8 d-flex align-items-center">
                                            <div class="custom-control custom-switch">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" id="isActive"
                                                    class="custom-control-input"
                                                    {{ old('is_active', $data->is_active ?? 1) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="isActive">Aktif</label>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('location.cells.index') }}" class="btn btn-secondary mr-2">
                                <i class="fas fa-times mr-1"></i>Batal
                            </a>
                            @if ($typeForm == 'create')
                                <button type="submit" form="form-cell" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Simpan
                                </button>
                            @else
                                <button type="submit" form="form-cell" class="btn btn-success">
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
