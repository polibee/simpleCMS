<?php

declare(strict_types=1);

namespace Modules\User\Filament\Resources\UserProfileResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\User\Filament\Resources\UserProfileResource\UserProfileResource;

class CreateUserProfile extends CreateRecord
{
    protected static string $resource = UserProfileResource::class;
}