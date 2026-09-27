<?php

namespace App\Filament\Pages;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\Anrede;
use App\Enums\ImportAktion;
use App\Exceptions\ImportFehlgeschlagen;
use App\Models\Foerderungszweck;
use App\Models\Spender;
use App\Services\ImportParserService;
use App\Services\ImportVorbereitungService;
use App\Services\SpendenImportService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Three-step import: pick a source, review the donor matching row by row,
 * then write the batch. Nothing is stored before the final step, so the
 * preview can be corrected as often as needed.
 */
class SpendenImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.spenden-import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Spenden importieren';

    protected static ?string $title = 'Spenden importieren';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'quelle'            => 'text',
            'ausstellungsdatum' => now()->toDateString(),
            'ankreuzfeld'       => AnkreuzfeldTyp::Unmittelbar->value,
            'zeilen'            => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    $this->schrittQuelle(),
                    $this->schrittZuordnung(),
                    $this->schrittPruefen(),
                ])
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" size="sm">Import starten</x-filament::button>'
                    ))),
            ])
            ->statePath('data');
    }

    // ─── Schritt 1: Quelle ──────────────────────────────────────────

    private function schrittQuelle(): Step
    {
        return Step::make('Quelle')
            ->description('Datei hochladen oder Zeilen einfügen')
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->schema([
                Radio::make('quelle')
                    ->label('Woher kommen die Daten?')
                    ->options([
                        'text'  => 'Aus der Zwischenablage eingefügt',
                        'datei' => 'Excel- oder CSV-Datei',
                    ])
                    ->required()
                    ->live()
                    ->inline()
                    ->inlineLabel(false),

                Textarea::make('zwischenablage')
                    ->label('Zeilen aus Excel')
                    ->helperText('Die erste Zeile muss die Spaltenüberschriften enthalten (An, Vorname1, Name, Straße, Plz, Ort, spende vom, Spende …).')
                    ->rows(12)
                    ->visible(fn (Get $get) => $get('quelle') === 'text')
                    ->required(fn (Get $get) => $get('quelle') === 'text'),

                FileUpload::make('datei')
                    ->label('Datei')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                    ])
                    ->storeFiles(false)
                    ->visible(fn (Get $get) => $get('quelle') === 'datei')
                    ->required(fn (Get $get) => $get('quelle') === 'datei'),

                Section::make('Gilt für alle importierten Spenden')
                    ->schema([
                        Select::make('foerderungszweck_id')
                            ->label('Förderungszweck')
                            ->options(fn () => Foerderungszweck::aktiv()->orderBy('sortierung')->pluck('name', 'id'))
                            ->required()
                            ->native(false),

                        Radio::make('ankreuzfeld')
                            ->label('Die Zuwendung…')
                            ->options(AnkreuzfeldTyp::class)
                            ->required(),

                        DatePicker::make('ausstellungsdatum')
                            ->label('Ausstellungsdatum')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                    ]),
            ])
            ->afterValidation(fn () => $this->zeilenEinlesen());
    }

    // ─── Schritt 2: Zuordnung ───────────────────────────────────────

    private function schrittZuordnung(): Step
    {
        return Step::make('Zuordnung')
            ->description('Spender prüfen und zuordnen')
            ->icon(Heroicon::OutlinedUsers)
            ->schema([
                Text::make(fn (Get $get) => $this->zusammenfassung($get('zeilen') ?? [])),

                Repeater::make('zeilen')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state) => $this->zeilenTitel($state))
                    ->schema([
                        Hidden::make('zeile_nr'),
                        Hidden::make('konfidenz'),
                        Hidden::make('treffer_name'),
                        Hidden::make('alte_lfd_nr'),

                        Text::make(fn (Get $get) => $get('hinweis'))
                            ->color('warning')
                            ->visible(fn (Get $get) => filled($get('hinweis'))),

                        Hidden::make('hinweis'),

                        Select::make('aktion')
                            ->label('Was soll passieren?')
                            ->options(ImportAktion::class)
                            ->required()
                            ->native(false)
                            ->live(),

                        Select::make('spender_id')
                            ->label('Vorhandener Spender')
                            ->options(fn (Get $get) => $this->spenderOptionen($get('spender_id')))
                            ->getSearchResultsUsing(fn (string $search) => $this->spenderSuche($search))
                            ->getOptionLabelUsing(fn ($value) => Spender::find($value)?->vollname)
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->visible(fn (Get $get) => ImportAktion::ausWert($get('aktion')) === ImportAktion::Verwenden),

                        Grid::make(3)
                            ->schema([
                                Select::make('anrede')
                                    ->label('Anrede')
                                    ->options(Anrede::class)
                                    ->native(false),
                                TextInput::make('vorname')->label('Vorname'),
                                TextInput::make('nachname')->label('Nachname'),
                                TextInput::make('firma')->label('Firma')->columnSpan(3),
                                TextInput::make('strasse')->label('Straße')->columnSpan(3),
                                TextInput::make('plz')->label('PLZ'),
                                TextInput::make('ort')->label('Ort')->columnSpan(2),
                            ])
                            ->visible(fn (Get $get) => ImportAktion::ausWert($get('aktion')) === ImportAktion::NeuAnlegen),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('spendendatum')
                                    ->label('Spendendatum')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),

                                TextInput::make('betrag')
                                    ->label('Betrag (€)')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01),

                                TextInput::make('betrag_in_worten')
                                    ->label('Betrag in Worten')
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('anlass')
                                    ->label('Anlass / Bemerkung')
                                    ->columnSpan(2),
                            ])
                            ->visible(fn (Get $get) => ImportAktion::ausWert($get('aktion')) !== ImportAktion::Ueberspringen),
                    ]),
            ]);
    }

    // ─── Schritt 3: Prüfen ──────────────────────────────────────────

    private function schrittPruefen(): Step
    {
        return Step::make('Prüfen & Importieren')
            ->description('Letzter Blick vor dem Speichern')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->schema([
                Text::make(fn (Get $get) => $this->zusammenfassung($get('zeilen') ?? [])),

                Text::make(
                    'Für jede importierte Spende wird eine Bescheinigungsnummer vergeben. '
                    . 'Die PDFs werden dabei noch nicht erzeugt – das geschieht anschließend '
                    . 'über die Liste der Bescheinigungen.'
                )->color('gray'),
            ]);
    }

    // ─── Aktionen ───────────────────────────────────────────────────

    /**
     * Parse the chosen source and build the preview rows for step 2.
     */
    private function zeilenEinlesen(): void
    {
        $istDatei = ($this->data['quelle'] ?? 'text') === 'datei';
        $feld     = $istDatei ? 'datei' : 'zwischenablage';
        $parser   = app(ImportParserService::class);

        try {
            $rohzeilen = $istDatei
                ? $parser->ausDatei($this->dateiPfad($this->data['datei'] ?? null))
                : $parser->ausText((string) ($this->data['zwischenablage'] ?? ''));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fehlerAmSchritt($feld, 'Die Daten konnten nicht gelesen werden: ' . $e->getMessage());
        }

        $zeilen = app(ImportVorbereitungService::class)->bereiteVor($rohzeilen);

        if ($zeilen === []) {
            $this->fehlerAmSchritt(
                $feld,
                'Es wurden keine auswertbaren Zeilen gefunden. Enthält die erste Zeile die Spaltenüberschriften?'
            );
        }

        $this->data['zeilen'] = $zeilen;
    }

    public function importieren(): void
    {
        $state = $this->form->getState();

        try {
            $ergebnis = app(SpendenImportService::class)->importiere($state['zeilen'] ?? [], [
                'foerderungszweck_id' => $state['foerderungszweck_id'],
                'ankreuzfeld'         => $state['ankreuzfeld'],
                'ausstellungsdatum'   => $state['ausstellungsdatum'],
            ]);
        } catch (ImportFehlgeschlagen $e) {
            Notification::make()
                ->title('Import abgebrochen – es wurde nichts gespeichert')
                ->body(implode("\n", array_slice($e->fehler, 0, 10)))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title("{$ergebnis['erstellt']} Bescheinigung(en) angelegt")
            ->body(
                "{$ergebnis['neue_spender']} neue Spender · "
                . "{$ergebnis['uebersprungen']} Zeile(n) übersprungen"
            )
            ->success()
            ->send();

        $this->mount();
    }

    // ─── Helfer ─────────────────────────────────────────────────────

    private function dateiPfad(mixed $datei): string
    {
        $datei = is_array($datei) ? reset($datei) : $datei;

        if (! $datei instanceof UploadedFile) {
            $this->fehlerAmSchritt('datei', 'Es wurde keine Datei hochgeladen.');
        }

        return $datei->getRealPath();
    }

    /**
     * Fail the current wizard step instead of throwing a 500, so the user stays
     * on the page and can correct the input.
     */
    private function fehlerAmSchritt(string $feld, string $meldung): never
    {
        throw ValidationException::withMessages(["data.{$feld}" => $meldung]);
    }

    /**
     * @param array<int, array<string, mixed>> $zeilen
     */
    private function zusammenfassung(array $zeilen): HtmlString
    {
        $zaehler = array_count_values(array_map(
            fn (array $z) => ImportAktion::ausWert($z['aktion'] ?? null)?->value ?? '',
            $zeilen,
        ));

        $verwenden = $zaehler[ImportAktion::Verwenden->value] ?? 0;
        $neu       = $zaehler[ImportAktion::NeuAnlegen->value] ?? 0;
        $skip      = $zaehler[ImportAktion::Ueberspringen->value] ?? 0;

        return new HtmlString(e(sprintf(
            '%d Zeile(n) gelesen · %d vorhandenen Spendern zugeordnet · %d neue Spender · %d übersprungen',
            count($zeilen),
            $verwenden,
            $neu,
            $skip,
        )));
    }

    /**
     * @param array<string, mixed> $state
     */
    private function zeilenTitel(array $state): string
    {
        $name = trim(($state['firma'] ?? '') ?: trim(($state['vorname'] ?? '') . ' ' . ($state['nachname'] ?? '')));
        $name = $name !== '' ? $name : 'ohne Name';

        $betrag = $state['betrag'] !== null && $state['betrag'] !== ''
            ? number_format((float) $state['betrag'], 2, ',', '.') . ' €'
            : '–';

        $aktion = ImportAktion::ausWert($state['aktion'] ?? null);

        $titel = sprintf(
            'Zeile %s · %s · %s · %s',
            $state['zeile_nr'] ?? '?',
            $name,
            $betrag,
            $aktion?->getLabel() ?? '',
        );

        // The item label stays visible while the row is collapsed, so warnings
        // belong here rather than only inside the expanded body.
        if (filled($state['hinweis'] ?? '')) {
            $titel .= ' · ⚠ ' . $state['hinweis'];
        }

        return $titel;
    }

    /**
     * Only the currently selected donor is preloaded; everything else comes
     * through the search callback, because there are far too many donors to
     * render as a full option list per repeater item.
     *
     * @return array<int, string>
     */
    private function spenderOptionen(mixed $spenderId): array
    {
        $spender = Spender::find($spenderId);

        return $spender ? [$spender->id => $spender->vollname] : [];
    }

    /**
     * @return array<int, string>
     */
    private function spenderSuche(string $search): array
    {
        return Spender::aktiv()
            ->where(function ($query) use ($search): void {
                foreach (['nachname', 'vorname', 'firma', 'ort', 'spendernummer'] as $spalte) {
                    $query->orWhere($spalte, 'like', "%{$search}%");
                }
            })
            ->orderBy('nachname')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Spender $s) => [
                $s->id => "{$s->vollname} – {$s->plz} {$s->ort} [{$s->spendernummer}]",
            ])
            ->all();
    }
}
