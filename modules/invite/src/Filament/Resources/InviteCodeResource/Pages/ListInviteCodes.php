<?php

declare(strict_types=1);

namespace Modules\Invite\Filament\Resources\InviteCodeResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Invite\Filament\Resources\InviteCodeResource;

class ListInviteCodes extends ListRecords
{
    protected static string $resource = InviteCodeResource::class;
}
