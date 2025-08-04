<?php

namespace Leftsky\AuthClient\OAuth2;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Log;

class AuthServerProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * 获取基础URL
     *
     * @return string
     */
    protected function getBaseUrl()
    {
        return rtrim(config('auth-client.auth_server.url'), '/');
    }

    /**
     * 获取授权URL
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->getBaseUrl() . '/oauth/authorize';
    }

    /**
     * 获取访问令牌URL
     *
     * @param array $params
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->getBaseUrl() . '/oauth/token';
    }

    /**
     * 获取资源所有者详情URL
     *
     * @param AccessToken $token
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->getBaseUrl() . config('auth-client.oauth2.user_endpoint');
    }

    /**
     * 获取默认请求范围
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return config('auth-client.sso.default_scopes', ['*']);
    }

    /**
     * 检查响应中的错误
     *
     * @param ResponseInterface $response
     * @param array|string $data
     * @throws IdentityProviderException
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException(
                $data['message'] ?? $data['error'] ?? '未知错误',
                $response->getStatusCode(),
                $data
            );
        }
    }

    /**
     * 创建资源所有者对象
     *
     * @param array $response
     * @param AccessToken $token
     * @return ResourceOwner
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new ResourceOwner($response);
    }

    protected function fetchResourceOwnerDetails(AccessToken $token)
    {
        $url = $this->getResourceOwnerDetailsUrl($token);

        $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url, $token);

        try {
            // 添加详细的日志记录
            Log::debug('正在获取用户资源信息', [
                'url' => $url,
                'token_values' => $token->jsonSerialize(),
                'expires' => $token->getExpires(),
            ]);

            $response = $this->getParsedResponse($request);

            // 记录原始响应
            // Log::debug('收到的响应内容', [
            //     'response' => is_string($response) ? substr($response, 0, 1000) : '非字符串响应',
            //     'type' => gettype($response),
            //     'response' => $response
            // ]);

            // 如果已经是数组，直接返回
            if (is_array($response)) {
                return $response['data'] ?? ($response ?? []);
            }

            throw new \UnexpectedValueException('从授权服务器收到无效响应类型：' . gettype($response));
        } catch (\Exception $e) {
            Log::error('获取用户资源信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'url' => $url
            ]);
            throw $e;
        }
    }
}
