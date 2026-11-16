{{--
    Panduan animasi per modul. Isi dari config/guides.php[$code]; dikendalikan public/js/m-guide.js.
    Dipasang otomatis oleh layouts/app.blade.php untuk menu yang sedang dibuka.
    Desktop: jendela modal di tengah. HP (< 768px): layar penuh.
--}}
@props(['code'])
@php($g = config("guides.{$code}"))
@if ($g)
<div class="g-guide is-modal" id="pageGuide" role="dialog" aria-modal="true" aria-label="Panduan {{ $g['title'] }}" hidden
     data-guide-key="mGuide:{{ $code }}:v{{ $g['version'] ?? 1 }}.2:{{ auth()->id() }}" data-g-finish="Mengerti">
    <div class="g-card">
        <div class="g-top">
            <span class="g-count"><i class="bi bi-question-circle me-1"></i>{{ $g['title'] }} · <span data-g-index>1</span>/<span data-g-total>{{ count($g['slides']) }}</span></span>
            <button type="button" class="g-skip" data-g-skip>Lewati</button>
        </div>
        <div class="g-track">
            @foreach ($g['slides'] as $i => $slide)
                <section class="g-slide" data-step="{{ $i }}">
                    <x-guide-scene :slide="$slide" />
                    @if (! empty($slide['badge']))<div class="g-badge">{{ $slide['badge'] }}</div>@endif
                    <h2 class="g-title">{{ $slide['title'] }}</h2>
                    <p class="g-text">{!! \Illuminate\Support\Str::inlineMarkdown($slide['text']) !!}</p>
                </section>
            @endforeach
        </div>
        <div class="g-bottom">
            <div class="g-dots" data-g-dots></div>
            <label class="g-dontshow"><input type="checkbox" data-g-dontshow> Jangan tampilkan lagi saat membuka modul ini</label>
            <div class="g-nav">
                <button type="button" class="btn g-btn-back" data-g-prev aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn g-btn-next" data-g-next>Lanjut <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>
@endif
