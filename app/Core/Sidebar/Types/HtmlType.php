<?php

namespace App\Core\Sidebar\Types;

use App\Core\Sidebar\CardTypeContract;
use Filament\Forms\Components\Textarea;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 内置类型：广告/代码卡片（HTML、JS）。
 * 安全红线：仅超级管理员可创建——内容原样输出，属高敏权限。
 */
class HtmlType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'html';
    }

    public function label(): string
    {
        return '广告代码（HTML/JS）';
    }

    public function schema(): array
    {
        return [
            Textarea::make('data.html')
                ->label('HTML / JS 代码')
                ->rows(8)
                ->helperText('⚠️ 仅超级管理员可配置；内容将原样输出，请确保代码可信'),
        ];
    }

    public function componentKey(): string
    {
        return 'html';
    }

    public function authorize(Authenticatable $user): bool
    {
        // spatie/laravel-permission（Filament Shield）角色判断
        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }
}
