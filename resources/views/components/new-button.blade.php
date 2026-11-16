{{-- Tombol "Baru" di control panel, tampil hanya bila punya akses create. --}}
@props(['access', 'modal', 'action', 'title', 'record' => []])
@can('access', [$access, 'create'])
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#{{ $modal }}"
            data-action="{{ $action }}" data-method="POST" data-title="{{ $title }}"
            data-record="{{ json_encode($record) }}">
        {{ $slot->isEmpty() ? 'Baru' : $slot }}
    </button>
@endcan
