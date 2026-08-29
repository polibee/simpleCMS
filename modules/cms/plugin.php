<?php

/**
 * Plugin Manifest for cms
 * 
 * This file defines the plugin metadata and configuration.
 * It is the source of truth for plugin information.
 */

return [
    // Unique plugin identifier (must match folder name)
    'id' => 'cms',
    
    // Human-readable name
    'name' => 'Cms',
    
    // Plugin version (SemVer)
    'version' => '1.0.0',
    
    // Plugin description
    'description' => '内容管理：文章/分类/标签/多形态展示',
    
    // Plugin author
    'author' => 'CMSForum',
    
    // Dependencies (other plugins or mksine version)
    'requires' => [
        'mksine' => '^1.0',
    ],
    
    // PHP Namespace
    'namespace' => 'Modules\CMS',
    
    // Main plugin class
    'plugin_class' => 'Modules\CMS\CmsPlugin',
    
    // PSR-4 autoload mapping
    'autoload' => [
        'Modules\CMS\\' => 'src/',
    ],
    
    // Hooks exposed by this plugin
    'hooks' => [
        // Public hooks (other plugins can listen)
        'public' => [
            // 'cms.example.created',
        ],
        
        // Private hooks (only this plugin can listen)
        'private' => [
            // 'cms.internal.process',
        ],
    ],
    
    // Public API (optional)
    // 'public_api' => [
    //     'facade' => 'Modules\CMS\Facades\Cms',
    // ],
];