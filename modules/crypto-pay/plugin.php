<?php

/**
 * Plugin Manifest for crypto-pay
 * 
 * This file defines the plugin metadata and configuration.
 * It is the source of truth for plugin information.
 */

return [
    // Unique plugin identifier (must match folder name)
    'id' => 'crypto-pay',
    
    // Human-readable name
    'name' => 'Crypto Pay',
    
    // Plugin version (SemVer)
    'version' => '1.0.0',
    
    // Plugin description
    'description' => 'CoinPayments 加密货币支付：付费订阅文章',
    
    // Plugin author
    'author' => 'CMSForum',
    
    // Dependencies (other plugins or mksine version)
    'requires' => [
        'mksine' => '^1.0',
    ],
    
    // PHP Namespace
    'namespace' => 'Modules\CryptoPay',
    
    // Main plugin class
    'plugin_class' => 'Modules\CryptoPay\CryptoPayPlugin',
    
    // PSR-4 autoload mapping
    'autoload' => [
        'Modules\CryptoPay\\' => 'src/',
    ],
    
    // Hooks exposed by this plugin
    'hooks' => [
        // Public hooks (other plugins can listen)
        'public' => [
            // 'crypto-pay.example.created',
        ],
        
        // Private hooks (only this plugin can listen)
        'private' => [
            // 'crypto-pay.internal.process',
        ],
    ],
    
    // Public API (optional)
    // 'public_api' => [
    //     'facade' => 'Modules\CryptoPay\Facades\CryptoPay',
    // ],
];