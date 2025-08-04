<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 认证中心配置
    |--------------------------------------------------------------------------
    |
    | 定义与认证中心的连接参数，包括基础URL、客户端ID和密钥等。
    |
    */
    'auth_server' => [
        'url' => env('AUTH_SERVER_URL', 'http://auth.example.com'),
        'client_id' => env('AUTH_CLIENT_ID'),
        'client_secret' => env('AUTH_CLIENT_SECRET'),
        'verify_ssl' => env('AUTH_VERIFY_SSL', true),
        'timeout' => env('AUTH_REQUEST_TIMEOUT', 5),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Token配置
    |--------------------------------------------------------------------------
    |
    | 配置API Token验证相关的选项，包括校验端点、缓存设置等。
    |
    */
    'api' => [
        'enabled' => env('AUTH_API_ENABLED', true),
        'verify_endpoint' => env('AUTH_API_VERIFY_ENDPOINT', '/api/verify-token'),
        'centralized' => env('AUTH_API_CENTRALIZED', true),
        'fallback_enabled' => env('AUTH_API_FALLBACK_ENABLED', false),
        
        // 令牌刷新配置
        'token_lifetime' => env('AUTH_API_TOKEN_LIFETIME', 60 * 24), // 分钟
        'refresh_threshold' => env('AUTH_API_REFRESH_THRESHOLD', 60), // 分钟
        'auto_refresh' => env('AUTH_API_AUTO_REFRESH', false),
        
        // JWT验证配置
        'jwt_enabled' => env('AUTH_JWT_ENABLED', false),
        'jwt_public_key_path' => env('AUTH_JWT_PUBLIC_KEY_PATH'),
        'jwt_public_key' => env('AUTH_JWT_PUBLIC_KEY', '-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAz8jv46AgCnC2d2EDYdx+
L2EZEWzkNnEbxVpoD4NvHDcJQuxjuuhJK6+BQV/CR3ka7tesEqo1qiGJfA+fyHpi
odFupgYvIkY8wg52eH4D0LIGh8X0JMdGU3vsZivcHA/cFSMS6w0mOeHkfFUYStl6
g549D/4VHkIsBSjbpU5X1pfu1rORzAw0f4HuzlF54I8MHqsCRQ1Ui44yRqg5Tonq
cnV/un+VzAls6TDRJeMwkAerrihXZ1c8LfiuQF4w7Jsy1/zTUwRwXKmc7LcoXOC8
3TNKHptfChQrwLTgTBRmOry2THMYMLzgImg5zLWm7XtHNxu+b0ADUueN+URJAV/T
uPfsxqUTXPiA6r91p3iKU1sBHz7vrWAlddnMBxy5Cjqws3+Tn9lszygP7PUfBFAb
ToRbBn8ax0a8rryBujuNhxxPOnt90Q+xhO7dmMuFmJyNIeKFPbhnWoa0qlYQL5Lc
v9jS9RkGq24faVj4l2DnPWrAJZ+4LiCkQvxJa4RSs+hsnHAG0jdgEXrKU2EPhLA5
WwidgkgHGDFgV9KA1XVQbqJ34BFycqNlUtvo+7MymaTbQ6WluZpypYJub/osukDF
+1zACYKFJpHTyo1WHj1i8Lymhwed6bKQxmDMm6lmWC86h3z/iJ9uhEQAf/xlISYL
YSL4+weLWAx9RXhUANHCd9kCAwEAAQ==
-----END PUBLIC KEY-----'),
        'jwt_algorithm' => env('AUTH_JWT_ALGORITHM', 'RS256'),
        
        // 请求重试配置
        'retry_times' => env('AUTH_API_RETRY_TIMES', 3),
        'retry_sleep' => env('AUTH_API_RETRY_SLEEP', 100), // 毫秒
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OAuth2 配置
    |--------------------------------------------------------------------------
    |
    | OAuth2客户端配置项，包括提供者类、端点和连接参数等。
    |
    */
    'oauth2' => [
        'provider_class' => \Leftsky\AuthClient\OAuth2\AuthServerProvider::class,
        'token_endpoint' => '/oauth/token',
        'auth_endpoint' => '/oauth/authorize',
        'user_endpoint' => '/api/user-direct',
        'connect_timeout' => 10,
        'request_timeout' => 30,
        'verify_ssl' => env('AUTH_VERIFY_SSL', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 缓存配置
    |--------------------------------------------------------------------------
    |
    | Token缓存相关配置，包括是否启用缓存、缓存连接和TTL等。
    |
    */
    'cache' => [
        'enabled' => env('AUTH_CACHE_ENABLED', true), // 默认启用缓存，使用Laravel缓存系统
        'store' => env('AUTH_CACHE_STORE', null), // null表示使用默认缓存
        'ttl' => env('AUTH_CACHE_TTL', 60), // 分钟
        'prefix' => env('AUTH_CACHE_PREFIX', 'auth_'),
        'refresh_on_hit' => env('AUTH_CACHE_REFRESH_ON_HIT', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | SSO配置
    |--------------------------------------------------------------------------
    |
    | 单点登录相关配置，包括回调URL、用户同步选项等。
    |
    */
    'sso' => [
        'enabled' => env('AUTH_SSO_ENABLED', false),
        'base_url' => env('AUTH_SSO_BASE_URL', null), // 默认使用auth_server.url
        'callback_url' => env('AUTH_SSO_CALLBACK_URL', '/auth/sso/callback'),
        'auto_redirect' => env('AUTH_SSO_AUTO_REDIRECT', true),
        'session_lifetime' => env('AUTH_SSO_SESSION_LIFETIME', 120), // 分钟
        
        // OAuth2 相关配置
        'scopes' => explode(',', env('AUTH_SSO_SCOPES', '*')),
        'default_scopes' => ['*'],
        'auth_params' => [],  // 自定义授权参数
        'token_middleware' => ['web'], // 令牌认证中间件
        
        // 用户同步配置
        'sync_local_user' => env('AUTH_SSO_SYNC_LOCAL_USER', false),
        'user_model' => env('AUTH_SSO_USER_MODEL', '\App\Models\User'),
        'user_find_by' => env('AUTH_SSO_USER_FIND_BY', 'phone_number'),
        'create_missing_users' => env('AUTH_SSO_CREATE_MISSING_USERS', true),
        'sync_user_attributes' => env('AUTH_SSO_SYNC_USER_ATTRIBUTES', true),
        'attribute_map' => [
            'avatar' => 'avatar',
            'name' => 'name',
            'email' => 'email',
            'phone_number' => 'phone_number',
        ],
        
        // 其他SSO选项
        'centralized_logout' => env('AUTH_SSO_CENTRALIZED_LOGOUT', true),
        'dispatch_login_event' => env('AUTH_SSO_DISPATCH_LOGIN_EVENT', true),
        'register_routes' => env('AUTH_SSO_REGISTER_ROUTES', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 路由配置
    |--------------------------------------------------------------------------
    |
    | 配置包注册的路由前缀、中间件等。
    |
    */
    'routes' => [
        'register' => env('AUTH_ROUTES_REGISTER', true),
        'prefix' => env('AUTH_ROUTES_PREFIX', 'auth'),
        'middleware' => ['web'],
        'name_prefix' => 'auth.',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    |
    | 配置包的日志选项，包括是否启用、日志级别等。
    |
    */
    'logging' => [
        'enabled' => env('AUTH_LOGGING_ENABLED', true),
        'level' => env('AUTH_LOGGING_LEVEL', 'debug'),
        'channel' => env('AUTH_LOGGING_CHANNEL', null), // null表示使用默认通道
    ],
    
]; 