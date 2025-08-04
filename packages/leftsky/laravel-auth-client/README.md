# Laravel Auth Client

Laravel认证中心客户端集成包，用于快速接入统一认证中心。使用 League OAuth2 客户端实现标准的 OAuth2 授权流程。

## 功能特性

- API Token验证
- 单点登录(SSO)
- Token缓存
- 用户同步
- OAuth2 标准流程支持
- Filament面板集成支持

## 安装

```bash
composer require leftsky/laravel-auth-client
```

## 配置

发布配置文件:

```bash
php artisan vendor:publish --tag=auth-client-config
```

或者使用包提供的设置命令：

```bash
php artisan auth-client:setup
```

在`.env`文件中配置:

```
AUTH_SERVER_URL=https://auth.example.com
AUTH_CLIENT_ID=your-client-id
AUTH_CLIENT_SECRET=your-client-secret
AUTH_API_ENABLED=true
AUTH_SSO_ENABLED=true
```

## 详细接入流程

### 1. 基础配置

安装并发布配置文件后，根据需要修改`config/auth-client.php`文件中的SSO配置：

```php
'sso' => [
    'enabled' => env('AUTH_SSO_ENABLED', true),
    'callback_url' => env('AUTH_SSO_CALLBACK_URL', '/auth/sso/callback'),
    'auto_redirect' => true,
    'sync_local_user' => true,  // 如果需要同步到本地用户表
    'user_model' => '\App\Models\User',
    'user_find_by' => 'email',
    'create_missing_users' => true,
    'attribute_map' => [
        'name' => 'name',
        'email' => 'email',
    ],
],
```

### 2. 路由配置

该包会自动注册以下路由：
- `/auth/sso/callback` - SSO回调处理
- `/auth/sso/logout` - SSO退出登录

如果需要自定义路由，可以在配置文件中设置：

```php
'routes' => [
    'register' => true,
    'prefix' => 'auth',
    'middleware' => ['web'],
    'name_prefix' => 'auth.',
],
```

### 3. Filament集成

#### 3.1 修改AdminPanelProvider

在`app/Providers/Filament/AdminPanelProvider.php`文件中添加自定义登录逻辑：

```php
<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Leftsky\AuthClient\Facades\SSO;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ... 现有代码 ...
            ->login()
            ->loginRouteActions([
                'sso' => fn () => redirect()->away(SSO::getLoginUrl(route('filament.admin.auth.login'))),
            ])
            // ... 现有代码 ...
    }
}
```

#### 3.2 创建自定义登录页面

创建Filament自定义登录页面，添加SSO登录选项：

```php
<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Leftsky\AuthClient\Facades\SSO;

class Login extends BaseLogin
{
    public function getTitle(): string|Htmlable
    {
        return '登录';
    }

    protected function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'ssoLoginUrl' => SSO::getLoginUrl(route('filament.admin.auth.login')),
        ];
    }
}
```

创建对应的视图文件：

```blade
<x-filament-panels::page.simple>
    <x-filament::form wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :full-width="true"
            :actions="$this->getCachedFormActions()"
            alignment="start"
        />
        
        <div class="mt-4 text-center">
            <a href="{{ $ssoLoginUrl }}" class="text-primary-600 hover:text-primary-500">
                使用单点登录
            </a>
        </div>
    </x-filament::form>
</x-filament-panels::page.simple>
```

### 4. 用户同步配置（可选）

如果需要将SSO用户同步到本地数据库，确保在配置中启用相关选项：

```php
'sync_local_user' => true,
'user_model' => '\App\Models\User',
'user_find_by' => 'email',
'create_missing_users' => true,
'sync_user_attributes' => true,
```

### 5. 保护路由

使用SSO中间件保护需要认证的路由：

```php
// 需要SSO认证的路由
Route::middleware('auth.sso')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard', [
            'user' => get_sso_user()
        ]);
    });
});
```

## OAuth2 支持

本包使用 `league/oauth2-client` 来提供标准化的 OAuth2 功能。这提供了以下优势：

1. 标准化 OAuth2 授权流程
2. 可靠的令牌处理
3. 更简单的代码维护
4. 经过验证的安全实现

## 快速开始

### API 验证

在路由中使用 API 验证中间件:

```php
Route::middleware('auth.api')->group(function () {
    Route::get('/profile', function (Request $request) {
        return $request->user();
    });
});
```

### 单点登录

在路由中使用 SSO 验证中间件:

```php
Route::middleware('auth.sso')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard', [
            'user' => get_sso_user()
        ]);
    });
});
```

### OAuth2 流程示例

初始化 OAuth2 流程：

```php
// 重定向到授权服务器
return redirect()->away(SSO::getLoginUrl(route('sso.callback')));
```

处理回调：

```php
// 在回调控制器中
public function handleCallback(Request $request)
{
    if (SSO::handleCallback($request)) {
        return redirect()->intended('/dashboard');
    }
    
    return redirect('/login')->with('error', '认证失败');
}
```

## 故障排除

如果遇到问题，可以使用包提供的命令查看缓存状态：

```bash
php artisan auth:token-cache stats
php artisan auth:cache-stats
```

或者清除缓存：

```bash
php artisan auth:token-cache clear
```

在本地开发环境，可启用模拟认证功能简化测试：

```
AUTH_LOCAL_DEV_ENABLED=true
AUTH_MOCK_ENABLED=true
```

## 文档

更详细的文档请参考 [Wiki](https://github.com/leftsky/laravel-auth-client/wiki)。

## 许可证

MIT 