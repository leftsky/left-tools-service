<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessStats extends Model
{
    protected $fillable = [
        'date',
        'url',
        'visit_count',
        'unique_visitors',
    ];

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联工具（如果URL对应特定工具）
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'url', 'name');
    }

    /**
     * 获取指定日期的统计
     */
    public static function getStatsByDate(string $date): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('date', $date)->orderBy('visit_count', 'desc')->get();
    }

    /**
     * 获取指定URL的统计
     */
    public static function getStatsByUrl(string $url, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('url', $url)
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();
    }

    /**
     * 更新或创建统计记录
     */
    public static function updateStats(string $date, string $url, int $visitCount, int $uniqueVisitors): static
    {
        return static::updateOrCreate(
            ['date' => $date, 'url' => $url],
            [
                'visit_count' => $visitCount,
                'unique_visitors' => $uniqueVisitors,
            ]
        );
    }
} 