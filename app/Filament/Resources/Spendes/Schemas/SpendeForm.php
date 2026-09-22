<?php

namespace App\Filament\Resources\Spendes\Schemas;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\SpendeStatus;
use App\Models\Foerderungszweck;
use App\Models\Spender;
use App\Services\BetragInWortenService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Schemas\Schema;

class SpendeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Spender')
                    ->schema([
                        Select::make('spender_id')
                            ->label('Spender')
                            ->options(fn () => Spender::aktiv()
                                ->orderBy('nachname')
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->id => "{$s->nachname}, {$s->vorname}" . ($s->firma ? " ({$s->firma})" : '') . " [{$s->spendernummer}]"])
                            )
                            ->searchable()
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Spende')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('spendendatum')
                                    ->label('Spendendatum')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),

                                TextInput::make('betrag')
                                    ->label('Betrag (€)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if (! empty($state) && is_numeric(str_replace(',', '.', $state))) {
                                            $betrag = (float) str_replace(',', '.', $state);
                                            try {
                                                $set('betrag_in_worten', app(BetragInWortenService::class)->konvertiere($betrag));
                                            } catch (\Throwable) {
                                                // ignore invalid amounts during typing
                                            }
                                        }
                                    }),
                            ]),

                        TextInput::make('betrag_in_worten')
                            ->label('Betrag in Worten')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Wird automatisch befüllt, kann aber korrigiert werden.'),

                        Select::make('foerderungszweck_id')
                            ->label('Förderungszweck')
                            ->options(fn () => Foerderungszweck::aktiv()->pluck('name', 'id'))
                            ->required()
                            ->native(false),

                        TextInput::make('anlass')
                            ->label('Anlass (optional)')
                            ->placeholder('z. B. Geburtstag, KiHi Kapstadt'),
                    ]),

                Section::make('Bescheinigung')
                    ->schema([
                        Radio::make('ankreuzfeld')
                            ->label('Die Zuwendung...')
                            ->options(AnkreuzfeldTyp::class)
                            ->required()
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('ausstellungsdatum')
                                    ->label('Ausstellungsdatum')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),

                                TextInput::make('bescheinigungsnummer')
                                    ->label('Bescheinigungsnummer')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('wird automatisch vergeben'),
                            ]),

                        Select::make('status')
                            ->label('Status')
                            ->options(SpendeStatus::class)
                            ->default(SpendeStatus::Erfasst->value)
                            ->required()
                            ->native(false)
                            ->visibleOn('edit'),

                        TextInput::make('alte_lfd_nr')
                            ->label('Alte lfd. Nr. (nur für Import)')
                            ->visibleOn('edit')
                            ->dehydrated(true),
                    ]),
            ]);
    }
}
