<?php

namespace App\Filament\Resources\UserAgents\Pages;

use App\Filament\Resources\UserAgents\UserAgentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewUserAgent extends ViewRecord
{
    protected static string $resource = UserAgentResource::class;

    public function getTitle(): string
    {
        return __('resources/user_agent.pages.view_title');
    }
}
