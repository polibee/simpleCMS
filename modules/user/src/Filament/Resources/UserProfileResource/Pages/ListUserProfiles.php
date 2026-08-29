<?php

declare(strict_types=1);

namespace Modules\User\Filament\Resources\UserProfileResource\Pages;

use Miran\Mksine\Filament\Resources\Pages\MksineListRecords;
use Modules\User\Filament\Resources\UserProfileResource\UserProfileResource;

class ListUserProfiles extends MksineListRecords
{
    protected static string $resource = UserProfileResource::class;
}