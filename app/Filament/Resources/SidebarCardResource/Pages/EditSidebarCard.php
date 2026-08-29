<?php

declare(strict_types=1);

namespace App\Filament\Resources\SidebarCardResource\Pages;

use Filament\Resources\Pages\EditRecord;
use App\Core\Sidebar\SidebarManager;
use App\Filament\Resources\SidebarCardResource;

class EditSidebarCard extends EditRecord
{
    protected static string $resource = SidebarCardResource::class;

    protected function afterSave(): void
    {
        SidebarManager::flushCache();
    }
}
