<?php

namespace App\Core\Sidebar;

use Illuminate\Support\Facades\Cache;

/**
 * 系统级侧边栏引擎。
 *
 * - 区域注册：模块在 activate() 里声明自己的侧边栏区域（cms.sidebar / forum.sidebar / ...）
 * - 类型注册：内置类型 + 模块特色类型 + 插件自定义类型
 * - 实例存储：sidebar_cards 表（area_key + type + data json），后台统一管理
 *
 * 前台渲染由通用 SidebarRenderer 按卡片 type 分发到对应 Vue 组件。
 */
final class SidebarManager
{
    /** @var array<string, array{key: string, label: string, module: string}> */
    private array $areas = [];

    /** @var array<string, CardTypeContract> */
    private array $types = [];

    /** 卡片实例表。 */
    public const TABLE = 'sidebar_cards';

    public function registerArea(string $key, string $label, string $module = 'core'): void
    {
        $this->areas[$key] = ['key' => $key, 'label' => $label, 'module' => $module];
    }

    /**
     * @return array<string, array{key: string, label: string, module: string}>
     */
    public function areas(): array
    {
        return $this->areas;
    }

    public function areaLabel(string $key): string
    {
        return $this->areas[$key]['label'] ?? $key;
    }

    public function registerType(CardTypeContract $type): void
    {
        $this->types[$type->typeKey()] = $type;
    }

    /** @return array<string, CardTypeContract> */
    public function types(): array
    {
        return $this->types;
    }

    public function type(string $typeKey): ?CardTypeContract
    {
        return $this->types[$typeKey] ?? null;
    }

    /** 区域下拉选项（后台表单用）。 */
    public function areaOptions(): array
    {
        return collect($this->areas)->mapWithKeys(fn ($a) => [$a['key'] => "{$a['label']}（{$a['key']}）"])->all();
    }

    /** 类型下拉选项（过滤当前用户无权限创建的类型）。 */
    public function typeOptionsFor(?object $user): array
    {
        return collect($this->types)
            ->filter(fn (CardTypeContract $t) => $user === null || $t->authorize($user))
            ->mapWithKeys(fn (CardTypeContract $t) => [$t->typeKey() => $t->label()])
            ->all();
    }

    /**
     * 合并全部类型字段为动态表单（按所选类型 visible 过滤）。
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    public function mergedTypeSchema(): array
    {
        $fields = [];

        foreach ($this->types as $type) {
            foreach ($type->schema() as $field) {
                $field->visible(fn ($get) => $get('type') === $type->typeKey());
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * 某区域的启用卡片（按 sort 排序），带缓存。
     * 返回原始行数组（id/type/title/data/sort），前台组件映射交给前端。
     */
    public function cardsFor(string $areaKey): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable(self::TABLE)) {
            return [];
        }

        return Cache::remember(
            "sidebar.cards.{$areaKey}",
            3600,
            fn () => \Illuminate\Support\Facades\DB::table(self::TABLE)
                ->where('area_key', $areaKey)
                ->where('enabled', true)
                ->orderBy('sort')
                ->get(['id', 'type', 'title', 'data'])
                ->map(function ($row) {
                    $data = json_decode((string) $row->data, true);

                    return [
                        'id' => (int) $row->id,
                        'type' => $row->type,
                        'data' => array_merge(
                            ['cardTitle' => $row->title ?: ($this->type($row->type)?->label() ?? $row->type)],
                            is_array($data) ? $data : [],
                        ),
                    ];
                })
                ->all(),
        );
    }

    /** 清空区域缓存（后台保存卡片后调用）。 */
    public static function flushCache(): void
    {
        Cache::flush();
    }
}
