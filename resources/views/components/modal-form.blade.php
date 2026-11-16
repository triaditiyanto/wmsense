{{--
    Modal berisi form AJAX (pola input standar WMSense).
    Pakai:
      <x-modal-form id="modalZone" title="Zona" size="modal-lg">
          <x-field label="Kode" required><input name="code" class="form-control"></x-field>
      </x-modal-form>
    Tombol pembuka mengisi action/method/record lewat data-* (lihat public/js/wms.js).
--}}
@props(['id', 'title' => '', 'size' => '', 'submit' => 'Simpan'])
<div class="modal fade" id="{{ $id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable {{ $size }}">
        <form {{ $attributes->merge(['class' => 'modal-content o-input', 'method' => 'POST']) }} data-ajax>
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="modal-header">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                {{ $slot }}
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">{{ $submit }}</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
        </form>
    </div>
</div>
