{{-- Modal HEADER item (tambah di index, ubah di show) --}}
<x-modal-form id="modalItem" title="Item" size="modal-xl">
    <div class="o-group mb-0">
        <div>
            <x-field label="Owner" required>
                <select name="owner_id" class="form-select">
                    <option value=""></option>
                    @foreach ($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="SKU" required><input name="sku" class="form-control text-uppercase"></x-field>
            <x-field label="Nama" required><input name="name" class="form-control"></x-field>
            <x-field label="Item class" required>
                <select name="item_class_id" class="form-select">
                    <option value=""></option>
                    @foreach ($classes->groupBy(fn ($c) => $c->itemGroup->name) as $group => $cs)
                        <optgroup label="{{ $group }}">@foreach ($cs as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</optgroup>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Base UOM" required help="Satuan terkecil; semua stok disimpan dalam satuan ini.">
                <select name="base_uom_id" class="form-select">@foreach ($uoms as $u)<option value="{{ $u->id }}">{{ $u->code }} - {{ $u->name }}</option>@endforeach</select>
            </x-field>
            <x-field label="Barcode"><input name="barcode" class="form-control font-monospace"></x-field>
            <x-active-switch />
        </div>
        <div>
            <x-field label="Rotasi" required>
                <select name="rotation_rule" class="form-select">@foreach ($rotations as $r)<option value="{{ $r->value }}">{{ $r->value }} - {{ $r->label() }}</option>@endforeach</select>
            </x-field>
            <x-field label="Kelas suhu" required>
                <select name="temperature_class" class="form-select">@foreach ($temperatures as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
            </x-field>
            <x-field label="Pakai lot"><input type="hidden" name="is_lot_tracked" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_lot_tracked" value="1"></div></x-field>
            <x-field label="Pakai expired" help="Otomatis aktif bila rotasi FEFO."><input type="hidden" name="is_expiry_tracked" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_expiry_tracked" value="1"></div></x-field>
            <x-field label="Umur simpan (hari)"><input type="number" min="1" name="shelf_life_days" class="form-control"></x-field>
            <x-field label="Min. sisa umur (hari)" help="Batas minimal saat barang diterima."><input type="number" min="0" name="min_shelf_life_days" class="form-control"></x-field>
            <x-field label="Barang B3"><input type="hidden" name="is_hazardous" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_hazardous" value="1"></div></x-field>
        </div>
    </div>
    <div class="o-separator">Fisik per 1 base UOM</div>
    <div class="o-group mb-0">
        <div>
            <x-field label="Berat (kg)"><input type="number" step="any" min="0" name="weight_kg" class="form-control"></x-field>
            <x-field label="Panjang (cm)"><input type="number" step="any" min="0" name="length_cm" class="form-control"></x-field>
        </div>
        <div>
            <x-field label="Lebar (cm)"><input type="number" step="any" min="0" name="width_cm" class="form-control"></x-field>
            <x-field label="Tinggi (cm)"><input type="number" step="any" min="0" name="height_cm" class="form-control"></x-field>
        </div>
    </div>
    <x-field label="Deskripsi"><textarea name="description" rows="2" class="form-control"></textarea></x-field>
</x-modal-form>
