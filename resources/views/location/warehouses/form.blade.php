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
                {{-- Denah & Manajemen Rak (hanya saat edit)                       --}}
                {{-- ============================================================ --}}
                @if ($typeForm == 'edit')
                <hr class="my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="font-weight-bold mb-0">
                            <i class="fas fa-layer-group mr-1 text-primary"></i> Denah &amp; Manajemen Rak
                        </h6>
                        <small class="text-muted">Klik area kosong di denah untuk menambah rak di posisi tersebut.</small>
                    </div>
                    <a href="{{ route('location.racks.create', ['warehouse_id' => $data->id]) }}"
                       class="btn btn-sm btn-primary">
                        <i class="fas fa-plus mr-1"></i> Tambah Rak
                    </a>
                </div>

                <div class="d-flex align-items-start" style="gap:16px;flex-wrap:wrap;">

                    {{-- Canvas --}}
                    <div>
                        <canvas id="whCanvas" width="520" height="320"
                            style="border:1px solid #ced4da;border-radius:6px;background:#f8f9fa;cursor:crosshair;display:block;"
                            title="Klik area kosong untuk menambah rak di posisi tersebut"></canvas>
                        <div id="whCanvasHelp" class="text-muted mt-1" style="font-size:11px;text-align:center;">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Memuat denah gudang...
                        </div>
                    </div>

                    {{-- Panel kanan --}}
                    <div style="min-width:180px;max-width:220px;">
                        <div class="card card-body p-2 border mb-2" style="background:#f8f9fa;font-size:11px;">
                            <div class="font-weight-bold text-muted mb-1">Legenda</div>
                            <div class="d-flex align-items-center mb-1">
                                <span style="width:13px;height:13px;background:#6c757d;border-radius:2px;display:inline-block;margin-right:5px;flex-shrink:0;"></span>Rak existing
                            </div>
                            <div class="d-flex align-items-center">
                                <span style="width:13px;height:13px;background:#0d8564;border-radius:2px;opacity:.55;display:inline-block;margin-right:5px;flex-shrink:0;"></span>Preview posisi baru
                            </div>
                        </div>

                        <p class="small text-muted mb-3">
                            <i class="fas fa-mouse-pointer mr-1"></i>
                            Klik di area kosong pada denah untuk langsung membuka form tambah rak dengan posisi tersebut sudah terisi.
                        </p>

                        <a href="{{ route('location.racks.create', ['warehouse_id' => $data->id]) }}"
                           class="btn btn-primary btn-sm btn-block mb-2">
                            <i class="fas fa-plus mr-1"></i> Tambah Rak Baru
                        </a>
                        <a href="{{ route('location.racks.index') }}"
                           class="btn btn-outline-secondary btn-sm btn-block">
                            <i class="fas fa-list mr-1"></i> Daftar Semua Rak
                        </a>
                    </div>

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

@push('scripts')
@if($typeForm == 'edit')
<script>
(function () {
    var canvas = document.getElementById('whCanvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var W = canvas.width, H = canvas.height;
    var PAD = 4, RACK_D = 1.0;
    function rackW(cols) { return Math.max(2.0, (cols || 1) * 1.5); }
    var minX = -15, maxX = 15, minZ = -10, maxZ = 10;
    var racks = [];
    var createUrl  = '{{ route("location.racks.create", ["warehouse_id" => $data->id]) }}';
    var editUrl    = '{{ route("location.racks.edit", ":id") }}';
    var warehouse3dUrl = '{{ route("warehouse3d.data") }}';
    var warehouseId = {{ $data->id }};

    function sX() { return W / (maxX - minX); }
    function sZ() { return H / (maxZ - minZ); }
    function w2c(x, z) { return { cx: (x - minX) * sX(), cy: (z - minZ) * sZ() }; }
    function c2w(cx, cy) { return { x: +(cx / sX() + minX).toFixed(2), z: +(cy / sZ() + minZ).toFixed(2) }; }

    function fitBounds() {
        if (!racks.length) return;
        var xs   = racks.map(function(r) { return parseFloat(r.pos_x) || 0; });
        var zs   = racks.map(function(r) { return parseFloat(r.pos_z) || 0; });
        var maxW = Math.max.apply(null, racks.map(function(r) { return rackW(r.total_columns || 1); }));
        var bminX = Math.min.apply(null, xs) - PAD - maxW;
        var bmaxX = Math.max.apply(null, xs) + PAD + maxW;
        var bminZ = Math.min.apply(null, zs) - PAD - RACK_D;
        var bmaxZ = Math.max.apply(null, zs) + PAD + RACK_D;
        var aspect = W / H, wR = bmaxX - bminX, hR = bmaxZ - bminZ;
        if (wR / hR > aspect) { var e = (wR / aspect - hR) / 2; bminZ -= e; bmaxZ += e; }
        else { var e2 = (hR * aspect - wR) / 2; bminX -= e2; bmaxX += e2; }
        minX = bminX; maxX = bmaxX; minZ = bminZ; maxZ = bmaxZ;
    }

    function drawBase() {
        ctx.fillStyle = '#f8f9fa'; ctx.fillRect(0, 0, W, H);
        ctx.strokeStyle = '#e0e0e0'; ctx.lineWidth = 0.5;
        var step = (maxX - minX) > 40 ? 2 : 1;
        for (var i = Math.ceil(minX); i <= maxX; i += step) {
            var c = w2c(i, 0); ctx.beginPath(); ctx.moveTo(c.cx, 0); ctx.lineTo(c.cx, H); ctx.stroke();
        }
        for (var j = Math.ceil(minZ); j <= maxZ; j += step) {
            var r = w2c(0, j); ctx.beginPath(); ctx.moveTo(0, r.cy); ctx.lineTo(W, r.cy); ctx.stroke();
        }
        if (minX <= 0 && maxX >= 0) {
            ctx.strokeStyle = '#adb5bd'; ctx.lineWidth = 1;
            var ax = w2c(0, 0);
            ctx.beginPath(); ctx.moveTo(ax.cx, 0); ctx.lineTo(ax.cx, H); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(0, ax.cy); ctx.lineTo(W, ax.cy); ctx.stroke();
        }
    }

    function drawRacks() {
        racks.forEach(function(rack) {
            var rot = parseFloat(rack.rotation_y) || 0;
            var w  = rackW(rack.total_columns || 1);
            var rw = rot !== 0 ? RACK_D : w, rd = rot !== 0 ? w : RACK_D;
            var rx = parseFloat(rack.pos_x) || 0, rz = parseFloat(rack.pos_z) || 0;
            var cp = w2c(rx - rw / 2, rz - rd / 2);
            var pw = rw * sX(), pd = rd * sZ();
            ctx.fillStyle = 'rgba(73,80,87,0.75)'; ctx.fillRect(cp.cx, cp.cy, pw, pd);
            ctx.strokeStyle = '#343a40'; ctx.lineWidth = 1; ctx.strokeRect(cp.cx, cp.cy, pw, pd);
            var lp = w2c(rx, rz);
            ctx.fillStyle = '#fff';
            var fs = Math.max(7, Math.min(11, pw * 0.4));
            ctx.font = 'bold ' + fs + 'px Arial';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(rack.rack_code || '', lp.cx, lp.cy);
            ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
        });
    }

    function findRackAtPoint(wx, wz) {
        for (var i = 0; i < racks.length; i++) {
            var r   = racks[i];
            var rot = parseFloat(r.rotation_y) || 0;
            var w   = rackW(r.total_columns || 1);
            var rw  = rot !== 0 ? RACK_D : w, rd = rot !== 0 ? w : RACK_D;
            var rx  = parseFloat(r.pos_x) || 0, rz = parseFloat(r.pos_z) || 0;
            if (wx >= rx - rw/2 && wx <= rx + rw/2 && wz >= rz - rd/2 && wz <= rz + rd/2) return r;
        }
        return null;
    }

    function highlightRack(rack) {
        var rot = parseFloat(rack.rotation_y) || 0;
        var w   = rackW(rack.total_columns || 1);
        var rw  = rot !== 0 ? RACK_D : w, rd = rot !== 0 ? w : RACK_D;
        var rx  = parseFloat(rack.pos_x) || 0, rz = parseFloat(rack.pos_z) || 0;
        var cp  = w2c(rx - rw/2, rz - rd/2);
        var pw  = rw * sX(), pd = rd * sZ();
        ctx.fillStyle = 'rgba(255,193,7,0.35)';
        ctx.fillRect(cp.cx, cp.cy, pw, pd);
        ctx.strokeStyle = '#ffc107'; ctx.lineWidth = 2;
        ctx.strokeRect(cp.cx, cp.cy, pw, pd);
    }

    function redraw() { drawBase(); drawRacks(); }

    $.getJSON(warehouse3dUrl, { warehouse_id: warehouseId }, function(data) {
        if (data && data[0] && data[0].racks) racks = data[0].racks;
        fitBounds();
        redraw();
        var help = document.getElementById('whCanvasHelp');
        if (help) help.innerHTML = racks.length
            ? '<i class="fas fa-mouse-pointer mr-1"></i>Klik rak untuk edit posisi &bull; Klik area kosong untuk tambah rak baru'
            : '<i class="fas fa-info-circle mr-1"></i>Belum ada rak. Klik kanvas atau tombol "Tambah Rak" untuk mulai.';
    }).fail(function() {
        redraw();
        var help = document.getElementById('whCanvasHelp');
        if (help) help.textContent = 'Gagal memuat denah gudang.';
    });

    canvas.addEventListener('mousemove', function(e) {
        var rect = canvas.getBoundingClientRect();
        var cx = (e.clientX - rect.left) * (W / rect.width);
        var cy = (e.clientY - rect.top)  * (H / rect.height);
        var pos = c2w(cx, cy);
        var px = Math.round(pos.x * 2) / 2, pz = Math.round(pos.z * 2) / 2;
        var hovered = findRackAtPoint(pos.x, pos.z);
        redraw();
        var help = document.getElementById('whCanvasHelp');
        if (hovered) {
            highlightRack(hovered);
            canvas.style.cursor = 'pointer';
            if (help) help.innerHTML = '<i class="fas fa-edit text-warning mr-1"></i><span class="text-warning">Klik untuk edit rak <strong>' + hovered.rack_code + '</strong></span>';
        } else {
            canvas.style.cursor = 'crosshair';
            var gw = rackW(1);
            var gp = w2c(px - gw / 2, pz - RACK_D / 2);
            ctx.fillStyle = 'rgba(13,133,100,0.18)'; ctx.fillRect(gp.cx, gp.cy, gw * sX(), RACK_D * sZ());
            ctx.strokeStyle = '#0d8564'; ctx.lineWidth = 1; ctx.setLineDash([4, 3]);
            ctx.strokeRect(gp.cx, gp.cy, gw * sX(), RACK_D * sZ());
            ctx.setLineDash([]);
            if (help) help.innerHTML = '<i class="fas fa-plus-circle text-success mr-1"></i><span class="text-success">Klik untuk tambah rak baru di sini</span>';
        }
    });

    canvas.addEventListener('mouseleave', function() {
        canvas.style.cursor = 'crosshair';
        redraw();
        var help = document.getElementById('whCanvasHelp');
        if (help) help.innerHTML = racks.length
            ? '<i class="fas fa-mouse-pointer mr-1"></i>Klik rak untuk edit posisi &bull; Klik area kosong untuk tambah rak baru'
            : '<i class="fas fa-info-circle mr-1"></i>Belum ada rak. Klik kanvas atau tombol "Tambah Rak" untuk mulai.';
    });

    canvas.addEventListener('click', function(e) {
        var rect = canvas.getBoundingClientRect();
        var cx = (e.clientX - rect.left) * (W / rect.width);
        var cy = (e.clientY - rect.top)  * (H / rect.height);
        var pos = c2w(cx, cy);
        var clicked = findRackAtPoint(pos.x, pos.z);
        if (clicked) {
            window.location.href = editUrl.replace(':id', clicked.rack_id);
        } else {
            var px = Math.round(pos.x * 2) / 2, pz = Math.round(pos.z * 2) / 2;
            window.location.href = createUrl + '&pos_x=' + px + '&pos_z=' + pz;
        }
    });
})();
</script>
@endif

@if($typeForm == 'create')
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
@endif
@endpush
