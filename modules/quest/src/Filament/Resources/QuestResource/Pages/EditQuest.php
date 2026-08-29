<?php

declare(strict_types=1);

namespace Modules\Quest\Filament\Resources\QuestResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Quest\Filament\Resources\QuestResource;

class EditQuest extends EditRecord
{
    protected static string $resource = QuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
