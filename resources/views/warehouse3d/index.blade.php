@extends('layouts.adminlte')
@section('title', 'Visualisasi 3D Gudang')

@push('styles')
<style>
#threeWrap {
    position: relative;
    width: 100%;
    height: 620px;
    border-radius: 10px;
    overflow: hidden;
    background: #0d1117;
}
#threeWrap canvas { display: block; }

/* Legend overlay */
#threeLegend {
    position: absolute;
    bottom: 14px;
    left: 14px;
    background: rgba(13,17,23,.85);
    border: 1px solid #2d3748;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 11px;
    color: #cbd5e1;
    backdrop-filter: blur(4px);
    pointer-events: none;
}
.leg-item { display:flex; align-items:center; gap:7px; margin-bottom:5px; }
.leg-item:last-child { margin-bottom:0; }
.leg-dot { width:13px; height:13px; border-radius:3px; flex-shrink:0; }

/* Controls hint */
#threeHint {
    position: absolute;
    top: 12px;
    right: 14px;
    background: rgba(13,17,23,.75);
    border: 1px solid #2d3748;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 11px;
    color: #64748b;
    pointer-events: none;
    line-height: 1.7;
}

/* Tooltip */
#threeTooltip {
    position: absolute;
    display: none;
    background: rgba(15,23,42,.92);
    border: 1px solid #334155;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 12px;
    color: #e2e8f0;
    pointer-events: none;
    z-index: 10;
    min-width: 130px;
    line-height: 1.6;
}

/* Loading overlay */
#threeLoading {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #0d1117;
    color: #64748b;
    font-size: 14px;
    gap: 12px;
    z-index: 5;
}

/* Warehouse selector button */
.btn-reset-cam {
    position: absolute;
    top: 12px;
    left: 14px;
    background: rgba(13,17,23,.8);
    border: 1px solid #334155;
    color: #94a3b8;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 11px;
    cursor: pointer;
    z-index: 5;
    transition: background .2s;
}
.btn-reset-cam:hover { background: rgba(51,65,85,.9); color: #fff; }

/* Highlight legend dot pulse */
@keyframes dot-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.35; }
}
.leg-dot-pulse { animation: dot-pulse 1.4s ease-in-out infinite; }

/* GA highlight banner star pulse */
@keyframes ga-star-beat {
    0%, 100% { transform: scale(1);   color: #856404; }
    50%       { transform: scale(1.4); color: #ffd700; }
}
.ga-star { display:inline-block; animation: ga-star-beat 1.2s ease-in-out infinite; margin-right:6px; }

/* GA banner — stronger visual weight */
.alert-ga {
    background: linear-gradient(90deg, #332200 0%, #1a1200 100%);
    border: 2px solid #ffd700;
    color: #ffe57f;
    border-radius: 8px;
}
</style>
@endpush

@section('content')
<div class="container-fluid pb-4">

{{-- ── Header ────────────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px">
    <div>
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-cube mr-2" style="color:#6f42c1"></i>Visualisasi Gudang 3D
        </h5>
        <small class="text-muted">Rotasi dengan mouse kiri · Zoom scroll · Klik cell untuk detail stok</small>
    </div>
    <div class="d-flex align-items-center flex-wrap" style="gap:6px">
        <select id="warehouseSelector" class="form-control form-control-sm" style="width:210px">
            <option value="">— Pilih Gudang —</option>
            @foreach($warehouses as $wh)
            <option value="{{ $wh->id }}" {{ $selectedWarehouse?->id == $wh->id ? 'selected' : '' }}>
                {{ $wh->name }}
            </option>
            @endforeach
        </select>
        {{-- SKU / Item search for highlight --}}
        @if($selectedWarehouse)
        <div class="input-group input-group-sm" style="width:220px">
            <input type="text" id="skuSearch" class="form-control" placeholder="Cari SKU / nama item..." style="border-color:#6c757d">
            <div class="input-group-append">
                <button class="btn btn-outline-secondary" id="btnSkuSearch" title="Highlight cell berisi item ini">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        @endif
        <a href="{{ route('stock.index') }}" class="btn btn-sm btn-light border">
            <i class="fas fa-boxes mr-1"></i>Stok
        </a>
    </div>
</div>

{{-- ── Summary Cards ─────────────────────────────────────────────────────── --}}
@if($selectedWarehouse && $summary)
<div class="row mb-3">
    <div class="col-4 col-md-2 mb-2">
        <div class="small-box bg-secondary mb-0">
            <div class="inner"><h4>{{ $summary['total_zones'] }}</h4><p>Zona</p></div>
            <div class="icon"><i class="fas fa-th-large"></i></div>
        </div>
    </div>
    <div class="col-4 col-md-2 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ $summary['total_racks'] }}</h4><p>Rak</p></div>
            <div class="icon"><i class="fas fa-server"></i></div>
        </div>
    </div>
    <div class="col-4 col-md-2 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner"><h4>{{ $summary['total_cells'] }}</h4><p>Total Cell</p></div>
            <div class="icon"><i class="fas fa-th"></i></div>
        </div>
    </div>
    <div class="col-4 col-md-2 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner"><h4>{{ $summary['used_cells'] }}</h4><p>Terisi</p></div>
            <div class="icon"><i class="fas fa-box"></i></div>
        </div>
    </div>
    <div class="col-4 col-md-2 mb-2">
        <div class="small-box bg-danger mb-0">
            <div class="inner"><h4>{{ $summary['full_cells'] }}</h4><p>Penuh</p></div>
            <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
        </div>
    </div>
    <div class="col-4 col-md-2 mb-2">
        @php $uc = $summary['utilization'] >= 90 ? 'danger' : ($summary['utilization'] >= 70 ? 'warning' : 'success'); @endphp
        <div class="small-box bg-{{ $uc }} mb-0">
            <div class="inner"><h4>{{ $summary['utilization'] }}%</h4><p>Utilisasi</p></div>
            <div class="icon"><i class="fas fa-chart-pie"></i></div>
        </div>
    </div>
</div>
@endif

{{-- ── Highlight Banner ───────────────────────────────────────────────────── --}}
<div id="highlightBanner" class="alert py-2 px-3 mb-2 d-flex align-items-center justify-content-between" style="display:none;border-radius:8px">
    <span id="highlightBannerText" class="font-weight-bold" style="font-size:13px"></span>
    <button class="btn btn-sm ml-3" id="btnClearHighlight" style="white-space:nowrap">
        <i class="fas fa-times mr-1"></i>Hapus Highlight
    </button>
</div>

{{-- ── Three.js Canvas ───────────────────────────────────────────────────── --}}
@if($selectedWarehouse)
<div id="threeWrap">
    <div id="threeLoading">
        <i class="fas fa-spinner fa-spin fa-2x"></i>
        <span>Membangun scene 3D...</span>
    </div>

    {{-- Legend --}}
    <div id="threeLegend">
        <div style="font-size:10px;font-weight:700;letter-spacing:.5px;color:#94a3b8;margin-bottom:7px">LEGENDA CELL</div>
        <div class="leg-item"><div class="leg-dot" style="background:#00897b"></div> Kosong</div>
        <div class="leg-item"><div class="leg-dot" style="background:#f57f17"></div> Terisi Sebagian</div>
        <div class="leg-item"><div class="leg-dot" style="background:#b71c1c"></div> Penuh</div>
        <div class="leg-item"><div class="leg-dot" style="background:#37474f"></div> Diblokir</div>
        <div class="leg-item"><div class="leg-dot" style="background:#4a148c"></div> Direservasi</div>
        <div class="leg-item leg-highlight" style="display:none">
            <div class="leg-dot leg-dot-pulse" id="legHighlightDot" style="background:#ffd700;border:1px solid #fff"></div>
            <span id="legHighlightLabel">Rekomendasi GA</span>
        </div>
    </div>

    {{-- Controls hint --}}
    <div id="threeHint">
        <i class="fas fa-mouse mr-1"></i> Kiri: Rotasi &nbsp;·&nbsp; Kanan: Geser<br>
        <i class="fas fa-search-plus mr-1"></i> Scroll: Zoom &nbsp;·&nbsp; Klik: Detail
    </div>

    {{-- Reset camera button --}}
    <button class="btn-reset-cam" id="btnResetCam">
        <i class="fas fa-crosshairs mr-1"></i> Reset Kamera
    </button>

    {{-- Tooltip --}}
    <div id="threeTooltip"></div>
</div>
@else
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="fas fa-warehouse fa-3x mb-3 d-block" style="opacity:.25"></i>
        <strong>Pilih gudang di atas untuk memuat visualisasi 3D</strong>
    </div>
</div>
@endif

</div>

{{-- ── Cell Detail Modal ─────────────────────────────────────────────────── --}}
<div class="modal fade" id="cellModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2 bg-dark text-white">
                <h6 class="modal-title mb-0">
                    <i class="fas fa-cube mr-2"></i>Detail Cell — <span id="modalCellCode">—</span>
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div id="cellModalBody" class="p-3">
                    <div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Three.js r128 (classic script, non-ESM) --}}
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

<script>
// ════════════════════════════════════════════════════════════════════════════
//  THREE.JS WAREHOUSE 3D  —  WMS Avian
// ════════════════════════════════════════════════════════════════════════════

$('#warehouseSelector').on('change', function () {
    const wid = $(this).val();
    if (wid) window.location.href = '{{ route("warehouse3d.index") }}?warehouse_id=' + wid;
});

// SKU search
$('#skuSearch').on('keydown', function (e) { if (e.key === 'Enter') $('#btnSkuSearch').trigger('click'); });
$('#btnSkuSearch').on('click', function () {
    const q = $('#skuSearch').val().trim();
    if (q.length < 2) { alert('Masukkan minimal 2 karakter untuk pencarian.'); return; }
    $.getJSON('{{ route("warehouse3d.cells-by-item") }}', { q }, function (results) {
        if (!results.length) {
            alert('Tidak ditemukan cell berisi item "' + q + '".');
            return;
        }
        const ids   = new Set(results.map(r => r.cell_id));
        const label = results[0].item + (results.length > 1 ? ' (+' + (results.length - 1) + ' item lain)' : '');
        applyHighlight(ids, 'search', label + ' — ' + results.length + ' cell ditemukan');
    }).fail(function () { alert('Gagal mencari item.'); });
});

$('#btnClearHighlight').on('click', function () { clearHighlight(); });

@if($selectedWarehouse)
// ── Constants ─────────────────────────────────────────────────────────────
const CW  = 2.0;   // cell width  (X)
const CH  = 1.3;   // cell height (Y)
const CD  = 1.8;   // cell depth  (Z)

const ZONE_FLOOR = { A: 0x0a2744, B: 0x2d1500, C: 0x2d0a0a };
const ZONE_LABEL = { A: '#4fc3f7', B: '#ffb74d', C: '#ef9a9a' };

function cellHex(cell) {
    if (cell.status === 'blocked')  return 0x37474f;
    if (cell.status === 'reserved') return 0x7b1fa2;
    if (cell.status === 'full')     return 0xd32f2f;
    if (cell.utilization > 0)       return 0xf57f17;
    return 0x00897b;   // teal-green: empty slot
}
function cellEmissive(cell) {
    if (cell.status === 'blocked')  return 0x050a0c;
    if (cell.status === 'reserved') return 0x220030;
    if (cell.status === 'full')     return 0x400000;
    if (cell.utilization > 0)       return 0x3d1f00;
    return 0x002d28;
}
function cellOpacity(cell) {
    if (cell.status === 'blocked')  return 0.65;
    if (cell.status === 'reserved') return 0.50;
    if (cell.status === 'full')     return 0.55;   // items are primary visual; panel = glow
    if (cell.utilization > 0)       return 0.42;
    return 0.28;   // empty — very faint, rack frame shows through
}

// Deterministic hash from cell code string → integer 0–65535
function hashCell(code) {
    let h = 0;
    for (let i = 0; i < code.length; i++) h = (h * 31 + code.charCodeAt(i)) & 0xffff;
    return h;
}

// ── Scene ─────────────────────────────────────────────────────────────────
const wrap      = document.getElementById('threeWrap');
const loading   = document.getElementById('threeLoading');
const tooltip   = document.getElementById('threeTooltip');

const scene    = new THREE.Scene();
scene.background = new THREE.Color(0x0d1117);
scene.fog        = new THREE.FogExp2(0x0d1117, 0.004);

const camera = new THREE.PerspectiveCamera(48, wrap.clientWidth / wrap.clientHeight, 0.1, 600);
camera.position.set(13, 42, 25);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(wrap.clientWidth, wrap.clientHeight);
renderer.shadowMap.enabled    = true;
renderer.shadowMap.type       = THREE.PCFSoftShadowMap;
wrap.appendChild(renderer.domElement);

// ── Controls ──────────────────────────────────────────────────────────────
const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.target.set(13, 0, -20);
controls.enableDamping  = true;
controls.dampingFactor  = 0.06;
controls.minDistance    = 6;
controls.maxDistance    = 200;
controls.maxPolarAngle  = Math.PI / 2 - 0.02;
controls.update();

const DEFAULT_CAM = { pos: new THREE.Vector3(13, 42, 25), target: new THREE.Vector3(13, 0, -20) };
document.getElementById('btnResetCam').addEventListener('click', function () {
    camera.position.copy(DEFAULT_CAM.pos);
    controls.target.copy(DEFAULT_CAM.target);
    controls.update();
});

// ── Lights ────────────────────────────────────────────────────────────────
scene.add(new THREE.AmbientLight(0xffffff, 0.48));
scene.add(new THREE.HemisphereLight(0x4466aa, 0x1a1a2e, 0.40));

const dir = new THREE.DirectionalLight(0xffffff, 0.85);
dir.position.set(25, 70, 20);
dir.castShadow = true;
dir.shadow.camera.set = (near, far, left, right, top, bottom) => {};
dir.shadow.camera.near   = 1;
dir.shadow.camera.far    = 200;
dir.shadow.camera.left   = -30;
dir.shadow.camera.right  = 30;
dir.shadow.camera.top    = 60;
dir.shadow.camera.bottom = -60;
dir.shadow.mapSize.set(2048, 2048);
scene.add(dir);

// ── Floor (concrete grey) ────────────────────────────────────────────────
const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(32, 66),
    new THREE.MeshLambertMaterial({ color: 0x484848 })
);
floor.rotation.x = -Math.PI / 2;
floor.position.set(13, -0.01, -21);
floor.receiveShadow = true;
scene.add(floor);

// ── Text Sprite ───────────────────────────────────────────────────────────
function makeSprite(text, size, color) {
    const cv = document.createElement('canvas');
    cv.width = 256; cv.height = 64;
    const ctx = cv.getContext('2d');
    ctx.font = `bold ${size}px 'Segoe UI',Arial,sans-serif`;
    ctx.fillStyle = color || '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 128, 32);
    const sp = new THREE.Sprite(new THREE.SpriteMaterial({
        map: new THREE.CanvasTexture(cv),
        transparent: true,
        depthTest: false,
    }));
    sp.renderOrder = 999;
    return sp;
}

// ── Zone Floor Marker ─────────────────────────────────────────────────────
// Draws a full-width horizontal strip covering the Z extent of all racks
// in the zone — matches the horizontal-band look of the physical floor plan.
function addZoneMarker(absX, absZ, racks, colorHex) {
    if (!racks.length) return;
    const zs   = racks.map(r => absZ + r.pos_z);
    const minZ = Math.min(...zs) - 2.5;
    const maxZ = Math.max(...zs) + 2.5;
    const cz   = (minZ + maxZ) / 2;
    const d    = maxZ - minZ;

    const m = new THREE.Mesh(
        new THREE.PlaneGeometry(40, d),   // 40 = full warehouse width
        new THREE.MeshLambertMaterial({ color: colorHex, transparent: true, opacity: 0.18 })
    );
    m.rotation.x = -Math.PI / 2;
    m.position.set(13, 0.01, cz);   // 13 = warehouse X centre
    scene.add(m);
}

// ── Environment: walls, aisle markings, ceiling neons ────────────────────
// Static warehouse shell — called once at init, persists across loadScene calls.
// Layout: X[−1 .. 27], Z[−52 .. 10], height 12.5 m.
// Front (Z=10) is left open as a loading-bay entrance so the top-down camera
// can see into the building without a ceiling obstructing the view.
function buildEnvironment() {
    const wX0 = -1,  wX1 = 27;     // warehouse X extents
    const wZ0 = -52, wZ1 = 10;     // warehouse Z extents
    const wW  = wX1 - wX0;         // 28 m wide
    const wD  = wZ1 - wZ0;         // 62 m deep
    const cX  = (wX0 + wX1) / 2;   // 13  (centre X)
    const cZ  = (wZ0 + wZ1) / 2;   // −21 (centre Z)
    const wH  = 12.5;               // wall height (racks are 7 × 1.3 = 9.1 m)

    // ── Back wall only — tembok samping dihilangkan ──────────────────────────
    const wallMat = new THREE.MeshLambertMaterial({ color: 0xcdd0d4, side: THREE.DoubleSide });
    const backWall = new THREE.Mesh(new THREE.BoxGeometry(wW + 0.6, wH, 0.3), wallMat);
    backWall.position.set(cX, wH / 2, wZ0);
    backWall.receiveShadow = true;
    scene.add(backWall);

    // ── Yellow aisle safety lines on floor ────────────────────────────────
    // Four longitudinal stripes marking the two main walking corridors:
    //   left corridor  : between wall racks (X≈2) and centre racks (X≈13)  → X 3.5 & 11.5
    //   right corridor : between centre racks (X≈13) and right racks (X≈20+) → X 14.5 & 21.5
    const laneMat = new THREE.MeshBasicMaterial({ color: 0xffc107 });
    const laneGeo = new THREE.PlaneGeometry(0.22, wD - 4);
    [3.5, 11.5, 14.5, 21.5].forEach(lx => {
        const ln = new THREE.Mesh(laneGeo, laneMat);
        ln.rotation.x = -Math.PI / 2;
        ln.position.set(lx, 0.03, cZ);
        scene.add(ln);
    });

    // ── Neon ceiling light strips ─────────────────────────────────────────
    // Neon tubes & point lights dihapus — tanpa plafon/dinding tabung mengambang
    // AmbientLight(0.48) + DirectionalLight(0.85) sudah cukup untuk pencahayaan scene.
}

// ── Shared rack-structure materials & cell panel geometry ─────────────────
// Rack colours: blue uprights + orange beams/shelves (industrial warehouse style)
const postMat     = new THREE.MeshLambertMaterial({ color: 0x1565c0 });   // blue upright
const beamMat     = new THREE.MeshLambertMaterial({ color: 0xe65100 });   // orange beam/shelf
// Cell panel: slightly inset from post/beam edges so rack frame shows around it
const cellPanelGeo = new THREE.BoxGeometry(CW - 0.22, CH - 0.14, CD - 0.28);

// ── Shared low-poly item geometries & materials ───────────────────────────
// 4 types: standard box · tall box · drum · flat/wide box
// Shared across all cells to keep draw calls low.
const ITEM_GEOS = [
    new THREE.BoxGeometry(0.52, 0.42, 0.50),           // type 0 – standard cardboard box
    new THREE.BoxGeometry(0.42, 0.66, 0.40),            // type 1 – tall box
    new THREE.CylinderGeometry(0.21, 0.21, 0.46, 8),   // type 2 – drum / canister
    new THREE.BoxGeometry(0.60, 0.32, 0.55),            // type 3 – flat/wide box
];
const ITEM_MATS = [
    new THREE.MeshLambertMaterial({ color: 0xa07840 }),  // brown cardboard
    new THREE.MeshLambertMaterial({ color: 0xd4a040 }),  // tan cardboard (tall)
    new THREE.MeshLambertMaterial({ color: 0x546e7a }),  // steel-blue drum
    new THREE.MeshLambertMaterial({ color: 0x8d6e63 }),  // muted brown (flat box)
];
const ITEM_H = [0.42, 0.66, 0.46, 0.32];   // item heights (must match ITEM_GEOS Y)

// X/Z offsets relative to cell centre for 1–4 items per shelf slot
const ITEM_LAYOUTS = [
    [],                                                                              // 0 items
    [[0, 0]],                                                                        // 1 item
    [[-0.40,  0.10], [ 0.40, -0.10]],                                               // 2 items
    [[-0.52, -0.22], [ 0.52, -0.22], [  0,    0.28]],                               // 3 items
    [[-0.48, -0.28], [ 0.48, -0.28], [-0.48,  0.28], [ 0.48,  0.28]],              // 4 items
];

function itemCountForCell(cell) {
    if (cell.status === 'blocked') return 0;    // blocked → no items shown
    if (cell.utilization === 0)    return 0;    // empty   → no items
    const u = cell.utilization || 0;
    if (u < 25) return 1;
    if (u < 55) return 2;
    if (u < 80) return 3;
    return 4;
}

// ── Build Scene ───────────────────────────────────────────────────────────
const cellMeshes = [];
const itemMeshes = [];   // non-interactive item models; cleared alongside cells
let hoveredMesh = null, hoveredOrigMat = null;

function clearScene() {
    // clearHighlight() is a function declaration — safe to call before its source line
    if (typeof clearHighlight === 'function') clearHighlight();
    cellMeshes.forEach(m => { scene.remove(m); m.material.dispose(); });
    cellMeshes.length = 0;
    itemMeshes.forEach(m => scene.remove(m));   // shared mats — don't dispose
    itemMeshes.length = 0;
}

function buildWarehouse(zones) {
    zones.forEach(zone => {
        const zc   = zone.zone_code || '?';
        const absX = zone.pos_x;
        const absZ = zone.pos_z;

        addZoneMarker(absX, absZ, zone.racks, ZONE_FLOOR[zc] ?? 0x1a2332);

        // Zone label — centred on the bounding box of the zone's racks
        if (zone.racks.length) {
            const zs = zone.racks.map(r => absZ + r.pos_z);
            const cz = (Math.min(...zs) + Math.max(...zs)) / 2;
            const sp = makeSprite(zone.zone_name, 22, ZONE_LABEL[zc] ?? '#94a3b8');
            sp.scale.set(10, 2.4, 1);
            sp.position.set(-3, 0.1, cz);   // left-edge label, outside the rack area
            scene.add(sp);
        }

        // ── Bridge adjacent racks into one continuous shelf unit ──────────────
        // Seeder positions racks side-by-side in X (all pos_z = 0), so group
        // by Z and bridge in the X direction.  Only bridge gaps ≤ 2.5 m so
        // real aisles between zones (≈ 10 m gap) are never spanned.
        {
            const rowMap = {};
            zone.racks.forEach(r => {
                const key = Math.round((absZ + r.pos_z) * 2); // group by Z
                (rowMap[key] = rowMap[key] || []).push(r);
            });

            Object.values(rowMap).forEach(row => {
                if (row.length < 2) return;
                row.sort((a, b) => a.pos_x - b.pos_x); // left → right

                for (let i = 0; i < row.length - 1; i++) {
                    const rA = row[i], rB = row[i + 1];
                    const rz  = absZ + rA.pos_z;
                    const rh  = rA.total_levels * CH;

                    // Right edge of A  →  left edge of B
                    const xA1 = absX + rA.pos_x + CW / 2;
                    const xB0 = absX + rB.pos_x - CW / 2;
                    const gap = xB0 - xA1;
                    if (gap <= 0.05) continue;   // touching
                    if (gap > 2.5)   continue;   // aisle — don't bridge

                    const gapCX = (xA1 + xB0) / 2;

                    // — Shelf decks spanning the gap (continuous orange surface) —
                    const sfG = new THREE.BoxGeometry(gap, 0.042, CD - 0.15);
                    for (let lv = 0; lv < rA.total_levels; lv++) {
                        const sf = new THREE.Mesh(sfG, beamMat);
                        sf.position.set(gapCX, lv * CH + 0.024, rz);
                        scene.add(sf);
                    }

                    // — Vertical junction beams at each shelf level (left + right edges) —
                    const jbGeo = new THREE.BoxGeometry(0.060, 0.075, CD + 0.10);
                    for (let lv = 0; lv <= rA.total_levels; lv++) {
                        [xA1 + 0.030, xB0 - 0.030].forEach(bx => {
                            const b = new THREE.Mesh(jbGeo, beamMat);
                            b.position.set(bx, lv * CH, rz);
                            scene.add(b);
                        });
                    }
                }
            });
        }

        zone.racks.forEach(rack => {
            const rx   = absX + rack.pos_x;
            const rz   = absZ + rack.pos_z;
            const rh   = rack.total_levels * CH;
            const pOff = 0.045;   // inset so posts sit at column corners

            // ── 4 Blue Corner Uprights ──────────────────────────────────────
            const postGeo = new THREE.BoxGeometry(0.085, rh + 0.40, 0.085);
            [
                [rx - CW/2 + pOff, rz - CD/2 + pOff],
                [rx + CW/2 - pOff, rz - CD/2 + pOff],
                [rx - CW/2 + pOff, rz + CD/2 - pOff],
                [rx + CW/2 - pOff, rz + CD/2 - pOff],
            ].forEach(([px, pz]) => {
                const p = new THREE.Mesh(postGeo, postMat);
                p.position.set(px, rh / 2, pz);
                p.castShadow = true;
                scene.add(p);
            });

            // ── Blue X Cross-Bracing on left & right upright frames ────────
            // Two equal-height X segments per side → clear "×" silhouette
            // Braces run in the Y-Z plane (side face) between front & back posts
            {
                const xFZ  = rz - CD/2 + pOff;   // front post Z
                const xBZ  = rz + CD/2 - pOff;   // back post Z
                const xDZ  = xBZ - xFZ;           // depth span ≈ 1.71
                const xMZ  = (xFZ + xBZ) / 2;    // Z midpoint of frame
                const half = rh / 2;

                [0, half].forEach(y0 => {
                    const y1   = y0 + half;
                    const dY   = y1 - y0;
                    const len  = Math.sqrt(dY * dY + xDZ * xDZ);
                    const ang  = Math.atan2(xDZ, dY);   // tilt from vertical (≈ 20°)
                    const mY   = (y0 + y1) / 2;
                    const brGeo = new THREE.BoxGeometry(0.040, len, 0.040);

                    [rx - CW/2 + pOff, rx + CW/2 - pOff].forEach(xPost => {
                        // Diagonal A: bottom-front → top-back
                        const da = new THREE.Mesh(brGeo, postMat);
                        da.position.set(xPost, mY, xMZ);
                        da.rotation.x = ang;
                        scene.add(da);
                        // Diagonal B: bottom-back → top-front  (together = X shape)
                        const db = new THREE.Mesh(brGeo, postMat);
                        db.position.set(xPost, mY, xMZ);
                        db.rotation.x = -ang;
                        scene.add(db);
                    });
                });
            }

            // ── Orange Load Beams: front + back edge, every level ───────────
            const bGeo = new THREE.BoxGeometry(CW + 0.10, 0.075, 0.060);
            const bFZ  = rz - CD/2 + 0.058;
            const bBZ  = rz + CD/2 - 0.058;
            for (let lv = 0; lv <= rack.total_levels; lv++) {
                [bFZ, bBZ].forEach(bz => {
                    const b = new THREE.Mesh(bGeo, beamMat);
                    b.position.set(rx, lv * CH, bz);
                    scene.add(b);
                });
            }

            // ── Orange Shelf Deck: flat surface at each level ───────────────
            const sfGeo = new THREE.BoxGeometry(CW - 0.10, 0.042, CD - 0.15);
            for (let lv = 0; lv < rack.total_levels; lv++) {
                const sf = new THREE.Mesh(sfGeo, beamMat);
                sf.position.set(rx, lv * CH + 0.024, rz);
                scene.add(sf);
            }

            // ── Rack Number Label ───────────────────────────────────────────
            const lbl = makeSprite('R' + rack.rack_code, 36, '#94a3b8');
            lbl.scale.set(2.2, 0.55, 1);
            lbl.position.set(rx, rh + 1.4, rz);
            scene.add(lbl);

            // ── Cell Status Panels (semi-transparent, WMS colour by status) ─
            rack.cells.forEach(cell => {
                const col = (cell.column ?? 1) - 1;
                const lvl = (cell.level  ?? 1) - 1;

                const mat = new THREE.MeshLambertMaterial({
                    color:       cellHex(cell),
                    emissive:    new THREE.Color(cellEmissive(cell)),
                    transparent: true,
                    opacity:     cellOpacity(cell),
                    side:        THREE.DoubleSide,
                    depthWrite:  false,
                });

                const mesh = new THREE.Mesh(cellPanelGeo, mat);
                mesh.renderOrder = 1;
                mesh.position.set(
                    rx + col * CW,
                    lvl * CH + CH / 2,
                    rz
                );
                mesh.userData = {
                    cellId:   cell.cell_id,
                    cellCode: cell.code,
                    status:   cell.status,
                    util:     cell.utilization,
                };
                scene.add(mesh);
                cellMeshes.push(mesh);

                // ── Low-poly shelf items based on utilization ───────────────
                // Items sit ON the shelf deck surface; panel glow shows status.
                // Geometry & materials are shared (defined above) — no GC churn.
                const nItems = itemCountForCell(cell);
                if (nItems > 0) {
                    const h      = hashCell(cell.code);
                    // Shelf deck top surface Y for this level
                    const shelfY = lvl * CH + 0.066;  // shelf center(0.024) + half-thick(0.021) + pad(0.021)
                    const slots  = ITEM_LAYOUTS[nItems];
                    slots.forEach(([xOff, zOff], si) => {
                        const iType = (h + si * 7) % ITEM_GEOS.length;
                        const ih    = ITEM_H[iType];
                        const im    = new THREE.Mesh(ITEM_GEOS[iType], ITEM_MATS[iType]);
                        im.position.set(
                            rx + col * CW + xOff,
                            shelfY + ih / 2,
                            rz + zOff
                        );
                        // Deterministic Y-rotation so items don't all face the same way
                        im.rotation.y = ((h * 13 + si * 97) % 628) / 100;
                        im.castShadow = true;
                        scene.add(im);
                        itemMeshes.push(im);
                    });
                }
            });
        });
    });
}

// ── Load Data ─────────────────────────────────────────────────────────────
function loadScene(wid) {
    loading.style.display = 'flex';
    clearScene();
    $.getJSON('{{ route("warehouse3d.data") }}', { warehouse_id: wid }, function (zones) {
        buildWarehouse(zones);
        loading.style.display = 'none';
        // Auto-highlight jika ada highlight_cell_id dari URL
        if (INIT_HIGHLIGHT_ID) {
            applyHighlight(new Set([INIT_HIGHLIGHT_ID]), 'ga', 'Cell rekomendasi GA disorot — klik cell untuk detail');
        }
    }).fail(function () {
        loading.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x text-danger"></i><span class="text-danger">Gagal memuat data 3D.</span>';
    });
}

// ── Highlight State ───────────────────────────────────────────────────────
const INIT_HIGHLIGHT_ID  = {{ $highlightCellId ?: 'null' }};
let highlightedMeshes    = [];
let highlightMats        = [];
let highlightOutlines    = [];   // BackSide gold shells for GA outlines

// Geometry for the GA outline shell: same shape as cellPanelGeo but 0.22 m larger all-round
// = effectively full-cell-width box so the gold border is clearly visible
const GA_OUTLINE_GEO = new THREE.BoxGeometry(CW, CH + 0.08, CD - 0.06);

function applyHighlight(ids, reason, label) {
    clearHighlight();

    const isGa   = (reason === 'ga');
    const color  = isGa ? 0xffd700 : 0x00e5ff;
    const emBase = isGa ? new THREE.Color(0.55, 0.38, 0) : new THREE.Color(0, 0.38, 0.55);

    let firstMesh = null;
    cellMeshes.forEach(mesh => {
        if (!ids.has(mesh.userData.cellId)) return;

        mesh.userData._savedMat = mesh.material;

        const mat = new THREE.MeshLambertMaterial({
            color, emissive: emBase.clone(),
            transparent: true, opacity: 0.92,
            side: THREE.DoubleSide, depthWrite: false,
        });
        mesh.material    = mat;
        mesh.renderOrder = 2;

        highlightMats.push(mat);
        highlightedMeshes.push(mesh);

        // ── GA: solid gold outline shell (BackSide = border only, no fill) ───
        // The shell is slightly larger than the cell panel; BackSide culls front
        // faces so only the outer border ring is visible around the panel.
        if (isGa) {
            const outlineMat = new THREE.MeshBasicMaterial({
                color: 0xffd700,
                side: THREE.BackSide,
            });
            const outline = new THREE.Mesh(GA_OUTLINE_GEO, outlineMat);
            outline.position.copy(mesh.position);
            outline.renderOrder = 1;   // render behind panel but above normal cells
            scene.add(outline);
            highlightOutlines.push(outline);
        }

        if (!firstMesh) firstMesh = mesh;
    });

    if (firstMesh) flyToCell(firstMesh);

    // ── Banner ────────────────────────────────────────────────────────────────
    const bannerEl  = document.getElementById('highlightBanner');
    const bannerTx  = document.getElementById('highlightBannerText');
    const bannerBtn = document.getElementById('btnClearHighlight');
    if (isGa) {
        bannerEl.className = 'alert alert-ga py-2 px-3 mb-2 d-flex align-items-center justify-content-between';
        bannerBtn.className = 'btn btn-sm ml-3';
        bannerBtn.style.cssText = 'border:1px solid #ffd700;color:#ffd700;background:transparent;white-space:nowrap';
        bannerTx.innerHTML = `
            <i class="fas fa-star ga-star"></i>
            <strong style="font-size:14px;letter-spacing:.4px;color:#ffd700">CELL REKOMENDASI GA</strong>
            <span style="opacity:.75;font-size:12px;margin-left:8px">${label || ''}</span>`;
    } else {
        bannerEl.className = 'alert alert-info py-2 px-3 mb-2 d-flex align-items-center justify-content-between';
        bannerBtn.className = 'btn btn-sm btn-outline-info ml-3';
        bannerBtn.style.cssText = '';
        bannerTx.innerHTML = '<i class="fas fa-search mr-2"></i>' + (label || 'Cell hasil pencarian disorot');
    }
    bannerEl.style.display = 'flex';

    // ── Legend ────────────────────────────────────────────────────────────────
    const legItem = document.querySelector('.leg-highlight');
    const legDot  = document.getElementById('legHighlightDot');
    const legLbl  = document.getElementById('legHighlightLabel');
    legItem.style.display = '';
    legDot.style.background = isGa ? '#ffd700' : '#00e5ff';
    legLbl.textContent = isGa ? 'Rekomendasi GA' : 'Hasil Pencarian';
}

function clearHighlight() {
    highlightedMeshes.forEach(mesh => {
        if (mesh.userData._savedMat) {
            mesh.material    = mesh.userData._savedMat;
            mesh.renderOrder = 1;
            delete mesh.userData._savedMat;
        }
    });
    highlightedMeshes = [];
    highlightMats     = [];

    // Remove GA outline shells
    highlightOutlines.forEach(m => { scene.remove(m); m.material.dispose(); });
    highlightOutlines = [];

    const bannerEl = document.getElementById('highlightBanner');
    if (bannerEl) bannerEl.style.display = 'none';
    const legItem = document.querySelector('.leg-highlight');
    if (legItem) legItem.style.display = 'none';
}

function flyToCell(mesh) {
    const p = mesh.position;
    camera.position.set(p.x + 12, p.y + 9, p.z + 12);
    controls.target.set(p.x, p.y, p.z);
    controls.update();
}

buildEnvironment();
loadScene({{ $selectedWarehouse->id }});

// ── Raycaster ─────────────────────────────────────────────────────────────
const raycaster = new THREE.Raycaster();
const mouse     = new THREE.Vector2();

function toNDC(e) {
    const r = renderer.domElement.getBoundingClientRect();
    mouse.x =  ((e.clientX - r.left) / r.width)  * 2 - 1;
    mouse.y = -((e.clientY - r.top)  / r.height)  * 2 + 1;
}

renderer.domElement.addEventListener('mousemove', function (e) {
    toNDC(e);
    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(cellMeshes);

    if (hoveredMesh) {
        hoveredMesh.material = hoveredOrigMat;
        hoveredMesh = hoveredOrigMat = null;
    }

    if (hits.length) {
        const m  = hits[0].object;
        hoveredOrigMat = m.material;
        hoveredMesh    = m;
        m.material     = new THREE.MeshLambertMaterial({
            color: 0xfbbf24, emissive: new THREE.Color(0x3d1500),
            transparent: true, opacity: 0.88, side: THREE.DoubleSide, depthWrite: false,
        });

        tooltip.style.display = 'block';
        tooltip.style.left    = (e.offsetX + 16) + 'px';
        tooltip.style.top     = (e.offsetY + 14) + 'px';
        const ud = m.userData;
        tooltip.innerHTML = `<strong style="color:#fbbf24">${ud.cellCode}</strong><br>
            Status: <span style="color:#94a3b8">${ud.status}</span><br>
            Terisi: <span style="color:#94a3b8">${ud.util}%</span>`;
        renderer.domElement.style.cursor = 'pointer';
    } else {
        tooltip.style.display = 'none';
        renderer.domElement.style.cursor = 'default';
    }
});

renderer.domElement.addEventListener('mouseleave', function () {
    tooltip.style.display = 'none';
});

// ── Click → Modal ─────────────────────────────────────────────────────────
const DETAIL_BASE = '{{ rtrim(url("warehouse-3d/cell"), "/") }}';

renderer.domElement.addEventListener('click', function (e) {
    toNDC(e);
    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(cellMeshes);
    if (!hits.length) return;

    const ud = hits[0].object.userData;
    $('#modalCellCode').text(ud.cellCode);
    $('#cellModalBody').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>');
    $('#cellModal').modal('show');

    $.getJSON(DETAIL_BASE + '/' + ud.cellId, function (res) {
        const c = res.cell;
        const uc = c.utilization >= 80 ? 'danger' : c.utilization >= 40 ? 'warning' : 'success';
        let rows = res.stocks.length === 0
            ? '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada stok di cell ini.</td></tr>'
            : res.stocks.map((s, i) => `
                <tr class="${i===0?'table-success':''}">
                    <td class="text-center">${i+1}</td>
                    <td><strong>${s.item_name}</strong>${i===0?'<span class="badge badge-success ml-1" style="font-size:9px">FIFO</span>':''}<br><small class="text-muted">${s.sku}</small></td>
                    <td class="text-center font-weight-bold">${s.quantity.toLocaleString('id')} <small>${s.unit}</small></td>
                    <td class="text-center"><small>${s.inbound_date||'—'}</small></td>
                    <td class="text-center"><small>${s.expiry_date||'—'}</small></td>
                    <td><small>${s.lpn||'—'}</small></td>
                </tr>`).join('');

        $('#cellModalBody').html(`
            <div class="row mx-0 border-bottom pb-3 pt-2">
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Lokasi</small>
                    <div class="font-weight-bold">${c.warehouse} › ${c.zone} › Rak ${c.rack} › ${c.code}</div>
                </div>
                <div class="col-md-2 mb-2">
                    <small class="text-muted">Level / Kolom</small>
                    <div class="font-weight-bold">L${c.level} / K${c.column}</div>
                </div>
                <div class="col-md-2 mb-2">
                    <small class="text-muted">Status</small>
                    <div><span class="badge badge-${c.status==='available'?'success':c.status==='full'?'danger':'warning'} px-2">${c.status}</span></div>
                </div>
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Kapasitas — ${c.utilization}% terpakai</small>
                    <div class="progress mt-1" style="height:8px">
                        <div class="progress-bar bg-${uc}" style="width:${c.utilization}%"></div>
                    </div>
                    <small class="text-muted">${c.capacity_used} / ${c.capacity_max}</small>
                </div>
            </div>
            <div class="p-2">
                <strong class="d-block mb-2"><i class="fas fa-boxes mr-1 text-primary"></i>Isi Stok (FIFO)</strong>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" width="35">#</th>
                                <th>Item</th>
                                <th class="text-center" width="90">Qty</th>
                                <th class="text-center" width="105">Tgl Masuk</th>
                                <th class="text-center" width="105">Kadaluarsa</th>
                                <th width="100">LPN</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`);
    }).fail(function () {
        $('#cellModalBody').html('<div class="text-center text-danger py-3">Gagal memuat detail cell.</div>');
    });
});

// ── Resize ────────────────────────────────────────────────────────────────
window.addEventListener('resize', function () {
    const W = wrap.clientWidth, H = wrap.clientHeight;
    camera.aspect = W / H;
    camera.updateProjectionMatrix();
    renderer.setSize(W, H);
});

// ── Animate ───────────────────────────────────────────────────────────────
(function animate() {
    requestAnimationFrame(animate);
    controls.update();

    // Pulse emissive + opacity untuk highlighted panels
    if (highlightMats.length) {
        const pulse = 0.55 + 0.45 * Math.sin(Date.now() * 0.003);
        highlightMats.forEach(mat => {
            mat.emissiveIntensity = pulse;
            mat.opacity = 0.60 + 0.35 * pulse;
        });
        // Breathe the GA outline shell scale (± 6%) in sync with panel pulse
        const outlineScale = 1.0 + 0.06 * Math.sin(Date.now() * 0.003);
        highlightOutlines.forEach(m => m.scale.setScalar(outlineScale));
    }

    renderer.render(scene, camera);
})();
@endif
</script>
@endpush
