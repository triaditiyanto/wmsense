/**
 * WMSScanner - scan barcode / QR pakai kamera HP (library html5-qrcode).
 *
 *   WMSScanner.open({ title: 'Scan LPN', onResult: (code) => { ... } });
 *
 * Catatan penting: browser hanya mengizinkan kamera di HTTPS (atau localhost).
 * Bila kamera tidak tersedia, overlay tetap tampil dengan kolom ketik manual.
 * Scanner genggam (Zebra/Honeywell) tidak butuh ini: hasil scan-nya masuk sebagai ketikan + Enter.
 */
window.WMSScanner = (() => {
    let overlay; let reader; let current; let busy = false;

    const FORMATS = () => {
        const F = window.Html5QrcodeSupportedFormats;
        return F ? [F.CODE_128, F.CODE_39, F.CODE_93, F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.ITF, F.CODABAR, F.QR_CODE, F.DATA_MATRIX] : undefined;
    };

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'm-scanner';
        overlay.innerHTML = `
            <div class="m-scanner-top">
                <span class="m-scanner-title"></span>
                <button type="button" class="m-scanner-close" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="m-scanner-view"><div id="mScannerRegion"></div><div class="m-scanner-frame"><span></span></div></div>
            <div class="m-scanner-msg"></div>
            <div class="m-scanner-bottom">
                <button type="button" class="m-scanner-torch d-none"><i class="bi bi-lightbulb"></i> Senter</button>
                <form class="m-scanner-manual">
                    <input class="form-control" placeholder="Atau ketik kode di sini" autocomplete="off">
                    <button class="btn btn-primary" type="submit">OK</button>
                </form>
            </div>`;
        document.body.append(overlay);
        overlay.querySelector('.m-scanner-close').addEventListener('click', close);
        overlay.querySelector('.m-scanner-manual').addEventListener('submit', (e) => {
            e.preventDefault();
            const v = e.target.querySelector('input').value.trim();
            if (v) done(v);
        });
        overlay.querySelector('.m-scanner-torch').addEventListener('click', toggleTorch);
    }

    async function open({ title = 'Scan barcode', onResult }) {
        if (!overlay) build();
        current = onResult;
        busy = false;
        overlay.querySelector('.m-scanner-title').textContent = title;
        overlay.querySelector('.m-scanner-manual input').value = '';
        setMsg('Arahkan kamera ke barcode');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';

        if (!window.isSecureContext) {
            setMsg('Kamera hanya bisa dipakai lewat HTTPS. Ketik kode secara manual di bawah.', true);
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            setMsg('Modul kamera gagal dimuat. Ketik kode secara manual.', true);
            return;
        }
        try {
            reader = new Html5Qrcode('mScannerRegion', { formatsToSupport: FORMATS(), verbose: false,
                experimentalFeatures: { useBarCodeDetectorIfSupported: true } });
            await reader.start(
                { facingMode: 'environment' },
                { fps: 12, qrbox: (w, h) => ({ width: Math.min(w * 0.85, 420), height: Math.min(h * 0.45, 220) }), aspectRatio: 1.333 },
                (text) => done(text),
                () => {},
            );
            // tampilkan tombol senter bila kamera mendukung
            try {
                const caps = reader.getRunningTrackCameraCapabilities?.();
                if (caps?.torchFeature?.().isSupported()) overlay.querySelector('.m-scanner-torch').classList.remove('d-none');
            } catch (_) { /* abaikan */ }
        } catch (err) {
            setMsg('Tidak bisa membuka kamera: ' + (err?.message || err) + '. Pastikan izin kamera diberikan.', true);
        }
    }

    async function toggleTorch() {
        try {
            const torch = reader.getRunningTrackCameraCapabilities().torchFeature();
            await torch.apply(!torch.value());
        } catch (_) { /* abaikan */ }
    }

    function done(code) {
        if (busy) return;
        busy = true;
        feedback();
        const cb = current;
        close().then(() => cb && cb(code.trim()));
    }

    async function close() {
        overlay?.classList.remove('show');
        document.body.style.overflow = '';
        if (reader) {
            try { if (reader.isScanning) await reader.stop(); reader.clear(); } catch (_) { /* abaikan */ }
            reader = null;
        }
        overlay?.querySelector('.m-scanner-torch').classList.add('d-none');
    }

    function setMsg(text, warn = false) {
        const el = overlay.querySelector('.m-scanner-msg');
        el.textContent = text;
        el.classList.toggle('warn', warn);
    }

    /** Bunyi "beep" + getar singkat sebagai tanda scan berhasil. */
    function feedback(ok = true) {
        try { navigator.vibrate?.(ok ? 60 : [80, 60, 80]); } catch (_) { /* abaikan */ }
        try {
            const ctx = feedback.ctx ||= new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.frequency.value = ok ? 1650 : 300; o.type = 'square';
            g.gain.value = 0.08;
            o.connect(g); g.connect(ctx.destination);
            o.start(); o.stop(ctx.currentTime + (ok ? 0.08 : 0.25));
        } catch (_) { /* abaikan */ }
    }

    return { open, close, feedback };
})();
