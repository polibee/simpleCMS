<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\CmsBannerResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\CMS\Filament\Resources\CmsBannerResource;

class CreateCmsBanner extends CreateRecord
{
    protected static string $resource = CmsBannerResource::class;
}
