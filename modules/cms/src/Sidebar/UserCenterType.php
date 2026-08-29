<?php

namespace Modules\CMS\Sidebar;

use App\Core\Sidebar\CardTypeContract;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CMS 特色类型：用户中心卡。
 */
class UserCenterType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'user_center';
    }

    public function label(): string
    {
        return '用户中心';
    }

    public function schema(): array
    {
        return []; // 无专属配置
    }

    public function componentKey(): string
    {
        return 'user_center';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}