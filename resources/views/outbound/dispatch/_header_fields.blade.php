{{-- Field header Surat Jalan (dipakai modal buat & ubah) --}}
<div class="o-group mb-0">
    <div>
        <div class="o-field"><label>Tgl Kirim</label><input type="date" name="ship_date" class="form-control"></div>
        <div class="o-field"><label>No. Kendaraan</label><input name="vehicle_no" class="form-control text-uppercase" placeholder="B 1234 XYZ" maxlength="20"></div>
        <div class="o-field"><label>Ekspedisi</label><input name="carrier" class="form-control" placeholder="Armada sendiri / nama ekspedisi" maxlength="100"></div>
    </div>
    <div>
        <div class="o-field"><label>Nama Sopir</label><input name="driver_name" class="form-control" maxlength="100"></div>
        <div class="o-field"><label>Telp. Sopir</label><input name="driver_phone" class="form-control" maxlength="30"></div>
    </div>
</div>
<div class="o-field" style="grid-template-columns:150px 1fr"><label>Catatan</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
