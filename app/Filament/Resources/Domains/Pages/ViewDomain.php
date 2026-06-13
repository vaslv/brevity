<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Models\Domain;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDomain extends ViewRecord
{
    protected static string $resource = DomainResource::class;

    public function getTitle(): string
    {
        return __('resources/domain.pages.view_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setAsDefault')
                ->label(__('resources/domain.actions.set_as_default.label'))
                ->icon(Heroicon::OutlinedStar)
                ->color('success')
                // Hidden once this domain is already the default — promoting it
                // again would be a no-op.
                ->visible(fn (Domain $record): bool => ! $record->is_default)
                ->requiresConfirmation()
                ->modalHeading(__('resources/domain.actions.set_as_default.modal_heading'))
                ->modalDescription(__('resources/domain.actions.set_as_default.modal_description'))
                ->modalSubmitActionLabel(__('resources/domain.actions.set_as_default.modal_submit'))
                ->action(function (Domain $record): void {
                    // The model's saving hook demotes the previous default, so
                    // the single-default invariant is preserved automatically.
                    $record->update(['is_default' => true]);

                    Notification::make()
                        ->title(__('resources/domain.actions.set_as_default.notification', [
                            'domain' => $record->value,
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
