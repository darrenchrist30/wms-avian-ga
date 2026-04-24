@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Role' : 'Edit Role')

@section('content')
<div class="container-fluid">
    <div class="row"><div class="col-md-12">
        <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Role' : 'Edit Role: ' . ($data->name ?? '') }}</h3>
    </div></div>

    <div class="row">
        {{-- Kolom Kiri: Info Role --}}
        <div class="col-md-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">
                        @if ($typeForm == 'create')
                            <i class="fas fa-plus-circle mr-1"></i> Role Baru
                        @else
                            <i class="fas fa-edit mr-1"></i> Edit Role
                        @endif
                    </span>
                    <a href="{{ route('roles.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i>Back
                    </a>
                </div>
                <div class="card-body">
                    <form id="form-role"
                        action="{{ $typeForm == 'create' ? route('roles.store') : route('roles.update', $data->id) }}"
                        method="POST">
                        @csrf
                        @if ($typeForm == 'edit') @method('PUT') @endif

                        <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                            <div class="card-body">

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Nama Role <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="name"
                                            class="form-control @error('name') is-invalid @enderror"
                                            value="{{ old('name', $data->name ?? '') }}"
                                            placeholder="Contoh: Admin" maxlength="100">
                                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Slug <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="slug"
                                            class="form-control @error('slug') is-invalid @enderror"
                                            value="{{ old('slug', $data->slug ?? '') }}"
                                            placeholder="Contoh: admin" maxlength="50"
                                            {{ ($typeForm == 'edit' && ($data->slug ?? '') === 'admin') ? 'readonly' : '' }}>
                                        <small class="text-muted">Huruf kecil, angka, underscore. Tidak bisa diubah setelah disimpan.</small>
                                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Deskripsi</label>
                                    <div class="col-sm-8">
                                        <textarea name="description" rows="3"
                                            class="form-control @error('description') is-invalid @enderror"
                                            placeholder="Keterangan singkat role ini...">{{ old('description', $data->description ?? '') }}</textarea>
                                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                @if ($typeForm == 'edit')
                                    <div class="alert alert-light border small text-muted mb-0">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        {{ $data->users_count ?? 0 }} pengguna menggunakan role ini.
                                    </div>
                                @endif

                            </div>
                        </div>

                </div>
                <div class="card-footer d-flex justify-content-end">
                    <a href="{{ route('roles.index') }}" class="btn btn-secondary mr-2">
                        <i class="fas fa-times mr-1"></i>Batal
                    </a>
                    @if ($typeForm == 'create')
                        <button type="submit" form="form-role" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i>Simpan
                        </button>
                    @else
                        <button type="submit" form="form-role" class="btn btn-success">
                            <i class="fas fa-save mr-1"></i>Update
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Permissions --}}
        <div class="col-md-7">
            <div class="card card-secondary card-outline">
                <div class="card-header">
                    <span class="font-weight-bold"><i class="fas fa-key mr-1"></i> Hak Akses (Permissions)</span>
                    <small class="text-muted ml-2">Admin secara otomatis memiliki semua akses</small>
                </div>
                <div class="card-body" style="max-height:520px;overflow-y:auto">
                    @if ($permissions->isEmpty())
                        <p class="text-muted text-center py-3"><i class="fas fa-info-circle mr-1"></i>Belum ada permission yang didefinisikan.</p>
                    @else
                        @foreach ($permissions as $module => $perms)
                            <div class="mb-3">
                                <h6 class="text-uppercase text-muted font-weight-bold border-bottom pb-1" style="font-size:11px;letter-spacing:1px">
                                    {{ $module }}
                                </h6>
                                <div class="row">
                                    @foreach ($perms as $perm)
                                        <div class="col-sm-6">
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input"
                                                    name="permissions[]" value="{{ $perm->id }}"
                                                    id="perm_{{ $perm->id }}"
                                                    {{ in_array($perm->id, old('permissions', $rolePerms)) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="perm_{{ $perm->id }}">
                                                    {{ $perm->name }}
                                                    <small class="text-muted d-block" style="font-size:10px">{{ $perm->slug }}</small>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="card-footer py-2">
                    <small>
                        <a href="#" id="checkAll" class="text-primary mr-3">Pilih Semua</a>
                        <a href="#" id="uncheckAll" class="text-secondary">Hapus Semua</a>
                    </small>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#checkAll').on('click', e => { e.preventDefault(); $('input[name="permissions[]"]').prop('checked', true); });
    $('#uncheckAll').on('click', e => { e.preventDefault(); $('input[name="permissions[]"]').prop('checked', false); });

    // Auto-generate slug from name on create
    @if ($typeForm == 'create')
    $('input[name="name"]').on('input', function () {
        const slug = $(this).val().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
        $('input[name="slug"]').val(slug);
    });
    @endif
});
</script>
@endpush
