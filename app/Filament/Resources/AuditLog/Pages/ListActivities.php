<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLog\Pages;

use App\Filament\Resources\AuditLog\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
