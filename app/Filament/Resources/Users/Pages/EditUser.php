<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn () => $this->getRecord()->is(auth()->user())),
        ];
    }

    /**
     * The PIN field stays empty on edit; an empty value means "keep the current
     * PIN". Clearing a PIN happens by saving the field with a new one or by
     * deleting the user.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['login_pin'] = null;

        return $data;
    }
}
