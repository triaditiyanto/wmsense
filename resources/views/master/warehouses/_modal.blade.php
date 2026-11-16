<x-modal-form id="modalWarehouse" title="Gudang" size="modal-lg">
    <div class="o-group mb-0">
        <div>
            <x-field label="Kode" required help="Dipakai di nomor dokumen, tidak bisa diubah.">
                <input name="code" class="form-control text-uppercase" maxlength="20" data-lock-on-edit>
            </x-field>
            <x-field label="Nama" required><input name="name" class="form-control"></x-field>
        </div>
        <div>
            <x-field label="Zona waktu" required>
                <select name="timezone" class="form-select">
                    @foreach ($timezones as $tz => $label)<option value="{{ $tz }}">{{ $label }}</option>@endforeach
                </select>
            </x-field>
            <x-active-switch />
        </div>
    </div>
    <x-field label="Alamat"><textarea name="address" rows="2" class="form-control"></textarea></x-field>
</x-modal-form>
