{{-- Switch Aktif/Arsip di dalam modal. Nilai 0 dikirim bila tidak dicentang (hidden input). --}}
@props(['name' => 'is_active', 'label' => 'Aktif'])
<x-field :label="$label">
    <input type="hidden" name="{{ $name }}" value="0">
    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="{{ $name }}" value="1"></div>
</x-field>
