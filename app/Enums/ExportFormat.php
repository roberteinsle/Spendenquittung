<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ExportFormat: string implements HasIcon, HasLabel
{
    case Excel = 'xlsx';
    case Markdown = 'md';
    case Xml = 'xml';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Excel => 'Excel (.xlsx)',
            self::Markdown => 'Markdown (.md)',
            self::Xml => 'XML (.xml)',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Excel => 'heroicon-o-table-cells',
            self::Markdown => 'heroicon-o-document-text',
            self::Xml => 'heroicon-o-code-bracket',
        };
    }

    public function dateiendung(): string
    {
        return $this->value;
    }

    public function mimeTyp(): string
    {
        return match ($this) {
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Markdown => 'text/markdown; charset=UTF-8',
            self::Xml => 'application/xml; charset=UTF-8',
        };
    }
}
