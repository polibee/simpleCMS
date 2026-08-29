<?php

declare(strict_types=1);

namespace Modules\Quest\Filament\Resources\QuestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Quest\Filament\Resources\QuestResource;

class ListQuests extends ListRecords
{
    protected static string $resource = QuestResource::class;

    protected function getHeaderActions(): array
    {
        // List 页无 record，不能放 EditAction（会触发 getEditAuthorizationResponse(null) 500）
        return [];
    }
}
