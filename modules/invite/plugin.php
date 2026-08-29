<?php

/**
 * Plugin Manifest for invite
 */

return [
    'id' => 'invite',

    'name' => 'Invite',

    'version' => '1.0.0',

    'description' => '邀请码：管理员生成 / 金币购买 / 加密货币购买，注册核销与开关控制',

    'author' => 'CMSForum',

    'requires' => [
        'mksine' => '^1.0',
    ],

    'namespace' => 'Modules\Invite',

    'plugin_class' => 'Modules\Invite\InvitePlugin',

    'autoload' => [
        'Modules\Invite\\' => 'src/',
    ],

    'hooks' => [
        'public' => [],
        'private' => [],
    ],
];
