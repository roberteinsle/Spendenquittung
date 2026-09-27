<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ProtokollAktion: string implements HasColor, HasIcon, HasLabel
{
    case Angelegt = 'angelegt';
    case Bearbeitet = 'bearbeitet';
    case Geloescht = 'geloescht';
    case Wiederhergestellt = 'wiederhergestellt';
    case PdfErzeugt = 'pdf_erzeugt';
    case PdfGeoeffnet = 'pdf_geoeffnet';
    case EmailVersendet = 'email_versendet';
    case EmailFehlgeschlagen = 'email_fehlgeschlagen';
    case PostVersendet = 'post_versendet';
    case Importiert = 'importiert';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Angelegt => 'Angelegt',
            self::Bearbeitet => 'Bearbeitet',
            self::Geloescht => 'Gelöscht',
            self::Wiederhergestellt => 'Wiederhergestellt',
            self::PdfErzeugt => 'PDF erzeugt',
            self::PdfGeoeffnet => 'PDF geöffnet',
            self::EmailVersendet => 'E-Mail versendet',
            self::EmailFehlgeschlagen => 'E-Mail fehlgeschlagen',
            self::PostVersendet => 'Per Post versendet',
            self::Importiert => 'Importiert',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Angelegt, self::Importiert => 'success',
            self::Bearbeitet => 'warning',
            self::Geloescht, self::EmailFehlgeschlagen => 'danger',
            self::Wiederhergestellt => 'info',
            default => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Angelegt => 'heroicon-o-plus-circle',
            self::Bearbeitet => 'heroicon-o-pencil-square',
            self::Geloescht => 'heroicon-o-trash',
            self::Wiederhergestellt => 'heroicon-o-arrow-uturn-left',
            self::PdfErzeugt => 'heroicon-o-document-plus',
            self::PdfGeoeffnet => 'heroicon-o-printer',
            self::EmailVersendet => 'heroicon-o-envelope',
            self::EmailFehlgeschlagen => 'heroicon-o-exclamation-triangle',
            self::PostVersendet => 'heroicon-o-inbox-arrow-down',
            self::Importiert => 'heroicon-o-arrow-up-tray',
        };
    }
}
