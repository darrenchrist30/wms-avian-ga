@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Satuan Ukuran' : 'Edit Satuan Ukuran')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Satuan Ukuran' : 'Edit Satuan Ukuran' }}</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                @if ($typeForm == 'create')
                                    <i class="fas fa-plus-circle mr-1"></i> Form Satuan Baru
                                @else
                                    <i class="fas fa-edit mr-1"></i> Edit: {{ $data->name }}
                                @endif
                            </div>
                            <a href="{{ route('master.units.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form-satuan"
                            action="{{ $typeForm == 'create' ? route('master.units.store') : route('master.units.update', $data->id) }}"
                            method="POST">
                            @csrf
                            @if ($typeForm == 'edit')
                                @method('PUT')
                            @endif

                            <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                                <div class="card-body">

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Kode <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="code"
                                                class="form-control @error('code') is-invalid @enderror"
                                                value="{{ old('code', $data->code ?? '') }}"
                                                placeholder="Contoh: PCS, BOX, KG" style="text-transform:uppercase"
                                                maxlength="20">
                                            @if ($typeForm == 'create')
                                                <small class="text-muted">Kode unik, maks 20 karakter.</small>
                                            @endif
                                            @error('code')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Nama Satuan <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="name"
                                                class="form-control @error('name') is-invalid @enderror"
                                                value="{{ old('name', $data->name ?? '') }}"
                                                placeholder="Contoh: Pieces, Kilogram, Box" maxlength="100">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Deskripsi</label>
                                        <div class="col-sm-9">
                                            <textarea name="description" rows="3"
                                                class="form-control @error('description') is-invalid @enderror"
                                                placeholder="Keterangan singkat...">{{ old('description', $data->description ?? '') }}</textarea>
                                            @error('description')
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
                                            Satuan ini digunakan oleh <strong>{{ $data->items_count ?? 0 }} sparepart</strong>.
                                            Dibuat: {{ $data->created_at->format('d M Y') }}
                                        </div>
                                    @endif

                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('master.units.index') }}" class="btn btn-secondary mr-2">
                                <i class="fas fa-times mr-1"></i>Batal
                            </a>
                            @if ($typeForm == 'create')
                                <button type="submit" form="form-satuan" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Simpan
                                </button>
                            @else
                                <button type="submit" form="form-satuan" class="btn btn-success">
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
