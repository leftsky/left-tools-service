<?php

namespace Leftsky\AuthClient\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    /**
     * 验证JWT令牌
     *
     * @param string $token
     * @return array|null
     */
    public function verify($token)
    {
        try {
            // 优先使用配置中的公钥文本
            $publicKey = config('auth-client.api.jwt_public_key');
            
            // 如果配置中没有公钥文本，则尝试从文件读取
            if (empty($publicKey)) {
                $publicKeyPath = config('auth-client.api.jwt_public_key_path');
                if (!$publicKeyPath || !file_exists($publicKeyPath)) {
                    logger()->error('JWT验证失败: 未配置公钥');
                    return null;
                }
                $publicKey = file_get_contents($publicKeyPath);
            }
            
            // 验证JWT格式
            if (!$this->isValidPEM($publicKey)) {
                logger()->error('JWT验证失败: 无效的公钥格式');
                return null;
            }
            
            $decoded = JWT::decode($token, new Key($publicKey, config('auth-client.api.jwt_algorithm', 'RS256')));
            
            return [
                'valid' => true,
                'user' => (array) $decoded->user,  // 从令牌中提取user数据
                'scopes' => (array) $decoded->scopes,  // 从令牌中提取scopes数据
                'expires_at' => date('Y-m-d H:i:s', $decoded->exp),
            ];
        } catch (Exception $e) {
            logger()->error('JWT验证失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 检查公钥是否为有效的PEM格式
     *
     * @param string $pemString
     * @return bool
     */
    protected function isValidPEM($pemString)
    {
        return strpos($pemString, '-----BEGIN PUBLIC KEY-----') !== false &&
               strpos($pemString, '-----END PUBLIC KEY-----') !== false;
    }
}