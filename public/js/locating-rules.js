/**
 * Master > Item Locating (resources/views/master/locating/index.blade.php). Konfigurasi: window.LOC.
 *
 * - Kiri  : pohon Item Group > Item Class > Item. Badge = punya aturan sendiri / ikut induk.
 * - Tengah: editor aturan cakupan terpilih. Daftar "Prioritas area" diisi dengan SERET area dari kanan (SortableJS,
 *           jalan juga di layar sentuh), urutan bisa diubah dengan seret, tiap area punya rentang level.
 *           Denah di bawahnya mewarnai lokasi sesuai prioritas draft (belum disimpan pun langsung terlihat).
 * - Kanan : palet area (zona, aisle, kelas lokasi, lokasi) + simulasi saran putaway (server: LocatingService::suggest).
 * Logika cocok-area di JS = LocatingRuleTarget::matches() di PHP (jaga tetap sama).
 */
(() => {
    const C = window.LOC;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const PRIO = ['#007aff', '#34c759', '#ff9f0a', '#af52de', '#ff2d55', '#30b0c7', '#a2845e', '#5856d6'];
    const prioColor = (p) => PRIO[(p - 1) % PRIO.length];
    const ICON = { ZONE: 'bi-map', AISLE: 'bi-distribute-vertical', LOCATION_CLASS: 'bi-bookshelf', LOCATION: 'bi-geo-alt' };
    const SCOPE_ICON = { ITEM_GROUP: 'bi-collection', ITEM_CLASS: 'bi-tags', ITEM: 'bi-box-seam' };

    let D = null;            // data dari server
    let rules = new Map();   // "TYPE:id" -> rule
    let sel = null;          // {type, id}
    let draft = null;        // aturan yang sedang diedit
    let dirty = false;
    let filter = 'all';
    let simResult = [];
    const open = new Set();  // node pohon yang terbuka

    const key = (t, id) => `${t}:${id}`;
    const zoneById = () => Object.fromEntries(D.zones.map((z) => [z.id, z]));
    const classById = () => Object.fromEntries(D.classes.map((c) => [c.id, c]));
    const locById = () => Object.fromEntries(D.locations.map((l) => [l.id, l]));
    const maxLevel = () => Math.max(1, ...D.locations.map((l) => l.level || 1));

    // ================================================================== data
    async function load(keepSel = true) {
        const res = await WMS.api(C.urls.data, { params: { warehouse: C.warehouse } });
        if (!res.ok) { $('locTree').innerHTML = `<div class="alert alert-warning m-3">${esc(res.data.message)}</div>`; return; }
        D = res.data;
        rules = new Map(D.rules.map((r) => [key(r.scope_type, r.scope_id), r]));
        renderTree();
        renderPalette();
        fillSimItems();
        if (keepSel && sel) select(sel.type, sel.id, true); else { renderMap(); }
    }

    // ================================================================== hierarki & pewarisan
    function nodeInfo(type, id) {
        for (const g of D.tree) {
            if (type === 'ITEM_GROUP' && g.id === id) return { name: g.name, code: g.code, group: g };
            for (const c of g.classes) {
                if (type === 'ITEM_CLASS' && c.id === id) return { name: c.name, code: c.code, group: g, cls: c };
                for (const i of c.items) if (type === 'ITEM' && i.id === id) return { name: `${i.sku} · ${i.name}`, code: i.sku, group: g, cls: c, item: i };
            }
        }
        return null;
    }
    /** Aturan induk yang berlaku bila cakupan ini tidak punya aturan sendiri. */
    function inherited(type, id) {
        const n = nodeInfo(type, id);
        if (!n) return null;
        if (type === 'ITEM' && rules.get(key('ITEM_CLASS', n.cls.id))) return { rule: rules.get(key('ITEM_CLASS', n.cls.id)), from: `Item Class ${n.cls.name}` };
        if (type !== 'ITEM_GROUP' && rules.get(key('ITEM_GROUP', n.group.id))) return { rule: rules.get(key('ITEM_GROUP', n.group.id)), from: `Item Group ${n.group.name}` };
        return null;
    }
    function badge(type, id) {
        const own = rules.get(key(type, id));
        if (own) return `<span class="o-loc-badge ${own.is_active ? '' : 'is-off'}" title="Punya aturan sendiri">${own.targets.length} area${own.strict ? ' · ketat' : ''}</span>`;
        const inh = inherited(type, id);
        return inh ? `<span class="o-loc-badge is-inherit" title="Ikut ${esc(inh.from)}">ikut ${inh.from.startsWith('Item Class') ? 'class' : 'group'}</span>` : '';
    }

    // ================================================================== pohon barang
    function renderTree() {
        const q = $('treeSearch').value.trim().toLowerCase();
        const has = (t, id) => rules.has(key(t, id));
        const hit = (s) => !q || s.toLowerCase().includes(q);
        let html = '';
        D.tree.forEach((g) => {
            const classes = g.classes.map((c) => {
                const items = c.items.filter((i) => hit(`${i.sku} ${i.name} ${c.name} ${g.name}`) && (filter === 'all' || has('ITEM', i.id) || has('ITEM_CLASS', c.id) || has('ITEM_GROUP', g.id)));
                const showClass = items.length || (hit(`${c.name} ${c.code} ${g.name}`) && (filter === 'all' || has('ITEM_CLASS', c.id) || has('ITEM_GROUP', g.id)));
                return showClass ? { c, items } : null;
            }).filter(Boolean);
            if (!classes.length && !(hit(`${g.name} ${g.code}`) && (filter === 'all' || has('ITEM_GROUP', g.id)))) return;
            const gOpen = open.has(`G${g.id}`) || q;
            html += `<div class="o-loc-g">
                ${row('ITEM_GROUP', g.id, g.name, g.code, 0, classes.length ? (gOpen ? 'open' : 'closed') : null, `G${g.id}`)}
                ${gOpen ? classes.map(({ c, items }) => {
                    const cOpen = open.has(`C${c.id}`) || q;
                    return row('ITEM_CLASS', c.id, c.name, c.code, 1, items.length ? (cOpen ? 'open' : 'closed') : null, `C${c.id}`)
                        + (cOpen ? items.map((i) => row('ITEM', i.id, i.name, i.sku, 2, null, null, i.owner)).join('') : '');
                }).join('') : ''}
            </div>`;
        });
        $('locTree').innerHTML = html || '<div class="text-muted small p-3">Tidak ada yang cocok.</div>';
    }
    function row(type, id, name, code, depth, toggle, tkey, owner) {
        const active = sel && sel.type === type && sel.id === id;
        return `<div class="o-loc-node d${depth} ${active ? 'is-active' : ''}" data-type="${type}" data-id="${id}">
            ${toggle ? `<button type="button" class="o-loc-tg" data-toggle="${tkey}" aria-label="Buka/tutup"><i class="bi bi-chevron-${toggle === 'open' ? 'down' : 'right'}"></i></button>` : '<span class="o-loc-tg"></span>'}
            <i class="bi ${SCOPE_ICON[type]} o-loc-ic"></i>
            <span class="o-loc-nm"><b>${esc(type === 'ITEM' ? code : name)}</b>${type === 'ITEM' ? `<small>${esc(name)} · ${esc(owner)}</small>` : ''}</span>
            ${badge(type, id)}
        </div>`;
    }
    $('locTree').addEventListener('click', (e) => {
        const tg = e.target.closest('[data-toggle]');
        if (tg) { open.has(tg.dataset.toggle) ? open.delete(tg.dataset.toggle) : open.add(tg.dataset.toggle); renderTree(); return; }
        const n = e.target.closest('.o-loc-node');
        if (!n) return;
        const type = n.dataset.type; const id = Number(n.dataset.id);
        if (dirty && !(sel.type === type && sel.id === id) && !confirm('Perubahan belum disimpan. Tinggalkan?')) return;
        if (type === 'ITEM_GROUP') open.add(`G${id}`);
        if (type === 'ITEM_CLASS') open.add(`C${id}`);
        select(type, id);
    });
    $('treeSearch').addEventListener('input', renderTree);
    document.querySelectorAll('#treeFilter [data-f]').forEach((b) => b.addEventListener('click', () => {
        filter = b.dataset.f;
        document.querySelectorAll('#treeFilter [data-f]').forEach((x) => x.classList.toggle('active', x === b));
        renderTree();
    }));

    // ================================================================== editor
    const blank = () => ({ level_preference: null, prefer_consolidate: true, strict: false, is_active: true, notes: '', targets: [] });
    const clone = (r) => JSON.parse(JSON.stringify({ level_preference: r.level_preference, prefer_consolidate: r.prefer_consolidate, strict: r.strict, is_active: r.is_active, notes: r.notes || '', targets: r.targets }));

    function select(type, id, keepDraft = false) {
        sel = { type, id };
        const own = rules.get(key(type, id));
        if (!keepDraft || !draft) draft = own ? clone(own) : blank();
        dirty = false;
        renderTree();
        renderEditor();
        const n = nodeInfo(type, id);
        // simulasi: item pertama di bawah cakupan ini
        const first = n?.item?.id ?? n?.cls?.items[0]?.id ?? n?.group?.classes.flatMap((c) => c.items)[0]?.id;
        if (first) $('simItem').value = String(first);
        changed(false);
    }

    function targetLabel(t) {
        const z = zoneById(); const c = classById(); const l = locById();
        const base = {
            ZONE: () => `Zona ${z[t.zone_id]?.name ?? '?'}`,
            AISLE: () => `Aisle ${t.aisle} (${z[t.zone_id]?.code ?? '?'})`,
            LOCATION_CLASS: () => `Kelas ${c[t.location_class_id]?.name ?? '?'}`,
            LOCATION: () => `Lokasi ${l[t.location_id]?.code ?? '?'}`,
        }[t.target_type]();
        return base;
    }
    function countFor(t) { return D.locations.filter((l) => matches(t, l)).length; }

    function renderEditor() {
        const n = nodeInfo(sel.type, sel.id);
        const own = rules.get(key(sel.type, sel.id));
        const inh = inherited(sel.type, sel.id);
        const ro = !C.canEdit;
        const lvMax = maxLevel();
        const lvOpts = (v, ph) => `<option value="">${ph}</option>` + Array.from({ length: lvMax }, (_, i) => `<option value="${i + 1}" ${Number(v) === i + 1 ? 'selected' : ''}>${i + 1}</option>`).join('');
        const crumbs = [n.group && sel.type !== 'ITEM_GROUP' ? n.group.name : null, n.cls && sel.type === 'ITEM' ? n.cls.name : null].filter(Boolean);

        $('locEditor').innerHTML = `
            <div class="o-loc-ehead">
                <div class="o-loc-scope"><i class="bi ${SCOPE_ICON[sel.type]}"></i></div>
                <div class="min-w-0 flex-grow-1">
                    <div class="small text-muted">${esc(D.scopes[sel.type])}${crumbs.length ? ' · ' + crumbs.map(esc).join(' › ') : ''}</div>
                    <div class="o-loc-title">${esc(n.name)}</div>
                    <div class="small ${own ? 'text-success' : 'text-muted'}">${own ? `<i class="bi bi-check-circle-fill me-1"></i>Punya aturan sendiri${own.updated ? ' · ' + esc(own.updated) : ''}`
                        : inh ? `<i class="bi bi-arrow-return-right me-1"></i>Belum punya aturan sendiri, saat ini <b>ikut ${esc(inh.from)}</b>` : '<i class="bi bi-dash-circle me-1"></i>Belum ada aturan: memakai strategi dasar (gabung item sama, lokasi kosong, level rendah)'}</div>
                </div>
                <span class="o-loc-dirty" id="dirtyMark" hidden>belum disimpan</span>
            </div>
            ${!own && inh && !ro ? `<div class="o-loc-inherit"><span><i class="bi bi-info-circle me-1"></i>Aturan ${esc(inh.from)}: ${inh.rule.targets.map((t) => esc(t.label)).join(' → ') || 'tanpa area'}</span>
                <button type="button" class="btn btn-sm btn-secondary" id="copyInherit"><i class="bi bi-files me-1"></i>Salin sebagai awal</button></div>` : ''}

            <div class="o-loc-sect">Prioritas area <span class="text-muted fw-normal">— urutan atas = dicoba duluan</span></div>
            <div class="o-loc-targets ${draft.targets.length ? '' : 'is-empty'}" id="locTargets">
                ${draft.targets.map((t, i) => `
                    <div class="o-loc-target" data-i="${i}" style="--pc:${prioColor(i + 1)}">
                        <span class="o-loc-grip" title="Seret untuk ubah urutan"><i class="bi bi-grip-vertical"></i></span>
                        <span class="o-loc-prio">${i + 1}</span>
                        <i class="bi ${ICON[t.target_type]} o-loc-tic"></i>
                        <div class="o-loc-tbody"><b>${esc(targetLabel(t))}</b><small>${countFor(t)} lokasi</small></div>
                        <label class="o-loc-lv">Level
                            <select data-lv="min" data-i="${i}" ${ro ? 'disabled' : ''}>${lvOpts(t.level_min, 'min')}</select>–
                            <select data-lv="max" data-i="${i}" ${ro ? 'disabled' : ''}>${lvOpts(t.level_max, 'max')}</select>
                        </label>
                        ${ro ? '' : `<button type="button" class="o-loc-rm" data-rm="${i}" title="Hapus area"><i class="bi bi-x-lg"></i></button>`}
                    </div>`).join('')}
                <div class="o-loc-drop"><i class="bi bi-box-arrow-in-down-left"></i> ${ro ? 'Belum ada area' : 'Seret area dari panel kanan ke sini, atau klik aisle / lokasi di denah'}</div>
            </div>

            <div class="o-loc-opts">
                <div class="o-loc-opt">
                    <span>Level</span>
                    <div class="btn-group btn-group-sm o-segment" id="optLevel">
                        ${[[null, 'Bebas'], ['LOW', 'Rendah dulu'], ['HIGH', 'Tinggi dulu']].map(([v, l]) => `<button type="button" class="btn btn-secondary ${draft.level_preference === v ? 'active' : ''}" data-v="${v ?? ''}" ${ro ? 'disabled' : ''}>${l}</button>`).join('')}
                    </div>
                </div>
                <label class="o-loc-opt form-check form-switch"><input class="form-check-input" type="checkbox" id="optCons" ${draft.prefer_consolidate ? 'checked' : ''} ${ro ? 'disabled' : ''}><span>Gabung ke lokasi berisi item sama dulu</span></label>
                <label class="o-loc-opt form-check form-switch"><input class="form-check-input" type="checkbox" id="optStrict" ${draft.strict ? 'checked' : ''} ${ro ? 'disabled' : ''}><span>Hanya area ini <small class="text-muted">(di luar area = butuh SPV)</small></span></label>
                <label class="o-loc-opt form-check form-switch"><input class="form-check-input" type="checkbox" id="optActive" ${draft.is_active ? 'checked' : ''} ${ro ? 'disabled' : ''}><span>Aktif</span></label>
                <input type="text" class="form-control form-control-sm" id="optNotes" maxlength="255" placeholder="Catatan (mis. barang berat di bawah)" value="${esc(draft.notes)}" ${ro ? 'disabled' : ''}>
            </div>

            ${ro ? '<div class="small text-muted p-3 pt-0"><i class="bi bi-lock me-1"></i>Anda hanya bisa melihat aturan.</div>' : `
            <div class="o-loc-actions">
                ${own && C.canDelete ? '<button type="button" class="btn btn-sm btn-link text-danger me-auto" id="btnDelete"><i class="bi bi-trash me-1"></i>Hapus aturan</button>' : '<span class="me-auto"></span>'}
                <button type="button" class="btn btn-sm btn-secondary" id="btnReset">Batal</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnSave"><i class="bi bi-check2 me-1"></i>Simpan aturan</button>
            </div>`}`;

        if (!ro) bindEditor();
        $('dirtyMark').hidden = !dirty;
    }

    function bindEditor() {
        // urutkan ulang + terima area dari palet
        Sortable.create($('locTargets'), {
            group: { name: 'loc', pull: false, put: true },
            handle: '.o-loc-target', draggable: '.o-loc-target', filter: 'select, .o-loc-rm', preventOnFilter: false,
            animation: 160, ghostClass: 'is-ghost', forceFallback: true, fallbackOnBody: true,
            onAdd: (e) => {
                const t = fromChip(e.item.dataset);
                e.item.remove();
                if (t) addTarget(t, Math.min(e.newDraggableIndex ?? draft.targets.length, draft.targets.length));
            },
            onUpdate: (e) => {
                const [m] = draft.targets.splice(e.oldDraggableIndex, 1);
                draft.targets.splice(e.newDraggableIndex, 0, m);
                changed();
            },
        });
        $('locTargets').addEventListener('change', (e) => {
            const s = e.target.closest('[data-lv]');
            if (!s) return;
            const t = draft.targets[Number(s.dataset.i)];
            t[`level_${s.dataset.lv}`] = s.value ? Number(s.value) : null;
            if (t.level_min && t.level_max && t.level_min > t.level_max) [t.level_min, t.level_max] = [t.level_max, t.level_min];
            changed();
        });
        $('locTargets').addEventListener('click', (e) => {
            const b = e.target.closest('[data-rm]');
            if (b) { draft.targets.splice(Number(b.dataset.rm), 1); changed(); }
        });
        document.querySelectorAll('#optLevel [data-v]').forEach((b) => b.addEventListener('click', () => { draft.level_preference = b.dataset.v || null; changed(); }));
        $('optCons').addEventListener('change', (e) => { draft.prefer_consolidate = e.target.checked; changed(); });
        $('optStrict').addEventListener('change', (e) => { draft.strict = e.target.checked; changed(); });
        $('optActive').addEventListener('change', (e) => { draft.is_active = e.target.checked; changed(); });
        $('optNotes').addEventListener('input', (e) => { draft.notes = e.target.value; dirty = true; $('dirtyMark').hidden = false; });
        $('copyInherit')?.addEventListener('click', () => { draft = clone(inherited(sel.type, sel.id).rule); changed(); });
        $('btnReset').addEventListener('click', () => { const own = rules.get(key(sel.type, sel.id)); draft = own ? clone(own) : blank(); dirty = false; changed(false); });
        $('btnSave').addEventListener('click', save);
        $('btnDelete')?.addEventListener('click', remove);
    }

    function changed(markDirty = true) {
        if (markDirty) dirty = true;
        renderEditor();
        renderMap();
        simulate();
    }

    function addTarget(t, at = draft.targets.length) {
        if (!sel || !C.canEdit) return;
        const sig = JSON.stringify([t.target_type, t.zone_id, t.aisle, t.location_class_id, t.location_id]);
        if (draft.targets.some((x) => JSON.stringify([x.target_type, x.zone_id, x.aisle, x.location_class_id, x.location_id]) === sig)) {
            WMS.showToast(`${targetLabel(t)} sudah ada di daftar.`, 'warning');
            return;
        }
        draft.targets.splice(at, 0, { level_min: null, level_max: null, zone_id: null, aisle: null, location_class_id: null, location_id: null, ...t });
        changed();
    }
    function fromChip(d) {
        if (!d.t) return null;
        return { target_type: d.t, zone_id: d.zone ? Number(d.zone) : null, aisle: d.aisle || null, location_class_id: d.cls ? Number(d.cls) : null, location_id: d.loc ? Number(d.loc) : null };
    }

    async function save() {
        const btn = $('btnSave'); btn.disabled = true;
        const res = await WMS.api(C.urls.save, { method: 'PUT', body: { warehouse_id: C.warehouse, scope_type: sel.type, scope_id: sel.id, ...draft } });
        btn.disabled = false;
        if (!res.ok) { WMS.showToast(res.data.message, 'danger'); return; }
        rules.set(key(sel.type, sel.id), res.data.rule);
        draft = clone(res.data.rule);
        dirty = false;
        WMS.showToast(res.data.message);
        renderTree(); changed(false);
    }
    async function remove() {
        const own = rules.get(key(sel.type, sel.id));
        if (!own || !confirm('Hapus aturan ini? Barang akan ikut aturan induknya (bila ada).')) return;
        const res = await WMS.api(C.urls.destroy.replace('__ID__', own.id), { method: 'DELETE' });
        if (!res.ok) { WMS.showToast(res.data.message, 'danger'); return; }
        rules.delete(key(sel.type, sel.id));
        draft = blank(); dirty = false;
        WMS.showToast(res.data.message);
        renderTree(); changed(false);
    }

    // ================================================================== palet area
    function renderPalette() {
        const chip = (data, icon, title, sub) => `<div class="o-loc-chip" ${Object.entries(data).map(([k, v]) => `data-${k}="${esc(v)}"`).join(' ')} title="Seret ke daftar prioritas, atau klik +">
            <i class="bi ${icon}"></i><span><b>${esc(title)}</b><small>${esc(sub)}</small></span><button type="button" class="o-loc-add" aria-label="Tambah"><i class="bi bi-plus-lg"></i></button></div>`;
        const sect = (title, inner, id) => `<div class="o-loc-psect"><div class="o-loc-ptitle">${title}</div><div class="o-loc-plist" id="${id}">${inner}</div></div>`;
        $('locPalette').innerHTML =
            sect('Zona', D.zones.map((z) => chip({ t: 'ZONE', zone: z.id }, ICON.ZONE, z.name, `${z.count} lokasi · ${z.temp}`)).join(''), 'palZone')
            + sect('Aisle', D.aisles.map((a) => chip({ t: 'AISLE', zone: a.zone_id, aisle: a.aisle }, ICON.AISLE, `Aisle ${a.aisle}`, `Zona ${a.zone} · ${a.bays} bay · level ${a.levels[0]}-${a.levels[1]} · ${a.used}/${a.count} terisi`)).join(''), 'palAisle')
            + sect('Kelas lokasi', D.classes.map((c) => chip({ t: 'LOCATION_CLASS', cls: c.id }, ICON.LOCATION_CLASS, c.name, `${c.count} lokasi`)).join(''), 'palClass')
            + `<div class="o-loc-psect"><div class="o-loc-ptitle">Lokasi tertentu</div><input type="search" class="form-control form-control-sm mb-2" id="palLocQ" placeholder="Cari kode lokasi..."><div class="o-loc-plist" id="palLoc"></div></div>`;
        renderLocChips();
        $('palLocQ').addEventListener('input', renderLocChips);
        ['palZone', 'palAisle', 'palClass', 'palLoc'].forEach((id) => Sortable.create($(id), {
            group: { name: 'loc', pull: 'clone', put: false }, sort: false, animation: 160, filter: '.o-loc-add', preventOnFilter: false,
            forceFallback: true, fallbackOnBody: true,
            disabled: !C.canEdit,
        }));
        $('locPalette').addEventListener('click', (e) => {
            const b = e.target.closest('.o-loc-add');
            if (!b) return;
            if (!sel) { WMS.showToast('Pilih barang di kiri dulu.', 'warning'); return; }
            addTarget(fromChip(b.closest('.o-loc-chip').dataset));
        });
    }
    function renderLocChips() {
        const q = ($('palLocQ')?.value || '').trim().toUpperCase();
        const list = D.locations.filter((l) => !q || l.code.includes(q)).slice(0, q ? 40 : 12);
        $('palLoc').innerHTML = list.map((l) => `<div class="o-loc-chip is-sm" data-t="LOCATION" data-loc="${l.id}"><i class="bi ${ICON.LOCATION}"></i><span><b class="font-monospace">${esc(l.code)}</b><small>${l.used ? 'terisi' : 'kosong'}</small></span><button type="button" class="o-loc-add" aria-label="Tambah"><i class="bi bi-plus-lg"></i></button></div>`).join('')
            + (q ? '' : '<div class="small text-muted px-1">Ketik untuk mencari lokasi lain...</div>');
    }

    // ================================================================== denah
    function matches(t, l) {
        const inArea = {
            ZONE: () => l.zone_id === t.zone_id,
            AISLE: () => l.zone_id === t.zone_id && String(l.aisle) === String(t.aisle),
            LOCATION_CLASS: () => l.class_id === t.location_class_id,
            LOCATION: () => l.id === t.location_id,
        }[t.target_type]();
        const lv = l.level || 1;
        return inArea && (t.level_min == null || lv >= t.level_min) && (t.level_max == null || lv <= t.level_max);
    }
    function priorityOf(l) {
        if (!draft || !draft.targets.length) return null;
        const i = draft.targets.findIndex((t) => matches(t, l));
        return i < 0 ? null : i + 1;
    }
    function renderMap() {
        if (!D) return;
        const simRank = new Map(simResult.map((s, i) => [s.id, i + 1]));
        const byAisle = new Map();
        const loose = [];
        D.locations.forEach((l) => {
            if (!l.aisle) { loose.push(l); return; }
            const k = `${l.zone_id}|${l.aisle}`;
            if (!byAisle.has(k)) byAisle.set(k, []);
            byAisle.get(k).push(l);
        });
        const cell = (l) => {
            const p = priorityOf(l);
            const out = draft && draft.targets.length && p === null;
            const r = simRank.get(l.id);
            return `<button type="button" class="o-loc-cell ${out ? (draft.strict ? 'is-blocked' : 'is-out') : ''} ${l.used ? 'is-used' : ''} ${l.active ? '' : 'is-inactive'} ${r ? 'is-sim' : ''}"
                data-loc="${l.id}" style="${p ? `--pc:${prioColor(p)}` : ''}" title="${esc(l.code)}${p ? ` · prioritas ${p}` : out ? (draft.strict ? ' · di luar area (diblok)' : ' · di luar area (cadangan)') : ''}${l.used ? ' · terisi' : ' · kosong'}">${r ? `<b>${r}</b>` : ''}</button>`;
        };
        const zones = zoneById();
        let html = '';
        [...byAisle.entries()].sort((a, b) => a[0].localeCompare(b[0], 'id', { numeric: true })).forEach(([k, list]) => {
            const [zid, aisle] = k.split('|');
            const bays = [...new Set(list.map((l) => l.bay))].sort((a, b) => String(a).localeCompare(String(b), 'id', { numeric: true }));
            const levels = [...new Set(list.map((l) => l.level || 1))].sort((a, b) => b - a);
            html += `<div class="o-loc-aisle">
                <button type="button" class="o-loc-alabel" data-zone="${zid}" data-aisle="${esc(aisle)}" title="Tambah aisle ini ke prioritas"><b>${esc(aisle)}</b><small>${esc(zones[zid]?.code ?? '')}</small><i class="bi bi-plus-circle"></i></button>
                <div class="o-loc-bays">${bays.map((b) => `<div class="o-loc-bay">${levels.map((lv) => {
                    const l = list.find((x) => x.bay === b && (x.level || 1) === lv);
                    return l ? cell(l) : '<span class="o-loc-cell is-none"></span>';
                }).join('')}<span class="o-loc-bayno">${esc(b)}</span></div>`).join('')}</div>
                <div class="o-loc-lvls">${levels.map((lv) => `<span>L${lv}</span>`).join('')}<span></span></div>
            </div>`;
        });
        if (loose.length) html += `<div class="o-loc-aisle"><span class="o-loc-alabel is-static"><b>Lantai</b><small>tanpa aisle</small></span><div class="o-loc-bays is-wrap">${loose.map((l) => `<div class="o-loc-bay">${cell(l)}<span class="o-loc-bayno">${esc(l.code)}</span></div>`).join('')}</div></div>`;
        $('locMap').innerHTML = html || '<div class="text-muted small p-3">Belum ada lokasi fungsi STORAGE di gudang ini.</div>';

        // legenda
        const pr = draft && draft.targets.length ? draft.targets.map((t, i) => `<span><i style="background:${prioColor(i + 1)}"></i>${i + 1}. ${esc(targetLabel(t))}</span>`).join('') : '';
        $('locLegend').innerHTML = pr + (draft && draft.targets.length ? `<span><i class="${draft.strict ? 'is-blocked' : 'is-out'}"></i>${draft.strict ? 'Di luar area (diblok)' : 'Di luar area (cadangan terakhir)'}</span>` : '<span class="text-muted">Pilih barang lalu tambah area</span>')
            + '<span><i class="is-usedlg"></i>Terisi</span>';
    }
    $('locMap').addEventListener('click', (e) => {
        if (!sel) { WMS.showToast('Pilih barang di kiri dulu.', 'warning'); return; }
        const a = e.target.closest('.o-loc-alabel[data-aisle]');
        if (a) { addTarget({ target_type: 'AISLE', zone_id: Number(a.dataset.zone), aisle: a.dataset.aisle }); return; }
        const c = e.target.closest('.o-loc-cell[data-loc]');
        if (c) addTarget({ target_type: 'LOCATION', location_id: Number(c.dataset.loc) });
    });

    // ================================================================== simulasi
    function fillSimItems() {
        $('simItem').innerHTML = D.tree.flatMap((g) => g.classes.flatMap((c) => c.items.map((i) => `<option value="${i.id}">${esc(i.sku)} · ${esc(i.name)} (${esc(c.name)})</option>`))).join('');
    }
    let simTimer; let simSeq = 0;
    function simulate() {
        clearTimeout(simTimer);
        simTimer = setTimeout(async () => {
            const itemId = Number($('simItem').value);
            if (!itemId) return;
            const seq = ++simSeq;
            $('simList').innerHTML = '<div class="text-muted small p-3"><span class="spinner-border spinner-border-sm me-2"></span>Menghitung...</div>';
            $('simSource').textContent = '';
            simResult = []; renderMap();
            const body = { warehouse_id: C.warehouse, item_id: itemId };
            if (sel) body.draft = { scope_type: sel.type, scope_id: sel.id, ...draft };
            const res = await WMS.api(C.urls.simulate, { method: 'POST', body });
            if (seq !== simSeq) return;
            if (!res.ok) { $('simList').innerHTML = `<div class="alert alert-warning m-3 small">${esc(res.data.message)}</div>`; simResult = []; renderMap(); return; }
            simResult = res.data.suggestions;
            $('simSource').innerHTML = {
                draft: `<i class="bi bi-pencil me-1"></i>Memakai aturan yang sedang diedit (${esc(res.data.rule_name)})${dirty ? ' — belum disimpan' : ''}`,
                saved: `<i class="bi bi-check2-circle me-1"></i>Memakai aturan tersimpan: ${esc(res.data.rule_name)}`,
                none: '<i class="bi bi-dash-circle me-1"></i>Tanpa aturan: strategi dasar',
            }[res.data.source];
            $('simList').innerHTML = simResult.length ? simResult.map((s, i) => `
                <div class="o-loc-simrow ${i === 0 ? 'is-best' : ''}">
                    <span class="o-loc-simno">${i + 1}</span>
                    <div class="min-w-0"><b class="font-monospace">${esc(s.code)}</b><small>${esc(s.reason)}</small></div>
                    ${s.priority ? `<span class="o-loc-pbadge" style="--pc:${prioColor(s.priority)}">P${s.priority}</span>` : ''}
                </div>`).join('')
                : '<div class="text-muted small p-3 text-center"><i class="bi bi-exclamation-circle d-block fs-4 mb-1"></i>Tidak ada lokasi yang cocok (penuh / suhu beda / di luar area ketat).</div>';
            renderMap();
        }, 350);
    }
    $('simItem').addEventListener('change', simulate);

    $('locWarehouse')?.addEventListener('change', (e) => { location.href = `${C.urls.page}?warehouse=${e.target.value}`; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    window.LOCAPP = { select, get draft() { return draft; }, get rules() { return rules; }, addTarget, get sim() { return simResult; } };
    load(false).then(() => { D.tree.slice(0, 1).forEach((g) => open.add(`G${g.id}`)); renderTree(); simulate(); });
})();
