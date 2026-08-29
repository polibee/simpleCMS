<?php

/**
 * Plugin Manifest for performance
 */

return [
    'id' => 'performance',

    'name' => 'Performance',

    'version' => '1.0.0',

    'description' => '性能优化中心：WP Super Cache 式页面缓存、Redis/OPcache/Octane/Horizon 集成管理与图表',

    'author' => 'CMSForum',

    'requires' => [
        'mksine' => '^1.0',
    ],

    'namespace' => 'Modules\Performance',

    'plugin_class' => 'Modules\Performance\PerformancePlugin',

    'autoload' => [
        'Modules\Performance\\' => 'src/',
    ],

    'hooks' => [
        'public' => [],
        'private' => [],
    ],
];
