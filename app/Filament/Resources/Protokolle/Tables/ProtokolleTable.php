<?php

namespace App\Filament\Resources\Protokolle\Tables;

use App\Enums\ProtokollAktion;
use App\Exports\ProtokollExport;
use App\Filament\Actions\ExportActions;
use App\Models\Protokolleintrag;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProtokolleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('benutzer_name')
                    ->label('Benutzer')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('aktion')
                    ->label('Aktion')
                    ->badge()
                    ->sortable(),

                TextColumn::make('art')
                    ->label('Art')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('bezeichnung')
                    ->label('Betrifft')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('beschreibung')
                    ->label('Details')
                    ->searchable()
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('aktion')
                    ->label('Aktion')
                    ->options(ProtokollAktion::class)
                    ->multiple(),

                SelectFilter::make('benutzer_id')
                    ->label('Benutzer')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple(),

                SelectFilter::make('betrifft_type')
                    ->label('Art')
                    ->options([
                        Spende::class => 'Bescheinigung',
                        Spender::class => 'Spender',
                    ]),

                Filter::make('zeitraum')
                    ->schema([
                        DatePicker::make('von')
                            ->label('Von')
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('bis')
                            ->label('Bis')
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['von'] ?? null, fn (Builder $q, $von) => $q->whereDate('created_at', '>=', $von))
                        ->when($data['bis'] ?? null, fn (Builder $q, $bis) => $q->whereDate('created_at', '<=', $bis))
                    )
                    ->indicateUsing(function (array $data): array {
                        $hinweise = [];
                        if ($data['von'] ?? null) {
                            $hinweise[] = 'Ab '.Carbon::parse($data['von'])->format('d.m.Y');
                        }
                        if ($data['bis'] ?? null) {
                            $hinweise[] = 'Bis '.Carbon::parse($data['bis'])->format('d.m.Y');
                        }

                        return $hinweise;
                    }),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Protokolleintrag')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schließen')
                    ->visible(fn (Protokolleintrag $record): bool => filled($record->aenderungen))
                    ->infolist([
                        Section::make()
                            ->schema([
                                TextEntry::make('bezeichnung')->label('Betrifft'),
                                TextEntry::make('beschreibung')->label('Details')->placeholder('—'),
                                KeyValueEntry::make('aenderungen')
                                    ->label('Geänderte Felder')
                                    ->keyLabel('Feld')
                                    ->valueLabel('alt → neu')
                                    ->state(fn (Protokolleintrag $record): array => collect($record->aenderungen ?? [])
                                        ->map(fn (array $wert): string => (string) ($wert['alt'] ?? '–').' → '.(string) ($wert['neu'] ?? '–'))
                                        ->all()
                                    ),
                            ]),
                    ]),
            ])
            ->headerActions([
                ExportActions::tabelle(ProtokollExport::class, 'Protokoll'),
            ])
            ->toolbarActions([]);
    }
}
