{{-- Pager gaya ERP: "1-20 / 45  < >". Pakai: @include('partials.pager', ['paginator' => $receipts]) --}}
@if ($paginator->total() > 0)
    <div class="o-pager d-flex align-items-center gap-2">
        <span>{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} / {{ $paginator->total() }}</span>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-secondary {{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() }}" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a>
            <a class="btn btn-secondary {{ $paginator->hasMorePages() ? '' : 'disabled' }}" href="{{ $paginator->nextPageUrl() }}" title="Berikutnya"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>
@endif
