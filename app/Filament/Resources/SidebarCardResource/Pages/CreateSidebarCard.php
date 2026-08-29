<?php

declare(strict_types=1);

namespace App\Filament\Resources\SidebarCardResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;
use App\Core\Sidebar\SidebarManager;
use App\Filament\Resources\SidebarCardResource;

class CreateSidebarCard extends CreateRecord
{
    protected static string $resource = SidebarCardResource::class;

    protected function afterCreate(): void
    {
        SidebarManager::flushCache();
    }
}
