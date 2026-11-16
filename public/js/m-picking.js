/**
 * Picking mobile - wizard: 1 Wave -> (per baris) 2 Scan lokasi -> 3 Scan barang + qty -> baris berikutnya ... -> Selesai.
 * Endpoint JSON = PickingController (sama dengan desktop). Konfigurasi dari window.PCK.
 */
(() => {
    const C = window.PCK;
    const $ = (id) => document.getElementById(id);
    if (!$('stepper')) return; // tanpa akses
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const norm = (s) => String(s ?? '').trim().toUpperCase();
    const state = { step: 1, wave: null, line: null, staging: null, keyboard: false, key: null, verifiedLoc: false };
    const panels = [...document.querySelectorAll('.m-panel')];
    const focusIf = (el) => { if (state.keyboard && el) setTimeout(() => el.focus(), 60); };

    function note(step, html, type = 'info') {
        const el = document.querySelector(`[data-note="${step}"]`);
        if (!el) return;
        el.className = `m-coach-note is-${type}${html ? '' : ' d-none'}`;
        el.innerHTML = html || '';
    }
    const openLines = () => (state.wave?.lines || []).filter((l) => l.status === 'OPEN');

    // ------------------------------------------------------------ navigasi
    function go(n) {
        if (n > 1 && !state.wave) n = 1;
        if (n > 2 && !state.line) n = 2;
        const dir = n >= state.step ? 'fwd' : 'back';
        state.step = n;
        panels.forEach((p) => {
            const on = Number(p.dataset.step) === n;
            p.hidden = !on;
            p.classList.remove('enter-fwd', 'enter-back');
            if (on) { void p.offsetWidth; p.classList.add(`enter-${dir}`); }
        });
        document.querySelectorAll('#stepper [data-go]').forEach((b) => {
            const s = Number(b.dataset.go);
            const done = s < n;
            b.parentElement.classList.toggle('is-current', s === n);
            b.parentElement.classList.toggle('is-done', done || n === 4);
            b.disabled = n === 4 || (s > 1 && !state.wave) || (s > 2 && !state.line);
            b.querySelector('.m-stepper-dot').innerHTML = done || n === 4 ? '<i class="bi bi-check-lg"></i>' : s;
        });
        const p = state.wave?.progress;
        $('chips').innerHTML = state.wave && n > 1 && n < 4
            ? `<span class="m-chip"><i class="bi bi-collection"></i>${esc(state.wave.wave_no)}</span><span class="m-chip"><i class="bi bi-list-check"></i>${p.done}/${p.total} baris</span>`
            : '';
        $('wizBar').hidden = !(n === 2 || n === 3);
        if (n === 2) { $('btnMain').innerHTML = 'Sudah di lokasi, scan <i class="bi bi-chevron-right"></i>'; renderTarget($('target2')); }
        if (n === 3) { $('btnMain').innerHTML = '<i class="bi bi-check2"></i> Konfirmasi pick'; renderTarget($('target3'), true); focusIf($('fItem')); }
        if (n === 1) focusIf($('fWave'));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    $('stepper').addEventListener('click', (e) => { const b = e.target.closest('[data-go]'); if (b && !b.disabled) go(Number(b.dataset.go)); });
    $('btnBack').addEventListener('click', () => go(state.step - 1));
    $('btnMain').addEventListener('click', () => {
        if (state.step === 2) {
            // tanpa scan lokasi: boleh lanjut tapi diberi tanda (lokasi belum diverifikasi)
            state.verifiedLoc = false;
            note(3, `<i class="bi bi-info-circle"></i> Lokasi <b>${esc(state.line.location)}</b> belum di-scan. Pastikan Anda mengambil dari rak yang benar.`, 'info');
            go(3);
        } else if (state.step === 3) submitPick();
    });

    // ------------------------------------------------------------ 1. wave
    async function loadWave(idOrNo, byNo = false) {
        const r = byNo
            ? await WMS.api(C.urls.find, { method: 'POST', body: { wave_no: idOrNo } })
            : await WMS.api(`${C.urls.waves}/${idOrNo}`);
        if (!r.ok) {
            WMSScanner.feedback(false);
            note(1, `<i class="bi bi-exclamation-circle"></i> ${esc(r.data.message)}`, 'warning');
            return false;
        }
        WMSScanner.feedback(true);
        note(1, '');
        $('fWave').value = '';
        setWave(r.data);
        return true;
    }
    function setWave(w) {
        state.wave = w;
        state.staging = w.staging || w.stagings[0] || null;
        if (!w.stagings.length) { note(1, '<i class="bi bi-x-octagon"></i> Belum ada lokasi staging outbound di gudang ini. Hubungi admin.', 'warning'); state.wave = null; return; }
        renderStaging();
        const lines = openLines();
        if (!lines.length) { finish(); return; }
        setLine(lines[0]);
        go(2);
    }
    $('fWave').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        state.keyboard = true;
        if (e.target.value.trim()) loadWave(e.target.value.trim().toUpperCase(), true);
    });
    $('waveList').addEventListener('click', (e) => { const b = e.target.closest('[data-id]'); if (b) loadWave(b.dataset.id); });

    // ------------------------------------------------------------ 2. lokasi
    function renderStaging() {
        $('stagingList').innerHTML = state.wave.stagings.map((s) => `<button type="button" class="m-chip is-sm ${state.staging && s.id === state.staging.id ? 'is-active' : ''}" data-sid="${s.id}" data-code="${esc(s.code)}">${esc(s.code)}</button>`).join('');
    }
    $('stagingList').addEventListener('click', (e) => {
        const b = e.target.closest('[data-sid]'); if (!b) return;
        state.staging = { id: Number(b.dataset.sid), code: b.dataset.code };
        renderStaging();
    });
    function setLine(l) {
        state.line = l;
        state.key = WMS.uuid();
        state.verifiedLoc = false;
        $('lineList').innerHTML = openLines().map((x) => `<button type="button" class="m-pick ${x.id === l.id ? 'is-active' : ''}" data-line="${x.id}">
            <span class="min-w-0"><b>${esc(x.location)}</b> · ${esc(x.sku)}<span class="d-block small text-muted text-truncate">${esc(x.name)}${x.lot_no ? ' · Lot ' + esc(x.lot_no) : ''}</span></span>
            <span class="fw-bold text-nowrap">${esc(x.qty)} ${esc(x.uom)}</span></button>`).join('');
        note(2, ''); note(3, '');
        $('hItem').textContent = '';
        $('fQty').value = l.qty_raw; $('qtyNeed').textContent = `${l.qty} ${l.uom}`; $('qtyUom').textContent = `(${l.uom})`;
    }
    $('lineList').addEventListener('click', (e) => {
        const b = e.target.closest('[data-line]'); if (!b) return;
        const l = openLines().find((x) => x.id === Number(b.dataset.line));
        if (l) { setLine(l); renderTarget($('target2')); }
    });
    function renderTarget(el, small = false) {
        const l = state.line; if (!l) return;
        el.innerHTML = `<span class="m-target-lbl">${small ? 'Ambil di ' + esc(l.location) : 'Lokasi pick'}</span>
            <span class="m-target-code">${small ? esc(l.sku) : esc(l.location)}</span>
            <span class="m-target-why">${small ? esc(l.name) : esc(l.sku) + ' · ' + esc(l.name)}${l.lot_no ? ' · Lot ' + esc(l.lot_no) : ''}${l.expiry_date ? ' · Exp ' + esc(l.expiry_date) : ''}${l.lpn_no ? ' · ' + esc(l.lpn_no) : ''} · <b>${esc(l.qty)} ${esc(l.uom)}</b> · ${esc(l.shipment_no)}</span>`;
    }
    function scanLocation(code) {
        code = norm(code);
        if (!code) return;
        if (code === norm(state.line.location)) {
            WMSScanner.feedback(true); state.verifiedLoc = true; note(2, ''); note(3, ''); go(3); return;
        }
        // scan lokasi lain yang juga ada di daftar? pindah ke baris itu
        const other = openLines().find((x) => norm(x.location) === code);
        if (other) { WMSScanner.feedback(true); setLine(other); state.verifiedLoc = true; go(3); MToast(`Pindah ke baris ${other.location}`); return; }
        WMSScanner.feedback(false);
        note(2, `<i class="bi bi-exclamation-triangle"></i> Anda scan <b>${esc(code)}</b>, tujuan <b>${esc(state.line.location)}</b>. Cek lagi rak-nya.`, 'warning');
    }

    // ------------------------------------------------------------ 3. barang + qty
    function scanItem(code) {
        code = norm(code);
        $('fItem').value = '';
        if (!code) return;
        const l = state.line;
        const ok = (l.barcodes || []).some((b) => norm(b) === code) || (l.lpn_no && norm(l.lpn_no) === code);
        if (!ok) {
            WMSScanner.feedback(false);
            $('hItem').className = 'm-hint text-danger';
            $('hItem').textContent = `Kode ${code} bukan ${l.sku}${l.lpn_no ? ' / ' + l.lpn_no : ''}.`;
            return;
        }
        WMSScanner.feedback(true);
        $('hItem').className = 'm-hint text-success';
        $('hItem').textContent = `Barang cocok: ${l.sku}`;
        focusIf($('fQty'));
    }
    $('fItem').addEventListener('keydown', (e) => { if (e.key !== 'Enter') return; e.preventDefault(); state.keyboard = true; scanItem(e.target.value); });
    $('qtyMinus').addEventListener('click', () => { $('fQty').value = Math.max(0, (parseFloat($('fQty').value) || 0) - 1); });
    $('qtyPlus').addEventListener('click', () => { $('fQty').value = Math.min(parseFloat(state.line.qty_raw), (parseFloat($('fQty').value) || 0) + 1); });
    $('fQty').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); submitPick(); } });

    async function submitPick(confirmFlag = false) {
        const qty = $('fQty').value;
        if (qty === '' || Number(qty) < 0) return note(3, '<i class="bi bi-exclamation-circle"></i> Isi qty yang diambil.', 'warning');
        const r = await WMS.api(`${C.urls.lines}/${state.line.id}/pick`, {
            method: 'POST', body: { qty, staging_location_id: state.staging?.id, confirm: confirmFlag ? 1 : 0, idempotency_key: state.key, source: 'MOBILE' },
        });
        if (r.status === 409 && r.data.confirm) {
            const text = r.data.warnings.join('\n');
            if (!r.data.can_confirm) { WMSScanner.feedback(false); return note(3, `<i class="bi bi-x-octagon"></i> ${esc(text)} Minta SPV untuk menyetujui short pick.`, 'warning'); }
            if (confirm(`${text}\n\nTetap lanjutkan?`)) return submitPick(true);
            return;
        }
        if (!r.ok) { WMSScanner.feedback(false); return note(3, esc(r.data.message), 'warning'); }
        WMSScanner.feedback(true);
        MToast(r.data.message);
        // muat ulang wave -> baris berikutnya
        const w = await WMS.api(`${C.urls.waves}/${state.wave.id}`);
        if (!w.ok) return location.reload();
        state.wave = w.data;
        const next = openLines()[0];
        if (!next || w.data.status === 'PICKED') return finish();
        setLine(next);
        go(2);
    }

    function finish() {
        const p = state.wave.progress;
        $('doneTitle').textContent = p.open ? 'Semua baris sudah ditangani' : `Wave ${state.wave.wave_no} selesai`;
        $('doneText').innerHTML = `${p.done} baris dipick${p.short ? `, <b>${p.short} kurang</b>` : ''}. Barang ada di staging <b>${esc(state.staging?.code || '-')}</b>. Lanjut ke Dispatch untuk Surat Jalan.`;
        const done = document.querySelector('.m-done');
        done.classList.remove('play'); void done.offsetWidth; done.classList.add('play');
        go(4);
    }

    // ------------------------------------------------------------ kamera
    document.querySelectorAll('[data-scan]').forEach((b) => b.addEventListener('click', () => {
        state.keyboard = false;
        const kind = b.dataset.scan;
        WMSScanner.open({
            title: kind === 'wave' ? 'Scan nomor wave' : (kind === 'item' ? 'Scan barcode barang / LPN' : 'Scan label lokasi'),
            onResult: (code) => (kind === 'wave' ? loadWave(code.toUpperCase(), true) : scanItem(code)),
        });
    }));
    // langkah 2: scanner genggam (keyboard wedge) boleh menembak tanpa fokus ke input
    let wedge = '';
    document.addEventListener('keydown', (e) => {
        if (state.step !== 2 || e.target.matches('input, textarea')) return;
        if (e.key === 'Enter' && wedge) { scanLocation(wedge); wedge = ''; return; }
        if (e.key.length === 1) wedge += e.key;
    });
    document.getElementById('target2')?.insertAdjacentHTML('afterend',
        '<button type="button" class="m-bigscan" id="scanLoc"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan lokasi</span></button>'
        + '<div class="m-scanbar mb-0"><input id="fLoc" class="form-control font-monospace text-uppercase" placeholder="atau ketik kode lokasi" enterkeyhint="go"></div>');
    $('scanLoc').addEventListener('click', () => { state.keyboard = false; WMSScanner.open({ title: 'Scan label lokasi rak', onResult: scanLocation }); });
    $('fLoc').addEventListener('keydown', (e) => { if (e.key !== 'Enter') return; e.preventDefault(); state.keyboard = true; scanLocation(e.target.value); e.target.value = ''; });

    $('btnNext').addEventListener('click', () => location.reload());
    go(1);
})();
