<?php

namespace App\Core\Sidebar;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerArea(string $key, string $label, string $module = 'core')
 * @method static array areas()
 * @method static string areaLabel(string $key)
 * @method static void registerType(\App\Core\Sidebar\CardTypeContract $type)
 * @method static array types()
 * @method static \App\Core\Sidebar\CardTypeContract|null type(string $typeKey)
 * @method static array areaOptions()
 * @method static array typeOptionsFor(?object $user)
 * @method static array mergedTypeSchema()
 * @method static array cardsFor(string $areaKey)
 *
 * @see \App\Core\Sidebar\SidebarManager
 */
class Sidebar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SidebarManager::class;
    }
}
