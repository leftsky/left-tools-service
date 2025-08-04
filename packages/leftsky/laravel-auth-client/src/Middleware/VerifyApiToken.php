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

class VerifyApiToken
{

    protected $userSyncService;

    public function __construct(UserSyncService $userSyncService)
    {
        $this->userSyncService = $userSyncService;
    }

    /**
     * 处理传入的请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$scopes
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$scopes)
    {
        // 如果本地开发模式启用了模拟认证，跳过认证逻辑
        if ($this->shouldUseMockAuth()) {
            return $this->handleWithMockUser($request, $next);
        }

        // 获取令牌
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return $this->unauthenticated('未提供认证令牌');
        }

        try {
            // 验证Token
            $result = Token::verify($token, $scopes);

            if (!$result || !($result['valid'] ?? false)) {
                return $this->unauthenticated('无效的认证令牌');
            }

            // 检查作用域
            if (!empty($scopes) && !$this->hasAllScopes($result['scopes'] ?? [], $scopes)) {
                return $this->unauthorized('权限不足：需要 ' . implode(',', $scopes) . ' 作用域');
            }

            // 使用模型层查询用户
            $userData = $result['user'] ?? [];
            $userId = $userData['id'] ?? null;
            $mobile = $userData['mobile'] ?? $userData['phone_number'] ?? null;

            if (!$userId || !$mobile) {
                return $this->unauthenticated('无效的用户信息');
            }

            $user = $this->userSyncService->findOrCreateUser($userData);

            // 新增: 集成到 Laravel Auth 系统
            Auth::setUser($user);  // 无需启动 session

            // 确定是否需要刷新令牌（可选）
            if (config('auth-client.api.auto_refresh', false)) {
                $this->checkTokenRefresh($request, $token, $result);
            }

            return $next($request);
        } catch (IdentityProviderException $e) {
            // 处理 OAuth2 错误
            logger()->error('OAuth2 令牌验证失败', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return $this->unauthenticated('OAuth2 认证失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            // 处理其他异常
            logger()->error('令牌验证异常', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'userData' => $userData ?? null,
                'userId' => $userId ?? null,
                'user' => $user ?? null,
            ]);

            return $this->unauthenticated('认证失败');
        }
    }

    /**
     * 检查令牌是否包含所有必需的作用域
     *
     * @param array $tokenScopes
     * @param array $requiredScopes
     * @return bool
     */
    protected function hasAllScopes($tokenScopes, $requiredScopes)
    {
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 返回未认证响应
     *
     * @param string $message
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($message = '未认证')
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => $message,
                'status_code' => 401
            ], 401);
        }

        throw new AuthenticationException($message);
    }

    /**
     * 返回未授权响应
     *
     * @param string $message
     * @return \Illuminate\Http\Response
     */
    protected function unauthorized($message = '未授权')
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => $message,
                'status_code' => 403
            ], 403);
        }

        abort(403, $message);
    }

    /**
     * 检查是否需要刷新令牌
     *
     * @param \Illuminate\Http\Request $request
     * @param string $token
     * @param array $result
     * @return void
     */
    protected function checkTokenRefresh($request, $token, $result)
    {
        // 如果Token即将过期，可以在响应中添加刷新提示
        if (isset($result['expires_at'])) {
            $expiresAt = \Carbon\Carbon::parse($result['expires_at']);
            $refreshThreshold = now()->addMinutes(
                config('auth-client.api.refresh_threshold', 60)
            );

            if ($expiresAt->lt($refreshThreshold)) {
                // 在响应中添加头部，提示客户端刷新令牌
                $request->headers->set('X-Token-Refresh-Required', 'true');
            }
        }
    }

    /**
     * 从请求中获取令牌
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function getTokenFromRequest($request)
    {
        // 首先检查 Authorization 头
        $token = $request->bearerToken();

        // 如果没有，检查请求参数
        if (!$token && config('auth-client.api.allow_token_param', false)) {
            $token = $request->input('access_token');
        }

        return $token;
    }

    /**
     * 检查是否应该使用模拟认证
     *
     * @return bool
     */
    protected function shouldUseMockAuth()
    {
        return config('auth-client.local_development.enabled', false) &&
            config('auth-client.local_development.mock_auth', false);
    }

    /**
     * 使用模拟用户处理请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    protected function handleWithMockUser($request, Closure $next)
    {
        $mockUser = config('auth-client.local_development.mock_user', []);
        $userId = $mockUser['id'] ?? null;

        if ($userId) {
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userModel::find($userId);

            if ($user) {
                $request->merge(['auth_user' => $user]);
                $request->setUserResolver(function () use ($user) {
                    return $user;
                });

                // 集成到 Laravel Auth 系统
                Auth::setUser($user);

                return $next($request);
            }
        }

        // 如果找不到用户或ID无效，则使用模拟数据作为备用
        $request->merge(['auth_user' => (object)$mockUser]);
        $request->setUserResolver(function () use ($mockUser) {
            return (object)$mockUser;
        });

        return $next($request);
    }
}
