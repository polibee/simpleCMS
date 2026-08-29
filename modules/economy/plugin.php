<?php

/**
 * Plugin Manifest for economy
 * 
 * This file defines the plugin metadata and configuration.
 * It is the source of truth for plugin information.
 */

return [
    // Unique plugin identifier (must match folder name)
    'id' => 'economy',
    
    // Human-readable name
    'name' => 'Economy',
    
    // Plugin version (SemVer)
    'version' => '1.0.0',
    
    // Plugin description
    'description' => '货币内核（金币/银币/铜币，可自定义名称）',
    
    // Plugin author
    'author' => 'CMSForum',
    
    // Dependencies (other plugins or mksine version)
    'requires' => [
        'mksine' => '^1.0',
    ],
    
    // PHP Namespace
    'namespace' => 'Modules\Economy',
    
    // Main plugin class
    'plugin_class' => 'Modules\Economy\EconomyPlugin',
    
    // PSR-4 autoload mapping
    'autoload' => [
        'Modules\Economy\\' => 'src/',
    ],
    
    // Hooks exposed by this plugin
    'hooks' => [
        // Public hooks (other plugins can listen)
        'public' => [
            // 'economy.example.created',
        ],
        
        // Private hooks (only this plugin can listen)
        'private' => [
            // 'economy.internal.process',
        ],
    ],
    
    // Public API (optional)
    // 'public_api' => [
    //     'facade' => 'Modules\Economy\Facades\Economy',
    // ],
];