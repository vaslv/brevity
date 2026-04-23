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
                ->label(__('resources/service.tokens.actions.create'))
                ->color('success')
                ->icon('heroicon-o-key')
                ->modalHeading(__('resources/service.tokens.actions.modal_heading'))
                ->modalDescription(__('resources/service.tokens.actions.modal_description'))
                ->modalSubmitActionLabel(__('resources/service.tokens.actions.modal_submit'))
                ->action(function () {
                    /** @var Service $service */
                    $service = $this->getRecord();
                    $plainTextToken = $service->createToken('service-token')->plainTextToken;

                    $markdown = implode("\n\n", [
                        '**'.__('resources/service.tokens.notifications.created_title').'**',
                        __('resources/service.tokens.notifications.token_line', ['token' => $plainTextToken]),
                        __('resources/service.tokens.notifications.hint'),
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
