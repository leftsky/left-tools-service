<?php

namespace Leftsky\AuthClient\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;

class TokenCacheService
{
    /**
     * 缓存是否启用
     *
     * @var bool
     */
    protected $cacheEnabled;
    
    /**
     * 缓存存储名称
     * 
     * @var string|null
     */
    protected $cacheStore;
    
    /**
     * 缓存标识前缀
     * 
     * @var string
     */
    protected $cachePrefix;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->cacheEnabled = config('auth-client.cache.enabled', true);
        $this->cacheStore = config('auth-client.cache.store', null);
        $this->cachePrefix = config('auth-client.cache.prefix', 'auth_');
    }
    
    /**
     * 从缓存获取Token数据
     *
     * @param string $token Bearer令牌
     * @return array|null 缓存的Token数据或null
     */
    public function get($token)
    {
        if (!$this->cacheEnabled) {
            return null;
        }
        
        $key = $this->getCacheKey($token);
        $result = $this->getCache()->get($key);
        
        // 记录缓存命中或未命中
        if ($result !== null) {
            $this->recordCacheHit();
        } else {
            $this->recordCacheMiss();
        }
        
        return $result;
    }
    
    /**
     * 将Token数据保存到缓存
     *
     * @param string $token Bearer令牌
     * @param array $data Token数据
     * @param int|null $minutes 缓存时间（分钟）
     * @return int 实际缓存分钟数
     */
    public function put($token, $data, $minutes = null)
    {
        if (!$this->cacheEnabled) {
            return 0;
        }
        
        $key = $this->getCacheKey($token);
        
        if ($minutes === null) {
            $minutes = config('auth-client.cache.ttl', 60);
            
            // 如果Token有过期时间，根据过期时间设置缓存
            if (isset($data['expires_at'])) {
                $expiresAt = \Carbon\Carbon::parse($data['expires_at']);
                $minutes = min(
                    $minutes,
                    max(1, $expiresAt->diffInMinutes(now()) - 5)
                );
            }
        }
        
        $this->getCache()->put($key, $data, now()->addMinutes($minutes));
        $this->addToTokenIndex($token);
        
        return $minutes;
    }
    
    /**
     * 使指定Token的缓存失效
     *
     * @param string $token Bearer令牌
     * @return bool 是否成功
     */
    public function invalidate($token)
    {
        if (!$this->cacheEnabled) {
            return true;
        }
        
        $key = $this->getCacheKey($token);
        $result = $this->getCache()->forget($key);
        $this->removeFromTokenIndex($token);
        
        return $result;
    }
    
    /**
     * 使所有已缓存的Token失效
     *
     * @return bool 是否成功
     */
    public function invalidateAll()
    {
        if (!$this->cacheEnabled) {
            return true;
        }
        
        $tokens = $this->getTokenIndex();
        
        foreach ($tokens as $token) {
            $this->invalidate($token);
        }
        
        return $this->clearTokenIndex();
    }
    
    /**
     * 获取缓存的Key
     *
     * @param string $token
     * @return string
     */
    private function getCacheKey($token)
    {
        return $this->cachePrefix . 'token_' . md5($token);
    }
    
    /**
     * 获取Token索引的Key
     *
     * @return string
     */
    private function getTokenIndexKey()
    {
        return $this->cachePrefix . 'token_index';
    }
    
    /**
     * 将Token添加到索引
     *
     * @param string $token
     * @return bool
     */
    private function addToTokenIndex($token)
    {
        if (!$this->cacheEnabled) {
            return true;
        }
        
        $tokenHash = md5($token);
        $key = $this->getTokenIndexKey();
        
        $indexData = $this->getCache()->get($key, []);
        if (!in_array($tokenHash, $indexData)) {
            $indexData[] = $tokenHash;
            $this->getCache()->put($key, $indexData, now()->addDays(7));
            return true;
        }
        
        return false;
    }
    
    /**
     * 从索引中移除Token
     *
     * @param string $token
     * @return bool
     */
    private function removeFromTokenIndex($token)
    {
        if (!$this->cacheEnabled) {
            return true;
        }
        
        $tokenHash = md5($token);
        $key = $this->getTokenIndexKey();
        
        $indexData = $this->getCache()->get($key, []);
        $newIndexData = array_filter($indexData, function($item) use ($tokenHash) {
            return $item !== $tokenHash;
        });
        
        if (count($newIndexData) !== count($indexData)) {
            $this->getCache()->put($key, $newIndexData, now()->addDays(7));
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取Token索引
     *
     * @return array
     */
    private function getTokenIndex()
    {
        if (!$this->cacheEnabled) {
            return [];
        }
        
        $key = $this->getTokenIndexKey();
        return $this->getCache()->get($key, []);
    }
    
    /**
     * 清除Token索引
     *
     * @return bool
     */
    private function clearTokenIndex()
    {
        if (!$this->cacheEnabled) {
            return true;
        }
        
        $key = $this->getTokenIndexKey();
        return $this->getCache()->forget($key);
    }
    
    /**
     * 获取缓存实例
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    private function getCache()
    {
        return $this->cacheStore ? Cache::store($this->cacheStore) : Cache::store();
    }
    
    /**
     * 记录缓存命中
     *
     * @return void
     */
    private function recordCacheHit()
    {
        try {
            if (App::has('auth-client.cache-monitor')) {
                App::make('auth-client.cache-monitor')->recordHit();
            }
        } catch (\Exception $e) {
            // 忽略监控错误，不影响主要功能
        }
    }
    
    /**
     * 记录缓存未命中
     *
     * @return void
     */
    private function recordCacheMiss()
    {
        try {
            if (App::has('auth-client.cache-monitor')) {
                App::make('auth-client.cache-monitor')->recordMiss();
            }
        } catch (\Exception $e) {
            // 忽略监控错误，不影响主要功能
        }
    }
} 