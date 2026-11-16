{{-- Pemakaian kapasitas satu lokasi. $c = hasil LocationCapacityService::statusFor()[id] --}}
@if ($c['percent'] === null)
    <span class="small text-muted" title="Kapasitas tidak dibatasi">{{ $c['used_lpn'] }} LPN · bebas</span>
@else
    @php($color = $c['percent'] >= 100 ? 'bg-danger' : ($c['percent'] >= 80 ? 'bg-warning' : 'bg-success'))
    <div class="o-cap" title="Berat {{ $c['weight'] }}{{ $c['max_weight_kg'] ? ' / '.$c['max_weight_kg'] : '' }} kg · Volume {{ $c['volume'] }}{{ $c['max_volume_m3'] ? ' / '.$c['max_volume_m3'] : '' }} m³">
        <div class="progress"><div class="progress-bar {{ $color }}" style="width: {{ $c['percent'] }}%"></div></div>
        <span class="small text-muted text-nowrap">{{ $c['used_lpn'] }}{{ $c['max_lpn'] !== null ? '/'.$c['max_lpn'] : '' }} LPN</span>
    </div>
@endif
