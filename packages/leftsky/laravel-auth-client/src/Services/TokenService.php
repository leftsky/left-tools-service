<?php

namespace Leftsky\AuthClient\Services;

use Illuminate\Support\Facades\Http;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Leftsky\AuthClient\OAuth2\AuthServerProvider;

class TokenService
{
    /**
     * 令牌缓存服务
     *
     * @var TokenCacheService
     */
    protected $tokenCache;

    /**
     * OAuth2 提供者
     *
     * @var AuthServerProvider|null
     */
    protected $provider;
    
    /**
     * 构造函数
     *
     * @param TokenCacheService $tokenCache
     */
    public function __construct(TokenCacheService $tokenCache)
    {
        $this->tokenCache = $tokenCache;
        $this->provider = app('auth-client.oauth2-provider');
    }
    
    /**
     * 验证令牌
     *
     * @param string $token
     * @param array $requiredScopes
     * @return array|null
     */
    public function verify($token, $requiredScopes = [])
    {
        // 1. 检查缓存
        $cachedResult = $this->tokenCache->get($token);
        if ($cachedResult) {
            return $this->validateScopes($cachedResult, $requiredScopes)
                ? $cachedResult
                : null;
        }
        
        // 2. 尝试 JWT 本地验证
        if (config('auth-client.api.jwt_enabled', false)) {
            $jwtService = app(JWTService::class);
            $jwtResult = $jwtService->verify($token);
            if ($jwtResult) {
                $this->tokenCache->put($token, $jwtResult);
                
                return $this->validateScopes($jwtResult, $requiredScopes)
                    ? $jwtResult
                    : null;
            }
        }
        
        // 3. 调用认证中心验证
        $result = $this->verifyWithServer($token, $requiredScopes);
        if ($result) {
            $this->tokenCache->put($token, $result);
        }
        
        return $result;
    }
    
    /**
     * 使用服务器验证令牌
     *
     * @param string $token
     * @param array $requiredScopes
     * @return array|null
     */
    protected function verifyWithServer($token, $requiredScopes = [])
    {
        try {
            // 优先使用 OAuth2 库的内置功能检查令牌
            if ($this->provider && method_exists($this->provider, 'getResourceOwner')) {
                try {
                    // 创建一个 AccessToken 对象供 getResourceOwner 使用
                    $accessToken = new \League\OAuth2\Client\Token\AccessToken([
                        'access_token' => $token
                    ]);
                    
                    // 获取资源所有者信息
                    $resourceOwner = $this->provider->getResourceOwner($accessToken);
                    $userData = $resourceOwner->toArray();
                    
                    // 构建结果集
                    return [
                        'valid' => true,
                        'user' => $userData,
                        'scopes' => $userData['scopes'] ?? [],
                        'expires_at' => null, // OAuth2 客户端没有直接方式获取过期时间
                    ];
                } catch (IdentityProviderException $e) {
                    // 静默失败，回退到传统 HTTP 方式
                }
            }
            
            // 回退到传统 HTTP 方式
            $verifyEndpoint = config('auth-client.auth_server.url') . 
                                config('auth-client.api.verify_endpoint');
                                
            $response = Http::withToken($token)
                ->timeout(config('auth-client.oauth2.request_timeout', 30))
                ->post($verifyEndpoint, [
                    'scopes' => $requiredScopes
                ]);
                
            if ($response->successful()) {
                return $response->json();
            }
            
            return null;
        } catch (\Exception $e) {
            // 记录错误但不抛出
            logger()->error('API令牌验证失败', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            
            return null;
        }
    }
    
    /**
     * 验证令牌是否具有所需的范围
     *
     * @param array $tokenData
     * @param array $requiredScopes
     * @return bool
     */
    protected function validateScopes($tokenData, $requiredScopes)
    {
        if (empty($requiredScopes)) {
            return true;
        }
        
        $tokenScopes = $tokenData['scopes'] ?? [];
        
        // 如果令牌具有通配符范围，则放行所有范围
        if (in_array('*', $tokenScopes)) {
            return true;
        }
        
        // 检查是否具有所有所需范围
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 使指定Token的缓存失效
     */
    public function invalidateToken($token)
    {
        return $this->tokenCache->invalidate($token);
    }
    
    /**
     * 使所有Token缓存失效
     */
    public function invalidateAllTokens()
    {
        return $this->tokenCache->invalidateAll();
    }
} 