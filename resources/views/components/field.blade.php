{{-- Baris field form: label kiri, input kanan. <x-field label="Nama" required help="...">input</x-field> --}}
@props(['label', 'required' => false, 'help' => null])
<div {{ $attributes->merge(['class' => 'o-field']) }}>
    <label @class(['o-required' => $required])>{{ $label }}</label>
    <div>
        {{ $slot }}
        @if ($help)<div class="form-text">{{ $help }}</div>@endif
    </div>
</div>
