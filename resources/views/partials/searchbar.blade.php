{{--
    Kotak cari + filter gaya ERP. Filter aktif tampil sebagai "chip" (facet) yang bisa dihapus.
    Pakai:
    @include('partials.searchbar', [
        'placeholder' => 'Cari...',
        'filters' => [   // name => [label, [value => text]]
            'status' => ['Status', ['OPEN' => 'Open', ...]],
        ],
    ])
--}}
@php($filters = $filters ?? [])
<form method="GET" class="o-searchbar">
    <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        @foreach ($filters as $name => [$label, $options])
            @if (request()->filled($name) && isset($options[request($name)]))
                <span class="o-facet">
                    {{ $label }}: {{ $options[request($name)] }}
                    <a href="{{ request()->fullUrlWithQuery([$name => null, 'page' => null]) }}" title="Hapus filter">&times;</a>
                </span>
                <input type="hidden" name="{{ $name }}" value="{{ request($name) }}">
            @endif
        @endforeach
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ $placeholder ?? 'Cari...' }}">
        @if ($filters)
            <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Filter">
                <i class="bi bi-funnel"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-3" style="width:280px">
                @foreach ($filters as $name => [$label, $options])
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">{{ $label }}</label>
                        <select class="form-select form-select-sm" data-filter="{{ $name }}">
                            <option value="">Semua</option>
                            @foreach ($options as $value => $text)
                                <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-primary js-apply-filter">Terapkan</button>
                    <a href="{{ url()->current() }}" class="btn btn-sm btn-secondary">Reset</a>
                </div>
            </div>
        @endif
    </div>
</form>
