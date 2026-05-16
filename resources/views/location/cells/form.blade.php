@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Sel' : 'Edit Sel')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-{{ $typeForm == 'create' ? 'th-large' : 'edit' }} mr-2 text-primary"></i>
                {{ $typeForm == 'create' ? 'Tambah Sel' : 'Edit Sel: ' . ($data->code ?? '') }}
            </h5>
            <small class="text-muted">
                {{ $typeForm == 'create' ? 'Tambah sel baru dalam rak gudang' : 'Perbarui data sel: ' . ($data->code ?? '') }}
            </small>
        </div>
        <a href="{{ route('location.cells.index') }}" class="btn btn-sm btn-outline-secondary">
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
                {{ $typeForm == 'create' ? 'Data Sel Baru' : 'Edit: ' . ($data->code ?? '') }}
            </h3>
        </div>

        <form id="form-cell"
            action="{{ $typeForm == 'create' ? route('location.cells.store') : route('location.cells.update', $data->id) }}"
            method="POST">
            @csrf
            @if ($typeForm == 'edit') @method('PUT') @endif

            <div class="card-body">

                @if ($typeForm == 'create')

                {{-- Rak --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Rak <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <select name="rack_id"
                                class="form-control @error('rack_id') is-invalid @enderror">
                                <option value="">-- Pilih Rak --</option>
                                @foreach ($racks as $rack)
                                    <option value="{{ $rack->id }}"
                                        {{ old('rack_id') == $rack->id ? 'selected' : '' }}>
                                        {{ $rack->code }} ({{ $rack->warehouse->name ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('rack_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Level --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Level <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <select name="level"
                                class="form-control @error('level') is-invalid @enderror">
                                @foreach (['A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5,'F'=>6,'G'=>7] as $lbl => $val)
                                    <option value="{{ $val }}"
                                        {{ old('level', 1) == $val ? 'selected' : '' }}>
                                        Level {{ $lbl }}
                                    </option>
                                @endforeach
                            </select>
                            @error('level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                @else

                {{-- Info sel (edit mode) --}}
                <div class="alert alert-light border small mb-3">
                    <div class="row">
                        <div class="col-6">
                            <strong>Kode Sel:</strong> <code>{{ $data->code }}</code><br>
                            <strong>Rak:</strong> {{ $data->rack->code ?? '-' }}<br>
                            <strong>Gudang:</strong> {{ $data->rack->warehouse->name ?? '-' }}
                        </div>
                        <div class="col-6">
                            <strong>Level:</strong> {{ chr(64 + $data->level) }}<br>
                            <strong>Terpakai:</strong> {{ $data->capacity_used }}
                        </div>
                    </div>
                </div>

                @endif

                {{-- Kapasitas Maks --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Kapasitas Maks <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="number" name="capacity_max"
                                class="form-control @error('capacity_max') is-invalid @enderror"
                                value="{{ old('capacity_max', $data->capacity_max ?? 100) }}" min="1">
                            @error('capacity_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Status Sel --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Status Sel <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-5">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <select name="status"
                                class="form-control @error('status') is-invalid @enderror">
                                @foreach (['available' => 'Available', 'partial' => 'Partial', 'full' => 'Full', 'blocked' => 'Blocked', 'reserved' => 'Reserved'] as $val => $label)
                                    <option value="{{ $val }}"
                                        {{ old('status', $data->status ?? 'available') == $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Kategori Dominan --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Kategori Dominan</label>
                    <div class="col-sm-7">
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
                        <small class="text-muted">Opsional. Kategori sparepart yang mendominasi sel ini.</small>
                    </div>
                </div>

                <hr class="my-3">

                {{-- Status Aktif --}}
                <div class="form-group row mb-0">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Status Aktif</label>
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

            </div>{{-- /card-body --}}

            <div class="card-footer d-flex justify-content-end align-items-center">
                <a href="{{ route('location.cells.index') }}" class="btn btn-secondary mr-2">
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
