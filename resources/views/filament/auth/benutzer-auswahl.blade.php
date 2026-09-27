<x-filament-panels::page.simple>
    @php
        $gewaehlt = $this->getGewaehlterBenutzer();
    @endphp

    @if ($gewaehlt)
        <form wire:submit="anmelden" class="space-y-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Angemeldet als <span class="font-medium text-gray-950 dark:text-white">{{ $gewaehlt->name }}</span>
            </p>

            <x-filament::input.wrapper :valid="! $errors->has('pin')">
                <x-filament::input
                    type="password"
                    wire:model="pin"
                    inputmode="numeric"
                    autocomplete="off"
                    autofocus
                    placeholder="PIN"
                />
            </x-filament::input.wrapper>

            @error('pin')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror

            <div class="flex gap-3">
                <x-filament::button type="submit" class="flex-1">
                    Anmelden
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="zurueck">
                    Zurück
                </x-filament::button>
            </div>
        </form>
    @else
        <div class="space-y-3">
            @forelse ($this->getBenutzer() as $benutzer)
                <button
                    type="button"
                    wire:click="waehle({{ $benutzer->id }})"
                    wire:loading.attr="disabled"
                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left transition hover:border-primary-500 hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                >
                    <span class="font-medium text-gray-950 dark:text-white">{{ $benutzer->name }}</span>

                    @if ($benutzer->brauchtPin())
                        <x-filament::icon
                            icon="heroicon-m-lock-closed"
                            class="h-5 w-5 text-gray-400 dark:text-gray-500"
                        />
                    @endif
                </button>
            @empty
                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                    <p>Es ist noch kein Benutzer angelegt.</p>

                    <p>Im Docker-Betrieb auf dem Server:</p>
                    <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs dark:bg-white/5"><code>docker compose exec app php artisan db:seed --force</code></pre>

                    <p>
                        Damit das bei jedem Start von allein passiert,
                        <code>AUTORUN_LARAVEL_MIGRATION_SEED=true</code> setzen.
                    </p>
                </div>
            @endforelse
        </div>
    @endif
</x-filament-panels::page.simple>
