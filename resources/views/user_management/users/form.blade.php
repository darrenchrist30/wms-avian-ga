@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Pengguna' : 'Edit Pengguna')

@section('content')
<div class="container-fluid">
    <div class="row"><div class="col-md-12">
        <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Pengguna' : 'Edit Pengguna' }}</h3>
    </div></div>

    <div class="row"><div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">
                    @if ($typeForm == 'create')
                        <i class="fas fa-plus-circle mr-1"></i> Form Pengguna Baru
                    @else
                        <i class="fas fa-edit mr-1"></i> Edit: {{ $data->name }}
                    @endif
                </span>
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left mr-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <form id="form-user"
                    action="{{ $typeForm == 'create' ? route('users.store') : route('users.update', $data->id) }}"
                    method="POST">
                    @csrf
                    @if ($typeForm == 'edit') @method('PUT') @endif

                    <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                        <div class="card-body">

                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $data->name ?? '') }}"
                                        placeholder="Nama lengkap pengguna" maxlength="100">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Email <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    <input type="email" name="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $data->email ?? '') }}"
                                        placeholder="email@avianbrands.com" maxlength="255">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">ID Karyawan</label>
                                <div class="col-sm-9">
                                    <input type="text" name="employee_id"
                                        class="form-control @error('employee_id') is-invalid @enderror"
                                        value="{{ old('employee_id', $data->employee_id ?? '') }}"
                                        placeholder="Contoh: EMP-001" maxlength="50">
                                    <small class="text-muted">Opsional. Harus unik.</small>
                                    @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Role <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    <select name="role_id" class="form-control @error('role_id') is-invalid @enderror">
                                        <option value="">-- Pilih Role --</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}"
                                                {{ old('role_id', $data->role_id ?? '') == $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">
                                    Password {{ $typeForm == 'edit' ? '' : '<span class="text-danger">*</span>' }}
                                </label>
                                <div class="col-sm-9">
                                    <input type="password" name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="{{ $typeForm == 'edit' ? 'Kosongkan jika tidak diubah' : 'Min. 8 karakter' }}"
                                        autocomplete="new-password">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Konfirmasi Password</label>
                                <div class="col-sm-9">
                                    <input type="password" name="password_confirmation"
                                        class="form-control"
                                        placeholder="Ulangi password">
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
                                <div class="alert alert-light border small text-muted mb-0">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Dibuat: {{ $data->created_at->format('d M Y') }}
                                    @if ($data->id == auth()->id())
                                        &nbsp;·&nbsp; <span class="text-primary"><i class="fas fa-user mr-1"></i>Ini akun Anda</span>
                                    @endif
                                </div>
                            @endif

                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <a href="{{ route('users.index') }}" class="btn btn-secondary mr-2">
                    <i class="fas fa-times mr-1"></i>Batal
                </a>
                @if ($typeForm == 'create')
                    <button type="submit" form="form-user" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>Simpan
                    </button>
                @else
                    <button type="submit" form="form-user" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i>Update
                    </button>
                @endif
            </div>
        </div>
    </div></div>
</div>
@endsection
