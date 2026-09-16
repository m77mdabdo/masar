<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ads\Pages;

use App\Filament\Resources\Ads\AdCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdCampaigns extends ListRecords
{
    protected static string $resource = AdCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('حملة جديدة')];
    }
}
