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

/* Camera control buttons (top-left of 3D canvas) */
#camBtnGroup {
    position: absolute;
    top: 12px;
    left: 14px;
    display: flex;
    gap: 6px;
    z-index: 5;
}
.btn-cam {
    background: rgba(13,17,23,.8);
    border: 1px solid #334155;
    color: #94a3b8;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 11px;
    cursor: pointer;
    transition: background .2s, color .2s, border-color .2s;
    white-space: nowrap;
}
.btn-cam:hover { background: rgba(51,65,85,.9); color: #fff; }
.btn-cam.active { background: rgba(20,50,90,.9); border-color: #4fc3f7; color: #4fc3f7; }

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
    <div class="col-6 col-md-2 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner"><h4>{{ $summary['total_racks'] }}</h4><p>Rak</p></div>
            <div class="icon"><i class="fas fa-server"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-2 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner"><h4>{{ $summary['total_cells'] }}</h4><p>Total Cell</p></div>
            <div class="icon"><i class="fas fa-th"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-2 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner"><h4>{{ $summary['used_cells'] }}</h4><p>Terisi</p></div>
            <div class="icon"><i class="fas fa-box"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-2 mb-2">
        <div class="small-box bg-danger mb-0">
            <div class="inner"><h4>{{ $summary['full_cells'] }}</h4><p>Penuh</p></div>
            <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-2 mb-2">
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
        {{-- Section 1: Status cell --}}
        <div style="font-size:10px;font-weight:700;letter-spacing:.5px;color:#94a3b8;margin-bottom:7px">LEGENDA CELL</div>
        <div class="leg-item"><div class="leg-dot" style="background:#00897b"></div> Kosong</div>
        <div class="leg-item"><div class="leg-dot" style="background:#fdd835"></div> Terisi Sebagian</div>
        <div class="leg-item"><div class="leg-dot" style="background:#b71c1c"></div> Penuh</div>
        <div class="leg-item leg-highlight" style="display:none">
            <div class="leg-dot leg-dot-pulse" id="legHighlightDot" style="background:#ffd700;border:1px solid #fff"></div>
            <span id="legHighlightLabel">Rekomendasi GA</span>
        </div>

        {{-- Divider --}}
        <div style="border-top:1px solid #2d3748;margin:9px 0 8px"></div>

        {{-- Section 2: Area fisik gudang --}}
        <div style="font-size:10px;font-weight:700;letter-spacing:.5px;color:#94a3b8;margin-bottom:7px">LEGENDA AREA</div>
        {{-- Rak: split oranye (beam) + biru (upright), keduanya dominan secara visual --}}
        <div class="leg-item">
            <div class="leg-dot" style="background:linear-gradient(135deg,#e65100 50%,#1565c0 50%);border-radius:50%"></div> Rak
        </div>
        {{-- Lemari: krem warm, sesuai platform 0xfff8e1 --}}
        <div class="leg-item">
            <div class="leg-dot" style="background:#bcaaa4;border-radius:50%"></div> Lemari
        </div>
        {{-- Oksigen & Argon: cyan, sesuai 0x80deea --}}
        <div class="leg-item">
            <div class="leg-dot" style="background:#4dd0e1;border-radius:50%"></div> Area Oksigen &amp; Argon
        </div>
        {{-- Rak Ban: amber kuning, readable ver. dari 0xfff176 --}}
        <div class="leg-item">
            <div class="leg-dot" style="background:#f9a825;border-radius:50%"></div> Rak Ban
        </div>
        {{-- V-Belt: exact match 0x90a4ae --}}
        <div class="leg-item">
            <div class="leg-dot" style="background:#90a4ae;border-radius:50%"></div> Area Gantungan V-Belt
        </div>
    </div>

    {{-- Controls hint --}}
    <div id="threeHint">
        <i class="fas fa-mouse mr-1"></i> Kiri: Rotasi &nbsp;·&nbsp; Kanan: Geser<br>
        <i class="fas fa-search-plus mr-1"></i> Scroll: Zoom &nbsp;·&nbsp; Klik: Detail
    </div>

    {{-- Camera control buttons --}}
    <div id="camBtnGroup">
        <button class="btn-cam" id="btnResetCam">
            <i class="fas fa-crosshairs mr-1"></i> Reset Kamera
        </button>
        <button class="btn-cam" id="btnTopView">
            <i class="fas fa-arrows-alt mr-1"></i> Top View
        </button>
        <button class="btn-cam {{ request('expanded') ? 'active' : '' }}" id="btnExpandedView">
            <i class="fas fa-expand-arrows-alt mr-1"></i> Expanded View
        </button>
    </div>

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
const CW  = 2.0;   // standard rack width (X)
const WW  = 14.0;  // wide rack width — 14 m ÷ 7 kolom = 2.0 m/kolom
const CH  = 1.4;   // cell height — 2.0 m lebar ÷ 1.4 m tinggi ≈ 1.43:1 landscape
const CD  = 1.8;   // cell depth  (Z)

// Rak wide = rak utama 1–11 (tampak atas: batang horizontal panjang)
const WIDE_RACK_CODES     = new Set(['1','2','3','4','5','6','7','8','9','10','11']);
// Rak vertikal 12–15 = perpendicular ke rak utama, memanjang di arah Z (tampak atas: batang vertikal)
const VERTICAL_RACK_CODES = new Set(['12','13','14','15']);
const VW = 32.0; // panjang Z rak vertikal (sama dengan span rak 1–11: Z=0 s/d Z=32)

// ── Mspart layout constants ────────────────────────────────────────────────
// MSpart columns use the existing 7 wide rack bays. Grup is data context, not another X subdivision.
const GRUP_X    = { A:0, B:0, C:0, D:0, E:0, F:0, G:0, H:0 };
const KOLOM_XS  = [6, 4, 2, 0, -2, -4, -6];
const MSPART_CW = (WW / 7) - 0.08;
const COLUMN_DETAIL_URL = '{{ route("warehouse3d.column-detail") }}';
const GRUP_DETAIL_URL   = '{{ route("warehouse3d.grup-detail") }}';
const DISPLAY_EXPANDED  = {{ request('expanded') ? 'true' : 'false' }};

const ZONE_LABEL = { A: '#4fc3f7', B: '#ffb74d', C: '#ef9a9a' };

function cellHex(cell) {
    if (cell.status === 'blocked')  return 0x37474f;
    if (cell.status === 'reserved') return 0x7b1fa2;
    if (cell.status === 'full')     return 0xd32f2f;
    if (cell.utilization > 0)       return 0xfdd835;   // kuning terang — jelas beda dari oranye full
    return 0x00897b;   // teal-green: empty slot
}
function cellEmissive(cell) {
    if (cell.status === 'blocked')  return 0x050a0c;
    if (cell.status === 'reserved') return 0x2a0040;
    if (cell.status === 'full')     return 0x4a0000;
    if (cell.utilization > 0)       return 0x3d3000;   // emissive kekuningan
    return 0x000000;   // empty — no emissive glow, sepenuhnya pasif
}
function cellOpacity(cell) {
    if (cell.status === 'blocked')  return 0.55;
    if (cell.status === 'reserved') return 0.72;
    if (cell.status === 'full')     return 0.88;
    if (cell.utilization > 0)       return 0.78;
    return 0.07;   // empty — hampir transparan, struktur rak lebih dominan
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
camera.position.set(1, 55, -8);   // tampak atas saat load pertama

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(wrap.clientWidth, wrap.clientHeight);
renderer.shadowMap.enabled    = true;
renderer.shadowMap.type       = THREE.PCFSoftShadowMap;
wrap.appendChild(renderer.domElement);

// ── Controls ──────────────────────────────────────────────────────────────
const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.target.set(1, 0, 17);
controls.enableDamping  = true;
controls.dampingFactor  = 0.06;
controls.minDistance    = 6;
controls.maxDistance    = 200;
controls.maxPolarAngle  = Math.PI / 2 - 0.02;
controls.update();

// Dua preset kamera
const DEFAULT_CAM = { pos: new THREE.Vector3(1, 55, -8),  target: new THREE.Vector3(1, 0, 17) };
// Z sedikit lebih besar dari target.Z=17 → azimuth terdefinisi (bukan singularitas)
// → screen RIGHT = +X → rak 12-15 (X negatif) tampil di sisi KIRI sesuai denah
const TOPVIEW_CAM = { pos: new THREE.Vector3(1, 65, 15),  target: new THREE.Vector3(1, 0, 17) };

const btnReset   = document.getElementById('btnResetCam');
const btnTopView = document.getElementById('btnTopView');
const btnExpandedView = document.getElementById('btnExpandedView');

function setCamPreset(preset, activeBtn) {
    camera.position.copy(preset.pos);
    controls.target.copy(preset.target);
    controls.update();
    [btnReset, btnTopView].forEach(b => b.classList.remove('active'));
    if (activeBtn) activeBtn.classList.add('active');
}

btnReset.addEventListener('click',   () => setCamPreset(DEFAULT_CAM, btnReset));
btnTopView.addEventListener('click', () => setCamPreset(TOPVIEW_CAM, btnTopView));
btnExpandedView.addEventListener('click', () => {
    const url = new URL(window.location.href);
    if (DISPLAY_EXPANDED) url.searchParams.delete('expanded');
    else url.searchParams.set('expanded', '1');
    window.location.href = url.toString();
});

controls.addEventListener('start', () => {
    [btnReset, btnTopView].forEach(b => b.classList.remove('active'));
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
const floorW = DISPLAY_EXPANDED ? 78 : 52;
const floorD = DISPLAY_EXPANDED ? 98 : 64;
const floorZ = DISPLAY_EXPANDED ? 25 : 22;
const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(floorW, floorD),
    new THREE.MeshLambertMaterial({ color: 0x484848 })
);
floor.rotation.x = -Math.PI / 2;
floor.position.set(7, -0.01, floorZ);
floor.receiveShadow = true;
scene.add(floor);

// ── Text Sprite (small labels) ────────────────────────────────────────────
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

// ── Rack label — besar, selalu menghadap kamera (Sprite = billboard otomatis) ──
function makeRackLabel(text) {
    const W = 320, H = 128;
    const cv = document.createElement('canvas');
    cv.width = W; cv.height = H;
    const ctx = cv.getContext('2d');

    // Background pill gelap
    const r = 28;
    ctx.beginPath();
    ctx.moveTo(r, 0);
    ctx.lineTo(W - r, 0);
    ctx.quadraticCurveTo(W, 0, W, r);
    ctx.lineTo(W, H - r);
    ctx.quadraticCurveTo(W, H, W - r, H);
    ctx.lineTo(r, H);
    ctx.quadraticCurveTo(0, H, 0, H - r);
    ctx.lineTo(0, r);
    ctx.quadraticCurveTo(0, 0, r, 0);
    ctx.closePath();
    ctx.fillStyle = 'rgba(10,20,40,0.82)';
    ctx.fill();

    // Border putih tipis
    ctx.strokeStyle = 'rgba(255,255,255,0.55)';
    ctx.lineWidth = 4;
    ctx.stroke();

    // Teks putih bold
    ctx.font = "bold 76px 'Segoe UI',Arial,sans-serif";
    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, W / 2, H / 2);

    const sp = new THREE.Sprite(new THREE.SpriteMaterial({
        map: new THREE.CanvasTexture(cv),
        transparent: true,
        depthTest: false,
        sizeAttenuation: true,
    }));
    sp.renderOrder = 1000;
    return sp;
}

// ── Area label sprite (colored pill, billboard) ───────────────────────────
function makeAreaLabel(text, bgRgba, fgColor) {
    const W = 320, H = 128, r = 22;
    const cv  = document.createElement('canvas');
    cv.width = W; cv.height = H;
    const ctx = cv.getContext('2d');
    ctx.beginPath();
    ctx.moveTo(r, 0); ctx.lineTo(W-r, 0); ctx.quadraticCurveTo(W, 0, W, r);
    ctx.lineTo(W, H-r); ctx.quadraticCurveTo(W, H, W-r, H);
    ctx.lineTo(r, H); ctx.quadraticCurveTo(0, H, 0, H-r);
    ctx.lineTo(0, r); ctx.quadraticCurveTo(0, 0, r, 0);
    ctx.closePath();
    ctx.fillStyle = bgRgba; ctx.fill();
    ctx.strokeStyle = fgColor; ctx.lineWidth = 3; ctx.stroke();
    ctx.font = "bold 56px 'Segoe UI',Arial,sans-serif";
    ctx.fillStyle = fgColor;
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(text, W / 2, H / 2);
    const sp = new THREE.Sprite(new THREE.SpriteMaterial({
        map: new THREE.CanvasTexture(cv), transparent: true, depthTest: false,
    }));
    sp.renderOrder = 1000;
    return sp;
}

// ── Definisi area non-rak berdasarkan kode rak di DB ─────────────────────
const SPECIAL_AREA_DEFS = {
    '16': { label: 'Lemari',     bg: 'rgba(255,248,225,0.92)', fg: '#5d4037', hex: 0xfff8e1 },
    '17': { label: 'O2 & Argon', bg: 'rgba(128,222,234,0.92)', fg: '#006064', hex: 0x80deea, noCells: true },
    '18': { label: 'Lemari',     bg: 'rgba(255,248,225,0.92)', fg: '#5d4037', hex: 0xfff8e1, noCells: true },
    '19': { label: 'Rak Ban',    bg: 'rgba(255,241,118,0.92)', fg: '#b45309', hex: 0xfff176, noCells: true },
    '20': { label: 'V-Belt',     bg: 'rgba(144,164,174,0.92)', fg: '#263238', hex: 0x90a4ae, noCells: true },
};

// ── Render area non-rak: flat platform berwarna + cell interaktif tipis ───
function buildSpecialArea(rx, rz, def, rack) {
    const AW    = CW;     // footprint X sama seperti rak standar
    const AD    = CD;     // footprint Z sama seperti rak standar
    const baseH = 0.35;   // tinggi platform rendah
    const cellH = 0.22;   // tebal slab cell
    const gap   = 0.04;

    // Platform dasar berwarna
    const base = new THREE.Mesh(
        new THREE.BoxGeometry(AW, baseH, AD),
        new THREE.MeshLambertMaterial({ color: def.hex })
    );
    base.position.set(rx, baseH / 2, rz);
    scene.add(base);

    // Slab cell tipis ditumpuk di atas platform (hanya jika noCells tidak aktif)
    const cGeo = new THREE.BoxGeometry(AW - 0.18, cellH, AD - 0.22);
    let topY = baseH;
    if (!def.noCells) rack.cells.forEach(cell => {
        const mat = new THREE.MeshLambertMaterial({
            color:       cellHex(cell),
            emissive:    new THREE.Color(cellEmissive(cell)),
            transparent: true,
            opacity:     cellOpacity(cell),
            side:        THREE.DoubleSide,
            depthWrite:  false,
        });
        const mesh = new THREE.Mesh(cGeo, mat);
        mesh.renderOrder = 1;
        mesh.position.set(rx, topY + cellH / 2, rz);
        mesh.userData = {
            cellId:    cell.cell_id,
            cellCode:  cell.code,
            status:    cell.status,
            util:      cell.utilization,
            rackW:     AW,
            columnKey: rack.rack_code + '_lv' + (cell.level ?? 1),
            rackCode:  rack.rack_code,
        };
        scene.add(mesh);
        cellMeshes.push(mesh);
        topY += cellH + gap;
    });

    // Label area — skip jika pasangan rak sudah punya label (noLabel)
    if (!def.noLabel) {
        const lbl = makeAreaLabel(def.label, def.bg, def.fg);
        lbl.scale.set(2.8, 1.0, 1);
        lbl.position.set(rx, topY + 1.2, rz);
        scene.add(lbl);
    }
}

// ── Environment: walls + aisle markings ───────────────────────────────────
// Layout baru: rak 1–11 wide di X=-1..7, Z=0..33.8 ; rak belakang Z=36 ; rak depan Z=-3
function buildEnvironment() {
    const wallMat = new THREE.MeshLambertMaterial({ color: 0xcdd0d4, side: THREE.DoubleSide });

    // Dinding belakang. Expanded View uses a larger visual floor only for inspection.
    const backWall = new THREE.Mesh(
        new THREE.BoxGeometry(DISPLAY_EXPANDED ? 70 : 46, 14, 0.3),
        wallMat
    );
    backWall.position.set(9, 7, DISPLAY_EXPANDED ? 52 : 38.5);
    backWall.receiveShadow = true;
    scene.add(backWall);

// Garis aisle kuning (antar pasangan rak): setiap pair dipisah ~4 m
    // Pasangan: (1,2) Z 0-3.3 | (3,4) Z 6.4-9.7 | (5,6) Z 12.8-16.1 |
    //           (7,8) Z 19.2-22.5 | (9,10) Z 25.6-28.9 | 11 Z 32
    const laneMat = new THREE.MeshBasicMaterial({ color: 0xffc107 });
    const laneW   = DISPLAY_EXPANDED ? 20 : 14;   // panjang garis aisle (melintang X)
    [4.4, 10.6, 16.8, 23.0, 30.0].forEach(rawZ => {
        const p = displayRackPosition(3, rawZ);
        const ln = new THREE.Mesh(new THREE.PlaneGeometry(laneW, 0.22), laneMat);
        ln.rotation.x = -Math.PI / 2;
        ln.position.set(p.x, 0.03, p.z);
        scene.add(ln);
    });
}

// ── Shared rack-structure materials & cell panel geometry ─────────────────
// Rack colours: blue uprights + orange beams/shelves (industrial warehouse style)
const postMat     = new THREE.MeshLambertMaterial({ color: 0x1565c0 });   // blue upright
const beamMat     = new THREE.MeshLambertMaterial({ color: 0xe65100 });   // orange beam/shelf
// Cell panel geometries — full-width so "full" cells span wall-to-wall; partial scaled in JS
const cellPanelGeo     = new THREE.BoxGeometry(CW - 0.06, CH - 0.06, CD - 0.15);
const cellPanelGeoWide = new THREE.BoxGeometry(WW - 0.06, CH - 0.06, CD - 0.15);
const cellPanelGeoVert = new THREE.BoxGeometry(CW - 0.06, CH - 0.06, VW - 0.15);

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

// ── Mspart: render 1 panel berwarna per (blok, grup, kolom) ──────────────
// Tampilan agregat — warna mengikuti utilisasi agregat 9 baris di dalamnya
// (yellow=partial, red=full, teal=empty). Detail per-baris muncul saat klik.
function buildMspartCells(rx, rz, rackCode, cells) {
    const geo = new THREE.BoxGeometry(MSPART_CW, CH - 0.08, CD + 0.04);
    const groupLevel = { A:1, B:2, C:3, D:4, E:5, F:6, G:7, H:8 };
    const columns = new Map();

    cells.forEach(cell => {
        const key = `${cell.blok}_${cell.grup}_${cell.kolom ?? 1}`;
        if (!columns.has(key)) {
            columns.set(key, {
                sample: cell,
                cellIds: [],
                totalUsed: 0,
                totalMax: 0,
                filledBaris: 0,
                hasBlocked: false,
                hasReserved: false,
            });
        }
        const col = columns.get(key);
        col.cellIds.push(cell.cell_id);
        col.totalUsed += Number(cell.capacity_used || 0);
        col.totalMax  += Number(cell.capacity_max  || 0);
        if ((cell.utilization ?? 0) > 0) col.filledBaris++;
        if (cell.status === 'blocked')  col.hasBlocked  = true;
        if (cell.status === 'reserved') col.hasReserved = true;
    });

    columns.forEach(col => {
        const cell = col.sample;
        const gx  = GRUP_X[cell.grup]   ?? 0;
        const kx  = KOLOM_XS[(cell.kolom ?? 1) - 1] ?? 0;
        const lv  = (groupLevel[cell.grup] ?? 1) - 1;
        const y   = lv * CH + CH / 2;

        // Aggregate status & utilization untuk 9 baris dalam kolom ini
        const aggUtil = col.totalMax > 0
            ? Math.round((col.totalUsed / col.totalMax) * 100)
            : 0;
        const aggStatus = col.hasBlocked  ? 'blocked'
                       : col.hasReserved  ? 'reserved'
                       : col.filledBaris === 0                  ? 'available'
                       : col.filledBaris >= col.cellIds.length  ? 'full'
                       : 'partial';
        const aggCell = { status: aggStatus, utilization: aggUtil };

        const mat = new THREE.MeshLambertMaterial({
            color:       cellHex(aggCell),
            emissive:    new THREE.Color(cellEmissive(aggCell)),
            transparent: true,
            opacity:     cellOpacity(aggCell),
            side:        THREE.DoubleSide,
            depthWrite:  false,
        });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.renderOrder = 3;
        mesh.position.set(rx + gx + kx, y, rz);
        mesh.userData = {
            cellId:    cell.cell_id,
            cellIds:   col.cellIds,
            cellCode:  cell.code,
            status:    aggStatus,
            util:      aggUtil,
            rackW:     MSPART_CW,
            isMspart:  true,
            columnKey: rackCode + '_' + cell.grup + '_' + (cell.kolom ?? 1),
            rowKey:    rackCode + '_' + cell.grup,
            blok:      cell.blok,
            grup:      cell.grup,
            kolom:     cell.kolom ?? 1,
            baris:     null,
            rowCount:  col.cellIds.length,
            filledRows: col.filledBaris,
        };
        scene.add(mesh);
        cellMeshes.push(mesh);
    });
}

// ── Build Scene ───────────────────────────────────────────────────────────
const cellMeshes = [];
const itemMeshes = [];   // non-interactive item models; cleared alongside cells
let hoveredMeshes = [];  // supports single-cell and column-group hover

function clearScene() {
    if (typeof clearHighlight === 'function') clearHighlight();
    hoveredMeshes.forEach(m => { delete m.userData._savedMat; });
    hoveredMeshes = [];
    cellMeshes.forEach(m => { scene.remove(m); m.material.dispose(); });
    cellMeshes.length = 0;
    itemMeshes.forEach(m => scene.remove(m));
    itemMeshes.length = 0;
}

// ── Posisi label zona [x, z, y] — y opsional, default 0.1 ────────────────
const ZONE_LABEL_POS = {
    'A': [ 12,  11,  0.1],  // aisle antara rak utama (X=10) dan rak vertikal (X=14)
    'B': [ 12,  30,  0.1],  // aisle, sejajar rak 9–11
    'C': [  3,  -9,  2.5],  // tengah antara cluster kiri (X=8-10) dan kanan (X=-4--2)
};

// ── Rak vertikal 12–15: perpendicular ke rak utama, memanjang di arah Z ──────
function buildVerticalRack(rx, rz, rack) {
    const rh   = rack.total_levels * CH;
    const pOff = 0.045;

    // Posisi Z upright: dari rz-VW/2 ke rz+VW/2 setiap 2 m
    const uprightZs = [];
    for (let zi = rz - VW / 2; zi <= rz + VW / 2 + 0.01; zi += 2.0) {
        uprightZs.push(Math.round(zi * 1000) / 1000);
    }

    // Tiang vertikal (posts) di setiap Z upright, di kedua sisi X
    const postGeo = new THREE.BoxGeometry(0.085, rh + 0.40, 0.085);
    uprightZs.forEach(pz => {
        [rx - CW / 2 + pOff, rx + CW / 2 - pOff].forEach(px => {
            const p = new THREE.Mesh(postGeo, postMat);
            p.position.set(px, rh / 2, pz);
            p.castShadow = true;
            scene.add(p);
        });
    });

    // X-bracing hanya di ujung depan dan belakang (arah Z)
    const xFX  = rx - CW / 2 + pOff;
    const xBX  = rx + CW / 2 - pOff;
    const xDX  = xBX - xFX;
    const xMX  = (xFX + xBX) / 2;
    const half = rh / 2;
    [uprightZs[0], uprightZs[uprightZs.length - 1]].forEach(zEnd => {
        [0, half].forEach(y0 => {
            const y1    = y0 + half;
            const dY    = y1 - y0;
            const len   = Math.sqrt(dY * dY + xDX * xDX);
            const ang   = Math.atan2(xDX, dY);
            const mY    = (y0 + y1) / 2;
            const brGeo = new THREE.BoxGeometry(0.040, len, 0.040);
            [ang, -ang].forEach(a => {
                const d = new THREE.Mesh(brGeo, postMat);
                d.position.set(xMX, mY, zEnd);
                d.rotation.z = a;
                scene.add(d);
            });
        });
    });

    // Load beam (X direction) di setiap upright Z, setiap level
    const bGeo = new THREE.BoxGeometry(CW + 0.10, 0.075, 0.060);
    for (let lv = 0; lv <= rack.total_levels; lv++) {
        uprightZs.forEach(pz => {
            const b = new THREE.Mesh(bGeo, beamMat);
            b.position.set(rx, lv * CH, pz);
            scene.add(b);
        });
    }

    // Rail memanjang (Z direction) di sisi kiri & kanan X, setiap level
    const railGeo = new THREE.BoxGeometry(0.060, 0.075, VW + 0.10);
    for (let lv = 0; lv <= rack.total_levels; lv++) {
        [rx - CW / 2 + 0.058, rx + CW / 2 - 0.058].forEach(bx => {
            const r = new THREE.Mesh(railGeo, beamMat);
            r.position.set(bx, lv * CH, rz);
            scene.add(r);
        });
    }

    // Shelf deck (spanning full VW in Z)
    const sfGeo = new THREE.BoxGeometry(CW - 0.10, 0.042, VW - 0.15);
    for (let lv = 0; lv < rack.total_levels; lv++) {
        const sf = new THREE.Mesh(sfGeo, beamMat);
        sf.position.set(rx, lv * CH + 0.024, rz);
        scene.add(sf);
    }

    // Label di atas tengah rak
    const lbl = makeRackLabel('R' + rack.rack_code);
    lbl.scale.set(1.8, 0.72, 1);
    lbl.position.set(rx, rh + 1.8, rz);
    lbl.userData.isRackLabel = true;
    lbl.userData.scaleWide   = false;
    scene.add(lbl);

    // Cell panels (satu panel per level, memanjang di Z = VW)
    rack.cells.forEach(cell => {
        const lvl = (cell.level ?? 1) - 1;
        const mat = new THREE.MeshLambertMaterial({
            color:       cellHex(cell),
            emissive:    new THREE.Color(cellEmissive(cell)),
            transparent: true,
            opacity:     cellOpacity(cell),
            side:        THREE.DoubleSide,
            depthWrite:  false,
        });
        const mesh = new THREE.Mesh(cellPanelGeoVert, mat);
        mesh.renderOrder = 1;
        mesh.position.set(rx, lvl * CH + CH / 2, rz);
        mesh.userData = {
            cellId:    cell.cell_id,
            cellCode:  cell.code,
            status:    cell.status,
            util:      cell.utilization,
            rackW:     CW,
            columnKey: rack.rack_code,
            rackCode:  rack.rack_code,
        };
        scene.add(mesh);
        cellMeshes.push(mesh);

        const nItems = itemCountForCell(cell);
        if (nItems > 0) {
            const h      = hashCell(cell.code);
            const shelfY = lvl * CH + 0.066;
            ITEM_LAYOUTS[nItems].forEach(([xOff, zOff], si) => {
                const iType = (h + si * 7) % ITEM_GEOS.length;
                const ih    = ITEM_H[iType];
                const im    = new THREE.Mesh(ITEM_GEOS[iType], ITEM_MATS[iType]);
                im.position.set(rx + xOff, shelfY + ih / 2, rz + zOff);
                im.rotation.y = ((h * 13 + si * 97) % 628) / 100;
                im.castShadow = true;
                scene.add(im);
                itemMeshes.push(im);
            });
        }
    });
}

// ── Render satu rak (wide atau standar) ──────────────────────────────────
function buildRack(rx, rz, rack) {
    // Area khusus non-rak → delegasi ke buildSpecialArea
    const areaDef = SPECIAL_AREA_DEFS[rack.rack_code];
    if (areaDef) { buildSpecialArea(rx, rz, areaDef, rack); return; }

    // Rak vertikal (12–15) → delegasi ke buildVerticalRack
    if (VERTICAL_RACK_CODES.has(rack.rack_code)) { buildVerticalRack(rx, rz, rack); return; }

    const isWide = WIDE_RACK_CODES.has(rack.rack_code);
    const RW     = isWide ? WW : CW;
    const rh     = rack.total_levels * CH;
    const pOff   = 0.045;

    // Hitung posisi X upright: 7 kolom untuk wide rack, 1 kolom untuk standar
    const xStep    = isWide ? WW / 7 : CW;
    const uprightXs = [];
    for (let xi = rx - RW / 2; xi <= rx + RW / 2 + 0.01; xi += xStep) {
        uprightXs.push(Math.round(xi * 1000) / 1000);
    }

    const postGeo = new THREE.BoxGeometry(0.085, rh + 0.40, 0.085);
    uprightXs.forEach(px => {
        [rz - CD / 2 + pOff, rz + CD / 2 - pOff].forEach(pz => {
            const p = new THREE.Mesh(postGeo, postMat);
            p.position.set(px, rh / 2, pz);
            p.castShadow = true;
            scene.add(p);
        });
    });

    // X-bracing hanya di ujung kiri & kanan rak (bukan tiap kolom tengah)
    const xFZ  = rz - CD / 2 + pOff;
    const xBZ  = rz + CD / 2 - pOff;
    const xDZ  = xBZ - xFZ;
    const xMZ  = (xFZ + xBZ) / 2;
    const half = rh / 2;

    [uprightXs[0], uprightXs[uprightXs.length - 1]].forEach(xPost => {
        [0, half].forEach(y0 => {
            const y1    = y0 + half;
            const dY    = y1 - y0;
            const len   = Math.sqrt(dY * dY + xDZ * xDZ);
            const ang   = Math.atan2(xDZ, dY);
            const mY    = (y0 + y1) / 2;
            const brGeo = new THREE.BoxGeometry(0.040, len, 0.040);
            [ang, -ang].forEach(a => {
                const d = new THREE.Mesh(brGeo, postMat);
                d.position.set(xPost, mY, xMZ);
                d.rotation.x = a;
                scene.add(d);
            });
        });
    });

    // Load beam (orange, depan & belakang, setiap level)
    const bGeo = new THREE.BoxGeometry(RW + 0.10, 0.075, 0.060);
    for (let lv = 0; lv <= rack.total_levels; lv++) {
        [rz - CD / 2 + 0.058, rz + CD / 2 - 0.058].forEach(bz => {
            const b = new THREE.Mesh(bGeo, beamMat);
            b.position.set(rx, lv * CH, bz);
            scene.add(b);
        });
    }

    // Shelf deck (orange, tiap level)
    const sfGeo = new THREE.BoxGeometry(RW - 0.10, 0.042, CD - 0.15);
    for (let lv = 0; lv < rack.total_levels; lv++) {
        const sf = new THREE.Mesh(sfGeo, beamMat);
        sf.position.set(rx, lv * CH + 0.024, rz);
        scene.add(sf);
    }

    // Label nomor rak — ukuran perspektif default; Top View memperbesar via setRackLabelScale()
    const lbl = makeRackLabel('R' + rack.rack_code);
    lbl.scale.set(isWide ? 4.5 : 1.8, isWide ? 0.9 : 0.72, 1);
    lbl.position.set(rx, rh + 1.8, rz);
    lbl.userData.isRackLabel = true;
    lbl.userData.scaleWide   = isWide;
    scene.add(lbl);

    // ── Mspart cells (have blok/grup/kolom/baris): rendered as column panels ─
    const mspartCells  = rack.cells.filter(c => c.grup != null);
    const regularCells = rack.cells.filter(c => c.grup == null);
    if (mspartCells.length > 0) {
        buildMspartCells(rx, rz, rack.rack_code, mspartCells);
    }
    const mspartByGroup = {};
    mspartCells.forEach(c => {
        if (!c.grup) return;
        if (!mspartByGroup[c.grup]) {
            mspartByGroup[c.grup] = { used: 0, max: 0, blocked: 0, reserved: 0 };
        }
        mspartByGroup[c.grup].used += Number(c.capacity_used || 0);
        mspartByGroup[c.grup].max  += Number(c.capacity_max || 0);
        if (c.status === 'blocked')  mspartByGroup[c.grup].blocked++;
        if (c.status === 'reserved') mspartByGroup[c.grup].reserved++;
    });
    const groupByLevel = { 1:'A', 2:'B', 3:'C', 4:'D', 5:'E', 6:'F', 7:'G', 8:'H' };
    function visualCellForRegular(cell) {
        const group = groupByLevel[cell.level ?? 1];
        const agg = group ? mspartByGroup[group] : null;
        if (!agg || agg.max <= 0) return cell;
        const utilization = Math.round((agg.used / agg.max) * 100);
        return {
            ...cell,
            status: agg.blocked > 0 ? 'blocked' : (agg.reserved > 0 ? 'reserved' : (agg.used <= 0 ? 'available' : (agg.used >= agg.max ? 'full' : 'partial'))),
            capacity_used: agg.used,
            capacity_max: agg.max,
            utilization,
        };
    }

    // Cell panels — full geometry, colour & opacity encode status
    const cellGeo = isWide ? cellPanelGeoWide : cellPanelGeo;
    regularCells.forEach(rawCell => {
        const cell = visualCellForRegular(rawCell);
        const lvl = (cell.level ?? 1) - 1;
        const mat = new THREE.MeshLambertMaterial({
            color:       cellHex(cell),
            emissive:    new THREE.Color(cellEmissive(cell)),
            transparent: true,
            opacity:     cellOpacity(cell),
            side:        THREE.DoubleSide,
            depthWrite:  false,
        });
        const mesh = new THREE.Mesh(cellGeo, mat);
        mesh.renderOrder = 1;
        mesh.position.set(rx, lvl * CH + CH / 2, rz);
        mesh.userData = {
            cellId:    cell.cell_id,
            cellCode:  cell.code,
            status:    cell.status,
            util:      cell.utilization,
            rackW:     RW,
            columnKey: rack.rack_code + '_lv' + (cell.level ?? 1),
            rackCode:  rack.rack_code,
        };
        scene.add(mesh);
        cellMeshes.push(mesh);

        // 3D items inside the cell
        if (cell.status !== 'blocked' && (cell.utilization || 0) > 0) {
            const h      = hashCell(cell.code);
            const shelfY = lvl * CH + 0.066;
            const u      = Math.min((cell.utilization || 0) / 100, 1.0);

            if (isWide) {
                // 7 columns at X offsets ±0,±2,±4,±6 relative to rack centre
                // Fill from screen-left (+X) side; number of columns ∝ utilization
                const COL_XS   = [6, 4, 2, 0, -2, -4, -6];
                const colsFill = cell.status === 'full' ? 7 : Math.max(1, Math.ceil(u * 7));
                for (let ci = 0; ci < colsFill; ci++) {
                    const colX  = COL_XS[ci];
                    const twoRow = cell.status === 'full' || u > 0.5;
                    const zOffs  = twoRow ? [-0.22, 0.22] : [0];
                    zOffs.forEach((zOff, ri) => {
                        const iType = (h + ci * 7 + ri * 3) % ITEM_GEOS.length;
                        const ih    = ITEM_H[iType];
                        const im    = new THREE.Mesh(ITEM_GEOS[iType], ITEM_MATS[iType]);
                        im.position.set(rx + colX, shelfY + ih / 2, rz + zOff);
                        im.rotation.y = ((h * 13 + ci * 97 + ri * 31) % 628) / 100;
                        im.castShadow = true;
                        scene.add(im);
                        itemMeshes.push(im);
                    });
                }
            } else {
                // Standard cell (CW = 2 m)
                const nItems = itemCountForCell(cell);
                ITEM_LAYOUTS[nItems].forEach(([xOff, zOff], si) => {
                    const iType = (h + si * 7) % ITEM_GEOS.length;
                    const ih    = ITEM_H[iType];
                    const im    = new THREE.Mesh(ITEM_GEOS[iType], ITEM_MATS[iType]);
                    im.position.set(rx + xOff, shelfY + ih / 2, rz + zOff);
                    im.rotation.y = ((h * 13 + si * 97) % 628) / 100;
                    im.castShadow = true;
                    scene.add(im);
                    itemMeshes.push(im);
                });
            }
        }
    });
}

function displayRackPosition(rx, rz) {
    if (!DISPLAY_EXPANDED) return { x: rx, z: rz };

    // Visual-only operator mode: spread racks around the layout centre so tight aisles
    // are inspectable without changing physical coordinates or stored cell locations.
    const cx = 3;
    const cz = 17;
    return {
        x: cx + (rx - cx) * 1.25,
        z: cz + (rz - cz) * 1.55,
    };
}

function buildWarehouse(areas) {
    areas.forEach(area => {
        const absX = area.pos_x || 0;
        const absZ = area.pos_z;

        // Render setiap rak
        area.racks.forEach(rack => {
            const p = displayRackPosition(absX + rack.pos_x, absZ + rack.pos_z);
            buildRack(p.x, p.z, rack);
        });
    });
}

// ── Load Data ─────────────────────────────────────────────────────────────
function loadScene(wid) {
    loading.style.display = 'flex';
    clearScene();
    $.getJSON('{{ route("warehouse3d.data") }}', { warehouse_id: wid }, function (areas) {
        buildWarehouse(areas);
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

// GA outline shell dibuat per-mesh (ukuran menyesuaikan lebar rak wide vs standar)

function applyHighlight(ids, reason, label) {
    clearHighlight();

    const isGa   = (reason === 'ga');
    const color  = isGa ? 0xffd700 : 0x00e5ff;
    const emBase = isGa ? new THREE.Color(0.55, 0.38, 0) : new THREE.Color(0, 0.38, 0.55);

    let firstMesh = null;
    cellMeshes.forEach(mesh => {
        const meshIds = mesh.userData.cellIds || [mesh.userData.cellId];
        if (!meshIds.some(id => ids.has(id))) return;

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

        // ── GA: gold outline shell (BackSide, ukuran menyesuaikan lebar rak) ─
        if (isGa) {
            const outlineW   = mesh.userData.rackW ?? CW;
            const outlineGeo = new THREE.BoxGeometry(outlineW, CH + 0.08, CD - 0.06);
            const outlineMat = new THREE.MeshBasicMaterial({ color: 0xffd700, side: THREE.BackSide });
            const outline = new THREE.Mesh(outlineGeo, outlineMat);
            outline.position.copy(mesh.position);
            outline.renderOrder = 1;
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

    // Restore all previously hovered meshes
    hoveredMeshes.forEach(m => {
        if (m.userData._savedMat) {
            m.material    = m.userData._savedMat;
            m.renderOrder = 1;
            delete m.userData._savedMat;
        }
    });
    hoveredMeshes = [];

    if (hits.length) {
        const m  = (hits.find(h => h.object.userData.isMspart) || hits[0]).object;
        const ud = m.userData;
        const hoverMat = new THREE.MeshLambertMaterial({
            color: 0xfbbf24, emissive: new THREE.Color(0x3d1500),
            transparent: true, opacity: 0.88, side: THREE.DoubleSide, depthWrite: false,
        });

        if (ud.isMspart) {
            // MSpart: hover the full blok-grup row across the existing K1-K7 shelf bays.
            const rowMeshes = cellMeshes.filter(cm => cm.userData.isMspart && cm.userData.rowKey === ud.rowKey);
            rowMeshes.forEach(cm => {
                cm.userData._savedMat = cm.material;
                cm.material    = hoverMat.clone();
                cm.renderOrder = 2;
                hoveredMeshes.push(cm);
            });
            const activeColumns = rowMeshes.length;
            const filledColumns = rowMeshes.filter(cm => (cm.userData.filledRows ?? 0) > 0).length;
            const rowFromGroup = {A:1, B:2, C:3, D:4, E:5, F:6, G:7, H:8}[ud.grup] || '-';
            tooltip.innerHTML = `
                <strong style="color:#fbbf24">Baris ${ud.blok}-${ud.grup} (R${rowFromGroup})</strong><br>
                <span style="color:#94a3b8">${activeColumns} kolom aktif &nbsp;&middot;&nbsp; ${filledColumns} kolom terisi</span><br>
                <span style="color:#64748b;font-size:10px">Klik untuk melihat isi Baris ${rowFromGroup}</span>`;
        } else if (ud.columnKey) {
            // ── Column hover: highlight all cells in the same column ──────────
            cellMeshes.forEach(cm => {
                if (cm.userData.columnKey !== ud.columnKey) return;
                cm.userData._savedMat = cm.material;
                cm.material    = hoverMat.clone();
                cm.renderOrder = 2;
                hoveredMeshes.push(cm);
            });
            const colCells = cellMeshes.filter(cm => cm.userData.columnKey === ud.columnKey);

            if (colCells.length > 1) {
                // Rak vertikal / multi-sel: tampilkan ringkasan kolom
                const filled = colCells.filter(cm => (cm.userData.util ?? 0) > 0).length;
                tooltip.innerHTML = `
                    <strong style="color:#fbbf24">Rak ${ud.rackCode}</strong><br>
                    <span style="color:#94a3b8">${colCells.length} level · ${filled} terisi</span><br>
                    <span style="color:#64748b;font-size:10px">Klik sel untuk detail</span>`;
            } else {
                // Sel tunggal (wide rack per group, special area)
                tooltip.innerHTML = `
                    <strong style="color:#fbbf24">${ud.cellCode}</strong><br>
                    Status: <span style="color:#94a3b8">${ud.status}</span><br>
                    Terisi: <span style="color:#94a3b8">${ud.util}%</span>`;
            }
        } else {
            // ── Fallback: single-cell hover ───────────────────────────────────
            m.userData._savedMat = m.material;
            m.material    = hoverMat;
            m.renderOrder = 2;
            hoveredMeshes = [m];
            tooltip.innerHTML = `<strong style="color:#fbbf24">${ud.cellCode}</strong><br>
                Status: <span style="color:#94a3b8">${ud.status}</span><br>
                Terisi: <span style="color:#94a3b8">${ud.util}%</span>`;
        }

        tooltip.style.display = 'block';
        tooltip.style.left    = (e.offsetX + 16) + 'px';
        tooltip.style.top     = (e.offsetY + 14) + 'px';
        renderer.domElement.style.cursor = 'pointer';
    } else {
        tooltip.style.display = 'none';
        renderer.domElement.style.cursor = 'default';
    }
});

renderer.domElement.addEventListener('mouseleave', function () {
    tooltip.style.display = 'none';
    hoveredMeshes.forEach(m => {
        if (m.userData._savedMat) {
            m.material    = m.userData._savedMat;
            m.renderOrder = 1;
            delete m.userData._savedMat;
        }
    });
    hoveredMeshes = [];
});

// ── Click → Modal ─────────────────────────────────────────────────────────
const DETAIL_BASE = '{{ rtrim(url("warehouse-3d/cell"), "/") }}';

function showMspartColumnDetail(blok, grup, kolom) {
    const rowFromGroup = {A:1, B:2, C:3, D:4, E:5, F:6, G:7, H:8}[grup] || '-';
    $('#modalCellCode').text(`Blok ${blok} - Grup ${grup} - Baris ${rowFromGroup} - Kolom ${kolom}`);
    $('#cellModalBody').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>');
    $('#cellModal').modal('show');

    $.getJSON(COLUMN_DETAIL_URL, { blok, grup, kolom }, function (res) {
        let rows = res.levels.length === 0
            ? '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data.</td></tr>'
            : res.levels.map(lv => {
                const sc = lv.status === 'full' ? 'danger' : lv.status === 'partial' ? 'warning' : 'success';
                const itemHtml = !lv.stocks.length
                    ? '<small class="text-muted">- kosong -</small>'
                    : lv.stocks.map(s => `<div><strong>${s.item_name}</strong> &nbsp;<small class="text-muted">${s.sku}</small> &nbsp;<span class="font-weight-bold text-success">${s.quantity.toLocaleString('id')} ${s.unit}</span><small class="text-muted ml-1">(masuk: ${s.inbound_date||'-'})</small></div>`).join('');
                return `<tr>
                    <td class="text-center font-weight-bold" style="font-size:16px;color:#6f42c1">${lv.baris}</td>
                    <td><span class="badge badge-light border">${lv.code}</span></td>
                    <td class="text-center"><span class="badge badge-${sc} px-2">${lv.status}</span></td>
                    <td>${itemHtml}</td>
                </tr>`;
            }).join('');

        $('#cellModalBody').html(`
            <div class="row mx-0 border-bottom pb-2 pt-2 bg-light">
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Lokasi Kolom</small>
                    <div class="font-weight-bold">Blok ${res.blok} &rsaquo; Grup ${res.grup} &rsaquo; Baris ${res.baris_rak} &rsaquo; Kolom ${res.kolom}</div>
                </div>
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Baris Rak</small>
                    <div class="font-weight-bold">Baris ${res.baris_rak}</div>
                </div>
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Total Item</small>
                    <div class="font-weight-bold">${res.levels.reduce((t,l)=>t+l.stocks.length,0)} item</div>
                </div>
            </div>
            <div class="p-2">
                <strong class="d-block mb-2"><i class="fas fa-layer-group mr-1 text-primary"></i>Isi per Baris (Level)</strong>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" width="55">Baris</th>
                                <th width="110">Kode Sel</th>
                                <th class="text-center" width="90">Status</th>
                                <th>Isi Item</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`);
    }).fail(function () {
        $('#cellModalBody').html('<div class="text-center text-danger py-3">Gagal memuat detail kolom.</div>');
    });
}

function showMspartRowDetail(blok, grup) {
    $('#modalCellCode').text(`Blok ${blok} - Grup ${grup}`);
    $('#cellModalBody').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>');
    $('#cellModal').modal('show');

    // Ambil detail per-kolom (1-7). Tiap kolom berisi 9 baris (cells).
    const requests = [1, 2, 3, 4, 5, 6, 7].map(kolom =>
        $.getJSON(COLUMN_DETAIL_URL, { blok, grup, kolom })
    );

    $.when.apply($, requests).done(function () {
        const responses = Array.from(arguments).map(arg => Array.isArray(arg) ? arg[0] : arg);

        // Flatten: 7 kolom × 9 baris = 63 cell, masing-masing dengan kode asli dari DB.
        const cells = [];
        responses.forEach(col => {
            col.levels.forEach(lv => {
                cells.push({
                    kolom:  col.kolom,
                    baris:  lv.baris,
                    code:   lv.code,                  // ← code asli DB: "1-A-1-2"
                    status: lv.status,
                    stocks: lv.stocks || [],
                });
            });
        });

        const totalCells   = cells.length;
        const filledCells  = cells.filter(c => c.stocks.length > 0).length;
        const totalItems   = cells.reduce((sum, c) => sum + c.stocks.length, 0);

        // Group rows by kolom untuk pemisah visual antar kolom
        const rows = cells.map((c, i) => {
            const sc = c.status === 'full' ? 'danger' : c.status === 'partial' ? 'warning' : 'success';
            const itemHtml = !c.stocks.length
                ? '<small class="text-muted">- kosong -</small>'
                : c.stocks.map(s => `<div><strong>${s.item_name}</strong> &nbsp;<small class="text-muted">${s.sku}</small> &nbsp;<span class="font-weight-bold text-success">${s.quantity.toLocaleString('id')} ${s.unit}</span></div>`).join('');

            // Header pemisah antar kolom (baris pertama tiap kolom)
            const isFirstOfKolom = c.baris === 1;
            const sep = isFirstOfKolom
                ? `<tr class="bg-light"><td colspan="5" class="py-1 font-weight-bold text-primary"><i class="fas fa-layer-group mr-1"></i>Kolom ${c.kolom}</td></tr>`
                : '';

            return `${sep}<tr>
                <td class="text-center text-muted" width="55">${c.kolom}</td>
                <td class="text-center font-weight-bold" width="55" style="color:#6f42c1">${c.baris}</td>
                <td width="110"><span class="badge badge-light border">${c.code}</span></td>
                <td class="text-center" width="85"><span class="badge badge-${sc} px-2">${c.status}</span></td>
                <td>${itemHtml}</td>
            </tr>`;
        }).join('');

        $('#cellModalBody').html(`
            <div class="row mx-0 border-bottom pb-2 pt-2 bg-light">
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Lokasi</small>
                    <div class="font-weight-bold">Blok ${blok} &rsaquo; Grup ${grup}</div>
                </div>
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Sel Terisi</small>
                    <div class="font-weight-bold">${filledCells} / ${totalCells} sel</div>
                </div>
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Total Item</small>
                    <div class="font-weight-bold">${totalItems} item</div>
                </div>
            </div>
            <div class="p-2">
                <strong class="d-block mb-2"><i class="fas fa-th-large mr-1 text-primary"></i>Isi Grup ${grup} per Kolom × Baris (kode: blok-grup-kolom-baris)</strong>
                <div class="table-responsive" style="max-height:60vh;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light" style="position:sticky;top:0;z-index:2;">
                            <tr>
                                <th class="text-center">Kolom</th>
                                <th class="text-center">Baris</th>
                                <th>Kode Sel</th>
                                <th class="text-center">Status</th>
                                <th>Isi Item</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`);
    }).fail(function () {
        $('#cellModalBody').html('<div class="text-center text-danger py-3">Gagal memuat detail.</div>');
    });
}

renderer.domElement.addEventListener('click', function (e) {
    toNDC(e);
    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(cellMeshes);
    if (!hits.length) return;

    const ud = (hits.find(h => h.object.userData.isMspart) || hits[0]).object.userData;
    $('#cellModalBody').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>');
    $('#cellModal').modal('show');

    if (ud.isMspart && ud.columnKey) {
        showMspartRowDetail(ud.blok, ud.grup);
        return;

        // ── MSpart click: step 1 — tampilkan daftar kolom dalam grup ini ─
        const rowFromGroup = {A:1, B:2, C:3, D:4, E:5, F:6, G:7, H:8}[ud.grup] || '—';
        $('#modalCellCode').text(`Blok ${ud.blok} – Grup ${ud.grup} – Baris ${rowFromGroup}`);

        $.getJSON(GRUP_DETAIL_URL, { blok: ud.blok, grup: ud.grup }, function (res) {
            if (!res.columns || !res.columns.length) {
                $('#cellModalBody').html('<div class="text-center text-muted py-3">Tidak ada kolom aktif.</div>');
                return;
            }

            const colButtons = res.columns.map(col => {
                const uc = col.util_pct >= 80 ? 'danger' : col.util_pct >= 40 ? 'warning' : 'success';
                const fullBadge = col.full  > 0 ? `<span class="badge badge-danger ml-1">${col.full} full</span>`    : '';
                const partBadge = col.partial > 0 ? `<span class="badge badge-warning ml-1">${col.partial} partial</span>` : '';
                return `<button class="btn btn-outline-secondary btn-sm mb-2 mr-2 px-3 py-2 btnKolom text-left"
                            data-blok="${res.blok}" data-grup="${res.grup}" data-kolom="${col.kolom}"
                            style="min-width:130px;border-left:4px solid var(--bs-${uc},#28a745)">
                    <div class="font-weight-bold">${col.label}</div>
                    <small class="text-muted d-block">Baris ${res.baris_rak}</small>
                    <div class="mt-1">
                        <div class="progress" style="height:6px;width:100px">
                            <div class="progress-bar bg-${uc}" style="width:${col.util_pct}%"></div>
                        </div>
                    </div>
                    <small class="text-muted">${col.util_pct}% terpakai &nbsp;·&nbsp; ${col.total} baris</small>
                    ${fullBadge}${partBadge}
                </button>`;
            }).join('');

            $('#cellModalBody').html(`
                <div class="px-3 pt-2 pb-1 bg-light border-bottom">
                    <div class="font-weight-bold"><i class="fas fa-th-large mr-1 text-primary"></i>${res.label}</div>
                    <small class="text-muted">Blok ${res.blok} · Grup ${res.grup} · Baris ${res.baris_rak} · Pilih kolom untuk melihat isi cell:</small>
                </div>
                <div class="p-3">${colButtons}</div>`);

        }).fail(function () {
            $('#cellModalBody').html('<div class="text-center text-danger py-3">Gagal memuat data grup.</div>');
        });

    } else {
        // ── Single-cell click: tampilkan detail satu sel ──────────────────
        $('#modalCellCode').text(ud.cellCode);

        $.getJSON(DETAIL_BASE + '/' + ud.cellId, function (res) {
            const c  = res.cell;
            const uc = c.utilization >= 80 ? 'danger' : c.utilization >= 40 ? 'warning' : 'success';
            const _lvl = ['','A','B','C','D','E','F','G'];
            const dispBlok  = c.blok  != null ? c.blok  : c.rack;
            const dispGrup  = c.grup  != null ? c.grup  : (_lvl[c.level] || c.level);
            const dispKolom = c.kolom != null ? c.kolom : c.column;
            const dispBaris = c.baris_rak != null ? c.baris_rak : (c.baris != null ? c.baris : '—');
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
                        <div class="font-weight-bold">${c.warehouse} › Rak ${c.rack} › ${c.code}</div>
                    </div>
                    <div class="col-md-1 mb-2">
                        <small class="text-muted">Blok</small>
                        <div class="font-weight-bold">${dispBlok}</div>
                    </div>
                    <div class="col-md-1 mb-2">
                        <small class="text-muted">Grup</small>
                        <div class="font-weight-bold">${dispGrup}</div>
                    </div>
                    <div class="col-md-1 mb-2">
                        <small class="text-muted">Kolom</small>
                        <div class="font-weight-bold">${dispKolom}</div>
                    </div>
                    <div class="col-md-1 mb-2">
                        <small class="text-muted">Baris</small>
                        <div class="font-weight-bold">${dispBaris}</div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <small class="text-muted">Status</small>
                        <div><span class="badge badge-${c.status==='available'?'success':c.status==='full'?'danger':'warning'} px-2">${c.status}</span></div>
                    </div>
                    <div class="col-md-2 mb-2">
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
    }
});

// ── MSpart step 2: klik kolom → detail baris ──────────────────────────────
$(document).on('click', '.btnKolom', function () {
    const blok  = $(this).data('blok');
    const grup  = $(this).data('grup');
    const kolom = $(this).data('kolom');

    const rowFromGroup = {A:1, B:2, C:3, D:4, E:5, F:6, G:7, H:8}[grup] || '—';
    $('#modalCellCode').text(`Blok ${blok} - Grup ${grup} - Baris ${rowFromGroup} - Kolom ${kolom}`);
    $('#cellModalBody').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>');

    $.getJSON(COLUMN_DETAIL_URL, { blok, grup, kolom }, function (res) {
        let rows = res.levels.length === 0
            ? '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data.</td></tr>'
            : res.levels.map(lv => {
                const sc = lv.status === 'full' ? 'danger' : lv.status === 'partial' ? 'warning' : 'success';
                const itemHtml = !lv.stocks.length
                    ? '<small class="text-muted">— kosong —</small>'
                    : lv.stocks.map(s => `<div><strong>${s.item_name}</strong> &nbsp;<small class="text-muted">${s.sku}</small> &nbsp;<span class="font-weight-bold text-success">${s.quantity.toLocaleString('id')} ${s.unit}</span><small class="text-muted ml-1">(masuk: ${s.inbound_date||'—'})</small></div>`).join('');
                return `<tr>
                    <td class="text-center font-weight-bold" style="font-size:16px;color:#6f42c1">${lv.baris}</td>
                    <td><span class="badge badge-light border">${lv.code}</span></td>
                    <td class="text-center"><span class="badge badge-${sc} px-2">${lv.status}</span></td>
                    <td>${itemHtml}</td>
                </tr>`;
            }).join('');

        $('#cellModalBody').html(`
            <div class="row mx-0 border-bottom pb-2 pt-2 bg-light">
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Lokasi Kolom</small>
                    <div class="font-weight-bold">Blok ${res.blok} › Grup ${res.grup} › Baris ${res.baris_rak} › Kolom ${res.kolom}</div>
                </div>
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Baris Rak</small>
                    <div class="font-weight-bold">Baris ${res.baris_rak}</div>
                </div>
                <div class="col-md-4 mb-2">
                    <small class="text-muted">Total Item</small>
                    <div class="font-weight-bold">${res.levels.reduce((t,l)=>t+l.stocks.length,0)} item</div>
                </div>
            </div>
            <div class="p-2">
                <strong class="d-block mb-2"><i class="fas fa-layer-group mr-1 text-primary"></i>Isi per Baris (Level)</strong>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" width="55">Baris</th>
                                <th width="110">Kode Sel</th>
                                <th class="text-center" width="90">Status</th>
                                <th>Isi Item</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`);
    }).fail(function () {
        $('#cellModalBody').html('<div class="text-center text-danger py-3">Gagal memuat detail kolom.</div>');
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
