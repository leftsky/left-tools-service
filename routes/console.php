<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Models\AccessLog;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 清理 3 天前的数据
Schedule::command('telescope:prune --hours=72')->at("01:30");

// 每天一点半清理访客日志
Schedule::call(function () {
    $totalCount = AccessLog::count();
    
    // 只有当记录数大于10万时才进行清理
    if ($totalCount > 100000) {
        // 保留最近的10万条记录，删除多余的旧记录
        $recordsToKeep = 100000;
        $recordsToDelete = $totalCount - $recordsToKeep;
        
        if ($recordsToDelete > 0) {
            // 按时间排序，删除最旧的记录
            $deletedCount = AccessLog::orderBy('created_at', 'asc')
                ->limit($recordsToDelete)
                ->delete();
                
            Log::info('访问日志清理完成', [
                'total_before' => $totalCount,
                'deleted_count' => $deletedCount,
                'total_after' => $totalCount - $deletedCount,
                'cleaned_at' => now()->toDateTimeString()
            ]);
        }
    }
})->dailyAt('01:30')->name('clean-access-logs');
