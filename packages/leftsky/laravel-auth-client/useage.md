<think>我需要根据用户提供的 Git 更改记录和 Laravel Auth Client 包的使用手册来更新安装文档。从 Git 状态信息中，我可以看到以下关键更改：

1. 新文件：
   - `app/Filament/Pages/Auth/Login.php` - 自定义 Filament 登录页面
   - `app/Http/Middleware/VerifyCsrfToken.php` - CSRF 验证中间件
   - `config/auth-client.php` - Auth Client 配置文件

2. 修改文件：
   - `app/Providers/Filament/AdminPanelProvider.php` - Filament 面板提供者
   - `composer.json` & `composer.lock` - 依赖更新
   - `resources/views/welcome.blade.php` - 欢迎页面
   - `routes/auth.php` - 认证路由

这些更改表明用户实际操作的安装步骤可能与现有文档有些不同。我需要根据这些实际更改来优化使用手册，重点应该放在：

1. Filament 集成的正确方式
2. CSRF 验证的配置
3. 详细的 Auth Client 配置
4. SSO 回调和自动用户创建的设置

我也需要确保包名称正确（`leftsky/laravel-auth-client` 而非 `yourorg/laravel-auth-client`）。</think># Laravel Auth Client 安装手册

根据当前项目的实际集成情况，我已更新了 Laravel Auth Client 包的安装与使用手册，以更准确地反映实际操作步骤。

```markdown
# Laravel Auth Client

Laravel认证中心客户端集成包，用于快速接入统一认证中心。使用 League OAuth2 客户端实现标准的 OAuth2 授权流程。

## 功能特性

- API Token验证
- 单点登录(SSO)
- Token缓存
- 用户自动同步和创建
- OAuth2 标准流程支持
- Filament面板集成支持

## 安装

```bash
composer require leftsky/laravel-auth-client
```

## 配置

发布配置文件:

```bash
php artisan vendor:publish --tag=config
```

在`.env`文件中配置:

```
APP_URL=http://localhost:8001

# Auth Client 配置
AUTH_SERVER_URL=http://localhost:8000
AUTH_CLIENT_ID=your-client-id
AUTH_CLIENT_SECRET=your-client-secret
AUTH_API_ENABLED=true
AUTH_SSO_ENABLED=true
AUTH_SSO_CALLBACK_URL=/auth/sso/callback
AUTH_SSO_SYNC_LOCAL_USER=true
AUTH_SSO_CREATE_MISSING_USERS=true
AUTH_SSO_USER_FIND_BY=email
AUTH_SSO_USER_MODEL=\App\Models\User
```

## 详细接入流程

### 1. 添加CSRF例外

由于SSO回调由第三方服务器发起，需要将回调URL添加到CSRF验证的例外列表中。编辑或创建`app/Http/Middleware/VerifyCsrfToken.php`文件：

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // 添加 SSO 回调路径到例外列表
        'auth/sso/callback',
    ];
}
```

### 2. Filament集成

#### 2.1 创建自定义登录页面

创建文件`app/Filament/Pages/Auth/Login.php`：

```php
<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Leftsky\AuthClient\Facades\SSO;
use Illuminate\Support\Facades\Auth;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (! Auth::check()) {
            // 用户未登录时直接重定向到SSO
            $ssoUrl = SSO::getAuthUrl(route('filament.admin.pages.dashboard'));
            $this->redirect($ssoUrl);
            return;
        }
        
        // 用户已登录，继续父类的mount逻辑
        parent::mount();
    }
    
    public function getTitle(): string|Htmlable
    {
        return '登录';
    }

    protected function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'ssoLoginUrl' => SSO::getAuthUrl(route('filament.admin.pages.dashboard')),
        ];
    }
    
    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }
}
```

#### 2.2 修改AdminPanelProvider

在`app/Providers/Filament/AdminPanelProvider.php`中指定自定义登录页面并添加SSO中间件：

```php
<?php

namespace App\Providers\Filament;

// ... 其他引入 ...
use Leftsky\AuthClient\Facades\SSO;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class) // 指定自定义登录页面
            ->colors([
                'primary' => Color::Amber,
            ])
            // ... 其他配置 ...
            ->middleware([
                // ... 默认中间件 ...
                'sso.auth', // 添加SSO认证中间件
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

### 3. 更新欢迎页面（可选）

在欢迎页面添加SSO登录入口：

```html
<a href="{{ url('/admin') }}" class="button primary">
    <span class="material-icons button-icon">login</span>
    进入系统
</a>
```

### 4. 用户同步与自动创建

Laravel Auth Client 包支持自动将SSO用户信息同步到本地数据库，并能在需要时自动创建用户账户。通过配置以下选项启用此功能：

```
AUTH_SSO_SYNC_LOCAL_USER=true
AUTH_SSO_CREATE_MISSING_USERS=true
AUTH_SSO_USER_FIND_BY=email
AUTH_SSO_USER_MODEL=\App\Models\User
```

这样，当用户通过SSO成功认证，但在本地数据库中找不到对应账户时，系统会自动创建一个新用户。

## 故障排除

### 1. 循环重定向问题

如果遇到登录后不断在登录页和回调页之间循环跳转，可能是因为：

- CSRF验证未正确配置
- SSO回调处理未同步到本地认证系统
- 回调URL格式不正确

解决方法：
- 确保CSRF例外正确配置
- 检查`.env`中的APP_URL是否包含正确端口号
- 查看日志中的详细错误信息

### 2. 认证失败

如果SSO认证失败，可能是因为：
- 客户端ID或密钥不正确
- 回调URL与认证服务器注册的不匹配
- 认证服务器配置问题

解决方法：
- 确认`.env`中的认证参数正确
- 使用相同的协议和端口配置回调URL
- 查看Laravel日志获取详细错误信息

## 文档

更详细的文档请参考 [Wiki](https://github.com/leftsky/laravel-auth-client/wiki)。

## 许可证

MIT
```
