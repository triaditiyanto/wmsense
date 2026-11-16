@props(['active' => true])
@if ($active)
    <span class="badge rounded-pill bg-success-subtle text-success-emphasis">Aktif</span>
@else
    <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Arsip</span>
@endif
