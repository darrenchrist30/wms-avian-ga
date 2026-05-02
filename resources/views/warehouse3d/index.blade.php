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
        <div class="leg-item"><div class="leg-dot" style="background:#2e7d32"></div> Kosong</div>
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
const CG  = 0.09;  // gap between cells

const ZONE_FLOOR = { A: 0x0a2744, B: 0x2d1500, C: 0x2d0a0a };
const ZONE_LABEL = { A: '#4fc3f7', B: '#ffb74d', C: '#ef9a9a' };

function cellHex(cell) {
    if (cell.status === 'blocked')  return 0x37474f;
    if (cell.status === 'reserved') return 0x4a148c;
    if (cell.status === 'full')     return 0xb71c1c;
    if (cell.utilization > 0)       return 0xf57f17;
    return 0x2e7d32;
}
function cellEmissive(cell) {
    if (cell.status === 'blocked')  return 0x050a0c;
    if (cell.status === 'reserved') return 0x150026;
    if (cell.status === 'full')     return 0x2d0000;
    if (cell.utilization > 0)       return 0x3d1f00;
    return 0x072207;
}

// ── Scene ─────────────────────────────────────────────────────────────────
const wrap      = document.getElementById('threeWrap');
const loading   = document.getElementById('threeLoading');
const tooltip   = document.getElementById('threeTooltip');

const scene    = new THREE.Scene();
scene.background = new THREE.Color(0x0d1117);
scene.fog        = new THREE.FogExp2(0x0d1117, 0.006);

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
scene.add(new THREE.AmbientLight(0xffffff, 0.38));
scene.add(new THREE.HemisphereLight(0x334466, 0x1a1a2e, 0.35));

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

// ── Floor ─────────────────────────────────────────────────────────────────
const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(42, 70),
    new THREE.MeshLambertMaterial({ color: 0x111827 })
);
floor.rotation.x = -Math.PI / 2;
floor.position.set(13, -0.02, -19);
floor.receiveShadow = true;
scene.add(floor);
const gridHelper = new THREE.GridHelper(70, 35, 0x1e293b, 0x1e293b);
gridHelper.position.set(13, 0.001, -19);
scene.add(gridHelper);

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

// ── Shared geometries ─────────────────────────────────────────────────────
const cellGeo = new THREE.BoxGeometry(CW - CG*2, CH - CG*2, CD - CG*2);
const postMat = new THREE.MeshLambertMaterial({ color: 0x334155 });
const barMat  = new THREE.MeshLambertMaterial({ color: 0x475569 });

// ── Build Scene ───────────────────────────────────────────────────────────
const cellMeshes = [];
let hoveredMesh = null, hoveredOrigMat = null;

function clearScene() {
    // Wipe highlight state before disposing meshes (avoids stale material refs)
    if (typeof highlightedMeshes !== 'undefined') {
        highlightedMeshes = [];
        highlightMats     = [];
        const b = document.getElementById('highlightBanner');
        if (b) b.style.display = 'none';
        const l = document.querySelector('.leg-highlight');
        if (l) l.style.display = 'none';
    }
    cellMeshes.forEach(m => { scene.remove(m); m.material.dispose(); });
    cellMeshes.length = 0;
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

        zone.racks.forEach(rack => {
            const rx = absX + rack.pos_x;
            const rz = absZ + rack.pos_z;
            const rh = rack.total_levels * CH;

            // Vertical posts
            [-CW / 2 + 0.06, CW / 2 - 0.06].forEach(ox => {
                const post = new THREE.Mesh(new THREE.BoxGeometry(0.07, rh + 0.2, 0.07), postMat);
                post.position.set(rx + ox, rh / 2, rz);
                scene.add(post);
            });

            // Horizontal shelf bars
            for (let lv = 0; lv <= rack.total_levels; lv++) {
                const bar = new THREE.Mesh(new THREE.BoxGeometry(CW + 0.04, 0.05, 0.07), barMat);
                bar.position.set(rx, lv * CH, rz);
                scene.add(bar);
            }

            // Rack label
            const lbl = makeSprite('R' + rack.rack_code, 36, '#94a3b8');
            lbl.scale.set(2.2, 0.55, 1);
            lbl.position.set(rx, rh + 1.4, rz);
            scene.add(lbl);

            // Cells
            rack.cells.forEach(cell => {
                const col = (cell.column ?? 1) - 1;
                const lvl = (cell.level  ?? 1) - 1;

                const mat = new THREE.MeshLambertMaterial({
                    color:    cellHex(cell),
                    emissive: new THREE.Color(cellEmissive(cell)),
                });

                const mesh = new THREE.Mesh(cellGeo, mat);
                mesh.position.set(
                    rx + col * CW,
                    lvl * CH + CH / 2,
                    rz
                );
                mesh.castShadow    = true;
                mesh.receiveShadow = true;
                mesh.userData = {
                    cellId:   cell.cell_id,
                    cellCode: cell.code,
                    status:   cell.status,
                    util:     cell.utilization,
                };
                scene.add(mesh);
                cellMeshes.push(mesh);
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

function applyHighlight(ids, reason, label) {
    clearHighlight();

    const isGa   = (reason === 'ga');
    const color   = isGa ? 0xffd700 : 0x00e5ff;   // gold for GA, cyan for search
    const emBase  = isGa ? new THREE.Color(0.4, 0.28, 0) : new THREE.Color(0, 0.35, 0.5);

    let firstMesh = null;
    cellMeshes.forEach(mesh => {
        if (!ids.has(mesh.userData.cellId)) return;

        mesh.userData._savedMat   = mesh.material;
        mesh.userData._savedScale = mesh.scale.y;

        const mat = new THREE.MeshLambertMaterial({ color, emissive: emBase.clone() });
        mesh.material = mat;
        mesh.scale.y  = 1.25;

        highlightMats.push(mat);
        highlightedMeshes.push(mesh);
        if (!firstMesh) firstMesh = mesh;
    });

    if (firstMesh) flyToCell(firstMesh);

    // Banner
    const bannerEl = document.getElementById('highlightBanner');
    const bannerTx = document.getElementById('highlightBannerText');
    const bannerBtn = document.getElementById('btnClearHighlight');
    if (isGa) {
        bannerEl.className = 'alert alert-warning py-2 px-3 mb-2 d-flex align-items-center justify-content-between';
        bannerBtn.className = 'btn btn-sm btn-outline-warning ml-3';
        bannerTx.innerHTML  = '<i class="fas fa-star mr-2" style="color:#856404"></i>' + (label || 'Cell rekomendasi GA disorot');
    } else {
        bannerEl.className = 'alert alert-info py-2 px-3 mb-2 d-flex align-items-center justify-content-between';
        bannerBtn.className = 'btn btn-sm btn-outline-info ml-3';
        bannerTx.innerHTML  = '<i class="fas fa-search mr-2"></i>' + (label || 'Cell hasil pencarian disorot');
    }
    bannerEl.style.display = 'flex';

    // Legend
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
            mesh.material      = mesh.userData._savedMat;
            mesh.scale.y       = mesh.userData._savedScale ?? 1;
            delete mesh.userData._savedMat;
            delete mesh.userData._savedScale;
        }
    });
    highlightedMeshes = [];
    highlightMats     = [];

    document.getElementById('highlightBanner').style.display = 'none';
    document.querySelector('.leg-highlight').style.display    = 'none';
}

function flyToCell(mesh) {
    const p = mesh.position;
    camera.position.set(p.x + 12, p.y + 9, p.z + 12);
    controls.target.set(p.x, p.y, p.z);
    controls.update();
}

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
        m.material     = new THREE.MeshLambertMaterial({ color: 0xfbbf24, emissive: new THREE.Color(0x664400) });

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

    // Pulse emissive untuk highlighted cells (range [0.1 – 1.0])
    if (highlightMats.length) {
        const pulse = 0.55 + 0.45 * Math.sin(Date.now() * 0.003);
        highlightMats.forEach(mat => mat.emissiveIntensity = pulse);
    }

    renderer.render(scene, camera);
})();
@endif
</script>
@endpush
