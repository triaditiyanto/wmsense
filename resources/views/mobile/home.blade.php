@extends('layouts.mobile')
@section('title', 'WMSense')
@section('subtitle', 'Halo, '.$user->name)

@section('content')
<div class="m-tiles">
    @foreach ($menus as $m)
        @php($allowed = $user->hasAccess($m['code'], 'view'))
        @continue(! $allowed)
        @if ($m['ready'])
            <a href="{{ route($m['route']) }}" class="m-tile">
                <span class="m-tile-icon"><i class="bi bi-{{ $m['icon'] }}"></i></span>
                <span class="m-tile-label">{{ $m['label'] }}</span>
            </a>
        @else
            <div class="m-tile is-disabled">
                <span class="m-tile-icon"><i class="bi bi-{{ $m['icon'] }}"></i></span>
                <span class="m-tile-label">{{ $m['label'] }}</span>
                <span class="m-tile-badge">segera</span>
            </div>
        @endif
    @endforeach
</div>
@endsection
