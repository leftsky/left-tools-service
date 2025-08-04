<?php

namespace Leftsky\AuthClient;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class AuthClientServiceProvider extends ServiceProvider
{
    /**
     * 注册任何应用服务
     *
     * @return void
     */
    public function register()
    {
        // 注册配置文件
        $this->mergeConfigFrom(
            __DIR__.'/../config/auth-client.php', 'auth-client'
        );
        
        // 注册核心服务
        $this->registerServices();
        
        // 注册助手函数
        $this->registerHelpers();
    }
    
    /**
     * 启动任何应用服务
     *
     * @return void
     */
    public function boot()
    {
        // 注册路由
        if (config('auth-client.routes.register', true)) {
            $this->registerRoutes();
        }
        
        // 注册中间件
        $this->app['router']->aliasMiddleware('sso.auth', \Leftsky\AuthClient\Http\Middleware\SSOAuthMiddleware::class);

        // 发布配置文件
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/auth-client.php' => config_path('auth-client.php'),
            ], 'config');
        }
        
        // 加载迁移文件
        $this->loadMigrationsWhenNeeded();
        
        // 注册控制台命令
        $this->registerCommands();
        
        // 注册事件监听器
        $this->registerEventListeners();

        // 注册中间件
        $this->registerMiddlewares();
        
        // 记录包已启动
        $this->logPackageStarted();
    }
    
    /**
     * 注册所有服务
     *
     * @return void
     */
    protected function registerServices()
    {
        // Token服务
        $this->app->singleton('auth-client.token', function ($app) {
            return new Services\TokenService(
                $app->make(Services\TokenCacheService::class)
            );
        });
        
        // Token缓存服务
        $this->app->singleton('auth-client.token-cache', function ($app) {
            return new Services\TokenCacheService();
        });
        
        // SSO服务
        $this->app->singleton('auth-client.sso', function ($app) {
            return new Services\SSOService();
        });
        
        // 用户同步服务
        $this->app->singleton('sso.user_syncer', function ($app) {
            return new Services\UserSyncService();
        });
        
        // JWT服务（可选）
        if (config('auth-client.api.jwt_enabled')) {
            $this->app->singleton('auth-client.jwt', function ($app) {
                return new Services\JWTService();
            });
        }
        
        // 缓存监控服务
        $this->app->singleton('auth-client.cache-monitor', function ($app) {
            return new Services\TokenCacheMonitor(
                $app->make(Services\TokenCacheService::class)
            );
        });
        
        // OAuth2 提供者
        $this->app->singleton('auth-client.oauth2-provider', function ($app) {
            $providerClass = config('auth-client.oauth2.provider_class', 
                             \Leftsky\AuthClient\OAuth2\AuthServerProvider::class);
                            
            return new $providerClass([
                'clientId' => config('auth-client.auth_server.client_id'),
                'clientSecret' => config('auth-client.auth_server.client_secret'),
                'redirectUri' => $this->getCallbackUrl(),
                'scopes' => config('auth-client.sso.scopes', ['*']),
            ]);
        });
    }
    
    /**
     * 注册中间件
     *
     * @return void
     */
    protected function registerMiddlewares()
    {
        // API相关中间件
        if (config('auth-client.api.enabled', true)) {
            Route::aliasMiddleware('auth.api', Middleware\VerifyApiToken::class);
            Route::aliasMiddleware('auth.api-scope', Middleware\VerifyApiScope::class);
            Route::aliasMiddleware('auth.api-optional', Middleware\OptionalApiToken::class);
        }
        
        // SSO相关中间件
        if (config('auth-client.sso.enabled', false)) {
            Route::aliasMiddleware('auth.sso', Middleware\VerifySSOSession::class);
            Route::aliasMiddleware('auth.sso-refresh', Middleware\RefreshSSOSession::class);
        }
    }
    
    /**
     * 注册路由
     *
     * @return void
     */
    protected function registerRoutes()
    {
        if (!config('auth-client.routes.register', true)) {
            return;
        }
        
        // SSO相关路由
        if (config('auth-client.sso.enabled', false) && config('auth-client.sso.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/sso.php');
        }
        
        // API相关路由（如果有的话）
        if (config('auth-client.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/auth-client.php');
        }
    }
    
    /**
     * 注册命令行工具
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\TokenCacheCommand::class,
                Console\Commands\SetupCommand::class,
                Console\Commands\CacheMonitorCommand::class,
            ]);
        }
    }
    
    /**
     * 注册事件监听器
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        // 示例：注册SSO登录事件监听器
        Event::listen(Events\SSOLogin::class, function ($event) {
            Log::info('SSO登录成功', ['user' => $event->user]);
        });
    }
    
    /**
     * 发布配置文件
     *
     * @return void
     */
    protected function publishConfigs()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/auth-client.php' => config_path('auth-client.php'),
            ], 'auth-client-config');
        }
    }
    
    /**
     * 加载迁移文件（如果需要）
     *
     * @return void
     */
    protected function loadMigrationsWhenNeeded()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
    
    /**
     * 注册助手函数
     *
     * @return void
     */
    protected function registerHelpers()
    {
        // 加载助手函数文件
        $helperPath = __DIR__ . '/Helpers/functions.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
        }
    }
    
    /**
     * 记录包启动日志
     *
     * @return void
     */
    protected function logPackageStarted()
    {
        if (config('auth-client.logging.enabled', true)) {
            // $level = config('auth-client.logging.level', 'debug');
            // Log::channel(config('auth-client.logging.channel'))->{$level}('Auth Client package started');
        }
    }
    
    /**
     * 获取回调URL
     *
     * @return string
     */
    protected function getCallbackUrl()
    {
        $baseUrl = url('/');
        $callbackPath = config('auth-client.sso.callback_url', '/auth/sso/callback');
        
        return $baseUrl . '/' . ltrim($callbackPath, '/');
    }
}
