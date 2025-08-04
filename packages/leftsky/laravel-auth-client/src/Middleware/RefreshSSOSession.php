<?php

namespace Leftsky\AuthClient\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RefreshSSOSession
{
    /**
     * 处理传入的请求，在需要时自动刷新SSO会话
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 检查是否需要刷新令牌
        $refreshToken = session('sso_refresh_token');
        $accessToken = session('sso_access_token');
        
        if ($accessToken && $refreshToken && $this->shouldRefreshToken()) {
            $this->refreshToken($refreshToken);
        }
        
        return $next($request);
    }
    
    /**
     * 判断是否应该刷新令牌
     *
     * @return bool
     */
    protected function shouldRefreshToken()
    {
        // 获取令牌过期时间
        $tokenExpiresAt = Cache::get('sso_token_expires_at');
        
        // 如果缓存中没有过期时间，假设需要刷新
        if (!$tokenExpiresAt) {
            return true;
        }
        
        // 如果令牌即将过期（例如，30分钟内），刷新它
        $refreshThreshold = now()->addMinutes(30);
        return $tokenExpiresAt <= $refreshThreshold;
    }
    
    /**
     * 刷新访问令牌
     *
     * @param string $refreshToken
     * @return bool
     */
    protected function refreshToken($refreshToken)
    {
        $response = Http::post(config('auth-client.sso.base_url') . '/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('auth-client.sso.client_id'),
            'client_secret' => config('auth-client.sso.client_secret'),
            'scope' => 'basic',
        ]);
        
        if (!$response->successful()) {
            // 刷新失败，清除会话
            session()->forget('sso_access_token');
            session()->forget('sso_refresh_token');
            return false;
        }
        
        $tokenData = $response->json();
        
        // 更新会话
        session([
            'sso_access_token' => $tokenData['access_token'],
            'sso_refresh_token' => $tokenData['refresh_token'] ?? $refreshToken,
        ]);
        
        // 计算过期时间并缓存
        $expiresIn = $tokenData['expires_in'] ?? 3600;
        $expiresAt = now()->addSeconds($expiresIn);
        Cache::put('sso_token_expires_at', $expiresAt, $expiresIn);
        
        return true;
    }
}
