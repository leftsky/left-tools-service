<?php

namespace Leftsky\AuthClient\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Leftsky\AuthClient\Services\TokenCacheMonitor;
use Leftsky\AuthClient\Services\TokenCacheService;
use Leftsky\AuthClient\Facades\CacheMonitor;
use Leftsky\AuthClient\AuthClientServiceProvider;

class TokenCacheMonitorTest extends TestCase
{
    /**
     * 获取包服务提供者
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            AuthClientServiceProvider::class,
        ];
    }

    /**
     * 获取包别名
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'CacheMonitor' => CacheMonitor::class,
        ];
    }

    /** @test */
    public function it_can_record_cache_hits()
    {
        // 创建监控实例
        $tokenCache = $this->createMock(TokenCacheService::class);
        $monitor = new TokenCacheMonitor($tokenCache);

        // 初始状态
        $initialMetrics = $monitor->getMetrics();
        $this->assertEquals(0, $initialMetrics['hits']);

        // 记录命中
        $monitor->recordHit();
        $metrics = $monitor->getMetrics();
        
        // 验证结果
        $this->assertEquals(1, $metrics['hits']);
        $this->assertEquals(0, $metrics['misses']);
        $this->assertEquals(100, $metrics['hit_ratio']);
    }

    /** @test */
    public function it_can_record_cache_misses()
    {
        // 创建监控实例
        $tokenCache = $this->createMock(TokenCacheService::class);
        $monitor = new TokenCacheMonitor($tokenCache);

        // 记录未命中
        $monitor->recordMiss();
        $metrics = $monitor->getMetrics();
        
        // 验证结果
        $this->assertEquals(0, $metrics['hits']);
        $this->assertEquals(1, $metrics['misses']);
        $this->assertEquals(0, $metrics['hit_ratio']);
    }

    /** @test */
    public function it_calculates_hit_ratio_correctly()
    {
        // 创建监控实例
        $tokenCache = $this->createMock(TokenCacheService::class);
        $monitor = new TokenCacheMonitor($tokenCache);

        // 记录3次命中和1次未命中
        $monitor->recordHit();
        $monitor->recordHit();
        $monitor->recordHit();
        $monitor->recordMiss();
        
        $metrics = $monitor->getMetrics();
        
        // 验证结果 - 命中率应为75%
        $this->assertEquals(3, $metrics['hits']);
        $this->assertEquals(1, $metrics['misses']);
        $this->assertEquals(75, $metrics['hit_ratio']);
    }

    /** @test */
    public function facade_works_correctly()
    {
        // 使用门面记录命中
        CacheMonitor::recordHit();
        CacheMonitor::recordHit();
        
        // 获取指标
        $metrics = CacheMonitor::getMetrics();
        
        // 验证结果
        $this->assertEquals(2, $metrics['hits']);
        $this->assertEquals(0, $metrics['misses']);
        $this->assertEquals(100, $metrics['hit_ratio']);
    }
} 