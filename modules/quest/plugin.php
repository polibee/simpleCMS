<?php

/**
 * Plugin Manifest for quest
 * 
 * This file defines the plugin metadata and configuration.
 * It is the source of truth for plugin information.
 */

return [
    // Unique plugin identifier (must match folder name)
    'id' => 'quest',
    
    // Human-readable name
    'name' => 'Quest',
    
    // Plugin version (SemVer)
    'version' => '1.0.0',
    
    // Plugin description
    'description' => '签到与内容发布任务（货币奖励规则）',
    
    // Plugin author
    'author' => 'CMSForum',
    
    // Dependencies (other plugins or mksine version)
    'requires' => [
        'mksine' => '^1.0',
    ],
    
    // PHP Namespace
    'namespace' => 'Modules\Quest',
    
    // Main plugin class
    'plugin_class' => 'Modules\Quest\QuestPlugin',
    
    // PSR-4 autoload mapping
    'autoload' => [
        'Modules\Quest\\' => 'src/',
    ],
    
    // Hooks exposed by this plugin
    'hooks' => [
        // Public hooks (other plugins can listen)
        'public' => [
            // 'quest.example.created',
        ],
        
        // Private hooks (only this plugin can listen)
        'private' => [
            // 'quest.internal.process',
        ],
    ],
    
    // Public API (optional)
    // 'public_api' => [
    //     'facade' => 'Modules\Quest\Facades\Quest',
    // ],
];