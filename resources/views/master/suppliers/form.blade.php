@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Supplier' : 'Edit Supplier')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">{{ $typeForm == 'create' ? 'Tambah Supplier' : 'Edit Supplier' }}</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                @if ($typeForm == 'create')
                                    <i class="fas fa-plus-circle mr-1"></i> Form Supplier Baru
                                @else
                                    <i class="fas fa-edit mr-1"></i> Edit: {{ $data->name }}
                                @endif
                            </div>
                            <a href="{{ route('master.suppliers.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form-supplier"
                            action="{{ $typeForm == 'create' ? route('master.suppliers.store') : route('master.suppliers.update', $data->id) }}"
                            method="POST">
                            @csrf
                            @if ($typeForm == 'edit')
                                @method('PUT')
                            @endif

                            <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-success' }} card-outline">
                                <div class="card-header">
                                    <h6 class="card-title mb-0"><i class="fas fa-info-circle mr-1"></i> Informasi Supplier</h6>
                                </div>
                                <div class="card-body">

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Kode <span class="text-danger">*</span></label>
                                        <div class="col-sm-4">
                                            <input type="text" name="code"
                                                class="form-control @error('code') is-invalid @enderror"
                                                value="{{ old('code', $data->code ?? '') }}"
                                                placeholder="Contoh: SUP001" style="text-transform:uppercase"
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
                                        <label class="col-sm-3 col-form-label">Nama Supplier <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="name"
                                                class="form-control @error('name') is-invalid @enderror"
                                                value="{{ old('name', $data->name ?? '') }}"
                                                placeholder="Nama lengkap supplier" maxlength="150">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Nama Kontak</label>
                                        <div class="col-sm-6">
                                            <input type="text" name="contact_person"
                                                class="form-control @error('contact_person') is-invalid @enderror"
                                                value="{{ old('contact_person', $data->contact_person ?? '') }}"
                                                placeholder="Nama PIC supplier" maxlength="100">
                                            @error('contact_person')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Telepon</label>
                                        <div class="col-sm-4">
                                            <input type="text" name="phone"
                                                class="form-control @error('phone') is-invalid @enderror"
                                                value="{{ old('phone', $data->phone ?? '') }}"
                                                placeholder="Contoh: 021-5551234" maxlength="20">
                                            @error('phone')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Email</label>
                                        <div class="col-sm-6">
                                            <input type="email" name="email"
                                                class="form-control @error('email') is-invalid @enderror"
                                                value="{{ old('email', $data->email ?? '') }}"
                                                placeholder="email@supplier.com" maxlength="100">
                                            @error('email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Alamat</label>
                                        <div class="col-sm-9">
                                            <textarea name="address" rows="3"
                                                class="form-control @error('address') is-invalid @enderror"
                                                placeholder="Alamat lengkap supplier...">{{ old('address', $data->address ?? '') }}</textarea>
                                            @error('address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">ERP Vendor ID</label>
                                        <div class="col-sm-4">
                                            <input type="text" name="erp_vendor_id"
                                                class="form-control @error('erp_vendor_id') is-invalid @enderror"
                                                value="{{ old('erp_vendor_id', $data->erp_vendor_id ?? '') }}"
                                                placeholder="ID vendor di sistem ERP" maxlength="50">
                                            <small class="text-muted">Opsional. Digunakan untuk sinkronisasi ERP.</small>
                                            @error('erp_vendor_id')
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
                                            Supplier ini memiliki <strong>{{ $data->inbound_orders_count ?? 0 }} riwayat pesanan</strong>.
                                            Dibuat: {{ $data->created_at->format('d M Y') }}
                                        </div>
                                    @endif

                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('master.suppliers.index') }}" class="btn btn-secondary mr-2">
                                <i class="fas fa-times mr-1"></i>Batal
                            </a>
                            @if ($typeForm == 'create')
                                <button type="submit" form="form-supplier" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Simpan
                                </button>
                            @else
                                <button type="submit" form="form-supplier" class="btn btn-success">
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
