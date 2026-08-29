<?php

namespace Modules\CMS\Sidebar;

use App\Core\Sidebar\CardTypeContract;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CMS 特色类型：作者中心卡。
 */
class AuthorCenterType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'author_center';
    }

    public function label(): string
    {
        return '作者中心';
    }

    public function schema(): array
    {
        return []; // 无专属配置
    }

    public function componentKey(): string
    {
        return 'author_center';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}