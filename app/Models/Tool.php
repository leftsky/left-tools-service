<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_enabled',
        'sort_weight',
        'hotness',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_weight' => 'integer',
        'hotness' => 'integer',
    ];

    /**
     * 获取工具的使用记录
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(ToolUsageLog::class);
    }

    /**
     * 获取工具的使用统计
     */
    public function usageStats(): HasMany
    {
        return $this->hasMany(ToolUsageStat::class);
    }

    /**
     * 获取收藏此工具的用户
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(UserToolFavorite::class);
    }

    /**
     * 获取使用此工具的历史记录
     */
    public function userHistory(): HasMany
    {
        return $this->hasMany(UserToolHistory::class);
    }

    /**
     * 获取今日使用次数
     */
    public function getTodayUsageCountAttribute(): int
    {
        return $this->usageStats()
            ->where('date', now()->toDateString())
            ->value('usage_count') ?? 0;
    }

    /**
     * 获取今日使用人数
     */
    public function getTodayUserCountAttribute(): int
    {
        return $this->usageStats()
            ->where('date', now()->toDateString())
            ->value('user_count') ?? 0;
    }

    /**
     * 获取总使用次数
     */
    public function getTotalUsageCountAttribute(): int
    {
        return $this->usageStats()->sum('usage_count');
    }

    /**
     * 获取总使用人数
     */
    public function getTotalUserCountAttribute(): int
    {
        return $this->usageStats()->sum('user_count');
    }
} 