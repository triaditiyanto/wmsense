/**
 * Peta Gudang 3D (resources/views/inventory/map.blade.php). Three.js lokal (public/vendor/three), konfigurasi window.WMAP.
 *
 * Tata letak dihitung dari master lokasi (tanpa gambar CAD):
 *   - lokasi ber-aisle  -> baris rak: aisle = baris (sumbu Z), bay = kolom (X), level = tingkat (Y), position = slot dalam bay.
 *     Ukuran slot dari kelas lokasi (panjang x lebar x tinggi cm), default rak pallet 120 x 100 x 150.
 *   - lokasi tanpa aisle -> area lantai di depan rak, dikelompokkan per fungsi (dock inbound, staging, dock outbound, ...).
 * Objek dibuat dengan InstancedMesh (ribuan lokasi tetap ringan). Klik slot -> panel isi lokasi (JSON urls.location).
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const C = window.WMAP;
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ------------------------------------------------------------------ konstanta tata letak (meter)
const CORRIDOR = 3.2;      // lebar lorong antar baris rak
const UPRIGHT = 0.1;       // tebal tiang rak
const BEAM = 0.12;         // tinggi balok
const FLOOR_GAP = 0.8;     // jarak antar petak lantai
const FN_ORDER = ['RECEIVING', 'QC', 'QUARANTINE', 'STAGING_OUT', 'SHIPPING', 'PICK_FACE', 'STORAGE'];
const FN_AREA = { RECEIVING: 'Dock Inbound', SHIPPING: 'Dock Outbound', STAGING_OUT: 'Staging Outbound', QC: 'Area QC', QUARANTINE: 'Karantina', PICK_FACE: 'Pick Face', STORAGE: 'Lantai Simpan' };
const FN_TILE = { RECEIVING: '#ffd60a', SHIPPING: '#5ac8fa', STAGING_OUT: '#64d2ff', QC: '#bf5af2', QUARANTINE: '#ff6961' };

// ------------------------------------------------------------------ warna per mode
const COL = {
    normal: '#c9a27a', exp: '#ff3b30', soon: '#ff9f0a', blocked: '#af52de', reserved: '#0a84ff',
    full: '#ff3b30', high: '#ff9f0a', part: '#34c759', dim: '#d5dbe4', mixed: '#8e8e93',
};
const PALETTE = ['#007aff', '#ff9500', '#34c759', '#af52de', '#ff2d55', '#5ac8fa', '#ffcc00', '#a2845e', '#30b0c7', '#5856d6'];
const MODES = {
    stock: {
        color: (l) => (l.blocked ? COL.blocked : l.exp_days !== null && l.exp_days < 0 ? COL.exp : l.exp_days !== null && l.exp_days <= 30 ? COL.soon : COL.normal),
        legend: () => [[COL.normal, 'Normal'], [COL.soon, 'Expired <= 30 hari'], [COL.exp, 'Kedaluwarsa'], [COL.blocked, 'Rusak / diblok'], [COL.reserved, 'Dipesan putaway', true]],
    },
    fill: {
        color: (l) => (l.full || (l.percent ?? 0) >= 100 ? COL.full : (l.percent ?? 0) >= 85 ? COL.high : COL.part),
        legend: () => [[COL.part, 'Terisi sebagian'], [COL.high, 'Hampir penuh (>= 85%)'], [COL.full, 'Penuh'], [COL.reserved, 'Dipesan putaway', true]],
    },
    owner: { cat: (l) => l.owners },
    item: { cat: (l) => l.skus },
    age: {
        color: (l) => (l.age_days === null ? COL.mixed : l.age_days <= 7 ? '#34c759' : l.age_days <= 30 ? '#a3d65c' : l.age_days <= 90 ? '#ffcc00' : '#ff3b30'),
        legend: () => [['#34c759', '0-7 hari'], ['#a3d65c', '8-30 hari'], ['#ffcc00', '31-90 hari'], ['#ff3b30', '> 90 hari']],
    },
};

// ------------------------------------------------------------------ scene
const stage = $('mapStage');
const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
renderer.domElement.className = 'o-map-canvas';
stage.prepend(renderer.domElement);

const scene = new THREE.Scene();
scene.background = new THREE.Color('#e8eef7');
const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 3000);
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.08;
controls.maxPolarAngle = Math.PI / 2 - 0.04;   // tidak menembus lantai
controls.minDistance = 2;
controls.maxDistance = 400;

scene.add(new THREE.HemisphereLight('#ffffff', '#aeb9cc', 1.15));
const sun = new THREE.DirectionalLight('#ffffff', 1.6);
sun.castShadow = true;
sun.shadow.mapSize.set(2048, 2048);
sun.shadow.bias = -0.0004;
scene.add(sun, sun.target);

const unitBox = new THREE.BoxGeometry(1, 1, 1).translate(0, 0.5, 0);   // alas di y = 0
const dummy = new THREE.Object3D();
const tmpColor = new THREE.Color();

let world = null;          // THREE.Group seluruh gudang (dibuat ulang saat muat data)
let data = null;           // respons urls.data
let slots = [];            // per lokasi: {loc, x, y, z, w, h, d, kind, cargo: [instance idx], ...}
let meshes = {};           // cargo, pallet, empty, ghost, hit, pins
let labels = null;         // grup sprite label
let bbox = new THREE.Box3();
let selected = null;
let matches = null;        // Set index slot hasil cari (null = tidak mencari)
let catColors = new Map(); // kategori (owner / sku) -> warna
const state = {
    mode: 'stock', level: 0, onlyFilled: false, showLabels: true, view: '3d',
};
try { Object.assign(state, JSON.parse(localStorage.getItem('wmap:prefs') || '{}')); } catch { /* */ }
const savePrefs = () => { try { localStorage.setItem('wmap:prefs', JSON.stringify({ mode: state.mode, showLabels: state.showLabels })); } catch { /* */ } };

// ================================================================== data
async function load(keepCamera = false) {
    $('mapLoading').hidden = false;
    try {
        const res = await fetch(`${C.urls.data}?warehouse=${C.warehouse}`, { headers: { Accept: 'application/json' } });
        if (res.status === 401 || res.status === 419) { location.reload(); return; }   // sesi habis -> ke halaman login
        if (!res.ok) throw new Error(res.status);
        data = await res.json();
    } catch {
        $('mapLoading').innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Gagal memuat data gudang. Coba muat ulang.';
        return;
    }
    build();
    if (!keepCamera) setView(state.view, false);
    $('mapLoading').hidden = true;
    const focus = new URLSearchParams(location.search).get('focus');
    if (focus && !keepCamera) {
        const i = slots.findIndex((s) => s.loc.code.toUpperCase() === focus.toUpperCase());
        if (i >= 0) select(i, true);
    }
    if (selected !== null && keepCamera) select(selected, false);
}

// ================================================================== tata letak
const natural = (a, b) => String(a ?? '').localeCompare(String(b ?? ''), 'id', { numeric: true });
const m = (cm, def) => (cm ? cm / 100 : def);

function layout(locations) {
    const out = [];
    const rows = [];
    const racks = locations.filter((l) => l.aisle && l.bay && l.fn !== 'VIRTUAL');
    const floors = locations.filter((l) => !(l.aisle && l.bay) && l.fn !== 'VIRTUAL');

    // ---- baris rak per zona + aisle
    const groups = new Map();
    racks.forEach((l) => { const k = `${l.zone}|${l.aisle}`; if (!groups.has(k)) groups.set(k, []); groups.get(k).push(l); });
    const keys = [...groups.keys()].sort(natural);
    let z = 0;
    let prevZone = null;
    keys.forEach((key) => {
        const list = groups.get(key);
        const [zone, aisle] = key.split('|');
        if (prevZone !== null && zone !== prevZone) z += 2;   // jeda antar zona
        prevZone = zone;
        const shelving = list.every((l) => l.storage === 'shelving');
        const slotW = Math.max(...list.map((l) => m(l.dim[0], shelving ? 1.0 : 1.2)));
        const slotD = Math.max(...list.map((l) => m(l.dim[1], shelving ? 0.5 : 1.0)));
        const bays = [...new Set(list.map((l) => l.bay))].sort(natural);
        const levels = [...new Set(list.map((l) => l.level || 1))].sort((a, b) => a - b);
        const posByBay = {};
        list.forEach((l) => { (posByBay[l.bay] ??= new Set()).add(l.position || ''); });
        const nPos = Math.max(...Object.values(posByBay).map((s) => s.size));
        const bayW = nPos * slotW + UPRIGHT + 0.06;
        // tinggi per level = tinggi slot tertinggi di level tsb + balok
        const levelY = {};
        let y = 0.12;
        levels.forEach((lv) => {
            levelY[lv] = y;
            const h = Math.max(...list.filter((l) => (l.level || 1) === lv).map((l) => m(l.dim[2], shelving ? 0.4 : 1.5)));
            y += h + BEAM + 0.1;
        });
        const top = y;
        const row = { zone, aisle, z, depth: slotD, length: bays.length * bayW, bays, bayW, levelY, levels, top, slotW, zoneName: list[0].zone_name, shelving };
        rows.push(row);

        list.forEach((l) => {
            const bi = bays.indexOf(l.bay);
            const pos = [...posByBay[l.bay]].sort(natural);
            const pi = Math.max(0, pos.indexOf(l.position || ''));
            const lv = l.level || 1;
            const h = (levels.indexOf(lv) < levels.length - 1 ? levelY[levels[levels.indexOf(lv) + 1]] : top) - levelY[lv] - BEAM - 0.1;
            out.push({
                loc: l, kind: 'rack', row,
                x: bi * bayW + UPRIGHT + 0.03 + pi * slotW + slotW / 2, y: levelY[lv] + BEAM, z: z + slotD / 2,
                w: slotW, h, d: slotD,
            });
        });
        z += slotD + CORRIDOR;
    });

    // ---- area lantai di depan rak (z negatif), dikelompokkan per fungsi
    const fgroups = new Map();
    floors.forEach((l) => { if (!fgroups.has(l.fn)) fgroups.set(l.fn, []); fgroups.get(l.fn).push(l); });
    const fkeys = [...fgroups.keys()].sort((a, b) => (FN_ORDER.indexOf(a) + 99 * (FN_ORDER.indexOf(a) < 0)) - (FN_ORDER.indexOf(b) + 99 * (FN_ORDER.indexOf(b) < 0)));
    const areas = [];
    let x = 0;
    let maxTileD = 0;
    fkeys.forEach((fn) => {
        const list = fgroups.get(fn).sort((a, b) => natural(a.code, b.code));
        const start = x;
        list.forEach((l) => {
            const w = m(l.dim[0], 3.0);
            const d = m(l.dim[1], 3.0);
            maxTileD = Math.max(maxTileD, d);
            out.push({ loc: l, kind: 'floor', x: x + w / 2, y: 0, z: 0, w, h: 1.6, d, fn });
            x += w + FLOOR_GAP;
        });
        areas.push({ fn, x0: start, x1: x - FLOOR_GAP, label: FN_AREA[fn] || fn });
        x += 2.4;
    });
    const floorZ = -(CORRIDOR + 0.6) - maxTileD / 2;
    out.filter((s) => s.kind === 'floor').forEach((s) => { s.z = floorZ; });

    // rata tengah: area lantai di tengah lebar rak
    const rackLen = Math.max(0, ...rows.map((r) => r.length));
    const floorLen = Math.max(0, x - 2.4);
    const shift = (rackLen - floorLen) / 2;
    out.filter((s) => s.kind === 'floor').forEach((s) => { s.x += shift; });
    areas.forEach((a) => { a.x0 += shift; a.x1 += shift; });

    return { slots: out, rows, areas, floorZ, maxTileD };
}

// ================================================================== bangun scene
function instanced(geo, mat, count, { shadow = true } = {}) {
    const mesh = new THREE.InstancedMesh(geo, mat, Math.max(1, count));
    mesh.count = count;
    mesh.castShadow = shadow;
    mesh.receiveShadow = true;
    mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
    return mesh;
}
function place(mesh, i, x, y, z, w, h, d) {
    dummy.position.set(x, y, z);
    dummy.scale.set(w, h, d);
    dummy.rotation.set(0, 0, 0);
    dummy.updateMatrix();
    mesh.setMatrixAt(i, dummy.matrix);
}

function textSprite(text, { size = 0.55, color = '#1d2433', bg = 'rgba(255,255,255,.88)', bold = true } = {}) {
    const pad = 18;
    const fontPx = 64;
    const cv = document.createElement('canvas');
    const ctx = cv.getContext('2d');
    ctx.font = `${bold ? 700 : 500} ${fontPx}px -apple-system, "Segoe UI", Roboto, sans-serif`;
    const w = Math.ceil(ctx.measureText(text).width) + pad * 2;
    cv.width = w; cv.height = fontPx + pad * 1.4;
    ctx.font = `${bold ? 700 : 500} ${fontPx}px -apple-system, "Segoe UI", Roboto, sans-serif`;
    if (bg) {
        ctx.fillStyle = bg;
        const r = 22;
        ctx.beginPath(); ctx.roundRect(0, 0, cv.width, cv.height, r); ctx.fill();
    }
    ctx.fillStyle = color;
    ctx.textBaseline = 'middle';
    ctx.fillText(text, pad, cv.height / 2 + 2);
    const tex = new THREE.CanvasTexture(cv);
    tex.colorSpace = THREE.SRGBColorSpace;
    tex.anisotropy = 4;
    const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, depthWrite: false, transparent: true }));
    sp.scale.set(size * (cv.width / cv.height), size, 1);
    sp.renderOrder = 10;
    return sp;
}

function disposeWorld() {
    if (!world) return;
    world.traverse((o) => {
        if (o.geometry && o.geometry !== unitBox) o.geometry.dispose();
        if (o.material) [].concat(o.material).forEach((mt) => { mt.map?.dispose(); mt.dispose(); });
    });
    scene.remove(world);
}

function build() {
    disposeWorld();
    world = new THREE.Group();
    scene.add(world);
    const L = layout(data.locations);
    slots = L.slots;

    // ---- batas gudang
    bbox = new THREE.Box3();
    slots.forEach((s) => {
        bbox.expandByPoint(new THREE.Vector3(s.x - s.w / 2, 0, s.z - s.d / 2));
        bbox.expandByPoint(new THREE.Vector3(s.x + s.w / 2, (s.row?.top ?? 2), s.z + s.d / 2));
    });
    if (bbox.isEmpty()) bbox.set(new THREE.Vector3(-5, 0, -5), new THREE.Vector3(5, 3, 5));
    const margin = 3;
    const minX = bbox.min.x - margin; const maxX = bbox.max.x + margin;
    const minZ = bbox.min.z - 1.8; const maxZ = bbox.max.z + margin;
    const W = maxX - minX; const D = maxZ - minZ;
    const cx = (minX + maxX) / 2; const cz = (minZ + maxZ) / 2;
    const wallH = Math.max(6, bbox.max.y + 1.5);

    // lantai + grid
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(W, D), new THREE.MeshStandardMaterial({ color: '#dde4ee', roughness: 0.95 }));
    floor.rotation.x = -Math.PI / 2;
    floor.position.set(cx, 0, cz);
    floor.receiveShadow = true;
    world.add(floor);
    const grid = new THREE.GridHelper(Math.ceil(Math.max(W, D)), Math.ceil(Math.max(W, D)), '#c3cddb', '#d3dbe7');
    grid.position.set(cx, 0.003, cz);
    grid.material.transparent = true; grid.material.opacity = 0.6;
    world.add(grid);
    // marka lorong (garis kuning di antara baris rak)
    const laneMat = new THREE.MeshBasicMaterial({ color: '#ffcc00' });
    L.rows.forEach((r) => {
        [r.z - 0.35, r.z + r.depth + 0.35].forEach((zz) => {
            const lane = new THREE.Mesh(new THREE.PlaneGeometry(r.length + 0.4, 0.08), laneMat);
            lane.rotation.x = -Math.PI / 2;
            lane.position.set(r.length / 2, 0.006, zz);
            world.add(lane);
        });
    });

    // dinding kaca
    const wallMat = new THREE.MeshStandardMaterial({ color: '#ffffff', transparent: true, opacity: 0.22, roughness: 0.2, depthWrite: false, side: THREE.DoubleSide });
    const edgeMat = new THREE.LineBasicMaterial({ color: '#9fb0c8' });
    [[cx, minZ, W, 0], [cx, maxZ, W, 0], [minX, cz, D, Math.PI / 2], [maxX, cz, D, Math.PI / 2]].forEach(([x, z, len, rot]) => {
        const wall = new THREE.Mesh(new THREE.PlaneGeometry(len, wallH), wallMat);
        wall.position.set(x, wallH / 2, z);
        wall.rotation.y = rot;
        world.add(wall);
        const edge = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.PlaneGeometry(len, wallH)), edgeMat);
        edge.position.copy(wall.position); edge.rotation.copy(wall.rotation);
        world.add(edge);
    });

    // ---- rak: tiang & balok
    const uprights = []; const beams = [];
    L.rows.forEach((r) => {
        for (let b = 0; b <= r.bays.length; b++) {
            const x = b * r.bayW + UPRIGHT / 2;
            uprights.push([x, 0, r.z + UPRIGHT / 2, UPRIGHT, r.top, UPRIGHT], [x, 0, r.z + r.depth - UPRIGHT / 2, UPRIGHT, r.top, UPRIGHT]);
        }
        r.levels.forEach((lv) => {
            const y = r.levelY[lv];
            beams.push([r.length / 2 + UPRIGHT / 2, y, r.z + 0.04, r.length, BEAM, 0.06], [r.length / 2 + UPRIGHT / 2, y, r.z + r.depth - 0.04, r.length, BEAM, 0.06]);
        });
    });
    const upM = instanced(unitBox, new THREE.MeshStandardMaterial({ color: '#1f5fd6', metalness: 0.35, roughness: 0.45 }), uprights.length);
    uprights.forEach((u, i) => place(upM, i, ...u));
    const bmM = instanced(unitBox, new THREE.MeshStandardMaterial({ color: '#ff8a00', metalness: 0.25, roughness: 0.5 }), beams.length);
    beams.forEach((u, i) => place(bmM, i, ...u));
    world.add(upM, bmM);

    // ---- petak lantai (dock, staging, ...)
    const floorSlots = slots.filter((s) => s.kind === 'floor');
    const tileM = instanced(unitBox, new THREE.MeshStandardMaterial({ color: '#ffffff', transparent: true, opacity: 0.75, roughness: 0.9 }), floorSlots.length, { shadow: false });
    floorSlots.forEach((s, i) => {
        place(tileM, i, s.x, 0.004, s.z, s.w, 0.02, s.d);
        tileM.setColorAt(i, tmpColor.set(FN_TILE[s.fn] || '#c9d3e0'));
    });
    world.add(tileM);
    // pintu dock di dinding depan
    const doorMat = new THREE.MeshStandardMaterial({ color: '#4a5568', roughness: 0.6 });
    floorSlots.filter((s) => s.fn === 'RECEIVING' || s.fn === 'SHIPPING').forEach((s) => {
        const door = new THREE.Mesh(new THREE.PlaneGeometry(Math.min(3, s.w), 3.4), doorMat);
        door.position.set(s.x, 1.7, minZ + 0.02);
        world.add(door);
    });

    // ---- muatan (pallet + kardus) & slot kosong
    const cargo = []; const empties = []; const ghosts = [];
    slots.forEach((s, si) => {
        s.cargo = []; s.empty = -1; s.ghost = -1;
        const l = s.loc;
        const filled = l.qty > 0;
        if (s.kind === 'rack') {
            if (filled) {
                const ratio = l.percent !== null ? Math.max(0.35, Math.min(1, l.percent / 100)) : 0.8;
                const ch = Math.max(0.15, s.h * (s.row.shelving ? 0.7 : 0.88) * ratio);
                s.cargo.push(cargo.length);
                cargo.push({ si, x: s.x, y: s.y, z: s.z, w: s.w * 0.86, h: ch, d: s.d * 0.9, pallet: !s.row.shelving });
            } else {
                s.empty = empties.length;
                empties.push({ si, x: s.x, y: s.y + 0.005, z: s.z, w: s.w * 0.92, h: 0.02, d: s.d * 0.92 });
                if (l.reserved) { s.ghost = ghosts.length; ghosts.push({ si, x: s.x, y: s.y, z: s.z, w: s.w * 0.86, h: s.h * 0.8, d: s.d * 0.9 }); }
            }
        } else if (filled) {
            const n = Math.min(9, Math.max(1, l.used_lpn || 1));
            const cols = Math.min(3, Math.max(1, Math.floor(s.w / 1.25)));
            const pw = Math.min(1.15, s.w / cols - 0.1);
            const pd = Math.min(0.95, s.d / Math.ceil(n / cols) - 0.1);
            for (let k = 0; k < n; k++) {
                const cx2 = s.x - s.w / 2 + (k % cols + 0.5) * (s.w / cols);
                const cz2 = s.z - s.d / 2 + (Math.floor(k / cols) + 0.5) * (s.d / Math.ceil(n / cols));
                s.cargo.push(cargo.length);
                cargo.push({ si, x: cx2, y: 0.02, z: cz2, w: pw, h: 1.15, d: pd, pallet: true });
            }
        }
    });
    const PALLET_H = 0.14;
    const palletList = cargo.filter((c) => c.pallet);
    const palM = instanced(unitBox, new THREE.MeshStandardMaterial({ color: '#b98b5b', roughness: 0.9 }), palletList.length);
    palletList.forEach((c, i) => { c.palletIdx = i; place(palM, i, c.x, c.y, c.z, c.w + 0.06, PALLET_H, c.d + 0.04); });
    const cgM = instanced(unitBox, new THREE.MeshStandardMaterial({ color: '#ffffff', roughness: 0.75 }), cargo.length);
    cargo.forEach((c, i) => {
        const base = c.pallet ? PALLET_H : 0;
        place(cgM, i, c.x, c.y + base, c.z, c.w, c.h - base, c.d);
        cgM.setColorAt(i, tmpColor.set(COL.normal));
    });
    const emM = instanced(unitBox, new THREE.MeshStandardMaterial({ color: '#ffffff', transparent: true, opacity: 0.55, roughness: 1 }), empties.length, { shadow: false });
    empties.forEach((e, i) => place(emM, i, e.x, e.y, e.z, e.w, e.h, e.d));
    const ghM = instanced(unitBox, new THREE.MeshStandardMaterial({ color: COL.reserved, transparent: true, opacity: 0.22, depthWrite: false }), ghosts.length, { shadow: false });
    ghosts.forEach((g, i) => place(ghM, i, g.x, g.y, g.z, g.w, g.h, g.d));
    // volume tak terlihat untuk klik / hover
    const hitM = new THREE.InstancedMesh(unitBox, new THREE.MeshBasicMaterial({ visible: false }), Math.max(1, slots.length));
    hitM.count = slots.length;
    slots.forEach((s, i) => place(hitM, i, s.x, s.kind === 'floor' ? 0 : s.y, s.z, s.w, s.kind === 'floor' ? 1.4 : s.h, s.d));
    // penanda hasil cari
    const pinGeo = new THREE.ConeGeometry(0.22, 0.5, 18).rotateX(Math.PI);
    const pinM = instanced(pinGeo, new THREE.MeshStandardMaterial({ color: '#007aff', emissive: '#0040a0', emissiveIntensity: 0.5 }), slots.length, { shadow: false });
    pinM.count = 0;
    world.add(palM, cgM, emM, ghM, hitM, pinM);
    meshes = { cargo: cgM, pallet: palM, empty: emM, ghost: ghM, hit: hitM, pins: pinM, cargoList: cargo, emptyList: empties, ghostList: ghosts };

    // ---- seleksi
    const sel = new THREE.Group();
    const selBox = new THREE.Mesh(unitBox, new THREE.MeshBasicMaterial({ color: '#ff2d55', transparent: true, opacity: 0.12, depthWrite: false }));
    const selEdge = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(1, 1, 1).translate(0, 0.5, 0)), new THREE.LineBasicMaterial({ color: '#ff2d55' }));
    sel.add(selBox, selEdge);
    sel.visible = false;
    world.add(sel);
    meshes.sel = sel;

    // ---- label
    labels = new THREE.Group();
    L.rows.forEach((r) => {
        const a = textSprite(`AISLE ${r.aisle}`, { size: 0.7, bg: 'rgba(0,122,255,.92)', color: '#fff' });
        a.position.set(-1.1, 0.6, r.z + r.depth / 2);
        labels.add(a);
        r.bays.forEach((b, i) => {
            const t = textSprite(b, { size: 0.36, bg: 'rgba(255,255,255,.8)' });
            t.position.set(i * r.bayW + r.bayW / 2, r.top + 0.35, r.z + r.depth / 2);
            labels.add(t);
        });
        r.levels.forEach((lv) => {
            const t = textSprite(`L${lv}`, { size: 0.32, bg: 'rgba(255,138,0,.92)', color: '#fff' });
            t.position.set(-0.35, r.levelY[lv] + 0.35, r.z - 0.15);
            labels.add(t);
        });
    });
    L.areas.forEach((a) => {
        const t = textSprite(a.label, { size: 0.6, bg: 'rgba(29,36,51,.85)', color: '#fff' });
        t.position.set((a.x0 + a.x1) / 2, 2.4, L.floorZ);
        labels.add(t);
    });
    floorSlots.forEach((s) => {
        const t = textSprite(s.loc.code, { size: 0.34, bg: 'rgba(255,255,255,.85)', bold: false });
        t.position.set(s.x, 0.25, s.z + s.d / 2 + 0.25);
        labels.add(t);
    });
    labels.visible = state.showLabels;
    world.add(labels);

    // ---- cahaya & bayangan mengikuti ukuran gudang
    const span = Math.max(W, D);
    sun.position.set(cx + span * 0.4, span * 0.9 + 10, cz - span * 0.6);
    sun.target.position.set(cx, 0, cz);
    Object.assign(sun.shadow.camera, { left: -span * 0.75, right: span * 0.75, top: span * 0.75, bottom: -span * 0.75, near: 1, far: span * 3 + 40 });
    sun.shadow.camera.updateProjectionMatrix();
    bbox.set(new THREE.Vector3(minX, 0, minZ), new THREE.Vector3(maxX, wallH, maxZ));

    fillLevels(L.rows);
    applyColors();
    renderKpis();
    renderLegend();
    if (matches) runSearch($('mapQuery').value);
}

// ================================================================== warna, filter, cari
function colorOf(l) {
    const mode = MODES[state.mode];
    if (mode.cat) {
        const cats = mode.cat(l);
        if (cats.length > 1) return COL.mixed;
        return catColors.get(cats[0]) || COL.mixed;
    }
    return mode.color(l);
}
function prepareCats() {
    catColors = new Map();
    const mode = MODES[state.mode];
    if (!mode.cat) return;
    const counts = new Map();
    data.locations.forEach((l) => mode.cat(l).forEach((c) => counts.set(c, (counts.get(c) || 0) + 1)));
    [...counts.entries()].sort((a, b) => b[1] - a[1]).forEach(([c], i) => catColors.set(c, PALETTE[i % PALETTE.length]));
}
function visibleSlot(s) {
    return !(state.level && s.kind === 'rack' && (s.loc.level || 1) > state.level);
}
function applyColors() {
    if (!meshes.cargo) return;
    prepareCats();
    const { cargo, pallet, empty, ghost, cargoList, emptyList, ghostList } = meshes;
    cargoList.forEach((c, i) => {
        const s = slots[c.si];
        const show = visibleSlot(s);
        const base = c.pallet ? 0.14 : 0;
        place(cargo, i, c.x, c.y + base, c.z, show ? c.w : 0, show ? c.h - base : 0, show ? c.d : 0);
        if (c.pallet) place(pallet, c.palletIdx, c.x, c.y, c.z, show ? c.w + 0.06 : 0, show ? 0.14 : 0, show ? c.d + 0.04 : 0);
        const dimmed = matches && !matches.has(c.si);
        cargo.setColorAt(i, tmpColor.set(dimmed ? COL.dim : colorOf(s.loc)));
    });
    emptyList.forEach((e, i) => {
        const show = visibleSlot(slots[e.si]) && !state.onlyFilled;
        place(empty, i, e.x, e.y, e.z, show ? e.w : 0, show ? e.h : 0, show ? e.d : 0);
    });
    ghostList.forEach((g, i) => {
        const show = visibleSlot(slots[g.si]);
        place(ghost, i, g.x, g.y, g.z, show ? g.w : 0, show ? g.h : 0, show ? g.d : 0);
    });
    [cargo, pallet, empty, ghost].forEach((mm) => { mm.instanceMatrix.needsUpdate = true; if (mm.instanceColor) mm.instanceColor.needsUpdate = true; });
    renderLegend();
}

function renderLegend() {
    if (!data) return;
    const mode = MODES[state.mode];
    let items;
    if (mode.cat) {
        items = [...catColors.entries()].slice(0, 8).map(([c, col]) => [col, c]);
        if (catColors.size > 8) items.push([COL.mixed, `+${catColors.size - 8} lainnya`]);
        items.push([COL.mixed, 'Campur']);
    } else {
        items = mode.legend();
    }
    $('mapLegend').innerHTML = `${items.map(([c, t, ghost]) => `<span><i style="background:${c};${ghost ? 'opacity:.45' : ''}"></i>${esc(t)}</span>`).join('')}
        <span><i class="is-empty"></i>Kosong</span>`;
}

function renderKpis() {
    const locs = data.locations.filter((l) => l.fn !== 'VIRTUAL');
    const filled = locs.filter((l) => l.qty > 0);
    const store = locs.filter((l) => l.fn === 'STORAGE' && l.aisle);
    const storeFilled = store.filter((l) => l.qty > 0).length;
    const pallets = locs.reduce((a, l) => a + (l.used_lpn || 0), 0);
    const risk = filled.filter((l) => l.exp_days !== null && l.exp_days <= 30).length;
    const kpi = (v, t, cls = '') => `<div class="o-map-kpi ${cls}"><b>${v}</b><span>${t}</span></div>`;
    $('mapKpis').innerHTML = kpi(nf.format(locs.length), 'Lokasi')
        + kpi(nf.format(filled.length), 'Terisi')
        + kpi(nf.format(locs.length - filled.length), 'Kosong')
        + kpi(store.length ? `${Math.round(storeFilled / store.length * 100)}%` : '-', 'Rak terpakai')
        + kpi(nf.format(pallets), 'Pallet (LPN)')
        + kpi(nf.format(risk), 'Expired <= 30 hr', risk ? 'is-warn' : '');
}

function fillLevels(rows) {
    const max = Math.max(1, ...rows.flatMap((r) => r.levels));
    const sel = $('mapLevel');
    sel.innerHTML = '<option value="0">Semua</option>' + Array.from({ length: max }, (_, i) => `<option value="${i + 1}">s/d ${i + 1}</option>`).join('');
    sel.value = String(state.level <= max ? state.level : 0);
}

function runSearch(q) {
    q = q.trim().toLowerCase();
    const box = $('mapResults');
    if (!q) {
        matches = null; meshes.pins.count = 0; box.hidden = true; applyColors(); return;
    }
    const hit = [];
    slots.forEach((s, i) => {
        const l = s.loc;
        const hay = [l.code, ...l.skus, ...l.names, ...l.lpns, ...l.lots, ...l.owners].join(' ').toLowerCase();
        if (hay.includes(q)) hit.push(i);
    });
    matches = new Set(hit);
    const pins = meshes.pins;
    pins.count = hit.length;
    hit.forEach((i, k) => { const s = slots[i]; place(pins, k, s.x, (s.kind === 'floor' ? 1.4 : s.y + s.h) + 0.7, s.z, 1, 1, 1); });
    pins.instanceMatrix.needsUpdate = true;
    applyColors();
    box.hidden = false;
    box.innerHTML = hit.length
        ? `<div class="o-map-results-head">${hit.length} lokasi cocok</div>${hit.slice(0, 40).map((i) => {
            const l = slots[i].loc;
            return `<button type="button" data-slot="${i}"><b class="font-monospace">${esc(l.code)}</b><span>${esc(l.skus.join(', ') || (l.qty > 0 ? '' : 'kosong'))}</span></button>`;
        }).join('')}`
        : '<div class="o-map-results-head">Tidak ada lokasi yang cocok.</div>';
}

// ================================================================== kamera
let tween = null;
function flyTo(pos, target, ms = 700) {
    if (reduceMotion || !ms) { camera.position.copy(pos); controls.target.copy(target); controls.update(); return; }
    tween = { t0: performance.now(), ms, p0: camera.position.clone(), t0v: controls.target.clone(), p1: pos, t1: target };
}
function setView(v, animate = true) {
    state.view = v;
    document.querySelectorAll('#mapViews [data-view]').forEach((b) => b.classList.toggle('active', b.dataset.view === v));
    const c = bbox.getCenter(new THREE.Vector3()); c.y = 0;
    const size = bbox.getSize(new THREE.Vector3());
    const span = Math.max(size.x, size.z, 8);
    const fov = camera.fov * Math.PI / 180;
    const dist = (span / 2) / Math.tan(fov / 2) / Math.min(1, camera.aspect) * 0.95;
    let pos;
    if (v === 'top') {
        // denah: pas lebar & panjang gudang di layar
        const t = Math.tan(fov / 2);
        pos = new THREE.Vector3(c.x, Math.max((size.x / 2) / t / camera.aspect, (size.z / 2) / t) * 1.08, c.z + 0.01);
    }
    else if (v === 'front') pos = new THREE.Vector3(c.x, Math.max(4, size.y * 0.9), bbox.min.z - dist * 0.55);
    else pos = new THREE.Vector3(c.x + dist * 0.45, dist * 0.55, bbox.min.z - dist * 0.5);
    flyTo(pos, c, animate ? 800 : 0);
}

// ================================================================== interaksi
const ray = new THREE.Raycaster();
const pointer = new THREE.Vector2();
let hoverIdx = -1;
let down = null;

function pick(ev) {
    if (!meshes.hit) return -1;
    const r = renderer.domElement.getBoundingClientRect();
    pointer.set(((ev.clientX - r.left) / r.width) * 2 - 1, -((ev.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(pointer, camera);
    const hits = ray.intersectObject(meshes.hit, false);
    for (const h of hits) {
        const s = slots[h.instanceId];
        if (s && visibleSlot(s) && !(state.onlyFilled && !(s.loc.qty > 0))) return h.instanceId;
    }
    return -1;
}
function tipHtml(l) {
    const parts = [`<b class="font-monospace">${esc(l.code)}</b> <span class="text-muted">${esc(l.fn_label)}</span>`];
    if (l.qty > 0) {
        parts.push(`${esc(l.skus.slice(0, 2).join(', '))}${l.skus.length > 2 ? ` +${l.skus.length - 2}` : ''} · ${nf.format(l.qty)}`);
        if (l.exp_days !== null) parts.push(l.exp_days < 0 ? `<span class="text-danger">Kedaluwarsa ${-l.exp_days} hari</span>` : `Expired ${l.exp_days} hari lagi`);
    } else {
        parts.push(l.reserved ? '<span class="text-primary">Kosong · dipesan putaway</span>' : 'Kosong');
    }
    if (l.percent !== null) parts.push(`Terisi ${l.percent}%${l.max_lpn !== null ? ` · ${l.used_lpn}/${l.max_lpn} LPN` : ''}`);
    return parts.join('<br>');
}
renderer.domElement.addEventListener('pointermove', (ev) => {
    if (ev.pointerType === 'touch') return;
    const i = pick(ev);
    const tip = $('mapTip');
    hoverIdx = i;
    renderer.domElement.style.cursor = i >= 0 ? 'pointer' : '';
    if (i < 0) { tip.hidden = true; return; }
    const r = stage.getBoundingClientRect();
    tip.innerHTML = tipHtml(slots[i].loc);
    tip.hidden = false;
    tip.style.left = `${Math.min(ev.clientX - r.left + 14, r.width - tip.offsetWidth - 8)}px`;
    tip.style.top = `${Math.max(8, ev.clientY - r.top - tip.offsetHeight - 10)}px`;
});
renderer.domElement.addEventListener('pointerleave', () => { $('mapTip').hidden = true; });
renderer.domElement.addEventListener('pointerdown', (ev) => { down = { x: ev.clientX, y: ev.clientY }; });
renderer.domElement.addEventListener('pointerup', (ev) => {
    if (!down || Math.hypot(ev.clientX - down.x, ev.clientY - down.y) > 6) return;
    const i = pick(ev);
    if (i >= 0) select(i, true);
});
renderer.domElement.addEventListener('dblclick', (ev) => { const i = pick(ev); if (i >= 0) focusSlot(i); });

function focusSlot(i) {
    const s = slots[i];
    const target = new THREE.Vector3(s.x, (s.kind === 'floor' ? 0.6 : s.y + s.h / 2), s.z);
    const dir = camera.position.clone().sub(controls.target).normalize();
    if (dir.y < 0.25) dir.y = 0.35;
    dir.normalize();
    flyTo(target.clone().add(dir.multiplyScalar(9)), target);
}

async function select(i, focus) {
    selected = i;
    const s = slots[i];
    const sel = meshes.sel;
    sel.visible = true;
    sel.position.set(s.x, s.kind === 'floor' ? 0 : s.y, s.z);
    sel.scale.set(s.w + 0.08, (s.kind === 'floor' ? 1.4 : s.h) + 0.04, s.d + 0.08);
    if (focus) focusSlot(i);
    const panel = $('mapPanel');
    panel.hidden = false;
    $('mapRoot').classList.add('has-panel');
    $('mpCode').textContent = s.loc.code;
    $('mpTitle').textContent = `${s.loc.fn_label} · ${s.loc.zone_name}`;
    $('mpBody').innerHTML = '<div class="text-muted small p-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat isi lokasi...</div>';
    try {
        const res = await fetch(C.urls.location.replace('__ID__', s.loc.id), { headers: { Accept: 'application/json' } });
        if (selected !== i) return;
        if (!res.ok) {
            const msg = {
                401: 'Sesi login sudah habis. <a href="" class="alert-link">Muat ulang halaman</a> lalu login lagi.',
                403: 'Anda tidak punya akses melihat isi lokasi.',
                404: 'Lokasi tidak ditemukan (data mungkin sudah berubah). <a href="" class="alert-link">Muat ulang peta</a>.',
                419: 'Sesi kedaluwarsa. <a href="" class="alert-link">Muat ulang halaman</a>.',
            }[res.status] || `Server error (${res.status}). Coba lagi atau hubungi admin.`;
            $('mpBody').innerHTML = `<div class="alert alert-warning m-3">${msg}</div>`;
            return;
        }
        renderPanel(await res.json(), s.loc);
    } catch {
        if (selected === i) $('mpBody').innerHTML = '<div class="alert alert-danger m-3">Tidak terhubung ke server. Periksa jaringan lalu coba lagi.</div>';
    }
}
function renderPanel(d, l) {
    const cap = d.capacity;
    const pct = cap.percent;
    const bar = pct === null ? '' : `<div class="o-map-cap"><div class="o-map-cap-bar"><i style="width:${pct}%;background:${pct >= 100 ? COL.full : pct >= 85 ? COL.high : COL.part}"></i></div>
        <span>${pct}% terisi${cap.max_lpn !== null ? ` · ${cap.used_lpn}/${cap.max_lpn} LPN` : ''}</span></div>`;
    const rows = d.rows.length ? d.rows.map((r) => `
        <div class="o-map-item">
            <div class="d-flex justify-content-between gap-2"><b>${esc(r.sku)}</b><b class="text-nowrap">${esc(r.qty)}</b></div>
            <div class="small">${esc(r.name)}</div>
            <div class="small text-muted">${[r.lpn ? `<span class="font-monospace">${esc(r.lpn)}</span>` : null, r.lot ? `Lot ${esc(r.lot)}` : null, r.exp ? `Exp ${esc(r.exp)}` : null, esc(r.owner)].filter(Boolean).join(' · ')}</div>
            ${r.available ? '' : `<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis mt-1">${esc(r.status)}</span>`}
        </div>`).join('')
        : `<div class="text-muted small p-3 text-center"><i class="bi bi-inbox d-block fs-3 mb-1"></i>Lokasi kosong${l.reserved ? ' · sedang dipesan putaway' : ''}.</div>`;
    $('mpBody').innerHTML = `
        <div class="o-map-meta">
            <div><span>Kelas</span><b>${esc(d.class)}</b></div>
            ${d.coord ? `<div><span>Posisi</span><b>${esc(d.coord)}</b></div>` : ''}
            <div><span>Status</span><b>${esc(d.status)}</b></div>
        </div>
        ${bar}
        <div class="o-map-items">${rows}</div>
        <div class="d-flex gap-2 p-3 pt-0">
            ${C.canStock && d.rows.length ? `<a class="btn btn-sm btn-primary flex-fill" href="${esc(d.stock_url)}"><i class="bi bi-boxes me-1"></i>Lihat di Inventory</a>` : ''}
            <button type="button" class="btn btn-sm btn-secondary flex-fill" id="mpFocus"><i class="bi bi-crosshair me-1"></i>Fokus</button>
        </div>`;
    $('mpFocus').addEventListener('click', () => focusSlot(selected));
}
function closePanel() {
    $('mapPanel').hidden = true;
    $('mapRoot').classList.remove('has-panel');
    selected = null;
    if (meshes.sel) meshes.sel.visible = false;
    resize();
}

// ================================================================== kontrol UI
$('mpClose').addEventListener('click', closePanel);
document.querySelectorAll('#mapViews [data-view]').forEach((b) => b.addEventListener('click', () => setView(b.dataset.view)));
$('mapMode').value = state.mode;
$('mapMode').addEventListener('change', (e) => { state.mode = e.target.value; savePrefs(); applyColors(); });
$('mapLevel').addEventListener('change', (e) => { state.level = Number(e.target.value); applyColors(); });
$('mapOnlyFilled').addEventListener('change', (e) => { state.onlyFilled = e.target.checked; applyColors(); });
$('mapLabels').checked = state.showLabels;
$('mapLabels').addEventListener('change', (e) => { state.showLabels = e.target.checked; if (labels) labels.visible = state.showLabels; savePrefs(); });
$('mapRefresh').addEventListener('click', () => load(true));
$('mapWarehouse')?.addEventListener('change', (e) => { location.href = `${C.urls.page}?warehouse=${e.target.value}`; });
let searchTimer;
$('mapQuery').addEventListener('input', (e) => { clearTimeout(searchTimer); searchTimer = setTimeout(() => runSearch(e.target.value), 200); });
$('mapSearch').addEventListener('submit', (e) => {
    e.preventDefault();
    runSearch($('mapQuery').value);
    const first = matches && [...matches][0];
    if (first !== undefined && first !== null) select(first, true);
});
$('mapResults').addEventListener('click', (e) => { const b = e.target.closest('[data-slot]'); if (b) select(Number(b.dataset.slot), true); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('mapPanel').hidden) closePanel(); });

// ================================================================== render loop
function fitHeight() {
    // tinggi peta = sisa layar di bawah control panel (control panel bisa 1-2 baris)
    const root = $('mapRoot');
    const top = root.getBoundingClientRect().top + window.scrollY;
    root.style.height = `${Math.max(420, window.innerHeight - top - 8)}px`;
}
window.addEventListener('resize', fitHeight);
fitHeight();
function resize() {
    const w = stage.clientWidth; const h = stage.clientHeight;
    if (!w || !h) return;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
}
new ResizeObserver(resize).observe(stage);
resize();

const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
renderer.setAnimationLoop((now) => {
    if (tween) {
        const t = Math.min(1, (now - tween.t0) / tween.ms);
        const k = ease(t);
        camera.position.lerpVectors(tween.p0, tween.p1, k);
        controls.target.lerpVectors(tween.t0v, tween.t1, k);
        if (t >= 1) tween = null;
    }
    // penanda hasil cari naik-turun
    if (meshes.pins && meshes.pins.count && !reduceMotion) meshes.pins.position.y = Math.sin(now / 280) * 0.12;
    controls.update();
    renderer.render(scene, camera);
});

window.WMAP3D = { get slots() { return slots; }, select, setView, runSearch, get state() { return state; }, camera, controls };
load();
