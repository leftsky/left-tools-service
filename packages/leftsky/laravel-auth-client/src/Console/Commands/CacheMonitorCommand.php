<?php

namespace Leftsky\AuthClient\Console\Commands;

use Illuminate\Console\Command;
use Leftsky\AuthClient\Facades\CacheMonitor;

class CacheMonitorCommand extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'auth:cache-stats {--reset : 重置统计数据}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '显示Token缓存使用统计信息';

    /**
     * 执行命令
     *
     * @return int
     */
    public function handle()
    {
        $metrics = CacheMonitor::getMetrics();
        
        // 如果指定了重置选项，则重置统计数据
        if ($this->option('reset')) {
            $this->info('正在重置缓存统计数据...');
            // 这里需要在TokenCacheMonitor中添加reset方法
            CacheMonitor::reset();
            $this->info('缓存统计数据已重置');
            return 0;
        }
        
        // 显示统计信息
        $this->info('Token缓存使用统计:');
        $this->table(
            ['指标', '值'],
            [
                ['命中次数', $metrics['hits']],
                ['未命中次数', $metrics['misses']],
                ['总请求次数', $metrics['hits'] + $metrics['misses']],
                ['命中率', $metrics['hit_ratio'] . '%'],
            ]
        );
        
        return 0;
    }
} 