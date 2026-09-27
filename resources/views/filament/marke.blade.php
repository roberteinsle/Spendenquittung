{{--
    Markenzeile des Panels: Herzfigur aus den Einstellungen neben dem Namen.
    Filament rahmt das Ganze in <div class="fi-logo" style="height: ..."> ein,
    deshalb hier keine Schriftgröße oder -stärke setzen – die wird geerbt und
    sieht damit aus wie der reine Textfall.
--}}
@php
    $pfad = \App\Models\Setting::get('herzfigur_pfad');

    $herzfigur = ($pfad && \Illuminate\Support\Facades\Storage::disk('public')->exists($pfad))
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($pfad)
        : null;
@endphp

<span style="display: inline-flex; align-items: center; gap: 0.5rem; height: 100%;">
    @if ($herzfigur)
        <img src="{{ $herzfigur }}" alt="" style="height: 100%; width: auto;">
    @endif

    <span>{{ filament()->getBrandName() }}</span>
</span>
