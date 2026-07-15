<?php

namespace App\Filament\Resources\Links\Pages;

use App\Filament\Resources\Links\LinkResource;
use App\Models\Link;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLink extends CreateRecord
{
    protected static string $resource = LinkResource::class;

    public function getTitle(): string
    {
        return __('resources/link.pages.create_title');
    }

    protected function afterCreate(): void
    {
        // A link with no rules resolves to 404; rules are added on the edit
        // page via the relation manager, so nudge the admin.
        if ($this->record instanceof Link && $this->record->rules()->doesntExist()) {
            Notification::make()
                ->warning()
                ->title(__('resources/link.no_rules_warning_title'))
                ->body(__('resources/link.no_rules_warning_body'))
                ->persistent()
                ->send();
        }
    }
}
