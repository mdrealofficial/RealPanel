@php
    $panelName = \App\Models\Setting::get('panel_name', 'RealPanel');
    $customLogo = \App\Models\Setting::get('logo_url');
@endphp
<a href="/" class="flex items-center">
    @if($customLogo)
        <img src="{{ $customLogo }}" alt="{{ $panelName }}" class="h-10 max-w-[220px] object-contain">
    @else
        <img src="{{ asset('img/logo.png') }}" alt="{{ $panelName }}" class="h-10 max-w-[220px] object-contain">
    @endif
</a>
