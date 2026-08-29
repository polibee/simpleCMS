<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\CmsBannerResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\CMS\Filament\Resources\CmsBannerResource;

class EditCmsBanner extends EditRecord
{
    protected static string $resource = CmsBannerResource::class;
}
