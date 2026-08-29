<?php

/**
 * Plugin Manifest for ads
 *
 * This file defines the plugin metadata and configuration.
 * It is the source of truth for plugin information.
 */

return [
    // Unique plugin identifier (must match folder name)
    'id' => 'ads',

    // Human-readable name
    'name' => 'Ads',

    // Plugin version (SemVer)
    'version' => '1.0.0',

    // Plugin description
    'description' => '广告管理：文本/图片/HTML(JS) 广告位，覆盖首页、文章、评论、侧边栏',

    // Plugin author
    'author' => 'CMSForum',

    // Dependencies (other plugins or mksine version)
    'requires' => [
        'mksine' => '^1.0',
    ],

    // PHP Namespace
    'namespace' => 'Modules\Ads',

    // Main plugin class
    'plugin_class' => 'Modules\Ads\AdsPlugin',

    // PSR-4 autoload mapping
    'autoload' => [
        'Modules\Ads\\' => 'src/',
    ],

    // Hooks exposed by this plugin
    'hooks' => [
        'public' => [],
        'private' => [],
    ],
];
