{{-- Field bersama untuk Supplier & Customer (header). --}}
<div class="o-group mb-0">
    <div>
        <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
        <x-field label="Nama" required><input name="name" class="form-control"></x-field>
        <x-field label="Owner" help="Kosong = bisa dipakai semua owner.">
            <select name="owner_id" class="form-select">
                <option value="">Semua owner (bersama)</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
        </x-field>
        <x-field label="NPWP"><input name="tax_id" class="form-control"></x-field>
        <x-active-switch />
    </div>
    <div>
        <x-field label="PIC"><input name="pic" class="form-control"></x-field>
        <x-field label="Telepon"><input name="phone" class="form-control"></x-field>
        <x-field label="Email"><input type="email" name="email" class="form-control"></x-field>
        <x-field label="Kota"><input name="city" class="form-control"></x-field>
    </div>
</div>
<x-field label="Alamat"><textarea name="address" rows="2" class="form-control"></textarea></x-field>
<x-field label="Catatan"><textarea name="notes" rows="2" class="form-control"></textarea></x-field>
