<?php

namespace Modules\CMS\Sidebar;

use App\Core\Sidebar\CardTypeContract;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CMS 特色类型：每日签到卡。
 */
class CheckinType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'checkin';
    }

    public function label(): string
    {
        return '每日签到';
    }

    public function schema(): array
    {
        return []; // 无专属配置
    }

    public function componentKey(): string
    {
        return 'checkin';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}