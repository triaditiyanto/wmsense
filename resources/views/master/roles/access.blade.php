@extends('layouts.app')
@section('title', 'Hak Akses · '.$role->name)

@section('content')
<form method="POST" action="{{ route('master.roles.access.save', $role) }}" data-ajax>
    @csrf <input type="hidden" name="_method" value="PUT">

    <div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
        <div class="o-breadcrumb me-auto">
            <a href="{{ route('master.roles.index') }}">Role &amp; Hak Akses</a>
            <span class="sep">/</span>
            <span class="current">{{ $role->name }}</span>
        </div>
    </div>

    <div class="o-form-header">
        @if ($canEdit)
            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
            <a href="{{ route('master.roles.access', $role) }}" class="btn btn-sm btn-secondary">Batal</a>
        @else
            <span class="text-muted small"><i class="bi bi-lock me-1"></i>Mode lihat saja: Anda tidak dapat mengubah hak akses role ini.</span>
        @endif
    </div>

    <div class="o-sheet-bg">
        <div class="o-sheet">
            <div class="o-doc-label">Hak Akses Role</div>
            <h1 class="o-title">{{ $role->name }} <span class="badge rounded-pill bg-dark-subtle text-dark-emphasis fs-6 align-middle">L{{ $role->level }}</span></h1>
            <div class="alert alert-danger form-alert d-none"></div>

            @foreach ($modules as $module)
                {{-- Header = module, detail = module detail --}}
                <div class="o-separator d-flex align-items-center">
                    <span class="me-auto"><i class="bi bi-{{ $module->icon }} me-1"></i> {{ $module->name }}</span>
                    @if ($canEdit)
                        <button type="button" class="btn btn-sm btn-link p-0 text-none js-check-module" style="text-transform:none">Centang semua</button>
                    @endif
                </div>
                <div class="o-list table-responsive mb-3">
                    <table class="table table-hover table-access mb-0">
                        <thead>
                        <tr>
                            <th style="width:40%">Menu / Fitur</th>
                            @foreach ($actions as $action)<th>{{ $action->label() }}</th>@endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($module->details as $detail)
                            @php($acc = $accesses->get($detail->id))
                            <tr>
                                <td>
                                    {{ $detail->name }}
                                    @unless ($detail->route_name)<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis ms-1">segera</span>@endunless
                                </td>
                                @foreach ($actions as $action)
                                    @php($mine = $me->hasAccess($detail->code, $action))
                                    <td>
                                        <div class="form-check form-switch d-inline-block m-0" @if (! $mine) title="Anda sendiri tidak punya akses ini" @endif>
                                            <input type="checkbox" class="form-check-input" name="access[{{ $detail->id }}][{{ $action->value }}]" value="1"
                                                   @checked($acc?->{$action->column()}) @disabled(! $canEdit || ! $mine)>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.js-check-module').forEach((btn) => btn.addEventListener('click', () => {
        const boxes = btn.closest('.o-separator').nextElementSibling.querySelectorAll('input[type=checkbox]:not(:disabled)');
        const allChecked = [...boxes].every((b) => b.checked);
        boxes.forEach((b) => (b.checked = !allChecked));
    }));
</script>
@endpush
