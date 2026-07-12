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
                // Deletion threshold (docs/07-plans.md §4): a link with real
                // traffic gets an explicit warning so a busy link is not
                // disabled by a mis-click.
                ->modalDescription(function (Link $record): string {
                    $description = __('resources/link.delete.modal_description', ['code' => $record->code]);
                    $clicks = (int) $record->clickCounters()->sum('count');

                    if ($clicks >= (int) config('link.delete_confirm_threshold')) {
                        $description .= ' '.__('resources/link.delete.threshold_warning', ['count' => $clicks]);
                    }

                    return $description;
                }),
            // No ForceDeleteAction: clicks.link_id is restrictOnDelete and
            // clicks/callbacks must outlive a link (see docs/01-architecture.md), so
            // force-deleting a link with clicks would raise a raw FK error.
            RestoreAction::make(),
        ];
    }
}
