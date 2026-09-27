{{--
    Layout über Inline-Styles statt Tailwind-Klassen: das Panel lädt nur
    Filaments fertig kompiliertes CSS, das keine Utility-Klassen wie "flex" oder
    "w-full" enthält. Alles Sichtbare kommt deshalb aus Filament-Komponenten.
--}}
<x-filament-panels::page.simple>
    @php
        $gewaehlt = $this->getGewaehlterBenutzer();
    @endphp

    @if ($gewaehlt)
        <form wire:submit="anmelden" style="display: grid; gap: 1.5rem;">
            <x-filament::input.wrapper
                :valid="! $errors->has('pin')"
                prefix-icon="heroicon-m-lock-closed"
            >
                <x-filament::input
                    type="password"
                    wire:model="pin"
                    inputmode="numeric"
                    autocomplete="off"
                    autofocus
                    :placeholder="'PIN für ' . $gewaehlt->name"
                />
            </x-filament::input.wrapper>

            @error('pin')
                <p style="margin-top: -1rem; font-size: 0.875rem; color: rgb(var(--danger-600, 220 38 38));">
                    {{ $message }}
                </p>
            @enderror

            <div style="display: grid; gap: 0.75rem;">
                <x-filament::button type="submit" size="lg" style="width: 100%;">
                    Anmelden
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="zurueck"
                    icon="heroicon-m-arrow-left"
                    style="width: 100%;"
                >
                    Zurück
                </x-filament::button>
            </div>
        </form>
    @else
        <div style="display: grid; gap: 0.75rem;">
            @forelse ($this->getBenutzer() as $benutzer)
                <x-filament::button
                    tag="button"
                    wire:click="waehle({{ $benutzer->id }})"
                    wire:loading.attr="disabled"
                    color="gray"
                    size="lg"
                    :icon="$benutzer->brauchtPin() ? 'heroicon-m-lock-closed' : 'heroicon-m-user'"
                    style="width: 100%;"
                >
                    {{ $benutzer->name }}
                </x-filament::button>
            @empty
                <div style="display: grid; gap: 0.75rem; font-size: 0.875rem;">
                    <p>Es ist noch kein Benutzer angelegt.</p>

                    <p>Im Docker-Betrieb auf dem Server:</p>

                    <pre style="overflow-x: auto; font-size: 0.75rem;"><code>docker compose exec app php artisan db:seed --force</code></pre>

                    <p>
                        Damit das bei jedem Start von allein passiert,
                        <code>AUTORUN_LARAVEL_MIGRATION_SEED=true</code> setzen.
                    </p>
                </div>
            @endforelse
        </div>
    @endif
</x-filament-panels::page.simple>
