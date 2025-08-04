<?php

namespace Leftsky\AuthClient\Console\Commands;

use Illuminate\Console\Command;
use Leftsky\AuthClient\Facades\TokenCache;

class TokenCacheCommand extends Command
{
    protected $signature = 'auth:token-cache {action : Action to perform (clear|stats)}';
    protected $description = '管理Token缓存';
    
    public function handle()
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'clear':
                $this->clearCache();
                break;
            case 'stats':
                $this->showStats();
                break;
            default:
                $this->error("Unknown action: {$action}");
                return 1;
        }
        
        return 0;
    }
    
    protected function clearCache()
    {
        $this->info('Clearing token cache...');
        
        if (TokenCache::invalidateAll()) {
            $this->info('Token cache cleared successfully.');
        } else {
            $this->error('Failed to clear token cache.');
        }
    }
    
    protected function showStats()
    {
        $this->info('Token cache statistics:');
        
        // 这里应添加获取统计信息的逻辑
        // 例如已缓存的token数量、命中率等
        
        $this->info('Feature not implemented yet.');
    }
} 