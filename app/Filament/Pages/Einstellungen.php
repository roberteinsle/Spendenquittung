<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

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
        ];

        $formData = [];
        foreach ($keys as $key) {
            $formData[$key] = Setting::get($key, '');
        }

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        return $form
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
                            ->label('Unterschrift (PNG mit transparentem Hintergrund)')
                            ->image()
                            ->disk('public')
                            ->directory('unterschriften')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->helperText('PNG mit transparentem Hintergrund empfohlen'),
                    ]),

                Section::make('Briefkopf-Assets')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('logo_pfad')
                                    ->label('Logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('logos'),

                                FileUpload::make('herzfigur_pfad')
                                    ->label('Herzfigur')
                                    ->image()
                                    ->disk('public')
                                    ->directory('logos'),
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
