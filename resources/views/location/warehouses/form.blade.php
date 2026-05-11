@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Warehouse' : 'Edit Warehouse')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-{{ $typeForm == 'create' ? 'warehouse' : 'edit' }} mr-2 text-primary"></i>
                {{ $typeForm == 'create' ? 'Tambah Warehouse' : 'Edit Warehouse' }}
            </h5>
            <small class="text-muted">
                {{ $typeForm == 'create' ? 'Daftarkan gudang baru ke sistem WMS' : 'Perbarui data gudang: ' . ($data->name ?? '') }}
            </small>
        </div>
        <a href="{{ route('location.warehouses.index') }}" class="btn btn-sm btn-outline-secondary">
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
                {{ $typeForm == 'create' ? 'Data Warehouse Baru' : 'Edit: ' . ($data->name ?? '') }}
            </h3>
        </div>

        <form id="form-warehouse"
            action="{{ $typeForm == 'create' ? route('location.warehouses.store') : route('location.warehouses.update', $data->id) }}"
            method="POST">
            @csrf
            @if ($typeForm == 'edit') @method('PUT') @endif

            <div class="card-body">

                {{-- Kode --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Kode <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="code"
                                class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code', $data->code ?? '') }}"
                                placeholder="CONTOH: WH-001" style="text-transform:uppercase"
                                maxlength="20">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Kode unik, maks 20 karakter.</small>
                    </div>
                </div>

                {{-- Nama Warehouse --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Nama Warehouse <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $data->name ?? '') }}"
                                placeholder="Nama gudang" maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- PIC --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">PIC (Penanggung Jawab)</label>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="pic"
                                class="form-control @error('pic') is-invalid @enderror"
                                value="{{ old('pic', $data->pic ?? '') }}"
                                placeholder="Nama penanggung jawab" maxlength="100">
                            @error('pic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Telepon --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Telepon</label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="phone"
                                class="form-control @error('phone') is-invalid @enderror"
                                value="{{ old('phone', $data->phone ?? '') }}"
                                placeholder="Contoh: 021-5551234" maxlength="20">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Alamat --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Alamat</label>
                    <div class="col-sm-9">
                        <textarea name="address" rows="3"
                            class="form-control @error('address') is-invalid @enderror"
                            placeholder="Alamat lengkap warehouse...">{{ old('address', $data->address ?? '') }}</textarea>
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                        <small class="text-muted ml-3">Warehouse nonaktif tidak muncul di pilihan lokasi.</small>
                    </div>
                </div>

                @if ($typeForm == 'edit')
                    <div class="alert alert-light border small text-muted mt-3 mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Warehouse ini memiliki <strong>{{ $data->zones_count ?? 0 }} zona</strong>.
                        Dibuat: {{ $data->created_at->format('d M Y') }}
                    </div>
                @endif

            </div>{{-- /card-body --}}

            <div class="card-footer d-flex justify-content-end align-items-center">
                <a href="{{ route('location.warehouses.index') }}" class="btn btn-secondary mr-2">
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
