<?php

namespace Leftsky\AuthClient\OAuth2;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class ResourceOwner implements ResourceOwnerInterface
{
    /**
     * 用户数据
     *
     * @var array
     */
    protected $response;

    /**
     * 创建新的资源所有者
     *
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * 返回用户ID
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->response['id'] ?? null;
    }

    /**
     * 返回所有用户数据
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
} 