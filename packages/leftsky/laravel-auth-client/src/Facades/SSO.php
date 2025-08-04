<?php

namespace Leftsky\AuthClient\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getLoginUrl(string|null $returnUrl = null, bool $forceLogin = false)
 * @method static array|null handleCallback(string $code, string $state)
 * @method static array|null getUserInfo(string $accessToken)
 * @method static bool verifySession()
 * @method static string getLogoutUrl(string|null $returnUrl = null)
 * @method static void logout()
 *
 * @see \Leftsky\AuthClient\Services\SSOService
 */
class SSO extends Facade
{
    /**
     * 获取组件注册名称
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'auth-client.sso';
    }
} 