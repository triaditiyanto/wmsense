/**
 * MGuide - panduan bergambar (onboarding) layar penuh untuk operator mobile.
 *
 *   const guide = MGuide.create(document.getElementById('rcvGuide'), {
 *       key: 'mGuide:receiving:v1:12',   // localStorage: sudah pernah dilihat?
 *       onClose: (finished) => {},
 *   });
 *   guide.autoOpen();      // buka otomatis, kecuali user sudah mencentang "Jangan tampilkan lagi"
 *   guide.open(2);         // buka slide yang data-step="2"
 *
 * Markup: lihat resources/views/mobile/receiving/_guide.blade.php
 * - .g-slide (data-step)  -> animasi CSS hanya jalan saat slide punya .is-active
 * - [data-count-to=N]     -> angka dihitung naik dari 0 saat slide tampil
 * - geser kiri/kanan, tombol Lanjut/Kembali, tombol panah keyboard, Esc = tutup
 */
window.MGuide = (() => {
    const storage = {
        get: (k) => { try { return localStorage.getItem(k); } catch { return null; } },
        set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* mode privat */ } },
        del: (k) => { try { localStorage.removeItem(k); } catch { /* mode privat */ } },
    };

    function create(root, { key, onClose } = {}) {
        if (!root) return { open() {}, autoOpen() {}, close() {} };
        const slides = [...root.querySelectorAll('.g-slide')];
        const dots = root.querySelector('[data-g-dots]');
        const next = root.querySelector('[data-g-next]');
        const prev = root.querySelector('[data-g-prev]');
        const dontShow = root.querySelector('[data-g-dontshow]'); // ceklis "Jangan tampilkan lagi"
        let index = 0;
        let timers = [];

        root.querySelector('[data-g-total]').textContent = slides.length;
        dots.innerHTML = slides.map((_, i) => `<button type="button" aria-label="Slide ${i + 1}" data-i="${i}"></button>`).join('');
        dots.addEventListener('click', (e) => { const b = e.target.closest('[data-i]'); if (b) show(Number(b.dataset.i)); });

        function countUp(el) {
            const to = Number(el.dataset.countTo);
            const start = performance.now();
            const dur = 900;
            const tick = (t) => {
                const p = Math.min(1, (t - start) / dur);
                el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
                if (p < 1 && root.contains(el) && !root.hidden) requestAnimationFrame(tick);
            };
            el.textContent = '0';
            timers.push(setTimeout(() => requestAnimationFrame(tick), 500));
        }

        function show(i, dir = i >= index ? 1 : -1) {
            index = Math.max(0, Math.min(slides.length - 1, i));
            timers.forEach(clearTimeout); timers = [];
            slides.forEach((s, n) => {
                s.classList.remove('is-active', 'from-left', 'from-right');
                if (n === index) {
                    void s.offsetWidth; // reflow -> animasi CSS diulang dari awal
                    s.classList.add('is-active', dir > 0 ? 'from-right' : 'from-left');
                    s.querySelectorAll('[data-count-to]').forEach(countUp);
                }
            });
            [...dots.children].forEach((d, n) => d.classList.toggle('on', n === index));
            root.querySelector('[data-g-index]').textContent = index + 1;
            prev.style.visibility = index === 0 ? 'hidden' : 'visible';
            const last = index === slides.length - 1;
            next.innerHTML = last ? `<i class="bi bi-play-fill"></i> ${root.dataset.gFinish || 'Mulai'}` : 'Lanjut <i class="bi bi-chevron-right"></i>';
            next.classList.toggle('is-last', last);
        }

        function open(step = 0) {
            const i = slides.findIndex((s) => Number(s.dataset.step) === Number(step));
            if (dontShow) dontShow.checked = !!storage.get(key);
            root.hidden = false;
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => root.classList.add('show'));
            show(i < 0 ? 0 : i, 1);
            next.focus({ preventScroll: true });
        }

        function close(finished = false) {
            // Tanpa ceklis: panduan tampil lagi saat modul dibuka berikutnya. Dengan ceklis: tidak otomatis lagi.
            if (!dontShow || dontShow.checked) storage.set(key, '1'); else storage.del(key);
            root.classList.remove('show');
            timers.forEach(clearTimeout);
            setTimeout(() => { root.hidden = true; document.body.style.overflow = ''; }, 250);
            onClose?.(finished);
        }

        next.addEventListener('click', () => (index === slides.length - 1 ? close(true) : show(index + 1, 1)));
        prev.addEventListener('click', () => show(index - 1, -1));
        root.querySelector('[data-g-skip]').addEventListener('click', () => close(false));
        // varian modal (desktop): klik area gelap di luar kartu = tutup
        root.addEventListener('click', (e) => { if (e.target === root && root.classList.contains('is-modal')) close(false); });
        root.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') show(index + 1, 1);
            if (e.key === 'ArrowLeft') show(index - 1, -1);
            if (e.key === 'Escape') close(false);
        });

        // geser (swipe) kiri/kanan
        let x0 = null; let y0 = null;
        const track = root.querySelector('.g-track');
        track.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
        track.addEventListener('touchend', (e) => {
            if (x0 === null) return;
            const dx = e.changedTouches[0].clientX - x0;
            const dy = e.changedTouches[0].clientY - y0;
            x0 = null;
            if (Math.abs(dx) < 50 || Math.abs(dy) > Math.abs(dx)) return;
            show(index + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
        });

        return {
            open,
            close,
            autoOpen() { if (!storage.get(key)) open(0); return !storage.get(key); },
        };
    }

    return { create };
})();

// Panduan halaman (layout desktop): #pageGuide dari components/guide.blade.php + tombol [data-guide-open]
(() => {
    const el = document.getElementById('pageGuide');
    if (!el) return;
    const guide = MGuide.create(el, { key: el.dataset.guideKey });
    document.querySelectorAll('[data-guide-open]').forEach((b) => b.addEventListener('click', () => guide.open(0)));
    guide.autoOpen();
})();
