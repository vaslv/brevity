<?php

namespace App\Filament\Resources\Links\Pages;

use App\Filament\Resources\Links\LinkResource;
use App\Models\Link;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLink extends EditRecord
{
    protected static string $resource = LinkResource::class;

    public function getTitle(): string
    {
        return __('resources/link.pages.edit_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalHeading(fn (Link $record): string => __('resources/link.delete.modal_heading', ['code' => $record->code]))
                ->modalDescription(fn (Link $record): string => __('resources/link.delete.modal_description', ['code' => $record->code])),
            // No ForceDeleteAction: clicks.link_id is restrictOnDelete and
            // clicks/callbacks must outlive a link (see docs/ARCHITECTURE.md), so
            // force-deleting a link with clicks would raise a raw FK error.
            RestoreAction::make(),
        ];
    }
}
