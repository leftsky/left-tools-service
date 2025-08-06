<?php

namespace Leftsky\AuthClient\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Leftsky\AuthClient\Facades\Token;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Leftsky\AuthClient\Services\UserSyncService;

class OptionalApiToken extends VerifyApiToken
{

    /**
     * 处理传入的请求
     * 如果用户登录了则使用相同的Auth进行登录
     * 如果未登录也不返回401，而是继续执行
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$scopes
     * @return mixed
     */
    public function handle($request, Closure $next, ...$scopes)
    {
        // 获取令牌
        $token = $this->getTokenFromRequest($request);

        // 如果没有令牌，继续执行（与VerifyApiToken不同，不返回401）
        if (!$token) {
            return $next($request);
        }

        try {
            // 验证Token
            $result = Token::verify($token, $scopes);

            if (!$result || !($result['valid'] ?? false)) {
                throw new \Exception('无效的认证令牌');
            }

            // 检查作用域
            if (!empty($scopes) && !$this->hasAllScopes($result['scopes'] ?? [], $scopes)) {
                throw new \Exception('权限不足：需要 ' . implode(',', $scopes) . ' 作用域');
            }

            // 使用模型层查询用户
            $userData = $result['user'] ?? [];

            if (!($userData['id'] ?? null) || !($userData['phone_number'] ?? null)) {
                throw new \Exception('无效的用户信息');
            }

            $user = $this->userSyncService->findOrCreateUser($userData);

            // 集成到 Laravel Auth 系统
            Auth::setUser($user);  // 无需启动 session

            // 确定是否需要刷新令牌（可选）
            if (config('auth-client.api.auto_refresh', false)) {
                $this->checkTokenRefresh($request, $token, $result);
            }

            return $next($request);
        } catch (IdentityProviderException $e) {
            // 捕获异常但不终止请求
            logger()->error('OAuth2 令牌验证失败', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return $next($request);
        } catch (\Exception $e) {
            // 捕获其他异常但不终止请求
            logger()->error('令牌验证异常', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return $next($request);
        }
    }
}
