/**
 * Layar penerimaan mobile (operator) - wizard langkah demi langkah.
 *
 *   1 Dock -> 2 LPN -> 3 Item -> 4 Detail (qty/lot/expired/kondisi) -> 5 Cek -> Terima -> 6 Selesai
 *
 * - Satu panel tampil per langkah; setiap panel punya "coach" (panduan singkat) + catatan kontekstual.
 * - Scan sukses (kamera / scanner genggam) langsung maju ke langkah berikutnya.
 * - Setelah Terima: dock & LPN dipertahankan -> "Scan barang berikutnya" kembali ke langkah 3.
 * - Panduan animasi (MGuide) tampil otomatis saat pertama kali, bisa dibuka ulang via tombol Panduan.
 * Konfigurasi dari window.RCV (lihat resources/views/mobile/receiving/show.blade.php).
 */
(() => {
    const C = window.RCV;
    const $ = (id) => document.getElementById(id);
    const fmt = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const store = {
        get: (k) => { try { return localStorage.getItem(k); } catch { return null; } },
        set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* mode privat */ } },
    };

    // ------------------------------------------------------------ progres dokumen
    let progress = C.progress;
    function renderProgress(p) {
        progress = p;
        $('pBar').style.width = `${p.percent}%`;
        $('pText').textContent = `${p.percent}%`;
        $('pItems').innerHTML = p.items.map((it) => `
            <div class="m-plist-row">
                <div class="min-w-0"><div class="fw-semibold text-truncate">${esc(it.sku)}</div><div class="small text-muted text-truncate">${esc(it.name)}</div></div>
                <div class="text-end"><div class="fw-semibold ${it.remaining === 0 ? 'text-success' : ''}">${fmt(it.received)} / ${fmt(it.expected)}</div>
                <div class="small text-muted">${it.remaining > 0 ? 'sisa ' + fmt(it.remaining) : (it.received > it.expected ? 'lebih' : 'lengkap')} ${esc(it.base_uom)}</div></div>
            </div>`).join('');
        renderPickList();
    }

    const form = $('rcvForm');
    if (!form) { renderProgress(C.progress); return; } // tidak boleh menerima (status / hak akses)

    const state = {
        step: 1,
        dockId: null, docks: {},          // docks: id -> status kapasitas dari server
        lpnNo: '', lpnOk: false, lpnInfo: null, newLpn: false,
        item: null, cond: 'GOOD',
        key: WMS.uuid(),                  // idempotency key transaksi ini
        keyboard: false,                  // true = scanner genggam / keyboard (pakai Enter)
        last: null,                       // baris terakhir yang diterima
    };

    function hint(id, text, type = 'muted') {
        const el = $(id);
        el.className = `m-hint text-${type}`;
        el.textContent = text || '';
    }
    function note(step, html, type = 'info') {
        const el = document.querySelector(`[data-note="${step}"]`);
        if (!el) return;
        el.className = `m-coach-note is-${type}${html ? '' : ' d-none'}`;
        el.innerHTML = html || '';
    }
    // Scanner genggam: pindah fokus otomatis. Kamera: jangan munculkan keyboard HP.
    const focusIf = (el) => { if (state.keyboard && el) setTimeout(() => el.focus(), 60); };

    // ================================================================ NAVIGASI WIZARD
    const panels = [...document.querySelectorAll('.m-panel')];
    const valid = {
        1: () => !!state.dockId,
        2: () => state.lpnOk || state.newLpn,
        3: () => !!state.item,
        4: () => detailErrors().length === 0,
        5: () => true,
    };
    const canEnter = (n) => n <= 6 && [1, 2, 3, 4, 5].filter((s) => s < n).every((s) => valid[s]());

    function go(n, { scroll = true } = {}) {
        if (n < 6 && !canEnter(n)) return;
        const dir = n >= state.step ? 'fwd' : 'back';
        state.step = n;
        panels.forEach((p) => {
            const on = Number(p.dataset.step) === n;
            p.hidden = !on;
            p.classList.remove('enter-fwd', 'enter-back');
            if (on) { void p.offsetWidth; p.classList.add(`enter-${dir}`); }
        });
        onEnter(n);
        renderStepper();
        renderChips();
        syncBar();
        if (scroll) document.getElementById('stepper').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function onEnter(n) {
        if (n === 1) focusIf($('fDockCode'));
        if (n === 2) { renderLpnChoice(); focusIf($('fLpn')); }
        if (n === 3) {
            const item = state.item;
            note(3, item ? `Barang terpilih: <b>${esc(item.sku)}</b>. Scan lagi untuk mengganti, atau tekan Lanjut.` : '');
            focusIf($('fItemCode'));
        }
        if (n === 4) { updateBase(); focusIf($('fQty')); }
        if (n === 5) { renderReview(); focusIf($('btnNext')); }
        if (n === 6) focusIf($('fNextScan'));
    }

    function renderStepper() {
        document.querySelectorAll('#stepper [data-go]').forEach((b) => {
            const n = Number(b.dataset.go);
            const li = b.parentElement;
            const done = n < state.step && valid[n]();
            li.classList.toggle('is-current', n === state.step);
            li.classList.toggle('is-done', done || state.step === 6);
            b.disabled = !canEnter(n) || state.step === 6;
            b.querySelector('.m-stepper-dot').innerHTML = done || state.step === 6 ? '<i class="bi bi-check-lg"></i>' : n;
        });
    }

    function renderChips() {
        const chips = [];
        const dock = state.docks[state.dockId];
        if (state.dockId && state.step > 1) chips.push([1, 'geo-alt-fill', dock?.code ?? '-']);
        if (valid[2]() && state.step > 2) chips.push([2, 'upc', state.newLpn ? 'LPN baru' : state.lpnNo]);
        if (state.item && state.step > 3) chips.push([3, 'box2', state.item.sku]);
        $('chips').innerHTML = state.step === 6 ? '' : chips.map(([s, i, t]) =>
            `<button type="button" class="m-chip" data-go="${s}"><i class="bi bi-${i}"></i>${esc(t)}<i class="bi bi-pencil m-chip-edit"></i></button>`).join('');
    }
    $('chips').addEventListener('click', (e) => { const b = e.target.closest('[data-go]'); if (b) go(Number(b.dataset.go)); });
    $('stepper').addEventListener('click', (e) => { const b = e.target.closest('[data-go]'); if (b && !b.disabled) go(Number(b.dataset.go)); });

    const LABELS = { 1: 'Lanjut ke LPN', 2: 'Lanjut ke barang', 3: 'Lanjut isi qty', 4: 'Cek ringkasan', 5: 'Terima' };
    function syncBar() {
        $('wizBar').hidden = state.step === 6;
        $('btnBack').style.display = state.step > 1 ? '' : 'none';
        const btn = $('btnNext');
        if (btn.dataset.busy) return;
        btn.innerHTML = state.step === 5 ? '<i class="bi bi-box-arrow-in-down me-1"></i> Terima' : `${LABELS[state.step] ?? 'Lanjut'} <i class="bi bi-chevron-right"></i>`;
        btn.classList.toggle('btn-success', state.step === 5);
        btn.classList.toggle('btn-primary', state.step !== 5);
        // Langkah 2 boleh ditekan walau belum dicek (nomor yang diketik dicek dulu).
        // Langkah 4 selalu aktif: bila belum lengkap, operator diberi tahu apa yang kurang.
        if (state.step === 2) btn.disabled = !(valid[2]() || $('fLpn').value.trim());
        else if (state.step === 4) btn.disabled = !state.item;
        else btn.disabled = !(valid[state.step]?.() ?? false);
    }
    $('btnBack').addEventListener('click', () => go(Math.max(1, state.step - 1)));

    // Tombol utama (juga Enter di dalam form)
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (state.step === 2 && !valid[2]()) { if (await checkLpn()) go(3); return; }
        if (state.step === 4) {
            const errs = detailErrors();
            if (errs.length) {
                const target = !(parseFloat(qtyInp.value) > 0) ? qtyInp : (errs[0].startsWith('Lot') ? $('fLot') : $('fExp'));
                target.classList.add('m-shake');
                setTimeout(() => target.classList.remove('m-shake'), 600);
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return MToast(errs[0], 'warning');
            }
        }
        if (state.step === 5) return send(payload());
        if (valid[state.step]()) go(state.step + 1);
    });

    // ================================================================ 1. DOCK
    function applyDockStatus(s, forNewLpn) {
        s.docks.forEach((d) => { state.docks[d.id] = d; });
        $('dockFull').classList.toggle('d-none', !s.all_full);

        if (!state.dockId || !state.docks[state.dockId]) {
            state.dockId = s.suggested?.id ?? s.docks[0]?.id ?? null;
        }
        const cur = state.docks[state.dockId];
        if (s.all_full) {
            hint('hDock', 'Semua dock penuh. Pallet baru belum bisa ditaruh.', 'danger');
        } else if (cur?.full && forNewLpn && s.suggested) {
            const from = cur.code;
            state.dockId = s.suggested.id;
            store.set('mDock', state.dockId);
            hint('hDock', `Dock ${from} penuh, dialihkan ke ${s.suggested.code}.`, 'primary');
            note(2, `<i class="bi bi-arrow-left-right"></i> Dock <b>${esc(from)}</b> penuh. Pallet baru ditaruh di <b>${esc(s.suggested.code)}</b>.`, 'warning');
            MToast(`Dock dialihkan ke ${s.suggested.code}`, 'warning');
        } else if (cur?.full) {
            hint('hDock', `Dock ${cur.code} penuh. Hanya untuk menambah isi pallet yang sudah ada di sini.`, 'warning');
        } else {
            hint('hDock', '');
        }
        state.suggestedId = s.suggested?.id ?? null;
        renderDocks();
        renderChips();
        syncBar();
    }

    function renderDocks() {
        const suggestedId = state.suggestedId;
        const list = Object.values(state.docks).sort((a, b) => a.code.localeCompare(b.code));
        $('dockList').innerHTML = list.map((d) => {
            const pct = d.percent ?? 0;
            const tone = d.full ? 'danger' : (pct >= 80 ? 'warning' : 'success');
            const used = d.max_lpn ? `${d.used_lpn}/${d.max_lpn} pallet` : `${d.used_lpn} pallet`;
            const tag = d.full ? '<span class="m-dock-tag is-full">Penuh</span>'
                : (d.id === suggestedId ? '<span class="m-dock-tag">Disarankan</span>' : '');
            return `<button type="button" class="m-dock ${d.id === state.dockId ? 'is-selected' : ''} ${d.full ? 'is-full' : ''}" data-id="${d.id}">
                <span class="m-dock-head"><b>${esc(d.code)}</b>${tag}</span>
                <span class="m-dock-bar"><span class="bg-${tone}" style="width:${d.percent === null ? 0 : pct}%"></span></span>
                <span class="m-dock-used">${used}${d.percent === null ? ' · tanpa batas' : ''}</span>
                <i class="bi bi-check-circle-fill m-dock-check"></i>
            </button>`;
        }).join('') || '<div class="m-empty small py-3">Belum ada lokasi dock (fungsi RECEIVING) aktif di gudang ini.</div>';
    }

    function selectDock(id, { advance = false } = {}) {
        state.dockId = Number(id);
        store.set('mDock', state.dockId);
        const d = state.docks[state.dockId];
        hint('hDock', d?.full ? `Dock ${d.code} penuh. Hanya untuk menambah isi pallet yang sudah ada di sini.` : '', 'warning');
        renderDocks();
        syncBar();
        if (advance) go(2);
    }
    $('dockList').addEventListener('click', (e) => {
        const b = e.target.closest('.m-dock');
        if (b) selectDock(b.dataset.id);
    });

    async function refreshDocks(forNewLpn = false) {
        const r = await WMS.api(C.urls.docks, { params: { current: state.dockId ?? '' } });
        if (r.ok) applyDockStatus(r.data, forNewLpn);
    }

    async function setDockByCode(code) {
        if (!code) return;
        const r = await WMS.api(C.urls.location, { params: { code } });
        if (!r.ok) { WMSScanner.feedback(false); hint('hDock', r.data.message, 'danger'); return; }
        if (!state.docks[r.data.id]) state.docks[r.data.id] = { id: r.data.id, code: r.data.code, used_lpn: 0, max_lpn: null, percent: null, full: false };
        $('fDockCode').value = '';
        WMSScanner.feedback(true);
        selectDock(r.data.id, { advance: true });
        refreshDocks(false);
    }
    $('fDockCode').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        state.keyboard = true;
        setDockByCode(e.target.value.trim());
    });

    $('btnRecheck').addEventListener('click', () => refreshDocks(true));
    $('btnNotify').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        const r = await WMS.api(C.urls.notify, { method: 'POST', body: {} });
        MToast(r.data.message, r.ok ? 'success' : 'warning', 5000);
        setTimeout(() => { btn.disabled = false; }, 30000);
    });

    // ================================================================ 2. LPN
    const lpnInp = $('fLpn');

    function renderLpnChoice() {
        $('btnNewLpn').classList.toggle('is-selected', state.newLpn);
    }

    async function checkLpn() {
        const code = lpnInp.value.trim().toUpperCase();
        if (!code) { hint('hLpn', 'Scan label LPN, atau pilih "Pallet baru".', 'danger'); return false; }
        const r = await WMS.api(C.urls.lpn, { params: { code } });
        if (!r.ok) {
            WMSScanner.feedback(false);
            hint('hLpn', r.data.message, 'danger');
            note(2, '<i class="bi bi-lightbulb"></i> Pastikan yang di-scan label <b>LPN</b> (bukan barcode barang). Label rusak? Pilih <b>Pallet baru</b>.', 'warning');
            lpnInp.select();
            return false;
        }
        WMSScanner.feedback(true);
        Object.assign(state, { lpnNo: r.data.lpn_no, lpnOk: true, lpnInfo: r.data, newLpn: false });
        lpnInp.value = r.data.lpn_no;
        hint('hLpn', `${r.data.lpn_no}: ${r.data.message}`, r.data.exists ? 'success' : 'primary');
        renderLpnChoice();

        if (r.data.active && r.data.location_id) {
            // LPN sudah berisi stok di dock tertentu -> lanjutkan di dock yang sama
            const moved = Number(r.data.location_id) !== Number(state.dockId);
            state.dockId = Number(r.data.location_id);
            if (moved) MToast(`Pallet ini ada di ${r.data.location}, dock disesuaikan.`, 'warning', 3500);
            note(2, `<i class="bi bi-info-circle"></i> Pallet ini sudah berisi stok di <b>${esc(r.data.location)}</b>. Barang akan ditambahkan ke pallet yang sama.`);
            refreshDocks(false);
        } else {
            note(2, '');
            refreshDocks(true); // pallet baru -> pastikan dock-nya muat (di latar; bila dialihkan, operator diberi toast)
        }
        return true;
    }

    lpnInp.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        state.keyboard = true;
        if (await checkLpn()) go(3);
    });
    lpnInp.addEventListener('input', () => {
        Object.assign(state, { lpnOk: false, lpnInfo: null, newLpn: false, lpnNo: '' });
        hint('hLpn', '');
        renderLpnChoice();
        syncBar();
    });
    $('btnNewLpn').addEventListener('click', async () => {
        lpnInp.value = '';
        Object.assign(state, { lpnOk: false, lpnInfo: null, lpnNo: '', newLpn: true });
        hint('hLpn', 'Nomor LPN dibuat otomatis saat Terima. Tulis / tempel nomornya di pallet.', 'primary');
        note(2, '');
        renderLpnChoice();
        go(3);
        refreshDocks(true);
    });

    // ================================================================ 3. ITEM
    const itemInp = $('fItemCode');
    const uomSel = $('fUom');
    const qtyInp = $('fQty');

    function renderPickList() {
        const el = $('pickItems');
        if (!el) return;
        el.innerHTML = progress.items.map((it) => `
            <button type="button" class="m-pick ${it.remaining === 0 ? 'is-done' : ''}" data-sku="${esc(it.sku)}">
                <span class="min-w-0"><b>${esc(it.sku)}</b><span class="d-block small text-muted text-truncate">${esc(it.name)}</span></span>
                <span class="text-end small">${it.remaining > 0 ? 'sisa <b>' + fmt(it.remaining) + '</b>' : '<span class="text-success">lengkap</span>'}<span class="d-block text-muted">${esc(it.base_uom)}</span></span>
            </button>`).join('');
    }
    $('pickItems').addEventListener('click', (e) => {
        const b = e.target.closest('.m-pick');
        if (b) { itemInp.value = b.dataset.sku; lookupItem(b.dataset.sku); }
    });

    async function lookupItem(code) {
        if (!code) return;
        const r = await WMS.api(C.urls.item, { params: { code } });
        if (!r.ok) {
            WMSScanner.feedback(false);
            hint('hItem', r.data.message, 'danger');
            note(3, '<i class="bi bi-lightbulb"></i> Barang tidak ada di dokumen ini? Pisahkan dulu & lapor SPV. Barcode tidak terbaca? Pilih dari daftar di bawah.', 'warning');
            state.item = null;
            syncBar();
            itemInp.select();
            return;
        }
        WMSScanner.feedback(true);
        hint('hItem', '');
        setItem(r.data);
        go(4);
    }

    function setItem(d) {
        const changed = state.item?.item_id !== d.item_id;
        state.item = d;
        $('iName').textContent = `[${d.sku}] ${d.name}`;
        $('iMeta').innerHTML = `Diterima <b>${fmt(d.received)}</b> dari ${fmt(d.expected)} ${esc(d.base_uom)} · sisa <b>${fmt(d.remaining)}</b>`;
        uomSel.innerHTML = '';
        d.uoms.forEach((u) => {
            const o = new Option(u.factor === '1' ? u.code : `${u.code} (${fmt(u.factor)})`, u.id);
            o.dataset.factor = u.factor;
            o.dataset.code = u.code;
            uomSel.add(o);
        });
        uomSel.value = d.scanned_uom_id;
        $('lotReq').classList.toggle('d-none', !d.lot_required);
        $('expReq').classList.toggle('d-none', !d.expiry_required);
        $('lotList').innerHTML = d.asn_lots.map((l) => `<option value="${esc(l)}">`).join('');
        $('lotChips').innerHTML = d.asn_lots.length
            ? '<span class="small text-muted me-1">Lot di dokumen:</span>' + d.asn_lots.map((l) => `<button type="button" class="m-chip is-sm" data-lot="${esc(l)}">${esc(l)}</button>`).join('')
            : '';
        if (changed) { qtyInp.value = ''; $('fLot').value = ''; $('fExp').value = ''; }
        updateBase();
    }
    $('lotChips').addEventListener('click', (e) => {
        const b = e.target.closest('[data-lot]');
        if (b) { $('fLot').value = b.dataset.lot; updateBase(); }
    });

    itemInp.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        state.keyboard = true;
        lookupItem(itemInp.value.trim());
    });

    // ================================================================ 4. DETAIL
    const factor = () => parseFloat(uomSel.selectedOptions[0]?.dataset.factor || 0);
    const baseQty = () => (parseFloat(qtyInp.value) || 0) * factor();

    function detailErrors() {
        const it = state.item;
        const errs = [];
        if (!it) return ['Scan barang dulu.'];
        if (!(parseFloat(qtyInp.value) > 0)) errs.push('Isi qty yang dihitung.');
        if (it.lot_required && !$('fLot').value.trim()) errs.push('Lot wajib diisi (lihat kemasan).');
        if (it.expiry_required && !$('fExp').value) errs.push('Tanggal expired wajib diisi (lihat kemasan).');
        return errs;
    }

    function check(id, html, type) {
        const el = $(id);
        el.className = `m-check is-${type}${html ? '' : ' d-none'}`;
        el.innerHTML = html || '';
    }

    function updateBase() {
        const it = state.item;
        const q = parseFloat(qtyInp.value || 0);
        const f = factor();
        $('iBase').textContent = it && f !== 1 && q ? `= ${fmt(q * f)} ${it.base_uom}` : '';

        // cek qty terhadap sisa dokumen (server tetap memvalidasi)
        if (it && q > 0 && baseQty() > it.remaining) {
            check('cQty', `<i class="bi bi-exclamation-triangle-fill"></i><span>Melebihi sisa dokumen (sisa ${fmt(it.remaining)} ${esc(it.base_uom)}). Hitung ulang; bila memang lebih, perlu persetujuan SPV.</span>`, 'warning');
        } else if (it && q > 0 && baseQty() === it.remaining) {
            check('cQty', '<i class="bi bi-check-circle-fill"></i><span>Pas, item ini akan lengkap.</span>', 'success');
        } else {
            check('cQty', '');
        }

        // cek expired
        const exp = $('fExp').value;
        if (exp) {
            const days = Math.round((new Date(exp) - new Date(new Date().toDateString())) / 86400000);
            if (days < 0) check('cExp', '<i class="bi bi-x-octagon-fill"></i><span>Tanggal sudah lewat, barang <b>kedaluwarsa</b>. Pisahkan & lapor SPV.</span>', 'danger');
            else if (days < 30) check('cExp', `<i class="bi bi-hourglass-split"></i><span>Expired ${days} hari lagi. Pastikan tanggal benar.</span>`, 'warning');
            else check('cExp', '');
        } else check('cExp', '');

        syncBar();
    }
    qtyInp.addEventListener('input', updateBase);
    uomSel.addEventListener('change', updateBase);
    $('fLot').addEventListener('input', updateBase);
    $('fExp').addEventListener('change', updateBase);
    qtyInp.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        if (state.item?.lot_required && !$('fLot').value) return $('fLot').focus();
        form.requestSubmit();
    });
    document.querySelectorAll('.m-qty-btn').forEach((b) => b.addEventListener('click', () => {
        const v = Math.max(0, (parseFloat(qtyInp.value) || 0) + Number(b.dataset.step));
        qtyInp.value = v || '';
        updateBase();
    }));
    document.querySelectorAll('#fCond .m-seg-btn').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('#fCond .m-seg-btn').forEach((x) => x.classList.toggle('active', x === b));
        state.cond = b.dataset.value;
        $('cCond').classList.toggle('d-none', state.cond !== 'DAMAGED');
    }));

    // ================================================================ 5. CEK
    function renderReview() {
        const it = state.item;
        const dock = state.docks[state.dockId];
        const uom = uomSel.selectedOptions[0]?.dataset.code ?? '';
        const f = factor();
        const rows = [
            [1, 'Dock', esc(dock?.code ?? '-')],
            [2, 'LPN', state.newLpn ? '<span class="text-primary">Baru (nomor otomatis)</span>' : esc(state.lpnNo)],
            [3, 'Barang', `<b>${esc(it.sku)}</b><span class="d-block small text-muted">${esc(it.name)}</span>`],
            [4, 'Qty', `<b class="fs-5">${fmt(qtyInp.value)} ${esc(uom)}</b>${f !== 1 ? `<span class="d-block small text-muted">= ${fmt(baseQty())} ${esc(it.base_uom)}</span>` : ''}`],
        ];
        if ($('fLot').value.trim()) rows.push([4, 'Lot', esc($('fLot').value.trim().toUpperCase())]);
        if ($('fExp').value) rows.push([4, 'Expired', new Date($('fExp').value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })]);
        rows.push([4, 'Kondisi', state.cond === 'DAMAGED' ? '<span class="text-danger fw-semibold">Rusak</span>' : '<span class="text-success fw-semibold">Baik</span>']);
        $('review').innerHTML = rows.map(([s, k, v]) => `
            <div class="m-review-row"><span class="m-review-k">${k}</span><span class="m-review-v">${v}</span>
            <button type="button" class="m-review-edit" data-go="${s}">Ubah</button></div>`).join('');
    }
    $('review').addEventListener('click', (e) => { const b = e.target.closest('[data-go]'); if (b) go(Number(b.dataset.go)); });

    // ================================================================ tombol kamera
    document.querySelectorAll('[data-scan]').forEach((btn) => btn.addEventListener('click', () => openScanner(btn.dataset.scan)));
    function openScanner(kind) {
        state.keyboard = false;
        WMSScanner.open({
            title: { dock: 'Scan label lokasi dock', lpn: 'Scan label LPN pallet', item: 'Scan barcode barang' }[kind],
            onResult: async (code) => {
                if (kind === 'dock') return setDockByCode(code);
                if (kind === 'lpn') { lpnInp.value = code.toUpperCase(); if (await checkLpn()) go(3); return; }
                itemInp.value = code;
                lookupItem(code);
            },
        });
    }

    // ================================================================ TERIMA
    function payload() {
        return {
            location_id: state.dockId,
            lpn_no: state.newLpn ? '' : state.lpnNo,
            item_id: state.item.item_id,
            uom_id: uomSel.value,
            qty: qtyInp.value,
            lot_no: $('fLot').value.trim(),
            expiry_date: $('fExp').value || null,
            condition: state.cond,
            idempotency_key: state.key,   // sama selama transaksi ini belum sukses -> kirim ulang aman
            source: 'MOBILE',
        };
    }

    async function send(body) {
        const btn = $('btnNext');
        btn.disabled = true;
        btn.dataset.busy = '1';
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';
        const r = await WMS.api(C.urls.receive, { method: 'POST', body });
        delete btn.dataset.busy;
        syncBar();

        if (r.status === 409 && r.data.confirm) {
            const text = (r.data.warnings || [r.data.message]).join('\n');
            if (!r.data.can_confirm) return MToast(`${text} Minta persetujuan SPV.`, 'danger', 6000);
            if (confirm(`${text}\n\nTetap terima?`)) return send({ ...body, confirm: 1 });
            return;
        }
        if (!r.ok) {
            WMSScanner.feedback(false);
            return MToast(r.data.message, 'danger', 5000);
        }

        WMSScanner.feedback(true);
        const l = r.data.line;
        state.last = l;
        addRecent(l);
        renderProgress(r.data.progress);
        if (r.data.dock) applyDockStatus(r.data.dock, false);

        // pallet tetap sama untuk barang berikutnya
        Object.assign(state, { lpnNo: l.lpn_no, lpnOk: true, newLpn: false, item: null, key: WMS.uuid() });
        lpnInp.value = l.lpn_no;
        hint('hLpn', '');
        itemInp.value = ''; qtyInp.value = ''; $('fLot').value = ''; $('fExp').value = '';
        document.querySelector('#fCond .m-seg-btn').click();
        ['cQty', 'cExp'].forEach((id) => check(id, ''));

        $('doneTitle').textContent = `${fmt(l.qty)} ${l.uom} ${l.sku} diterima`;
        $('doneText').innerHTML = `Masuk ke LPN <b>${esc(l.lpn_no)}</b> di dock <b>${esc(state.docks[state.dockId]?.code ?? '-')}</b>.`
            + (body.lpn_no === '' ? `<div class="m-check is-info mt-2"><i class="bi bi-pencil-square"></i><span>LPN baru: tulis / tempel <b>${esc(l.lpn_no)}</b> di pallet sekarang.</span></div>` : '');
        $('doneAll').classList.toggle('d-none', r.data.progress.percent < 100);
        $('nextItemSub').textContent = `ke pallet ${l.lpn_no}`;
        $('nextScanWrap').classList.toggle('d-none', !state.keyboard);
        $('fNextScan').value = '';
        const done = document.querySelector('.m-done');
        done.classList.remove('play'); void done.offsetWidth; done.classList.add('play');
        go(6);
    }

    $('btnNextItem').addEventListener('click', () => {
        go(3, { scroll: false });
        if (!state.keyboard) openScanner('item'); // satu ketukan langsung buka kamera
    });
    $('btnNextPallet').addEventListener('click', () => {
        lpnInp.value = '';
        Object.assign(state, { lpnNo: '', lpnOk: false, lpnInfo: null, newLpn: false });
        note(2, '');
        go(2);
        refreshDocks(true);
    });
    // scanner genggam di layar selesai: langsung scan barang berikutnya
    $('fNextScan').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const code = e.target.value.trim();
        if (!code) return;
        go(3, { scroll: false });
        itemInp.value = code;
        lookupItem(code);
    });

    function addRecent(l) {
        $('recentEmpty')?.remove();
        const el = document.createElement('div');
        el.className = 'm-card m-recent';
        el.innerHTML = `
            <div class="d-flex justify-content-between"><span class="fw-semibold">${esc(l.sku)}</span><span class="fw-bold">${fmt(l.qty)} ${esc(l.uom)}</span></div>
            <div class="small text-muted">${esc(l.lpn_no)} · ${l.lot_no ? 'Lot ' + esc(l.lot_no) + ' · ' : ''}${l.expiry_date ? 'Exp ' + esc(l.expiry_date) + ' · ' : ''}${esc(l.condition)} · ${esc(l.time)}</div>`;
        $('recentList').prepend(el);
    }

    // ================================================================ PANDUAN
    const guide = MGuide.create($('rcvGuide'), {
        key: `mGuide:receiving:v2:${C.userId}`,
        onClose: () => focusIf(panels.find((p) => !p.hidden)?.querySelector('input')),
    });
    $('btnGuide').addEventListener('click', () => guide.open(0));
    document.querySelectorAll('[data-guide]').forEach((b) => b.addEventListener('click', () => guide.open(b.dataset.guide)));

    // ================================================================ mulai
    renderProgress(C.progress);
    const saved = Number(store.get('mDock'));
    if (saved && C.dockStatus.docks.some((d) => d.id === saved)) state.dockId = saved;
    applyDockStatus(C.dockStatus, true); // belum ada pallet -> anggap pallet baru
    go(1, { scroll: false });
    guide.autoOpen();
})();
