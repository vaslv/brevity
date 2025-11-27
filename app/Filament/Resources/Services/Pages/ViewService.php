<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('createToken')
                ->label(__('Create API Token'))
                ->color('success')
                ->icon('heroicon-o-key')
                ->modalHeading(__('Create a new API token?'))
                ->modalDescription(__('The token will be shown once. Store it securely.'))
                ->modalSubmitActionLabel(__('Create'))
                ->action(function () {
                    /** @var Service $service */
                    $service = $this->getRecord();
                    $plainTextToken = $service->createToken('service-token')->plainTextToken;

                    $markdown = implode("\n\n", [
                        __('**API token created**'),
                        __('Token: :token', ['token' => $plainTextToken]),
                        __('Hint: copy and store the token — it will not be shown again.'),
                    ]);

                    Notification::make()
                        ->body(Str::markdown($markdown))
                        ->success()
                        ->duration(0)
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
