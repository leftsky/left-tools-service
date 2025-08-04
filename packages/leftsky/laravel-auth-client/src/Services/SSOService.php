<?php

namespace Leftsky\AuthClient\Services;

use Leftsky\AuthClient\OAuth2\AuthServerProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

class SSOService
{
    /**
     * OAuth2 提供者
     *
     * @var AuthServerProvider
     */
    protected $provider;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->provider = new AuthServerProvider([
            'clientId'     => config('auth-client.auth_server.client_id'),
            'clientSecret' => config('auth-client.auth_server.client_secret'),
            'redirectUri'  => $this->getRedirectUrl(),
            'scopes'       => config('auth-client.sso.scopes', ['*']),
        ]);
    }

    /**
     * 获取授权URL
     *
     * @param string|null $returnUrl 登录后重定向的URL
     * @return string
     */
    public function getAuthUrl($returnUrl = null)
    {
        if ($returnUrl) {
            Session::put('sso_return_url', $returnUrl);
        }

        $options = [
            'state' => $this->generateState(),
        ];
        
        // 添加自定义参数
        if ($customParams = config('auth-client.sso.auth_params', [])) {
            $options = array_merge($options, $customParams);
        }

        return $this->provider->getAuthorizationUrl($options);
    }

    /**
     * 处理回调请求，获取访问令牌
     *
     * @param Request $request
     * @return array|bool 成功返回用户数据和令牌信息，失败返回false
     */
    public function handleCallback(Request $request)
    {
        try {
            // 记录回调开始
            Log::info('开始处理SSO回调', [
                'code' => $request->input('code'),
                'state' => $request->input('state')
            ]);

            // 验证state
            if (!$this->validateState($request->input('state'))) {
                Log::warning('SSO state验证失败');
                return false;
            }

            // 获取访问令牌
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->input('code')
            ]);

            Log::info('成功获取访问令牌', [
                'token' => $token->getToken(),
                'expires' => $token->getExpires(),
                'has_refresh_token' => !empty($token->getRefreshToken())
            ]);

            try {
                // 获取用户资源信息
                $resourceOwner = $this->provider->getResourceOwner($token);
                $userData = $resourceOwner->toArray();
                
                Log::info('成功获取用户信息', [
                    'user_id' => $userData['id'] ?? 'unknown',
                    'source' => $userData['_source'] ?? 'api'
                ]);
            } catch (\Exception $e) {
                Log::error('获取用户信息失败，使用备用数据', [
                    'error' => $e->getMessage()
                ]);
                
                return false;
            }

            // 获取回调后的重定向地址
            $returnUrl = Session::pull('sso_return_url', config('auth-client.sso.default_redirect', '/'));
            
            // 构建返回结果
            return [
                'token' => $token->jsonSerialize(),
                'user' => $userData,
                'return_url' => $returnUrl,
            ];
        } catch (\Exception $e) {
            Log::error('SSO回调处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 刷新访问令牌
     *
     * @return bool 是否成功
     */
    public function refreshToken()
    {
        if (!Session::has('sso_refresh_token')) {
            return false;
        }

        try {
            $refreshToken = Session::get('sso_refresh_token');
            
            $token = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken
            ]);
            
            // 获取用户信息
            $resourceOwner = $this->provider->getResourceOwner($token);
            
            // 更新会话中的令牌
            $this->storeTokenInSession($token, $resourceOwner->toArray());
            
            return true;
        } catch (IdentityProviderException $e) {
            Log::error('SSO令牌刷新失败', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return false;
        }
    }

    /**
     * 检查用户是否通过SSO认证
     *
     * @return bool
     */
    public function check()
    {
        if (!Session::has('sso_access_token')) {
            return false;
        }

        // 验证令牌是否过期
        $expiresAt = Session::get('sso_expires_at');
        
        if (now()->timestamp >= $expiresAt) {
            // 令牌已过期，尝试刷新
            if (!$this->refreshToken()) {
                $this->logout();
                return false;
            }
        }

        return true;
    }

    /**
     * 注销SSO会话
     *
     * @return void
     */
    public function logout()
    {
        // 清除会话中的SSO数据
        Session::forget([
            'sso_access_token',
            'sso_refresh_token',
            'sso_expires_at',
            'sso_user',
        ]);
        
        // 如果启用了集中式注销，重定向到认证服务器注销端点
        if (config('auth-client.sso.centralized_logout', true)) {
            return redirect($this->provider->getBaseUrl() . '/logout');
        }
    }

    /**
     * 获取重定向URL
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        // 获取应用程序的基本URL
        $baseUrl = config('app.url', 'http://localhost');
        $callbackPath = config('auth-client.sso.callback_url', '/auth/sso/callback');
        
        // 确保URL格式正确
        $baseUrl = rtrim($baseUrl, '/');
        $callbackPath = '/' . ltrim($callbackPath, '/');
        
        // 对于localhost环境，指定端口
        if (parse_url($baseUrl, PHP_URL_HOST) == 'localhost') {
            // 使用配置或默认端口
            $port = request()->getPort();
            if ($port && $port != 80 && $port != 443) {
                $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http';
                $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'localhost';
                $baseUrl = "{$scheme}://{$host}:{$port}";
            }
        }
        
        $url = $baseUrl . $callbackPath;
        
        // 记录最终的URL用于调试
        Log::debug('SSO回调URL', ['url' => $url]);
        
        return $url;
    }

    /**
     * 生成随机state参数
     *
     * @return string
     */
    protected function generateState()
    {
        $state = bin2hex(random_bytes(16));
        Session::put('sso_state', $state);
        return $state;
    }

    /**
     * 验证state参数
     *
     * @param string $state
     * @return bool
     */
    protected function validateState($state)
    {
        $savedState = Session::get('sso_state');
        Session::forget('sso_state');
        
        return $savedState && $state && $savedState === $state;
    }

    /**
     * 将令牌保存到会话
     *
     * @param AccessToken $token
     * @param array $userData
     * @return void
     */
    protected function storeTokenInSession(AccessToken $token, array $userData)
    {
        Session::put('sso_access_token', $token->getToken());
        Session::put('sso_refresh_token', $token->getRefreshToken());
        Session::put('sso_expires_at', $token->getExpires());
        Session::put('sso_user', $userData);
    }
} 