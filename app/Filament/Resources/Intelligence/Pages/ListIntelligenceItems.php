<?php

declare(strict_types=1);

namespace App\Filament\Resources\Intelligence\Pages;

use App\Filament\Resources\Intelligence\IntelligenceItemResource;
use Filament\Resources\Pages\ListRecords;

class ListIntelligenceItems extends ListRecords
{
    protected static string $resource = IntelligenceItemResource::class;

    public function getTitle(): string
    {
        return 'صندوق الرصد';
    }
}
