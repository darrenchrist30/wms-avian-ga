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

    <div class="card shadow-sm">
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

                {{-- Status Aktif --}}
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
                        Warehouse ini memiliki <strong>{{ $data->racks_count ?? 0 }} rak</strong>.
                        Dibuat: {{ $data->created_at->format('d M Y') }}
                    </div>
                @endif

                {{-- ============================================================ --}}
                {{-- Generate Layout Otomatis (hanya saat create)                  --}}
                {{-- ============================================================ --}}
                @if($typeForm == 'create')
                <hr class="my-4">

                <div class="d-flex align-items-center mb-3">
                    <input type="hidden" name="generate_layout" value="0">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="generate_layout" value="1" id="generateLayout"
                            class="custom-control-input"
                            {{ old('generate_layout') ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold" for="generateLayout">
                            <i class="fas fa-magic mr-1 text-primary"></i> Generate Layout Otomatis
                        </label>
                    </div>
                    <small class="text-muted ml-3">Buat rak &amp; sel sekaligus saat gudang disimpan.</small>
                </div>

                <div id="layoutSection" style="display:none;">
                    <div class="card border mb-0">
                        <div class="card-header py-2 bg-light">
                            <h6 class="card-title mb-0 font-weight-bold">
                                <i class="fas fa-th mr-1 text-primary"></i> Parameter Layout Gudang
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                {{-- Kolom kiri --}}
                                <div class="col-md-6">

                                    {{-- Jumlah Rak --}}
                                    <div class="form-group row mb-3">
                                        <label class="col-sm-5 col-form-label" style="font-weight:600">
                                            Jumlah Rak <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-sm-7">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="min-width:40px;"></span>
                                                </div>
                                                <input type="number" name="rack_count" id="rack_count"
                                                    class="form-control @error('rack_count') is-invalid @enderror"
                                                    value="{{ old('rack_count', 10) }}" min="1" max="100">
                                                @error('rack_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Prefix Kode Rak --}}
                                    <div class="form-group row mb-3">
                                        <label class="col-sm-5 col-form-label" style="font-weight:600">
                                            Prefix Kode Rak <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-sm-7">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="min-width:40px;"></span>
                                                </div>
                                                <input type="text" name="rack_prefix" id="rack_prefix"
                                                    class="form-control @error('rack_prefix') is-invalid @enderror"
                                                    value="{{ old('rack_prefix', 'R') }}" maxlength="10"
                                                    placeholder="Contoh: R"
                                                    style="text-transform:uppercase">
                                                @error('rack_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <small class="text-muted">Kode rak: PREFIX-01, PREFIX-02, …</small>
                                        </div>
                                    </div>

                                    {{-- Jumlah Level --}}
                                    <div class="form-group row mb-0">
                                        <label class="col-sm-5 col-form-label" style="font-weight:600">
                                            Jumlah Level <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-sm-7">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="min-width:40px;"></span>
                                                </div>
                                                <select name="rack_levels" id="rack_levels"
                                                    class="form-control @error('rack_levels') is-invalid @enderror">
                                                    @foreach(['A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5,'F'=>6,'G'=>7] as $lbl => $val)
                                                        <option value="{{ $val }}" {{ old('rack_levels', 5) == $val ? 'selected' : '' }}>
                                                            {{ $val }} Level (A–{{ $lbl }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('rack_levels')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>

                                </div>{{-- /col kiri --}}

                                {{-- Kolom kanan --}}
                                <div class="col-md-6">

                                    {{-- Kolom per Rak --}}
                                    <div class="form-group row mb-3">
                                        <label class="col-sm-5 col-form-label" style="font-weight:600">
                                            Kolom per Rak <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-sm-7">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="min-width:40px;"></span>
                                                </div>
                                                <input type="number" name="rack_columns" id="rack_columns"
                                                    class="form-control @error('rack_columns') is-invalid @enderror"
                                                    value="{{ old('rack_columns', 1) }}" min="1" max="10">
                                                @error('rack_columns')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <small class="text-muted">Slot horizontal per level.</small>
                                        </div>
                                    </div>

                                    {{-- Rak per Baris (layout lantai untuk 3D) --}}
                                    <div class="form-group row mb-3">
                                        <label class="col-sm-5 col-form-label" style="font-weight:600">
                                            Rak per Baris <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-sm-7">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="min-width:40px;"></span>
                                                </div>
                                                <input type="number" name="rack_layout_cols" id="rack_layout_cols"
                                                    class="form-control @error('rack_layout_cols') is-invalid @enderror"
                                                    value="{{ old('rack_layout_cols', 5) }}" min="1" max="20">
                                                @error('rack_layout_cols')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <small class="text-muted">Jumlah rak per baris di denah 3D.</small>
                                        </div>
                                    </div>

                                    {{-- Kapasitas Default --}}
                                    <div class="form-group row mb-0">
                                        <label class="col-sm-5 col-form-label" style="font-weight:600">
                                            Kapasitas Default <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-sm-7">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="min-width:40px;"></span>
                                                </div>
                                                <input type="number" name="default_capacity" id="default_capacity"
                                                    class="form-control @error('default_capacity') is-invalid @enderror"
                                                    value="{{ old('default_capacity', 100) }}" min="1" max="9999">
                                                @error('default_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <small class="text-muted">capacity_max tiap sel (unit).</small>
                                        </div>
                                    </div>

                                </div>{{-- /col kanan --}}
                            </div>{{-- /row --}}

                            {{-- Preview --}}
                            <div class="alert alert-info border-0 mb-0 mt-3 py-2" id="layoutPreview">
                                <i class="fas fa-eye mr-1"></i>
                                <span id="previewText">Isi parameter di atas untuk melihat ringkasan.</span>
                            </div>

                        </div>{{-- /card-body --}}
                    </div>{{-- /card --}}
                </div>{{-- /#layoutSection --}}
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

@if($typeForm == 'create')
@push('scripts')
<script>
$(document).ready(function () {
    var $toggle  = $('#generateLayout');
    var $section = $('#layoutSection');

    function levelLetter(n) {
        return String.fromCharCode(64 + parseInt(n));
    }

    function updatePreview() {
        var count   = parseInt($('#rack_count').val()) || 0;
        var prefix  = ($('#rack_prefix').val() || 'R').toUpperCase().trim();
        var levels  = parseInt($('#rack_levels').val()) || 1;
        var cols    = parseInt($('#rack_columns').val()) || 1;
        var total   = count * levels * cols;
        var lastNum = String(count).padStart(2, '0');
        var lastLtr = levelLetter(levels);
        var sfx1    = cols > 1 ? '1' : '';
        var sfxN    = cols > 1 ? String(cols) : '';
        var ex1     = prefix + '-01-A' + sfx1;
        var exN     = prefix + '-' + lastNum + '-' + lastLtr + sfxN;

        var html = 'Akan dibuat: <strong>' + count + ' rak</strong> &times; '
                 + '<strong>' + levels + ' level</strong> &times; '
                 + '<strong>' + cols + ' kolom/level</strong> = '
                 + '<strong>' + total + ' sel</strong><br>'
                 + 'Kode rak: <code>' + prefix + '-01</code> s.d. <code>' + prefix + '-' + lastNum + '</code><br>'
                 + 'Contoh kode sel: <code>' + ex1 + '</code> … <code>' + exN + '</code>';

        $('#previewText').html(html);
    }

    $toggle.change(function () {
        if ($(this).is(':checked')) {
            $section.slideDown(200);
        } else {
            $section.slideUp(200);
        }
        updatePreview();
    });

    $('#rack_count, #rack_levels, #rack_columns').on('input change', updatePreview);
    $('#rack_prefix').on('input', updatePreview);

    @if(old('generate_layout'))
    $toggle.prop('checked', true);
    $section.show();
    updatePreview();
    @endif
});
</script>
@endpush
@endif
