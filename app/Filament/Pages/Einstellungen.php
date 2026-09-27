<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
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
