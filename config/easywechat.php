<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EasyWeChat Configuration
    |--------------------------------------------------------------------------
    |
    | EasyWeChat 配置文件
    |
    */

    'mini_app' => [
        'app_id' => env('WECHAT_MINI_APP_ID'),
        'secret' => env('WECHAT_MINI_APP_SECRET'),
        
        // 可选配置
        'token' => env('WECHAT_MINI_APP_TOKEN'),
        'aes_key' => env('WECHAT_MINI_APP_AES_KEY'),
        
        // 日志配置
        'log' => [
            'level' => env('WECHAT_LOG_LEVEL', 'debug'),
            'file' => storage_path('logs/wechat.log'),
        ],
        
        // HTTP 配置
        'http' => [
            'timeout' => 5.0,
            'retry' => 1,
        ],
    ],

    'official_account' => [
        'app_id' => env('WECHAT_OFFICIAL_ACCOUNT_APP_ID'),
        'secret' => env('WECHAT_OFFICIAL_ACCOUNT_SECRET'),
        'token' => env('WECHAT_OFFICIAL_ACCOUNT_TOKEN'),
        'aes_key' => env('WECHAT_OFFICIAL_ACCOUNT_AES_KEY'),
    ],

    'open_platform' => [
        'app_id' => env('WECHAT_OPEN_PLATFORM_APP_ID'),
        'secret' => env('WECHAT_OPEN_PLATFORM_SECRET'),
        'token' => env('WECHAT_OPEN_PLATFORM_TOKEN'),
        'aes_key' => env('WECHAT_OPEN_PLATFORM_AES_KEY'),
    ],

    'work' => [
        'corp_id' => env('WECHAT_WORK_CORP_ID'),
        'secret' => env('WECHAT_WORK_SECRET'),
        'agent_id' => env('WECHAT_WORK_AGENT_ID'),
        'token' => env('WECHAT_WORK_TOKEN'),
        'aes_key' => env('WECHAT_WORK_AES_KEY'),
    ],
]; 