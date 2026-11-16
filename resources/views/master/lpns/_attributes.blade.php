{{-- Field atribut LPN (dipakai modal generate & registrasi/ubah) --}}
<x-field label="Tipe" required>
    <select name="lpn_type" class="form-select">
        @foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
    </select>
</x-field>
<x-field label="Jenis pallet" class="js-pallet-field">
    <select name="pallet_id" class="form-select">
        <option value=""></option>
        @foreach ($pallets as $p)<option value="{{ $p->id }}">{{ $p->code }} - {{ $p->name }}</option>@endforeach
    </select>
</x-field>
<x-field label="Catatan"><textarea name="notes" rows="2" class="form-control"></textarea></x-field>
