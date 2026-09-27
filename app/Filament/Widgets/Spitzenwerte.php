<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Spenders\SpenderResource;
use App\Filament\Resources\Spendes\SpendeResource;
use App\Models\Spende;
use App\Models\Spender;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Spitzenwerte extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 2;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            $this->hoechsteSpende(),
            $this->meisteSpenden(),
        ];
    }

    private function hoechsteSpende(): Stat
    {
        $spende = Spende::query()
            ->with('spender')
            // id als Tiebreaker, sonst ist die Auswahl bei gleichem Betrag
            // von Abfrage zu Abfrage verschieden.
            ->orderByDesc('betrag')
            ->orderByDesc('id')
            ->first();

        if (! $spende) {
            return Stat::make('Höchste Einzelspende', '—')
                ->description('Noch keine Spende erfasst')
                ->icon('heroicon-o-banknotes')
                ->color('gray');
        }

        return Stat::make('Höchste Einzelspende', $spende->betrag_formatiert)
            ->description(trim(($spende->spender?->vollname ?? 'Unbekannt').' · '.$spende->spendendatum?->format('d.m.Y')))
            ->descriptionIcon('heroicon-m-user')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->url(SpendeResource::getUrl('edit', ['record' => $spende]));
    }

    private function meisteSpenden(): Stat
    {
        $spender = Spender::query()
            ->withCount('spenden')
            // whereHas statt having: withCount erzeugt eine korrelierte
            // Subquery, auf deren Alias sich HAVING weder in SQLite noch in
            // PostgreSQL beziehen kann.
            ->whereHas('spenden')
            ->orderByDesc('spenden_count')
            ->orderByDesc('id')
            ->first();

        if (! $spender) {
            return Stat::make('Meiste Spenden', '—')
                ->description('Noch keine Spende erfasst')
                ->icon('heroicon-o-trophy')
                ->color('gray');
        }

        $anzahl = (int) $spender->spenden_count;

        return Stat::make('Meiste Spenden', $anzahl.' '.($anzahl === 1 ? 'Spende' : 'Spenden'))
            ->description($spender->vollname)
            ->descriptionIcon('heroicon-m-user')
            ->icon('heroicon-o-trophy')
            ->color('primary')
            ->url(SpenderResource::getUrl('edit', ['record' => $spender]));
    }
}
