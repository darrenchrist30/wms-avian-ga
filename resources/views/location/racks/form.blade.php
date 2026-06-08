@extends('layouts.adminlte')

@php
    $selectedWarehouseId = $selectedWarehouseId ?? null;
    $selectedPosX        = $selectedPosX ?? null;
    $selectedPosZ        = $selectedPosZ ?? null;
@endphp

@section('title', $typeForm == 'create' ? 'Tambah Rak' : 'Edit Rak')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-{{ $typeForm == 'create' ? 'layer-group' : 'edit' }} mr-2 text-primary"></i>
                {{ $typeForm == 'create' ? 'Tambah Rak' : 'Edit Rak' }}
            </h5>
            <small class="text-muted">
                {{ $typeForm == 'create' ? 'Daftarkan rak baru dalam gudang' : 'Perbarui data rak: ' . ($data->code ?? '') }}
            </small>
        </div>
        @if(!empty($selectedWarehouseId) && $typeForm == 'create')
        <a href="{{ route('location.warehouses.edit', $selectedWarehouseId) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Gudang
        </a>
        @elseif($typeForm == 'edit')
        <a href="{{ route('location.racks.show', $data->id) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Detail
        </a>
        @else
        <a href="{{ route('location.racks.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
        @endif
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
                {{ $typeForm == 'create' ? 'Data Rak Baru' : 'Edit: ' . ($data->code ?? '') }}
            </h3>
        </div>

        <form id="form-rack"
            action="{{ $typeForm == 'create' ? route('location.racks.store') : route('location.racks.update', $data->id) }}"
            method="POST">
            @csrf
            @if ($typeForm == 'edit') @method('PUT') @endif

            <div class="card-body">

                {{-- Gudang --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Gudang <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <select name="warehouse_id"
                                class="form-control @error('warehouse_id') is-invalid @enderror"
                                {{ !empty($selectedWarehouseId) ? 'disabled' : '' }}>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}"
                                        {{ old('warehouse_id', $data->warehouse_id ?? $selectedWarehouseId ?? '') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if(!empty($selectedWarehouseId))
                                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                            @endif
                            @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Kode Rak --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Kode Rak <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="code"
                                class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code', $data->code ?? '') }}"
                                placeholder="Contoh: R-A01" style="text-transform:uppercase"
                                maxlength="20">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Unik per gudang, maks 20 karakter.</small>
                    </div>
                </div>

                {{-- Nama Rak --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">Nama Rak</label>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="text" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $data->name ?? '') }}"
                                placeholder="Nama rak (opsional)" maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Total Level (create only) --}}
                @if ($typeForm == 'create')
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Total Level <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="number" name="total_levels"
                                class="form-control @error('total_levels') is-invalid @enderror"
                                value="{{ old('total_levels', 7) }}" min="1" max="26">
                            @error('total_levels')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Jumlah level rak (A, B, C, ...). Vertikal (atas–bawah).</small>
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Jumlah Kolom <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="min-width:40px;"></span>
                            </div>
                            <input type="number" name="total_columns"
                                class="form-control @error('total_columns') is-invalid @enderror"
                                value="{{ old('total_columns', 1) }}" min="1" max="20">
                            @error('total_columns')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Jumlah kolom berdampingan. 1 = satu kolom (default). Horizontal (kiri–kanan).</small>
                    </div>
                </div>

                <div class="alert alert-info border small py-2">
                    <i class="fas fa-magic mr-1"></i>
                    Sel akan di-generate otomatis: <strong id="cellCount">7 sel</strong>
                    — <span id="cellRange">label A–G (1 kolom)</span>
                </div>
                @else
                <div class="alert alert-light border small text-muted py-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Dimensi rak (level/kolom) tidak dapat diubah karena sel sudah ada.
                    Rak ini memiliki <strong>{{ $data->cells_count ?? 0 }} sel</strong>.
                </div>
                @endif

                <hr class="my-3">

                {{-- Posisi di Denah 3D --}}
                <div class="form-group row mb-3">
                    <label class="col-sm-3 col-form-label" style="font-weight:600">
                        Posisi di Denah 3D
                        <div class="text-muted font-weight-normal" style="font-size:11px;">Klik minimap untuk menempatkan rak</div>
                    </label>
                    <div class="col-sm-9">
                        <div class="d-flex align-items-start" style="gap:12px;flex-wrap:wrap;">

                            {{-- Canvas floor plan --}}
                            <div style="position:relative;">
                                <canvas id="floorCanvas" width="480" height="360"
                                    style="border:1px solid #ced4da;border-radius:6px;cursor:crosshair;background:#f8f9fa;display:block;"></canvas>
                                <div id="floorHelp" class="text-muted" style="font-size:11px;margin-top:4px;text-align:center;">
                                    <i class="fas fa-mouse-pointer mr-1"></i>{{ $typeForm === 'edit' ? 'Klik pada minimap untuk memindahkan posisi rak' : 'Klik pada minimap untuk meletakkan rak baru' }}
                                </div>
                            </div>

                            {{-- Panel kanan --}}
                            <div style="min-width:180px;">
                                {{-- Koordinat --}}
                                <div class="card card-body p-2 mb-2 border" style="background:#f8f9fa;">
                                    <div class="small font-weight-bold text-muted mb-1">Koordinat Terpilih</div>
                                    <div class="d-flex" style="gap:8px;">
                                        <div>
                                            <label class="small text-muted mb-0">Pos X</label>
                                            <input type="number" id="dispPosX" class="form-control form-control-sm" step="0.5" value="{{ old('pos_x', $data->pos_x ?? $selectedPosX ?? 0) }}" style="width:80px;">
                                        </div>
                                        <div>
                                            <label class="small text-muted mb-0">Pos Z</label>
                                            <input type="number" id="dispPosZ" class="form-control form-control-sm" step="0.5" value="{{ old('pos_z', $data->pos_z ?? $selectedPosZ ?? 0) }}" style="width:80px;">
                                        </div>
                                    </div>
                                </div>

                                {{-- Rotasi --}}
                                <div class="card card-body p-2 mb-2 border" style="background:#f8f9fa;">
                                    <div class="small font-weight-bold text-muted mb-1">Rotasi</div>
                                    <div class="btn-group btn-group-sm w-100" id="rotBtnGroup">
                                        <button type="button" class="btn btn-outline-secondary btnRot {{ old('rotation_y', $data->rotation_y ?? 0) == 0 ? 'active' : '' }}" data-rot="0">↔ Horizontal</button>
                                        <button type="button" class="btn btn-outline-secondary btnRot {{ old('rotation_y', $data->rotation_y ?? 0) == 1.5708 ? 'active' : '' }}" data-rot="1.5708">↕ Vertikal</button>
                                    </div>
                                </div>

                                <button type="button" id="btnReloadMap" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                                    <i class="fas fa-sync-alt mr-1"></i>Muat Ulang Denah
                                </button>
                            </div>
                        </div>

                        {{-- Hidden inputs --}}
                        <input type="hidden" name="pos_x"      id="posX"      value="{{ old('pos_x', $data->pos_x ?? $selectedPosX ?? 0) }}">
                        <input type="hidden" name="pos_z"      id="posZ"      value="{{ old('pos_z', $data->pos_z ?? $selectedPosZ ?? 0) }}">
                        <input type="hidden" name="rotation_y" id="rotationY" value="{{ old('rotation_y', $data->rotation_y ?? 0) }}">
                        @if(!empty($selectedWarehouseId))
                            <input type="hidden" name="from_warehouse" value="{{ $selectedWarehouseId }}">
                        @endif
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
                    </div>
                </div>

                @if ($typeForm == 'edit')
                    <div class="alert alert-light border small text-muted mt-3 mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Gudang: {{ $data->warehouse->name ?? '-' }} |
                        Level: {{ $data->total_levels }} (A–{{ chr(64 + $data->total_levels) }}) |
                        Dibuat: {{ $data->created_at->format('d M Y') }}
                    </div>
                @endif

            </div>{{-- /card-body --}}

            <div class="card-footer d-flex justify-content-end align-items-center">
                <a href="{{ route('location.racks.index') }}" class="btn btn-secondary mr-2">
                    <i class="fas fa-times mr-1"></i>Batal
                </a>
                <button type="submit" class="btn btn-{{ $typeForm == 'create' ? 'primary' : 'success' }}">
                    <i class="fas fa-save mr-1"></i>
                    {{ $typeForm == 'create' ? 'Simpan & Generate Sel' : 'Update' }}
                </button>
            </div>

        </form>
    </div>

</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('adminlte/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
</script>
<script>
// ── Cell count label ─────────────────────────────────────────────────────────
var letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
function updateCellCount() {
    var levels = parseInt($('[name=total_levels]').val()) || 0;
    var cols   = parseInt($('[name=total_columns]').val()) || 1;
    var total  = levels * cols;
    $('#cellCount').text(total + ' sel');
    if (levels > 0 && levels <= 26) {
        var lastLetter = letters[levels - 1];
        if (cols === 1) {
            $('#cellRange').text('label A–' + lastLetter + ' (1 kolom)');
        } else {
            $('#cellRange').text('label A1–' + lastLetter + cols + ' (' + levels + ' level × ' + cols + ' kolom)');
        }
    }
}
$('[name=total_levels]').on('input', updateCellCount);
$('[name=total_columns]').on('input', updateCellCount);
updateCellCount();

// ── Floor Plan Picker ─────────────────────────────────────────────────────────
var canvas = document.getElementById('floorCanvas');
var ctx    = canvas.getContext('2d');
var W = canvas.width, H = canvas.height;
var PAD   = 4;   // world-unit padding around bounding box

var minX = -15, maxX = 15, minZ = -10, maxZ = 10;  // defaults
var existingRacks = [];
var curPosX = parseFloat($('#posX').val()) || 0;
var curPosZ = parseFloat($('#posZ').val()) || 0;
var curRot  = parseFloat($('#rotationY').val()) || 0;
var RACK_D  = 1.0;
var isEditMode   = {{ $typeForm === 'edit' ? 'true' : 'false' }};
var editRackCode = '{{ $typeForm === "edit" ? addslashes($data->code ?? "") : "" }}';

function rackW(cols) { return Math.max(2.0, (cols || 1) * 1.5); }
function curRackW()   { return rackW(parseInt($('[name=total_columns]').val()) || 1); }

function scaleX() { return W / (maxX - minX); }
function scaleZ() { return H / (maxZ - minZ); }

function w2c(x, z) {
    return { cx: (x - minX) * scaleX(), cy: (z - minZ) * scaleZ() };
}
function c2w(cx, cy) {
    return { x: +(cx / scaleX() + minX).toFixed(2), z: +(cy / scaleZ() + minZ).toFixed(2) };
}

function fitBounds(racks) {
    if (!racks.length) { minX = -15; maxX = 15; minZ = -10; maxZ = 10; return; }
    var xs = racks.map(function(r) { return parseFloat(r.pos_x) || 0; });
    var zs = racks.map(function(r) { return parseFloat(r.pos_z) || 0; });
    xs.push(curPosX); zs.push(curPosZ);
    var maxW  = Math.max(curRackW(), Math.max.apply(null, racks.map(function(r){ return rackW(r.total_columns||1); })));
    var bminX = Math.min.apply(null, xs) - PAD - maxW;
    var bmaxX = Math.max.apply(null, xs) + PAD + maxW;
    var bminZ = Math.min.apply(null, zs) - PAD - RACK_D;
    var bmaxZ = Math.max.apply(null, zs) + PAD + RACK_D;
    // Keep aspect ratio so racks don't distort
    var wRange = bmaxX - bminX, hRange = bmaxZ - bminZ;
    var aspect = W / H;
    if (wRange / hRange > aspect) { var extra = (wRange / aspect - hRange) / 2; bminZ -= extra; bmaxZ += extra; }
    else { var extra = (hRange * aspect - wRange) / 2; bminX -= extra; bmaxX += extra; }
    minX = bminX; maxX = bmaxX; minZ = bminZ; maxZ = bmaxZ;
}

function getRackRect(x, z, rot, cols) {
    var w  = (cols !== undefined) ? rackW(cols) : curRackW();
    var rw = (rot !== 0) ? RACK_D : w;
    var rd = (rot !== 0) ? w : RACK_D;
    return { x: x - rw/2, z: z - rd/2, w: rw, d: rd };
}
function rectsOverlap(a, b) {
    return !(a.x + a.w <= b.x || b.x + b.w <= a.x || a.z + a.d <= b.z || b.z + b.d <= a.z);
}

function drawGrid() {
    ctx.clearRect(0, 0, W, H);
    // Light background
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, W, H);
    // Grid
    ctx.strokeStyle = '#e0e0e0'; ctx.lineWidth = 0.5;
    var step = 1;
    if ((maxX - minX) > 40) step = 2;
    for (var i = Math.ceil(minX); i <= maxX; i += step) {
        var c = w2c(i, 0); ctx.beginPath(); ctx.moveTo(c.cx, 0); ctx.lineTo(c.cx, H); ctx.stroke();
    }
    for (var j = Math.ceil(minZ); j <= maxZ; j += step) {
        var r = w2c(0, j); ctx.beginPath(); ctx.moveTo(0, r.cy); ctx.lineTo(W, r.cy); ctx.stroke();
    }
    // Axes
    if (minX <= 0 && maxX >= 0) {
        ctx.strokeStyle = '#adb5bd'; ctx.lineWidth = 1;
        var ax = w2c(0, 0);
        ctx.beginPath(); ctx.moveTo(ax.cx, 0); ctx.lineTo(ax.cx, H); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(0, ax.cy); ctx.lineTo(W, ax.cy); ctx.stroke();
    }
}

function drawExistingRacks() {
    existingRacks.forEach(function(rack) {
        var rot  = parseFloat(rack.rotation_y) || 0;
        var rect = getRackRect(parseFloat(rack.pos_x), parseFloat(rack.pos_z), rot, rack.total_columns || 1);
        var cp   = w2c(rect.x, rect.z);
        var pw   = rect.w * scaleX(), pd = rect.d * scaleZ();

        ctx.fillStyle = 'rgba(73,80,87,0.75)';
        ctx.fillRect(cp.cx, cp.cy, pw, pd);
        ctx.strokeStyle = '#343a40'; ctx.lineWidth = 1;
        ctx.strokeRect(cp.cx, cp.cy, pw, pd);

        var lp = w2c(parseFloat(rack.pos_x), parseFloat(rack.pos_z));
        ctx.fillStyle = '#fff';
        var fs = Math.max(7, Math.min(11, pw * 0.4));
        ctx.font = 'bold ' + fs + 'px Arial';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(rack.rack_code || '', lp.cx, lp.cy);
        ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
    });
}

function drawNewRack() {
    var rect    = getRackRect(curPosX, curPosZ, curRot);
    var overlap = existingRacks.some(function(r) {
        var rr = getRackRect(parseFloat(r.pos_x), parseFloat(r.pos_z), parseFloat(r.rotation_y)||0, r.total_columns||1);
        return rectsOverlap(rect, rr);
    });
    var cp = w2c(rect.x, rect.z);
    var pw = rect.w * scaleX(), pd = rect.d * scaleZ();

    ctx.fillStyle   = overlap ? 'rgba(220,53,69,0.5)' : 'rgba(13,133,100,0.65)';
    ctx.strokeStyle = overlap ? '#dc3545' : '#0d8564';
    ctx.lineWidth   = 2;
    ctx.fillRect(cp.cx, cp.cy, pw, pd);
    ctx.strokeRect(cp.cx, cp.cy, pw, pd);

    var lp = w2c(curPosX, curPosZ);
    var label = isEditMode ? editRackCode : 'BARU';
    var fs = Math.max(7, Math.min(11, pw * 0.4));
    ctx.fillStyle = '#fff'; ctx.font = 'bold ' + fs + 'px Arial';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(label, lp.cx, lp.cy);
    ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';

    if (overlap) {
        $('#floorHelp').html('<i class="fas fa-exclamation-triangle text-danger mr-1"></i><span class="text-danger">Posisi tumpang tindih — geser ke tempat kosong</span>');
    } else {
        var msg = isEditMode ? 'Posisi rak' : 'Posisi valid';
        $('#floorHelp').html('<i class="fas fa-check-circle text-success mr-1"></i><span class="text-success">' + msg + ' (X: ' + curPosX + ', Z: ' + curPosZ + ')</span>');
    }
}

function redraw() { drawGrid(); drawExistingRacks(); drawNewRack(); }

function loadRacks(fitView) {
    if (fitView === undefined) fitView = true;
    var wid = $('[name=warehouse_id]').val();
    if (!wid) {
        existingRacks = [];
        if (fitView) fitBounds([]);
        redraw();
        $('#floorHelp').html('<i class="fas fa-info-circle text-muted mr-1"></i>Pilih gudang terlebih dahulu untuk melihat denah');
        return;
    }
    $('#floorHelp').html('<i class="fas fa-spinner fa-spin mr-1"></i>Memuat denah gudang...');
    $.getJSON('{{ route("warehouse3d.data") }}', { warehouse_id: wid }, function(data) {
        existingRacks = [];
        if (data && data[0] && data[0].racks) {
            data[0].racks.forEach(function(r) {
                @if($typeForm == 'edit')
                if (r.rack_id != {{ $data->id }}) existingRacks.push(r);
                @else
                existingRacks.push(r);
                @endif
            });
        }
        if (fitView) fitBounds(existingRacks);
        redraw();
    }).fail(function() { if (fitView) fitBounds([]); redraw(); });
}

canvas.addEventListener('click', function(e) {
    var r  = canvas.getBoundingClientRect();
    var cx = (e.clientX - r.left) * (W / r.width);
    var cy = (e.clientY - r.top)  * (H / r.height);
    var pos = c2w(cx, cy);
    curPosX = Math.round(pos.x * 2) / 2;
    curPosZ = Math.round(pos.z * 2) / 2;
    $('#posX').val(curPosX); $('#posZ').val(curPosZ);
    $('#dispPosX').val(curPosX); $('#dispPosZ').val(curPosZ);
    redraw();
});

canvas.addEventListener('mousemove', function(e) {
    var r  = canvas.getBoundingClientRect();
    var cx = (e.clientX - r.left) * (W / r.width);
    var cy = (e.clientY - r.top)  * (H / r.height);
    var pos = c2w(cx, cy);
    var hx = Math.round(pos.x * 2) / 2, hz = Math.round(pos.z * 2) / 2;
    drawGrid(); drawExistingRacks();
    var gr = getRackRect(hx, hz, curRot);
    var gp = w2c(gr.x, gr.z);
    ctx.fillStyle = 'rgba(13,133,100,0.15)';
    ctx.fillRect(gp.cx, gp.cy, gr.w * scaleX(), gr.d * scaleZ());
    ctx.strokeStyle = '#0d8564'; ctx.lineWidth = 1; ctx.setLineDash([4,3]);
    ctx.strokeRect(gp.cx, gp.cy, gr.w * scaleX(), gr.d * scaleZ());
    ctx.setLineDash([]);
    drawNewRack();
});

canvas.addEventListener('mouseleave', function() { redraw(); });

$('#dispPosX').on('input', function() { curPosX = parseFloat($(this).val()) || 0; $('#posX').val(curPosX); redraw(); });
$('#dispPosZ').on('input', function() { curPosZ = parseFloat($(this).val()) || 0; $('#posZ').val(curPosZ); redraw(); });

$('.btnRot').on('click', function() {
    $('.btnRot').removeClass('active btn-secondary').addClass('btn-outline-secondary');
    $(this).addClass('active btn-secondary').removeClass('btn-outline-secondary');
    curRot = parseFloat($(this).data('rot'));
    $('#rotationY').val(curRot);
    redraw();
});

$('#btnReloadMap').on('click', function() { loadRacks(false); });
$('[name=warehouse_id]').on('change', function() { loadRacks(true); });
$('[name=total_columns]').on('input', function() { fitBounds(existingRacks); redraw(); });

@if($typeForm == 'create')
// Pre-select warehouse if arriving from warehouse edit page
@if(!empty($selectedWarehouseId))
$('[name=warehouse_id]').val('{{ $selectedWarehouseId }}');
@else
var $wSel = $('[name=warehouse_id]');
if (!$wSel.val()) {
    var firstVal = $wSel.find('option[value!=""]').first().val();
    if (firstVal) { $wSel.val(firstVal); }
}
@endif
@endif
loadRacks();
</script>
@endpush
