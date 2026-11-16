/**
 * Putaway mobile - wizard: 1 Pallet (scan LPN) -> 2 Saran lokasi -> 3 Scan lokasi tujuan -> Selesai.
 * Endpoint JSON = PutawayController (sama dengan desktop). Konfigurasi dari window.PTW.
 */
(() => {
    const C = window.PTW;
    const $ = (id) => document.getElementById(id);
    if (!$('stepper')) return; // tanpa akses
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const state = { step: 1, task: null, target: null, keyboard: false };
    const panels = [...document.querySelectorAll('.m-panel')];
    const focusIf = (el) => { if (state.keyboard && el) setTimeout(() => el.focus(), 60); };

    function note(step, html, type = 'info') {
        const el = document.querySelector(`[data-note="${step}"]`);
        if (!el) return;
        el.className = `m-coach-note is-${type}${html ? '' : ' d-none'}`;
        el.innerHTML = html || '';
    }

    // ------------------------------------------------------------ navigasi
    function go(n) {
        if (n > 1 && !state.task) n = 1;
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
            b.disabled = n === 4 || (s > 1 && !state.task);
            b.querySelector('.m-stepper-dot').innerHTML = done || n === 4 ? '<i class="bi bi-check-lg"></i>' : s;
        });
        $('chips').innerHTML = state.task && n > 1 && n < 4
            ? `<span class="m-chip"><i class="bi bi-upc"></i>${esc(state.task.lpn_no)}</span><span class="m-chip"><i class="bi bi-geo-alt-fill"></i>${esc(state.task.from)}</span>`
            : '';
        $('wizBar').hidden = n !== 2;
        if (n === 1) focusIf($('fLpn'));
        if (n === 3) { renderTarget($('target3')); focusIf($('fLoc')); }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    $('stepper').addEventListener('click', (e) => { const b = e.target.closest('[data-go]'); if (b && !b.disabled) go(Number(b.dataset.go)); });
    $('btnBack').addEventListener('click', () => go(state.step - 1));
    $('btnMain').addEventListener('click', () => go(3));

    // ------------------------------------------------------------ 1. pallet
    async function start(lpn) {
        if (!lpn) return;
        const r = await WMS.api(C.urls.start, { method: 'POST', body: { lpn_no: lpn } });
        if (!r.ok) {
            WMSScanner.feedback(false);
            note(1, `<i class="bi bi-exclamation-circle"></i> ${esc(r.data.message)}`, 'warning');
            return;
        }
        WMSScanner.feedback(true);
        note(1, '');
        $('fLpn').value = '';
        setTask(r.data);
        go(2);
    }
    $('fLpn').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        state.keyboard = true;
        start(e.target.value.trim());
    });
    $('pendingList').addEventListener('click', (e) => { const b = e.target.closest('[data-lpn]'); if (b) start(b.dataset.lpn); });

    // ------------------------------------------------------------ 2. saran
    function setTask(t) {
        state.task = t;
        state.target = t.suggested ? { id: t.suggested.id, code: t.suggested.code, reason: t.suggested.reason } : null;
        renderTarget($('target'));
        $('altList').innerHTML = t.alternatives.map((a) => `<button type="button" class="m-chip is-sm" data-id="${a.id}" data-code="${esc(a.code)}" data-reason="${esc(a.reason)}">${esc(a.code)} · ${esc(a.cap)}</button>`).join('')
            || '<span class="small text-muted">Tidak ada alternatif.</span>';
        $('contents').innerHTML = t.contents.map((c) => `
            <div class="m-pick"><span class="min-w-0"><b>${esc(c.sku)}</b><span class="d-block small text-muted text-truncate">${esc(c.name)}</span>
            <span class="d-block small text-muted">${c.lot_no ? 'Lot ' + esc(c.lot_no) : ''}${c.expiry_date ? ' · Exp ' + esc(c.expiry_date) : ''}</span></span>
            <span class="fw-bold text-nowrap">${esc(c.qty)} ${esc(c.uom)}</span></div>`).join('');
    }
    function renderTarget(el) {
        const t = state.target;
        el.innerHTML = t
            ? `<span class="m-target-lbl">${state.task.suggested && t.id === state.task.suggested.id ? 'Lokasi saran' : 'Lokasi tujuan'}</span><span class="m-target-code">${esc(t.code)}</span><span class="m-target-why">${esc(t.reason || '')}</span>`
            : `<span class="m-target-lbl">Tidak ada lokasi yang cocok</span><span class="m-target-why">${esc(state.task?.suggest_reason)} Scan lokasi kosong mana pun di langkah berikutnya.</span>`;
        el.classList.toggle('is-empty', !t);
    }
    $('altList').addEventListener('click', (e) => {
        const b = e.target.closest('[data-id]');
        if (!b) return;
        state.target = { id: Number(b.dataset.id), code: b.dataset.code, reason: b.dataset.reason };
        renderTarget($('target'));
        MToast(`Tujuan diganti ke ${b.dataset.code}`);
    });
    $('btnCancelTask').addEventListener('click', async () => {
        if (!confirm('Batalkan task ini? Pallet tetap di dock.')) return;
        const r = await WMS.api(`${C.urls.tasks}/${state.task.id}/cancel`, { method: 'POST', body: {} });
        MToast(r.data.message, r.ok ? 'success' : 'danger');
        if (r.ok) location.reload();
    });

    // ------------------------------------------------------------ 3. scan lokasi tujuan
    async function scanLocation(code) {
        if (!code) return;
        const r = await WMS.api(`${C.urls.tasks}/${state.task.id}/resolve-location`, { params: { code } });
        $('fLoc').value = '';
        if (!r.ok) {
            WMSScanner.feedback(false);
            $('hLoc').className = 'm-hint text-danger';
            $('hLoc').textContent = r.data.message;
            return;
        }
        $('hLoc').textContent = '';
        if (state.target && r.data.id !== state.target.id) {
            note(3, `<i class="bi bi-exclamation-triangle"></i> Anda scan <b>${esc(r.data.code)}</b>, tujuan <b>${esc(state.target.code)}</b>.
                <button type="button" class="btn btn-sm btn-warning mt-2 w-100" data-use="${r.data.id}" data-code="${esc(r.data.code)}">Tetap simpan di ${esc(r.data.code)}</button>`, 'warning');
            WMSScanner.feedback(false);
            return;
        }
        complete(r.data.id, r.data.code);
    }
    document.querySelector('[data-note="3"]').addEventListener('click', (e) => {
        const b = e.target.closest('[data-use]');
        if (b) complete(Number(b.dataset.use), b.dataset.code);
    });
    $('fLoc').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        state.keyboard = true;
        scanLocation(e.target.value.trim());
    });

    async function complete(locationId, code, confirmFlag = false) {
        const r = await WMS.api(`${C.urls.tasks}/${state.task.id}/complete`, {
            method: 'POST', body: { location_id: locationId, confirm: confirmFlag ? 1 : 0, source: 'MOBILE' },
        });
        if (r.status === 409 && r.data.confirm) {
            const text = r.data.warnings.join('\n');
            if (!r.data.can_confirm) { WMSScanner.feedback(false); return note(3, `<i class="bi bi-x-octagon"></i> ${esc(text)} Pilih lokasi lain atau minta SPV.`, 'warning'); }
            if (confirm(`${text}\n\nTetap simpan?`)) return complete(locationId, code, true);
            return;
        }
        if (!r.ok) { WMSScanner.feedback(false); return note(3, esc(r.data.message), 'warning'); }

        WMSScanner.feedback(true);
        note(3, '');
        $('doneTitle').textContent = `Disimpan di ${r.data.to}`;
        $('doneText').innerHTML = `LPN <b>${esc(r.data.lpn_no)}</b> · task ${esc(r.data.task_no)}`;
        const done = document.querySelector('.m-done');
        done.classList.remove('play'); void done.offsetWidth; done.classList.add('play');
        go(4);
    }

    // ------------------------------------------------------------ kamera
    document.querySelectorAll('[data-scan]').forEach((b) => b.addEventListener('click', () => {
        state.keyboard = false;
        const kind = b.dataset.scan;
        WMSScanner.open({
            title: kind === 'lpn' ? 'Scan label LPN pallet' : 'Scan label lokasi rak',
            onResult: (code) => (kind === 'lpn' ? start(code.toUpperCase()) : scanLocation(code)),
        });
    }));

    $('btnNext').addEventListener('click', () => location.reload()); // daftar pallet di dock diperbarui

    go(1);
})();
