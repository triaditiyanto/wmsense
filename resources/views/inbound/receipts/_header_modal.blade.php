{{-- Modal HEADER receipt: dipakai untuk buat baru (index) dan edit (show). --}}
<div class="modal fade" id="modalReceiptHeader" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content o-input" method="POST" data-ajax>
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="modal-header"><h5 class="modal-title">Receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-group mb-0">
                    <div>
                        <div class="o-field">
                            <label class="o-required">Gudang</label>
                            <select name="warehouse_id" class="form-select">
                                @foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="o-field">
                            <label class="o-required">Owner</label>
                            <select name="owner_id" class="form-select">
                                <option value=""></option>
                                @foreach ($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="o-field">
                            <label class="o-required">Tipe</label>
                            <select name="receipt_type" class="form-select">
                                @foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="o-field"><label>Tgl Kedatangan</label><input type="date" name="expected_date" class="form-control"></div>
                        <div class="o-field"><label>No. Referensi</label><input name="reference_no" class="form-control" placeholder="No. PO / DO"></div>
                        {{-- Pengirim: tampil sesuai tipe. Daftar dicari ke server & disaring per owner. --}}
                        <x-field label="Supplier" data-show-when="receipt_type=PURCHASE,TRANSFER,OTHER">
                            <select name="supplier_id" data-remote="{{ route('lookup.suppliers') }}" data-remote-depends="owner_id" placeholder="Cari supplier..."></select>
                        </x-field>
                        <x-field label="Customer" data-show-when="receipt_type=RETURN">
                            <select name="customer_id" data-remote="{{ route('lookup.customers') }}" data-remote-depends="owner_id" placeholder="Cari customer..."></select>
                        </x-field>
                    </div>
                </div>
                <div class="o-field" style="grid-template-columns:150px 1fr"><label>Catatan</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
