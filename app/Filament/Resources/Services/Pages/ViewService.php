<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    public function getTitle(): string
    {
        return __('resources/service.pages.view_title');
    }

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
                ->schema([
                    Select::make('expires_in_days')
                        ->label(__('resources/service.tokens.fields.expires_in'))
                        ->helperText(__('resources/service.tokens.fields.expires_in_help'))
                        ->placeholder(__('resources/service.tokens.expiry_options.none'))
                        ->options([
                            30 => __('resources/service.tokens.expiry_options.30'),
                            90 => __('resources/service.tokens.expiry_options.90'),
                            365 => __('resources/service.tokens.expiry_options.365'),
                        ])
                        ->native(false),
                ])
                ->action(function (array $data) {
                    /** @var Service $service */
                    $service = $this->getRecord();

                    $days = $data['expires_in_days'] ?? null;
                    $expiresAt = filled($days) ? now()->addDays((int) $days) : null;

                    // Least-privilege: exactly the abilities the /api/v1
                    // surface needs — create, read own links, update own links.
                    $plainTextToken = $service
                        ->createToken('service-token', ['links:create', 'links:read', 'links:update'], $expiresAt)
                        ->plainTextToken;

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
