<?php

namespace App\Filament\Resources\Links\Pages;

use App\Filament\Resources\Links\LinkResource;
use App\Models\Link;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
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
            ForceDeleteAction::make()
                ->modalHeading(fn (Link $record): string => __('resources/link.force_delete.modal_heading', ['code' => $record->code]))
                ->modalDescription(fn (Link $record): string => __('resources/link.force_delete.modal_description', ['code' => $record->code])),
            RestoreAction::make(),
        ];
    }
}
