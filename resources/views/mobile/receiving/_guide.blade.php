{{--
    Panduan animasi "sebelum mulai" untuk operator penerimaan.
    Dikendalikan public/js/m-guide.js. Setiap .g-slide punya data-step (langkah wizard terkait) sehingga
    tombol "Lihat contoh" di tiap langkah bisa membuka slide yang sesuai.
    Animasi hanya berjalan pada slide aktif (.is-active) dan dimatikan bila HP memakai "kurangi gerakan".
--}}
<div class="g-guide" id="rcvGuide" role="dialog" aria-modal="true" aria-label="Panduan penerimaan" data-g-finish="Mulai terima barang" hidden>
    <div class="g-top">
        <span class="g-count"><span data-g-index>1</span>/<span data-g-total>7</span></span>
        <button type="button" class="g-skip" data-g-skip>Lewati</button>
    </div>

    <div class="g-track">
        {{-- 0. Pembuka --}}
        <section class="g-slide" data-step="0">
            <div class="g-scene">
                <div class="g-road"></div>
                <div class="g-truck"><i class="bi bi-truck"></i></div>
                <div class="g-drop g-drop-1"><i class="bi bi-box-seam-fill"></i></div>
                <div class="g-drop g-drop-2"><i class="bi bi-box-seam-fill"></i></div>
                <div class="g-drop g-drop-3"><i class="bi bi-box-seam-fill"></i></div>
                <div class="g-steps-mini"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span></div>
            </div>
            <h2 class="g-title">Terima barang dalam 5 langkah</h2>
            <p class="g-text">Barang datang, operator cukup <b>scan</b> dan <b>hitung</b>. Ikuti panduan singkat ini (± 30 detik) sebelum mulai.</p>
        </section>

        {{-- 1. Dock --}}
        <section class="g-slide" data-step="1">
            <div class="g-scene">
                <div class="g-floor"></div>
                <div class="g-target g-tag"><div class="g-barcode"></div><span>RCV-01</span></div>
                <div class="g-pin"><i class="bi bi-geo-alt-fill"></i></div>
                <div class="g-phone"><div class="g-phone-screen"><div class="g-laser"></div></div></div>
                <div class="g-ok"><i class="bi bi-check-lg"></i></div>
            </div>
            <div class="g-badge">Langkah 1</div>
            <h2 class="g-title">Pilih dock</h2>
            <p class="g-text">Sistem <b>menyarankan dock yang masih muat</b>. Scan label lokasi dock atau ketuk kartunya. Dock penuh? Otomatis dialihkan ke dock lain.</p>
        </section>

        {{-- 2. LPN --}}
        <section class="g-slide" data-step="2">
            <div class="g-scene">
                <div class="g-target g-pallet">
                    <div class="g-boxes"><i class="bi bi-box-seam-fill"></i><i class="bi bi-box-seam-fill"></i><i class="bi bi-box-seam-fill"></i></div>
                    <div class="g-pallet-base"></div>
                    <div class="g-sticker"><div class="g-barcode"></div><span>LPN-000123</span></div>
                </div>
                <div class="g-phone"><div class="g-phone-screen"><div class="g-laser"></div></div></div>
                <div class="g-ok"><i class="bi bi-check-lg"></i></div>
            </div>
            <div class="g-badge">Langkah 2</div>
            <h2 class="g-title">Scan label LPN pallet</h2>
            <p class="g-text">Setiap pallet wajib punya <b>label LPN</b>. Belum ada label? Tekan <b>Pallet baru</b>, nomor dibuat otomatis, lalu tulis/tempel di pallet.</p>
        </section>

        {{-- 3. Item --}}
        <section class="g-slide" data-step="3">
            <div class="g-scene">
                <div class="g-target g-carton">
                    <i class="bi bi-box2-fill"></i>
                    <div class="g-barcode g-barcode-sm"></div>
                </div>
                <div class="g-phone"><div class="g-phone-screen"><div class="g-laser"></div></div></div>
                <div class="g-beep"></div>
                <div class="g-ok"><i class="bi bi-check-lg"></i></div>
            </div>
            <div class="g-badge">Langkah 3</div>
            <h2 class="g-title">Scan barcode barang</h2>
            <p class="g-text">Scan barcode di <b>karton</b> atau <b>satuan</b>. Barcode karton otomatis memilih satuan CTN. Barcode rusak? Pilih item dari daftar.</p>
        </section>

        {{-- 4. Detail --}}
        <section class="g-slide" data-step="4">
            <div class="g-scene">
                <div class="g-form">
                    <div class="g-row"><span>Qty</span><span class="g-qty"><i class="bi bi-dash"></i><b data-count-to="24">0</b><i class="bi bi-plus"></i></span><span class="g-uom">CTN</span></div>
                    <div class="g-row"><span>Lot</span><span class="g-type">TB-2610</span></div>
                    <div class="g-row"><span>Expired</span><span class="g-date"><i class="bi bi-calendar-check"></i> 20/10/2027</span></div>
                    <div class="g-row"><span>Kondisi</span><span class="g-seg"><span class="on">Baik</span><span>Rusak</span></span></div>
                </div>
            </div>
            <div class="g-badge">Langkah 4</div>
            <h2 class="g-title">Hitung &amp; cek kemasan</h2>
            <p class="g-text">Isi qty <b>sesuai fisik yang dihitung</b>, bukan sesuai dokumen. Salin lot &amp; tanggal expired dari kemasan. Barang rusak, pilih <b>Rusak</b>.</p>
        </section>

        {{-- 5. Terima --}}
        <section class="g-slide" data-step="5">
            <div class="g-scene">
                <div class="g-press"><i class="bi bi-box-arrow-in-down"></i> Terima</div>
                <svg class="g-check" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="23"/><path d="M15 27l7.5 7.5L38 19"/></svg>
                <div class="g-progress"><div></div></div>
            </div>
            <div class="g-badge">Langkah 5</div>
            <h2 class="g-title">Cek ringkasan, lalu Terima</h2>
            <p class="g-text">Pastikan ringkasan sesuai barang, tekan <b>Terima</b>. Lanjut scan item berikutnya di pallet yang sama, atau mulai pallet baru.</p>
        </section>

        {{-- 6. Checklist sebelum mulai --}}
        <section class="g-slide" data-step="6">
            <div class="g-checklist">
                <label class="g-chk"><input type="checkbox"><span><i class="bi bi-file-earmark-text"></i> Surat jalan / dokumen ada di tangan</span></label>
                <label class="g-chk"><input type="checkbox"><span><i class="bi bi-upc"></i> Label LPN kosong tersedia</span></label>
                <label class="g-chk"><input type="checkbox"><span><i class="bi bi-camera"></i> Kamera diizinkan / scanner genggam siap</span></label>
                <label class="g-chk"><input type="checkbox"><span><i class="bi bi-boxes"></i> Barang sudah diturunkan di area dock</span></label>
            </div>
            <h2 class="g-title">Sebelum mulai</h2>
            <p class="g-text">Centang yang sudah siap. Panduan bisa dibuka lagi kapan saja lewat tombol <b><i class="bi bi-question-circle"></i> Panduan</b>.</p>
        </section>
    </div>

    <div class="g-bottom">
        <div class="g-dots" data-g-dots></div>
            <label class="g-dontshow"><input type="checkbox" data-g-dontshow> Jangan tampilkan lagi saat membuka penerimaan</label>
        <div class="g-nav">
            <button type="button" class="btn g-btn-back" data-g-prev aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="btn g-btn-next" data-g-next>Lanjut <i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
</div>
