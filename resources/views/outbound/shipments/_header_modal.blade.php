{{-- Modal HEADER shipment: dipakai untuk buat baru (index) dan ubah (show). --}}
<div class="modal fade" id="modalShipmentHeader" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content o-input" method="POST" data-ajax>
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="modal-header"><h5 class="modal-title">Shipment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
                        <x-field label="Customer" required>
                            <select name="customer_id" data-remote="{{ route('lookup.customers') }}" data-remote-depends="owner_id" placeholder="Cari customer..."></select>
                        </x-field>
                        <x-field label="Alamat kirim" help="Kosong = alamat default customer.">
                            <select name="customer_address_id" class="form-select" id="shipAddress"><option value="">Alamat default</option></select>
                        </x-field>
                    </div>
                    <div>
                        <div class="o-field"><label>Tgl Order</label><input type="date" name="order_date" class="form-control"></div>
                        <div class="o-field"><label>Tgl Kirim</label><input type="date" name="expected_ship_date" class="form-control"></div>
                        <div class="o-field"><label>No. Referensi</label><input name="reference_no" class="form-control" placeholder="No. SO / PO customer"></div>
                        <x-field label="Aturan alokasi" help="Kosong = ikut aturan rotasi masing-masing item. Aturan di Wave Planning menimpa ini.">
                            <select name="allocation_rule" class="form-select">
                                <option value="">Ikut item</option>
                                @foreach ($rules as $r)<option value="{{ $r->value }}">{{ $r->value }} - {{ $r->short() }}</option>@endforeach
                            </select>
                        </x-field>
                        <x-field label="Prioritas" help="1 = paling penting, 9 = paling akhir.">
                            <input type="number" name="priority" class="form-control" min="1" max="9" style="max-width:110px">
                        </x-field>
                    </div>
                </div>
                <div class="o-field" style="grid-template-columns:150px 1fr"><label>Catatan</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Alamat kirim mengikuti customer yang dipilih (daftar alamat ikut dikirim oleh lookup.customers)
    (() => {
        const modal = document.getElementById('modalShipmentHeader');
        const cust = modal.querySelector('[name=customer_id]');
        const addr = document.getElementById('shipAddress');
        let pending = null;
        const render = (addresses) => {
            addr.innerHTML = '<option value="">Alamat default</option>';
            (addresses || []).forEach((a) => addr.add(new Option(`${a.label} - ${a.address}${a.is_default ? ' (default)' : ''}`, a.id)));
            if (pending) { addr.value = String(pending); pending = null; }
        };
        const onChange = () => render(cust.tomselect?.options[cust.value]?.addresses);
        cust.addEventListener('change', onChange);
        cust.tomselect?.on('change', onChange);
        modal.addEventListener('wms:fill', (e) => {
            pending = e.detail.record.customer_address_id || null;
            const opt = e.detail.record.customer_addresses;
            render(opt || []);
        });
    })();
</script>
@endpush
