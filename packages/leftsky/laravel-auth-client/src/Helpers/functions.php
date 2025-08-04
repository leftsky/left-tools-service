<?php

if (!function_exists('auth_client_config')) {
    /**
     * 获取auth-client配置
     *
     * @param string|null $key 配置键，null表示获取所有配置
     * @param mixed $default 默认值
     * @return mixed
     */
    function auth_client_config($key = null, $default = null)
    {
        if ($key === null) {
            return config('auth-client');
        }
        
        return config("auth-client.{$key}", $default);
    }
}

if (!function_exists('is_sso_authenticated')) {
    /**
     * 检查当前会话是否已通过SSO认证
     *
     * @return bool
     */
    function is_sso_authenticated()
    {
        return session()->has('sso_access_token');
    }
}

if (!function_exists('get_sso_user')) {
    /**
     * 获取SSO用户信息
     *
     * @return array|null
     */
    function get_sso_user()
    {
        return session('sso_user');
    }
}

if (!function_exists('verify_api_token')) {
    /**
     * 验证API令牌
     *
     * @param string $token 令牌
     * @param array $scopes 所需作用域
     * @return array|null
     */
    function verify_api_token($token, array $scopes = [])
    {
        return app('auth-client.token')->verify($token, $scopes);
    }
}

if (!function_exists('invalidate_token')) {
    /**
     * 使令牌失效
     *
     * @param string $token 令牌
     * @return bool
     */
    function invalidate_token($token)
    {
        return app('auth-client.token')->invalidateToken($token);
    }
}
