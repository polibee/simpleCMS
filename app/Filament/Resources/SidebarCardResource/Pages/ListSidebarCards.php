<?php

declare(strict_types=1);

namespace App\Filament\Resources\SidebarCardResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\SidebarCardResource;
use App\Core\Sidebar\SidebarManager;

class ListSidebarCards extends ListRecords
{
    protected static string $resource = SidebarCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
