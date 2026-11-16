/**
 * WMSense - helper modal & form AJAX.
 *
 * 1. Tombol pembuka modal:
 *    <button data-bs-toggle="modal" data-bs-target="#modalX"
 *            data-action="/url" data-method="PUT" data-title="Edit ..." data-record='{"name":"..."}'>
 *    -> form di dalam modal di-reset, action/method diganti, field diisi dari data-record.
 *    -> event "wms:fill" dikirim ke modal untuk logika tambahan per halaman.
 *
 * 2. <form data-ajax> dikirim via fetch (tanpa pindah halaman).
 *    - 422: pesan error ditampilkan di bawah field masing-masing.
 *    - sukses: halaman di-reload (atau pindah ke `redirect` dari server).
 *    - data-confirm="Yakin?" -> minta konfirmasi dulu.
 */
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    // ------------------------------------------------------------ modal fill
    document.addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        const modal = e.target;
        const form = modal.querySelector('form[data-ajax]');
        if (!btn || !form) return;

        form.reset();
        form.querySelectorAll('select[data-remote]').forEach((s) => s.tomselect?.clear(true));
        clearErrors(form);
        if (btn.dataset.action) form.action = btn.dataset.action;
        const method = form.querySelector('input[name="_method"]');
        if (method) method.value = btn.dataset.method || 'POST';
        const title = modal.querySelector('.modal-title');
        if (title && btn.dataset.title) title.textContent = btn.dataset.title;

        const record = btn.dataset.record ? JSON.parse(btn.dataset.record) : {};
        fillForm(form, record);

        // Field yang hanya boleh diisi saat tambah (mis. kode gudang): readonly saat ubah
        const isEdit = (btn.dataset.method || 'POST') !== 'POST';
        form.querySelectorAll('[data-lock-on-edit]').forEach((el) => { el.readOnly = isEdit; });

        // Bagian form yang tampil hanya saat tambah / hanya saat ubah
        form.querySelectorAll('[data-show-on]').forEach((el) => {
            el.classList.toggle('d-none', el.dataset.showOn !== (isEdit ? 'edit' : 'create'));
        });
        syncConditional(form);
        modal.dispatchEvent(new CustomEvent('wms:fill', { detail: { record, button: btn, form } }));
    });

    // Fokus ke field pertama setelah modal terbuka
    document.addEventListener('shown.bs.modal', (e) => {
        e.target.querySelector('form [autofocus], form input:not([type=hidden]):not([readonly]), form select')?.focus();
    });

    function fillForm(form, record) {
        Object.entries(record).forEach(([name, value]) => {
            form.querySelectorAll(`[name="${name}"]`).forEach((el) => {
                if (el.tomselect) {
                    // Dropdown remote: opsi belum ada di halaman -> tambahkan dari record ("<name>_text")
                    const ts = el.tomselect;
                    ts.clear(true);
                    if (value !== null && value !== '' && value !== undefined) {
                        ts.addOption({ id: value, text: record[`${name}_text`] ?? String(value) });
                        ts.setValue(String(value), true);
                    }
                } else if (el.type === 'checkbox') el.checked = !!value && value !== '0';
                else if (el.type === 'radio') el.checked = String(el.value) === String(value);
                else if (el.type !== 'hidden' || name !== '_method') el.value = value ?? '';
            });
        });
    }

    // ------------------------------------------------------------ ajax submit
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!form.matches('form[data-ajax]')) return;
        e.preventDefault();
        if (form.dataset.confirm && !confirm(form.dataset.confirm)) return;

        const buttons = form.querySelectorAll('[type="submit"]');
        buttons.forEach((b) => { b.disabled = true; b.dataset.label = b.innerHTML; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...'; });
        clearErrors(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST', // PUT/DELETE dikirim lewat field _method (method spoofing Laravel)
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                if (data.redirect) window.location.href = data.redirect;
                else window.location.reload();
                return;
            }
            if (res.status === 419) return showErrors(form, {}, 'Sesi sudah habis. Muat ulang halaman lalu coba lagi.');
            if (res.status === 409 && data.confirm) {
                // Butuh konfirmasi (mis. qty melebihi ASN): tanya user, lalu kirim ulang dengan confirm=1
                const text = (data.warnings || [data.message]).join('\n');
                if (!data.can_confirm) return showErrors(form, {}, `${text} Butuh persetujuan SPV.`);
                if (confirm(`${text}\n\nTetap lanjutkan?`)) {
                    let flag = form.querySelector('input[name="confirm"]');
                    if (!flag) { flag = Object.assign(document.createElement('input'), { type: 'hidden', name: 'confirm' }); form.append(flag); }
                    flag.value = '1';
                    setTimeout(() => form.requestSubmit(), 0);
                }
                return;
            }
            showErrors(form, data.errors || {}, data.message || `Terjadi kesalahan (${res.status}).`);
        } catch (err) {
            showErrors(form, {}, 'Tidak dapat terhubung ke server.');
        } finally {
            buttons.forEach((b) => { b.disabled = false; if (b.dataset.label) b.innerHTML = b.dataset.label; });
        }
    });

    // "details.0.qty" -> "details[0][qty]"
    const toFieldName = (key) => key.split('.').map((p, i) => (i === 0 ? p : `[${p}]`)).join('');

    function showErrors(form, errors, message) {
        const unplaced = [];
        Object.entries(errors).forEach(([key, msgs]) => {
            const el = form.querySelector(`[name="${toFieldName(key)}"]`);
            if (!el) { unplaced.push(msgs[0]); return; }
            el.classList.add('is-invalid');
            const fb = document.createElement('div');
            fb.className = 'invalid-feedback d-block';
            fb.textContent = msgs[0];
            const anchor = el.closest('.input-group') || (el.tomselect ? el.tomselect.wrapper : el);
            anchor.insertAdjacentElement('afterend', fb);
        });

        const box = form.querySelector('.form-alert');
        const text = Object.keys(errors).length ? unplaced.join(' ') : message;
        if (!text) return;
        if (box) { box.textContent = text; box.classList.remove('d-none'); }
        else alert(text);
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());
        form.querySelector('.form-alert')?.classList.add('d-none');
    }

    // ------------------------------------------------------------ tampil bersyarat
    // <div data-show-when="receipt_type=RETURN">           -> tampil bila receipt_type = RETURN
    // <div data-show-when="receipt_type=PURCHASE,OTHER">   -> tampil bila salah satu nilai
    function syncConditional(form) {
        form.querySelectorAll('[data-show-when]').forEach((el) => {
            const [field, values] = el.dataset.showWhen.split('=');
            const current = form.querySelector(`[name="${field}"]`)?.value ?? '';
            el.classList.toggle('d-none', !values.split(',').includes(current));
        });
    }
    document.addEventListener('change', (e) => {
        const form = e.target.closest('form');
        if (form) syncConditional(form);
    });

    // ------------------------------------------------------------ dropdown pencarian ke server
    // <select name="supplier_id" data-remote="/lookup/suppliers" data-remote-depends="owner_id">
    //   - ketik untuk mencari (server mengembalikan [{id, text}])
    //   - data-remote-depends: nilai field lain ikut dikirim sebagai parameter; bila field itu
    //     berubah, pilihan dikosongkan (mis. supplier harus milik owner yang dipilih)
    function initRemoteSelect(el) {
        if (el.tomselect || typeof TomSelect === 'undefined') return;
        const form = el.closest('form');
        const dep = el.dataset.remoteDepends;
        const depEl = dep ? form.querySelector(`[name="${dep}"]`) : null;
        const ts = new TomSelect(el, {
            valueField: 'id', labelField: 'text', searchField: ['text'],
            preload: 'focus', loadThrottle: 300, maxOptions: 50, allowEmptyOption: true,
            placeholder: el.getAttribute('placeholder') || 'Ketik untuk mencari...',
            load(query, callback) {
                const url = new URL(el.dataset.remote, window.location.origin);
                url.searchParams.set('q', query);
                if (depEl) url.searchParams.set(dep, depEl.value || '');
                fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then(callback).catch(() => callback());
            },
        });
        depEl?.addEventListener('change', () => {
            ts.clear(); ts.clearOptions(); ts.loadedSearches = {};
        });
    }
    document.querySelectorAll('select[data-remote]').forEach(initRemoteSelect);

    // ------------------------------------------------------------ toast notifikasi (flash session)
    document.querySelectorAll('.toast').forEach((t) => bootstrap.Toast.getOrCreateInstance(t).show());

    // ------------------------------------------------------------ filter di searchbar
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-apply-filter');
        if (!btn) return;
        const form = btn.closest('form');
        const url = new URL(window.location.href);
        form.querySelectorAll('[data-filter]').forEach((sel) => {
            if (sel.value) url.searchParams.set(sel.dataset.filter, sel.value);
            else url.searchParams.delete(sel.dataset.filter);
        });
        const q = form.querySelector('[name="q"]').value;
        if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
        url.searchParams.delete('page');
        window.location.href = url.toString();
    });

    // Klik baris tabel (tr[data-href]) -> buka halaman detail
    document.addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-href]');
        if (!row || e.target.closest('a, button, form, input')) return;
        window.location.href = row.dataset.href;
    });

    // Hapus flag confirm setiap kali modal dibuka ulang
    document.addEventListener('show.bs.modal', (e) => e.target.querySelector('input[name="confirm"]')?.remove());

    // ------------------------------------------------------------ util untuk halaman scan (mobile/desktop)
    /** ID unik per transaksi scan (idempotency key). crypto.randomUUID butuh HTTPS, jadi ada cadangan. */
    function uuid() {
        if (window.crypto?.randomUUID) return crypto.randomUUID();
        return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    }

    /**
     * Panggil endpoint JSON. Mengembalikan {ok, status, data}. Tidak melempar error untuk 4xx,
     * supaya pemanggil bisa menampilkan pesan dari server.
     */
    async function api(url, { method = 'GET', body = null, params = null } = {}) {
        const u = new URL(url, window.location.origin);
        if (params) Object.entries(params).forEach(([k, v]) => v !== null && v !== undefined && u.searchParams.set(k, v));
        const opt = { method, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } };
        if (body) {
            opt.headers['Content-Type'] = 'application/json';
            opt.body = JSON.stringify(body);
        }
        try {
            const res = await fetch(u, opt);
            const data = await res.json().catch(() => ({}));
            if (res.status === 419) data.message = 'Sesi habis, silakan muat ulang halaman / login lagi.';
            if (!res.ok && !data.message) data.message = `Terjadi kesalahan (${res.status}).`;
            if (res.status === 422 && data.errors) data.message = Object.values(data.errors).map((m) => m[0]).join(' ');
            return { ok: res.ok, status: res.status, data };
        } catch (err) {
            return { ok: false, status: 0, data: { message: 'Tidak terhubung ke server. Periksa jaringan lalu coba lagi.' } };
        }
    }

    // ------------------------------------------------------------ lonceng notifikasi (desktop)
    // Polling tiap 60 detik. Bila ada notifikasi belum dibaca yang baru, tampilkan toast.
    const bell = document.getElementById('notifBell');
    if (bell) {
        let lastUnread = null;
        const escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const loadNotif = async () => {
            const r = await api(bell.dataset.url);
            if (!r.ok) return;
            const { unread, items } = r.data;
            const badge = document.getElementById('notifCount');
            badge.textContent = unread > 9 ? '9+' : unread;
            badge.classList.toggle('d-none', unread === 0);
            document.getElementById('notifList').innerHTML = items.length
                ? items.map((n) => `
                    <a href="${n.open_url}" class="o-notif-item ${n.read ? '' : 'unread'}">
                        <i class="bi bi-${escHtml(n.icon)} text-${escHtml(n.level)}"></i>
                        <span class="min-w-0"><span class="d-block fw-semibold">${escHtml(n.title)}</span>
                        <span class="d-block small text-muted">${escHtml(n.message)}</span>
                        <span class="d-block small text-muted">${escHtml(n.time)}</span></span>
                    </a>`).join('')
                : '<div class="text-muted small p-3 text-center">Tidak ada notifikasi.</div>';
            if (lastUnread !== null && unread > lastUnread && items[0] && !items[0].read) {
                showToast(`${items[0].title}: ${items[0].message}`, 'danger');
            }
            lastUnread = unread;
        };
        document.getElementById('notifReadAll').addEventListener('click', async () => {
            await api(bell.dataset.readAll, { method: 'POST', body: {} });
            loadNotif();
        });
        loadNotif();
        setInterval(() => { if (!document.hidden) loadNotif(); }, 60000);
    }

    // ------------------------------------------------------------ tabel -> kartu di HP
    // CSS (.o-cardable, < 768px) menampilkan tiap baris sebagai kartu; label kolom diambil dari <thead>.
    // Sel pertama yang berlabel jadi judul kartu, sel checkbox ke pojok kanan, sel aksi tanpa label ke bawah.
    function cardify(table) {
        const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((tr) => {
            let col = 0;
            let titled = !!tr.querySelector(':scope > .o-td-title'); // view boleh menentukan judul kartu sendiri
            [...tr.children].forEach((td) => {
                const label = heads[col] ?? '';
                col += td.colSpan || 1;
                if (td.classList.contains('o-empty')) return;
                if (!td.dataset.label && label) td.dataset.label = label;
                const onlyCheck = td.children.length === 1 && td.firstElementChild.matches('input[type=checkbox]') && !td.textContent.trim();
                if (onlyCheck) td.classList.add('o-td-check');
                else if (!label) td.classList.add('o-td-action');
                else if (!titled) { td.classList.add('o-td-title'); titled = true; }
            });
        });
        table.classList.add('o-cardable');
    }
    document.querySelectorAll('.o-list > table, .o-list .table').forEach(cardify);

    /** Toast dinamis di pojok kanan atas (desktop). */
    function showToast(message, color = 'success') {
        const wrap = document.querySelector('.toast-container');
        if (!wrap) return;
        const el = document.createElement('div');
        el.className = `toast align-items-center text-bg-${color} border-0`;
        el.setAttribute('data-bs-delay', '8000');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        el.querySelector('.toast-body').textContent = message;
        wrap.append(el);
        bootstrap.Toast.getOrCreateInstance(el).show();
    }

    window.WMS = { fillForm, clearErrors, csrf, initRemoteSelect, syncConditional, uuid, api, showToast, cardify };
})();
