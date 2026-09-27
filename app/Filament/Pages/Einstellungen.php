<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use App\Mail\TestMail;
use App\Services\MailKonfigurationService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Einstellungen extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.einstellungen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static \UnitEnum|string|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    /**
     * Gilt für die Navigation und für den direkten Aufruf der Route.
     */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->istAdmin();
    }

    public function mount(): void
    {
        $keys = [
            'stiftung_name', 'stiftung_strasse', 'stiftung_plz_ort',
            'stiftung_telefon', 'stiftung_mobil', 'stiftung_email', 'stiftung_web',
            'spendenkonto_kontoempfaenger', 'spendenkonto_iban', 'spendenkonto_bic', 'spendenkonto_bank',
            'rechtliche_form',
            'stiftung_finanzamt', 'stiftung_steuernummer', 'stiftung_freistellung_datum', 'stiftung_veranlagungszeitraum',
            'unterzeichner_name', 'unterzeichner_titel', 'ausstellungsort',
            'unterschrift_pfad', 'logo_pfad', 'herzfigur_pfad',
            'mail_betreff', 'mail_text', 'mail_text_du',
            'mail_host', 'mail_port', 'mail_benutzername', 'mail_verschluesselung',
            'mail_absender_name', 'mail_absender_email',
        ];

        $formData = [];
        foreach ($keys as $key) {
            $formData[$key] = Setting::get($key, '');
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Stiftungsdaten')
                    ->schema([
                        TextInput::make('stiftung_name')
                            ->label('Stiftungsname')
                            ->required(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('stiftung_strasse')
                                    ->label('Straße'),
                                TextInput::make('stiftung_plz_ort')
                                    ->label('PLZ Ort'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('stiftung_telefon')
                                    ->label('Telefon'),
                                TextInput::make('stiftung_mobil')
                                    ->label('Mobil'),
                                TextInput::make('stiftung_email')
                                    ->label('E-Mail')
                                    ->email(),
                            ]),

                        TextInput::make('stiftung_web')
                            ->label('Website'),
                    ]),

                Section::make('Spendenkonto')
                    ->schema([
                        TextInput::make('spendenkonto_kontoempfaenger')
                            ->label('Kontoempfänger'),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('spendenkonto_iban')
                                    ->label('IBAN'),
                                TextInput::make('spendenkonto_bic')
                                    ->label('BIC'),
                                TextInput::make('spendenkonto_bank')
                                    ->label('Bank'),
                            ]),
                    ]),

                Section::make('Rechtliche Form der Stiftung')
                    ->description('Bestimmt den Bescheinigungstext auf dem PDF. Bitte mit Steuerberater klären.')
                    ->schema([
                        Select::make('rechtliche_form')
                            ->label('Rechtsform')
                            ->options([
                                'placeholder'         => '⚠️ Noch nicht geklärt (Platzhalter-Text)',
                                'oeffentlich_rechtlich' => 'Körperschaft des öffentlichen Rechts',
                                'privatrechtlich'     => 'Gemeinnützige Stiftung privaten Rechts',
                            ])
                            ->native(false)
                            ->required(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('stiftung_finanzamt')
                                    ->label('Finanzamt (nur privat)')
                                    ->helperText('Nur für privatrechtliche Stiftungen'),
                                TextInput::make('stiftung_steuernummer')
                                    ->label('Steuernummer (nur privat)'),
                                TextInput::make('stiftung_freistellung_datum')
                                    ->label('Freistellung: Datum des Bescheids')
                                    ->placeholder('z. B. 15.03.2023'),
                                TextInput::make('stiftung_veranlagungszeitraum')
                                    ->label('Veranlagungszeitraum')
                                    ->placeholder('z. B. 2021-2023'),
                            ]),
                    ]),

                Section::make('Unterschrift & Unterzeichner')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('unterzeichner_name')
                                    ->label('Name des Unterzeichners'),
                                TextInput::make('unterzeichner_titel')
                                    ->label('Titel/Funktion'),
                                TextInput::make('ausstellungsort')
                                    ->label('Ausstellungsort'),
                            ]),

                        FileUpload::make('unterschrift_pfad')
                            ->label('Unterschrift')
                            ->image()
                            ->disk('public')
                            ->directory('unterschriften')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->helperText('PNG mit transparentem Hintergrund, eng um die Unterschrift beschnitten. Im PDF erscheint sie in maximal 60 × 15 mm – für einen sauberen Druck also mindestens 709 × 177 Pixel, besser 1400 × 350.'),
                    ]),

                Section::make('SMTP-Server')
                    ->description('Zugang zum Postausgangsserver. Bleibt das Feld Server leer, gilt weiterhin, was in der Umgebung konfiguriert ist.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('mail_absender_name')
                                    ->label('Absendername')
                                    ->placeholder(fn () => Setting::get('stiftung_name', 'Name der Organisation'))
                                    ->helperText('Leer lassen: es gilt der Stiftungsname.'),

                                TextInput::make('mail_absender_email')
                                    ->label('Absenderadresse')
                                    ->email()
                                    ->placeholder(fn () => Setting::get('stiftung_email', 'kontakt@beispiel.de'))
                                    ->helperText('Viele Anbieter verlangen, dass sie zum SMTP-Konto passt. Leer lassen: es gilt die E-Mail aus den Stiftungsdaten.'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('mail_host')
                                    ->label('Server')
                                    ->placeholder('smtp.beispiel.de')
                                    ->columnSpan(2),

                                TextInput::make('mail_port')
                                    ->label('Port')
                                    ->numeric()
                                    ->placeholder('587'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('mail_benutzername')
                                    ->label('Benutzername')
                                    ->autocomplete('off'),

                                TextInput::make('mail_passwort')
                                    ->label('Passwort')
                                    ->password()
                                    ->revealable()
                                    ->autocomplete('new-password')
                                    // Wird verschlüsselt abgelegt und nie ins
                                    // Formular zurückgeschrieben; leer heisst
                                    // "bestehendes Passwort behalten".
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->helperText('Leer lassen, um das gespeicherte Passwort zu behalten.'),

                                Select::make('mail_verschluesselung')
                                    ->label('Verschlüsselung')
                                    ->options([
                                        'tls'  => 'STARTTLS (Port 587)',
                                        'ssl'  => 'SSL/TLS (Port 465)',
                                        'keine' => 'Keine',
                                    ])
                                    ->default('tls')
                                    ->native(false),
                            ]),

                        Actions::make([
                            Action::make('testmail')
                                ->label('Testmail senden')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('secondary')
                                ->schema([
                                    TextInput::make('empfaenger')
                                        ->label('An welche Adresse?')
                                        ->email()
                                        ->required()
                                        ->default(fn () => auth()->user()?->email),
                                ])
                                ->modalHeading('Testmail senden')
                                ->modalDescription('Verwendet die Angaben, die gerade im Formular stehen – auch ungespeicherte.')
                                ->modalSubmitActionLabel('Senden')
                                ->action(fn (array $data) => $this->sendeTestmail($data['empfaenger'])),
                        ]),
                    ]),

                Section::make('E-Mail-Versand')
                    ->description('Platzhalter: :nummer, :betrag, :datum, :jahr, :zweck. Anrede und Grußformel werden automatisch ergänzt.')
                    ->schema([
                        TextInput::make('mail_betreff')
                            ->label('Betreff')
                            ->placeholder('Ihre Zuwendungsbestätigung Nr. :nummer'),

                        Textarea::make('mail_text')
                            ->label('Text (Sie-Form)')
                            ->rows(6)
                            ->helperText('Wird für alle Spender verwendet, die nicht geduzt werden.'),

                        Textarea::make('mail_text_du')
                            ->label('Text (Du-Form)')
                            ->rows(6)
                            ->helperText('Wird für Spender mit gesetztem Haken "Duzen" verwendet.'),
                    ]),

                Section::make('Briefkopf-Assets')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('logo_pfad')
                                    ->label('Logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('logos')
                                    ->helperText('Maximal 40 × 20 mm im PDF, also mindestens 472 × 236 Pixel, besser 945 × 472. PNG mit transparentem Hintergrund, da der Briefkopf farbig hinterlegt ist.'),

                                FileUpload::make('herzfigur_pfad')
                                    ->label('Herzfigur')
                                    ->image()
                                    ->disk('public')
                                    ->directory('logos')
                                    ->helperText('Maximal 25 × 18 mm im PDF, also mindestens 295 × 213 Pixel, besser 591 × 425. Ebenfalls PNG mit Transparenz.'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        Notification::make()
            ->title('Einstellungen gespeichert')
            ->success()
            ->send();
    }

    /**
     * Sendet synchron, nicht über die Queue: der Sinn des Knopfes ist die
     * sofortige Rückmeldung, ob der Zugang stimmt.
     */
    public function sendeTestmail(string $empfaenger): void
    {
        // Absichtlich der Rohzustand statt form->getState(): letzteres validiert
        // das komplette Formular, eine Testmail würde dann an einem leeren
        // Pflichtfeld in einem ganz anderen Abschnitt scheitern.
        $daten = $this->data;

        app(MailKonfigurationService::class)->anwenden([
            'host'             => $daten['mail_host'] ?? '',
            'port'             => $daten['mail_port'] ?? '',
            'benutzername'     => $daten['mail_benutzername'] ?? '',
            // Leeres Feld heisst "gespeichertes Passwort verwenden".
            'passwort'         => filled($daten['mail_passwort'] ?? null)
                ? $daten['mail_passwort']
                : Setting::get('mail_passwort', ''),
            'verschluesselung' => $daten['mail_verschluesselung'] ?? 'tls',
            'absender_email' => $daten['mail_absender_email'] ?? '',
            'absender_name' => $daten['mail_absender_name'] ?? '',
        ]);

        try {
            // Absender aus dem Formular, damit auch der ungespeicherte Stand
            // geprüft werden kann.
            $absender = app(MailKonfigurationService::class)->absender([
                'absender_email' => $daten['mail_absender_email'] ?? '',
                'absender_name' => $daten['mail_absender_name'] ?? '',
            ]);

            Mail::to($empfaenger)->send(new TestMail($absender));

            Notification::make()
                ->title('Testmail verschickt')
                ->body("Sie ging an {$empfaenger}. Kommt sie nicht an, lohnt auch ein Blick in den Spam-Ordner.")
                ->success()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Testmail fehlgeschlagen')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Speichern')
                ->action('save')
                ->color('primary'),
        ];
    }
}
