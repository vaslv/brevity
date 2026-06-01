<?php

namespace App\Filament\Resources\Conditions\Pages;

use App\Filament\Resources\Conditions\ConditionResource;
use App\Models\Condition;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCondition extends CreateRecord
{
    protected static string $resource = ConditionResource::class;

    public function getTitle(): string
    {
        return __('resources/condition.pages.create_title');
    }

    /**
     * Conditions are deduplicated by (type, data). Reuse an existing row
     * instead of letting the unique index raise a raw 500.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $encoded = json_encode($data['data'] ?? []);

        Condition::query()->insertOrIgnore([
            'type' => $data['type'],
            'data' => $encoded,
            'created_at' => now(),
        ]);

        return Condition::query()
            ->where('type', $data['type'])
            ->whereRaw('"data"::jsonb = ?::jsonb', [$encoded])
            ->firstOrFail();
    }
}
