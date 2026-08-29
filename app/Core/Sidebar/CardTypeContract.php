<?php

namespace App\Core\Sidebar;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 侧边栏卡片类型契约。
 * 系统内置类型、模块特色类型、插件自定义类型都实现它并注册到 SidebarManager。
 */
interface CardTypeContract
{
    /** 类型唯一标识，如 'image_link' / 'latest_posts'。 */
    public function typeKey(): string;

    /** 后台展示名。 */
    public function label(): string;

    /**
     * 后台表单字段（Filament Schema 组件数组，data json 点语法）。
     * 系统按所选类型用 visible() 过滤后合并进"新建/编辑卡片"表单。
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    public function schema(): array;

    /** 前台渲染组件 key（resources/js/components/sidebar/cards/{key}.vue）。 */
    public function componentKey(): string;

    /** 谁可以创建该类型卡片（如 html 类型仅超级管理员）。 */
    public function authorize(Authenticatable $user): bool;
}
