<?php

namespace Leftsky\AuthClient\Services;

class TokenCacheMonitor
{
    protected $tokenCache;
    protected $metrics = [];
    
    public function __construct(TokenCacheService $tokenCache)
    {
        $this->tokenCache = $tokenCache;
        $this->initMetrics();
    }
    
    protected function initMetrics()
    {
        $this->metrics = [
            'hits' => 0,
            'misses' => 0,
            'hit_ratio' => 0,
        ];
    }
    
    public function recordHit()
    {
        $this->metrics['hits']++;
        $this->updateRatio();
    }
    
    public function recordMiss()
    {
        $this->metrics['misses']++;
        $this->updateRatio();
    }
    
    protected function updateRatio()
    {
        $total = $this->metrics['hits'] + $this->metrics['misses'];
        $this->metrics['hit_ratio'] = $total > 0 ? round(($this->metrics['hits'] / $total) * 100) : 0;
    }
    
    public function getMetrics()
    {
        return $this->metrics;
    }
    
    /**
     * 重置所有指标
     *
     * @return void
     */
    public function reset()
    {
        $this->initMetrics();
    }
} 