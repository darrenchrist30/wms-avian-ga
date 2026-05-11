@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Pengguna' : 'Edit Pengguna')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-{{ $typeForm == 'create' ? 'user-plus' : 'user-edit' }} mr-2 text-primary"></i>
                {{ $typeForm == 'create' ? 'Tambah Pengguna' : 'Edit Pengguna' }}
            </h5>
            <small class="text-muted">
                {{ $typeForm == 'create' ? 'Buat akun pengguna baru untuk sistem WMS' : 'Perbarui data akun: ' . ($data->name ?? '') }}
            </small>
        </div>
        <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle mr-1"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="row">

        {{-- ── Form utama ──────────────────────────────────────────────────── --}}
        <div class="col-12">
            <div class="card shadow-sm {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                <div class="card-header py-2">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-{{ $typeForm == 'create' ? 'plus-circle' : 'edit' }} mr-1"></i>
                        {{ $typeForm == 'create' ? 'Data Pengguna Baru' : 'Edit: ' . ($data->name ?? '') }}
                    </h3>
                </div>

                <form id="form-user"
                    action="{{ $typeForm == 'create' ? route('users.store') : route('users.update', $data->id) }}"
                    method="POST">
                    @csrf
                    @if ($typeForm == 'edit') @method('PUT') @endif

                    <div class="card-body">

                        {{-- Nama --}}
                        <div class="form-group row mb-3">
                            <label class="col-sm-3 col-form-label font-weight-600" style="font-weight:600">
                                Nama Lengkap <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    </div>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $data->name ?? '') }}"
                                        placeholder="Nama lengkap pengguna" maxlength="100" autofocus>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- Email --}}
                        <div class="form-group row mb-3">
                            <label class="col-sm-3 col-form-label" style="font-weight:600">
                                Email <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    </div>
                                    <input type="email" name="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $data->email ?? '') }}"
                                        placeholder="email@avianbrands.com" maxlength="255">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- ID Karyawan --}}
                        <div class="form-group row mb-3">
                            <label class="col-sm-3 col-form-label" style="font-weight:600">ID Karyawan</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                    </div>
                                    <input type="text" name="employee_id"
                                        class="form-control @error('employee_id') is-invalid @enderror"
                                        value="{{ old('employee_id', $data->employee_id ?? '') }}"
                                        placeholder="Contoh: EMP-001" maxlength="50">
                                    @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <small class="text-muted">Opsional — harus unik jika diisi.</small>
                            </div>
                        </div>

                        {{-- Role --}}
                        <div class="form-group row mb-3">
                            <label class="col-sm-3 col-form-label" style="font-weight:600">
                                Role <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                                    </div>
                                    <select name="role_id"
                                        class="form-control @error('role_id') is-invalid @enderror">
                                        <option value="">— Pilih Role —</option>
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
                        </div>

                        <hr class="my-3">

                        {{-- Password --}}
                        <div class="form-group row mb-3">
                            <label class="col-sm-3 col-form-label" style="font-weight:600">
                                Password
                                @if ($typeForm == 'create')
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    </div>
                                    <input type="password" name="password" id="inputPassword"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="{{ $typeForm == 'edit' ? 'Kosongkan jika tidak diubah' : 'Min. 8 karakter' }}"
                                        autocomplete="new-password">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary btnTogglePwd"
                                            data-target="#inputPassword" tabindex="-1">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                @if ($typeForm == 'edit')
                                    <small class="text-muted">Biarkan kosong jika tidak ingin mengubah password.</small>
                                @endif
                            </div>
                        </div>

                        {{-- Konfirmasi Password --}}
                        <div class="form-group row mb-3">
                            <label class="col-sm-3 col-form-label" style="font-weight:600">Konfirmasi</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    </div>
                                    <input type="password" name="password_confirmation" id="inputPasswordConfirm"
                                        class="form-control"
                                        placeholder="Ulangi password"
                                        autocomplete="new-password">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary btnTogglePwd"
                                            data-target="#inputPasswordConfirm" tabindex="-1">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
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
                                <small class="text-muted ml-3">Pengguna nonaktif tidak bisa login.</small>
                            </div>
                        </div>

                    </div>{{-- /card-body --}}

                    <div class="card-footer d-flex justify-content-end align-items-center">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary mr-2">
                            <i class="fas fa-times mr-1"></i>Batal
                        </a>
                        <button type="submit" class="btn btn-{{ $typeForm == 'create' ? 'primary' : 'success' }}">
                            <i class="fas fa-save mr-1"></i>
                            {{ $typeForm == 'create' ? 'Simpan Pengguna' : 'Update' }}
                        </button>
                    </div>

                </form>
            </div>
        </div>

        {{-- ── Panel info akun (edit mode) ───────────────────────────────────── --}}
        @if ($typeForm == 'edit')
        <div class="col-12">
            <div class="card shadow-sm mt-3">
                <div class="card-header py-2">
                    <span class="font-weight-bold">
                        <i class="fas fa-user-circle mr-1 text-secondary"></i> Info Akun
                    </span>
                </div>
                <div class="card-body" style="font-size:13px;">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mr-3"
                            style="width:48px;height:48px;background:#e9ecef;font-size:20px;font-weight:700;color:#495057;">
                            {{ strtoupper(substr($data->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="font-weight-bold">{{ $data->name }}</div>
                            <small class="text-muted">{{ $data->email }}</small>
                        </div>
                    </div>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted pl-0" style="width:110px">Role saat ini</td>
                            <td>
                                @php
                                    $roleColors = ['admin'=>'badge-danger','supervisor'=>'badge-warning text-dark','operator'=>'badge-info'];
                                    $slug = $data->role?->slug ?? '';
                                @endphp
                                <span class="badge {{ $roleColors[$slug] ?? 'badge-secondary' }}">
                                    {{ $data->role?->name ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-0">Status</td>
                            <td>
                                @if ($data->is_active)
                                    <span class="badge badge-success">Aktif</span>
                                @else
                                    <span class="badge badge-secondary">Nonaktif</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-0">Login terakhir</td>
                            <td>{{ $data->last_login_at ? $data->last_login_at->diffForHumans() : '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    // Toggle show/hide password
    $(document).on('click', '.btnTogglePwd', function () {
        const target = $($(this).data('target'));
        const isPassword = target.attr('type') === 'password';
        target.attr('type', isPassword ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });
});
</script>
@endpush
