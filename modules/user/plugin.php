<?php

/**
 * Plugin Manifest for user
 * 
 * This file defines the plugin metadata and configuration.
 * It is the source of truth for plugin information.
 */

return [
    // Unique plugin identifier (must match folder name)
    'id' => 'user',
    
    // Human-readable name
    'name' => 'User',
    
    // Plugin version (SemVer)
    'version' => '1.0.0',
    
    // Plugin description
    'description' => '用户中心与注册流程模块',
    
    // Plugin author
    'author' => 'CMSForum',
    
    // Dependencies (other plugins or mksine version)
    'requires' => [
        'mksine' => '^1.0',
    ],
    
    // PHP Namespace
    'namespace' => 'Modules\User',
    
    // Main plugin class
    'plugin_class' => 'Modules\User\UserPlugin',
    
    // PSR-4 autoload mapping
    'autoload' => [
        'Modules\User\\' => 'src/',
    ],
    
    // Hooks exposed by this plugin
    'hooks' => [
        // Public hooks (other plugins can listen)
        'public' => [
            // 'user.example.created',
        ],
        
        // Private hooks (only this plugin can listen)
        'private' => [
            // 'user.internal.process',
        ],
    ],
    
    // Public API (optional)
    // 'public_api' => [
    //     'facade' => 'Modules\User\Facades\User',
    // ],
];