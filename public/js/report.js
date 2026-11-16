/**
 * Laporan interaktif (resources/views/reports/index.blade.php). Konfigurasi: window.REPORT.
 *
 * - Tabel   : render server (paginasi). Kolom yang tampil = pilihan user (localStorage), sama dengan "export kolom terpilih".
 * - Pivot   : dataset (GET urls.dataset, maks 20.000 baris) diolah di browser: baris x kolom, nilai + agregasi,
 *             total baris/kolom, heatmap. Bisa di-export ke Excel (POST urls.exportPivot).
 * - Grafik  : Chart.js dari hasil pivot (label = baris, seri = kolom). Unduh PNG.
 */
(() => {
    const R = window.REPORT;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const store = {
        get: (k, d) => { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch { return d; } },
        set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* */ } },
    };
    const K = (s) => `report:${R.key}:${s}`;
    const colsByKey = Object.fromEntries(R.columns.map((c) => [c.key, c]));
    const nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const AGG = { sum: 'Jumlah', avg: 'Rata-rata', min: 'Terkecil', max: 'Terbesar', count: 'Banyak baris', distinct: 'Banyak unik' };

    // ================================================================ tampilan (tab)
    let view = 'table';
    function show(v) {
        view = v;
        document.querySelectorAll('#viewTabs [data-view]').forEach((b) => b.classList.toggle('active', b.dataset.view === v));
        document.querySelectorAll('[data-only]').forEach((el) => { el.hidden = el.dataset.only !== v; });
        store.set(K('view'), v);
        if (v !== 'table') loadData().then(() => { renderPivot(); if (v === 'chart') renderChart(); });
    }
    document.querySelectorAll('#viewTabs [data-view]').forEach((b) => b.addEventListener('click', () => show(b.dataset.view)));
    document.querySelectorAll('[data-goto]').forEach((b) => b.addEventListener('click', () => show(b.dataset.goto)));

    // ================================================================ periode cepat
    document.querySelectorAll('[data-range]').forEach((b) => b.addEventListener('click', () => {
        const f = $('filterForm');
        const d = new Date();
        const iso = (x) => `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
        let from = new Date(d);
        let to = new Date(d);
        const r = b.dataset.range;
        if (r === '7' || r === '30') from.setDate(d.getDate() - (Number(r) - 1));
        if (r === 'month') from = new Date(d.getFullYear(), d.getMonth(), 1);
        if (r === 'last') { from = new Date(d.getFullYear(), d.getMonth() - 1, 1); to = new Date(d.getFullYear(), d.getMonth(), 0); }
        if (r === 'year') from = new Date(d.getFullYear(), 0, 1);
        f.from.value = iso(from);
        f.to.value = iso(to);
        f.requestSubmit();
    }));

    // ================================================================ pilih kolom (tabel + export)
    const defaults = R.columns.filter((c) => c.default).map((c) => c.key);
    let selected = store.get(K('cols'), defaults).filter((k) => colsByKey[k]);
    if (!selected.length) selected = defaults;

    function applyCols() {
        document.querySelectorAll('[data-col]').forEach((el) => el.classList.toggle('o-col-hidden', !selected.includes(el.dataset.col)));
        document.querySelectorAll('[data-col-count]').forEach((el) => { el.textContent = selected.length; });
    }
    function renderColList() {
        const q = $('colSearch').value.toLowerCase();
        $('colList').innerHTML = R.columns.filter((c) => !q || c.label.toLowerCase().includes(q)).map((c) => `
            <label class="o-col-item"><input type="checkbox" class="form-check-input" value="${c.key}" ${selected.includes(c.key) ? 'checked' : ''}>
            <span>${esc(c.label)}</span><small>${{ number: '123', percent: '%', date: 'tgl', datetime: 'waktu', string: 'teks' }[c.type]}</small></label>`).join('');
    }
    $('colList').addEventListener('change', (e) => {
        const k = e.target.value;
        selected = e.target.checked ? R.columns.map((c) => c.key).filter((x) => x === k || selected.includes(x)) : selected.filter((x) => x !== k);
        document.querySelectorAll('[data-col-count]').forEach((el) => { el.textContent = selected.length; });
    });
    $('colSearch').addEventListener('input', renderColList);
    document.querySelectorAll('[data-cols]').forEach((b) => b.addEventListener('click', () => {
        selected = b.dataset.cols === 'all' ? R.columns.map((c) => c.key) : (b.dataset.cols === 'none' ? [] : [...defaults]);
        renderColList();
        document.querySelectorAll('[data-col-count]').forEach((el) => { el.textContent = selected.length; });
    }));
    $('modalCols').addEventListener('show.bs.modal', () => { $('colSearch').value = ''; renderColList(); });

    function exportUrl(keys) {
        const p = new URLSearchParams();
        Object.entries(R.query || {}).forEach(([k, v]) => { if (v !== null && v !== '' && !Array.isArray(v)) p.append(k, v); });
        keys.forEach((k) => p.append('columns[]', k));
        return `${R.urls.export}?${p}`;
    }
    function commitCols() {
        if (!selected.length) { WMS.showToast('Pilih minimal satu kolom.', 'danger'); return false; }
        store.set(K('cols'), selected);
        applyCols();
        bootstrap.Modal.getInstance($('modalCols'))?.hide();
        return true;
    }
    $('colApply').addEventListener('click', commitCols);
    $('colExport').addEventListener('click', () => { if (commitCols()) location.href = exportUrl(selected); });
    $('exportSelected').addEventListener('click', (e) => { e.preventDefault(); location.href = exportUrl(selected); });
    applyCols();

    // ================================================================ dataset
    let data = null;
    let loading = null;
    function loadData() {
        if (data) return Promise.resolve(data);
        loading ??= WMS.api(R.urls.dataset).then((r) => {
            if (!r.ok) { $('pvTable').innerHTML = `<div class="text-danger p-4">${esc(r.data.message)}</div>`; throw new Error(r.data.message); }
            const idx = Object.fromEntries(r.data.fields.map((f, i) => [f, i]));
            data = { rows: r.data.rows, idx, truncated: r.data.truncated, limit: r.data.limit };
            return data;
        });
        return loading;
    }

    // ================================================================ pivot
    const dims = R.columns.filter((c) => c.dim);
    const measures = R.columns.filter((c) => c.measure);
    const cfg = { ...R.preset, ...store.get(K('pivot'), {}) };

    const opt = (v, t, sel) => `<option value="${v}" ${String(v) === String(sel) ? 'selected' : ''}>${esc(t)}</option>`;
    function fillSelects() {
        $('pvRows').innerHTML = dims.map((d) => opt(d.key, d.label, cfg.rows)).join('');
        $('pvCols').innerHTML = opt('', '(tanpa kolom)', cfg.cols) + dims.map((d) => opt(d.key, d.label, cfg.cols)).join('');
        $('pvValue').innerHTML = `<optgroup label="Angka">${opt('__rows', 'Jumlah baris', cfg.value)}${measures.map((m) => opt(m.key, m.label, cfg.value)).join('')}</optgroup>`
            + `<optgroup label="Hitung unik">${dims.map((d) => opt(d.key, d.label, cfg.value)).join('')}</optgroup>`;
        syncAgg();
        $('pvSort').value = cfg.sort || 'label';
    }
    function syncAgg() {
        const v = cfg.value;
        const allowed = v === '__rows' ? ['count'] : (colsByKey[v]?.measure ? ['sum', 'avg', 'min', 'max'] : ['distinct', 'count']);
        if (!allowed.includes(cfg.agg)) cfg.agg = v === '__rows' || !colsByKey[v]?.measure ? allowed[0] : (colsByKey[v].agg || 'sum');
        $('pvAgg').innerHTML = allowed.map((a) => opt(a, AGG[a], cfg.agg)).join('');
    }
    ['pvRows', 'pvCols', 'pvValue', 'pvAgg', 'pvSort'].forEach((id) => $(id).addEventListener('change', () => {
        cfg.rows = $('pvRows').value;
        cfg.cols = $('pvCols').value;
        if (cfg.cols === cfg.rows) cfg.cols = '';
        cfg.value = $('pvValue').value;
        cfg.agg = $('pvAgg').value;
        cfg.sort = $('pvSort').value;
        syncAgg();
        store.set(K('pivot'), cfg);
        renderPivot();
    }));
    $('pvSwap').addEventListener('click', () => {
        if (!cfg.cols) return;
        [cfg.rows, cfg.cols] = [cfg.cols, cfg.rows];
        fillSelects();
        store.set(K('pivot'), cfg);
        renderPivot();
    });

    const label = (key, v) => {
        if (v === null || v === undefined || v === '') return '(kosong)';
        const t = colsByKey[key]?.type;
        if (t === 'date' && /^\d{4}-\d{2}-\d{2}/.test(v)) return `${v.slice(8, 10)}/${v.slice(5, 7)}/${v.slice(0, 4)}`;
        return String(v);
    };

    /** Hasil pivot: { rowKeys, colKeys, cell(r,c), rowTotal(r), colTotal(c), grand } */
    let pivot = null;
    function compute() {
        const { rows, idx } = data;
        const ri = idx[cfg.rows];
        const ci = cfg.cols ? idx[cfg.cols] : null;
        const vi = cfg.value === '__rows' ? null : idx[cfg.value];
        const acc = new Map();
        const get = (k) => { let a = acc.get(k); if (!a) { a = { sum: 0, n: 0, min: Infinity, max: -Infinity, set: new Set() }; acc.set(k, a); } return a; };
        const rowSet = new Set();
        const colSet = new Set();
        for (const row of rows) {
            const rk = row[ri] ?? '';
            const ck = ci === null ? '' : (row[ci] ?? '');
            rowSet.add(rk); colSet.add(ck);
            const v = vi === null ? null : row[vi];
            for (const key of [`${rk}\u0001${ck}`, `${rk}\u0001\u0002`, `\u0002\u0001${ck}`, '\u0002\u0001\u0002']) {
                const a = get(key);
                a.n++;
                if (cfg.agg === 'distinct') { if (v !== null && v !== '') a.set.add(v); continue; }
                const num = Number(v);
                if (v !== null && v !== '' && !Number.isNaN(num)) { a.sum += num; a.min = Math.min(a.min, num); a.max = Math.max(a.max, num); a.cnt = (a.cnt || 0) + 1; }
            }
        }
        const value = (a) => {
            if (!a) return null;
            switch (cfg.agg) {
                case 'count': return a.n;
                case 'distinct': return a.set.size;
                case 'avg': return a.cnt ? a.sum / a.cnt : null;
                case 'min': return a.cnt ? a.min : null;
                case 'max': return a.cnt ? a.max : null;
                default: return a.sum;
            }
        };
        const cell = (r, c) => value(acc.get(`${r}\u0001${c}`));
        const rowTotal = (r) => value(acc.get(`${r}\u0001\u0002`));
        const colTotal = (c) => value(acc.get(`\u0002\u0001${c}`));
        const sortKeys = (keys, total) => {
            const arr = [...keys];
            if (cfg.sort === 'desc') return arr.sort((a, b) => (total(b) ?? -Infinity) - (total(a) ?? -Infinity));
            if (cfg.sort === 'asc') return arr.sort((a, b) => (total(a) ?? Infinity) - (total(b) ?? Infinity));
            return arr.sort((a, b) => String(a).localeCompare(String(b), 'id', { numeric: true }));
        };
        let colKeys = sortKeys(colSet, colTotal);
        const tooMany = colKeys.length > 40;
        if (tooMany) colKeys = [...colSet].sort((a, b) => (colTotal(b) ?? 0) - (colTotal(a) ?? 0)).slice(0, 40);
        pivot = { rowKeys: sortKeys(rowSet, rowTotal), colKeys, cell, rowTotal, colTotal, grand: value(acc.get('\u0002\u0001\u0002')), tooMany };
        return pivot;
    }

    const fmtVal = (v) => {
        if (v === null || v === undefined) return '';
        const isPct = colsByKey[cfg.value]?.type === 'percent' && ['avg', 'min', 'max'].includes(cfg.agg);
        return nf.format(v) + (isPct ? '%' : '');
    };
    const valueTitle = () => (cfg.value === '__rows' ? 'Jumlah baris' : `${AGG[cfg.agg]} ${colsByKey[cfg.value].label}`);

    function renderPivot() {
        if (!data) return;
        fillSelects();
        if (!data.rows.length) { $('pvTable').innerHTML = '<div class="text-muted p-4 text-center">Tidak ada data pada periode / filter ini.</div>'; pivot = null; return; }
        const p = compute();
        const hasCols = !!cfg.cols;
        const max = Math.max(0, ...p.rowKeys.flatMap((r) => p.colKeys.map((c) => Number(p.cell(r, c)) || 0)));
        const heat = (v) => (max > 0 && v ? `style="background: rgba(0,122,255,${(0.06 + 0.32 * (v / max)).toFixed(3)})"` : '');

        let html = '<table class="o-pivot"><thead><tr>';
        html += `<th class="o-pv-corner">${esc(colsByKey[cfg.rows].label)}${hasCols ? ` <span class="text-muted">/ ${esc(colsByKey[cfg.cols].label)}</span>` : ''}</th>`;
        if (hasCols) p.colKeys.forEach((c) => { html += `<th class="text-end">${esc(label(cfg.cols, c))}</th>`; });
        html += `<th class="text-end o-pv-total">${hasCols ? 'Total' : esc(valueTitle())}</th></tr></thead><tbody>`;
        p.rowKeys.forEach((r) => {
            html += `<tr><th>${esc(label(cfg.rows, r))}</th>`;
            if (hasCols) p.colKeys.forEach((c) => { const v = p.cell(r, c); html += `<td class="text-end" ${heat(v)}>${fmtVal(v)}</td>`; });
            html += `<td class="text-end o-pv-total" ${hasCols ? '' : heat(p.rowTotal(r))}>${fmtVal(p.rowTotal(r))}</td></tr>`;
        });
        html += '</tbody><tfoot><tr><th>Total</th>';
        if (hasCols) p.colKeys.forEach((c) => { html += `<td class="text-end">${fmtVal(p.colTotal(c))}</td>`; });
        html += `<td class="text-end o-pv-total">${fmtVal(p.grand)}</td></tr></tfoot></table>`;
        $('pvTable').innerHTML = html;

        const notes = [`${nf.format(data.rows.length)} baris data · ${esc(valueTitle())} · ${p.rowKeys.length} baris${hasCols ? ` × ${p.colKeys.length} kolom` : ''}`];
        if (data.truncated) notes.push(`<span class="text-warning-emphasis">Data dibatasi ${nf.format(data.limit)} baris pertama, persempit periode untuk hasil lengkap.</span>`);
        if (p.tooMany) notes.push('<span class="text-warning-emphasis">Kolom dibatasi 40 nilai terbesar.</span>');
        $('pvNote').innerHTML = notes.join(' · ');
    }

    $('pvToChart').addEventListener('click', () => show('chart'));
    $('pvExport').addEventListener('click', async () => {
        if (!pivot) return;
        const hasCols = !!cfg.cols;
        const headers = [colsByKey[cfg.rows].label, ...(hasCols ? pivot.colKeys.map((c) => label(cfg.cols, c)) : []), hasCols ? 'Total' : valueTitle()];
        const num = (v) => (v === null || v === undefined ? null : Math.round(v * 10000) / 10000);
        const rows = pivot.rowKeys.map((r) => [label(cfg.rows, r), ...(hasCols ? pivot.colKeys.map((c) => num(pivot.cell(r, c))) : []), num(pivot.rowTotal(r))]);
        rows.push(['Total', ...(hasCols ? pivot.colKeys.map((c) => num(pivot.colTotal(c))) : []), num(pivot.grand)]);
        const body = {
            title: `${valueTitle()} per ${colsByKey[cfg.rows].label}${hasCols ? ` dan ${colsByKey[cfg.cols].label}` : ''}`,
            headers, types: headers.map((_, i) => (i === 0 ? 'string' : 'number')), rows,
        };
        const res = await fetch(R.urls.exportPivot, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(body),
        });
        if (!res.ok) { const j = await res.json().catch(() => ({})); return WMS.showToast(j.message || `Export gagal (${res.status}).`, 'danger'); }
        const blob = await res.blob();
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `${R.key}-pivot.xlsx` });
        document.body.append(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    });

    // ================================================================ grafik
    const PALETTE = ['#007aff', '#34c759', '#ff9500', '#5856d6', '#ff2d55', '#5ac8fa', '#af52de', '#ffcc00', '#30b0c7', '#a2845e', '#8e8e93', '#0040dd'];
    let chart = null;
    $('chType').value = store.get(K('chart'), R.preset.chart || 'bar');
    $('chTop').value = String(store.get(K('top'), 20));
    ['chType', 'chTop'].forEach((id) => $(id).addEventListener('change', () => {
        store.set(K('chart'), $('chType').value);
        store.set(K('top'), Number($('chTop').value));
        renderChart();
    }));

    function renderChart() {
        if (!pivot) return;
        const type = $('chType').value;
        const top = Number($('chTop').value);
        const round = type === 'pie' || type === 'doughnut';
        let rowKeys = [...pivot.rowKeys];
        if (top && rowKeys.length > top) {
            rowKeys = rowKeys.sort((a, b) => (pivot.rowTotal(b) ?? 0) - (pivot.rowTotal(a) ?? 0)).slice(0, top);
            if (cfg.sort === 'label') rowKeys.sort((a, b) => String(a).localeCompare(String(b), 'id', { numeric: true }));
        }
        const labels = rowKeys.map((r) => label(cfg.rows, r));
        const series = cfg.cols && !round ? pivot.colKeys : [null];
        const datasets = series.map((c, i) => {
            const color = PALETTE[i % PALETTE.length];
            return {
                label: c === null ? valueTitle() : label(cfg.cols, c),
                data: rowKeys.map((r) => (c === null ? pivot.rowTotal(r) : pivot.cell(r, c)) ?? 0),
                backgroundColor: round ? rowKeys.map((_, j) => PALETTE[j % PALETTE.length]) : (type === 'area' ? `${color}33` : color),
                borderColor: round ? '#fff' : color,
                borderWidth: round ? 2 : (type === 'line' || type === 'area' ? 2.5 : 0),
                borderRadius: type.includes('bar') ? 6 : 0,
                fill: type === 'area',
                tension: 0.3,
                pointRadius: type === 'line' || type === 'area' ? 3 : 0,
            };
        });
        chart?.destroy();
        const stacked = type === 'bar-stacked';
        chart = new Chart($('chCanvas'), {
            type: round ? type : (type === 'line' || type === 'area' ? 'line' : 'bar'),
            data: { labels, datasets },
            // latar putih supaya PNG yang diunduh tidak transparan
            plugins: [{ id: 'bg', beforeDraw: (c) => { const g = c.ctx; g.save(); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); g.restore(); } }],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: type === 'hbar' ? 'y' : 'x',
                plugins: {
                    legend: { display: round || series.length > 1, position: 'bottom' },
                    title: { display: true, text: `${valueTitle()} per ${colsByKey[cfg.rows].label}${cfg.cols && !round ? ` / ${colsByKey[cfg.cols].label}` : ''}`, font: { size: 14, weight: '600' } },
                    tooltip: { callbacks: { label: (c) => `${round ? c.label : c.dataset.label}: ${fmtVal(c.raw)}` } },
                },
                scales: round ? {} : {
                    x: { stacked, grid: { display: false } },
                    y: { stacked, beginAtZero: true, ticks: { callback: (v) => nf.format(v) } },
                },
            },
        });
        $('chInfo').textContent = rowKeys.length < pivot.rowKeys.length ? `Menampilkan ${rowKeys.length} dari ${pivot.rowKeys.length}` : '';
    }
    $('chPng').addEventListener('click', () => {
        if (!chart) return;
        const a = Object.assign(document.createElement('a'), { href: chart.toBase64Image('image/png', 1), download: `${R.key}-grafik.png` });
        a.click();
    });

    // ================================================================ detail baris (mis. Inventory -> kartu stok)
    if (R.urls.row) {
        document.querySelector('.o-report-table tbody')?.addEventListener('click', async (e) => {
            const tr = e.target.closest('tr[data-row]');
            if (!tr || !tr.dataset.row) return;
            const body = $('modalRowBody');
            body.innerHTML = '<div class="text-center text-muted p-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</div>';
            bootstrap.Modal.getOrCreateInstance($('modalRow')).show();
            try {
                const res = await fetch(`${R.urls.row}?id=${encodeURIComponent(tr.dataset.row)}`, { headers: { Accept: 'text/html' } });
                body.innerHTML = res.ok ? await res.text() : '<div class="alert alert-danger">Gagal memuat detail.</div>';
                const title = body.querySelector('[data-modal-title]');
                if (title) $('modalRow').querySelector('.modal-title').textContent = title.dataset.modalTitle;
                body.querySelectorAll('table').forEach((t) => window.WMS?.cardify?.(t));
            } catch {
                body.innerHTML = '<div class="alert alert-danger">Tidak terhubung ke server.</div>';
            }
        });
    }

    show(store.get(K('view'), 'table'));
})();
