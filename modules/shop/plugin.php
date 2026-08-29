<?php

/**
 * Plugin Manifest for shop
 */

return [
    'id' => 'shop',

    'name' => 'Shop',

    'version' => '1.0.0',

    'description' => '商品商城：上架/下架/库存管理、订单流水与图表统计，集成系统支付网关（Mock/Xcash/PayPal）',

    'author' => 'CMSForum',

    'requires' => [
        'mksine' => '^1.0',
    ],

    'namespace' => 'Modules\Shop',

    'plugin_class' => 'Modules\Shop\ShopPlugin',

    'autoload' => [
        'Modules\Shop\\' => 'src/',
    ],

    'hooks' => [
        'public' => [],
        'private' => [],
    ],
];
