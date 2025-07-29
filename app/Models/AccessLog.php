<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'url',
        'referer',
        'session_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取访问统计
     */
    public static function getStats(string $date, string $url = null): array
    {
        $query = static::whereDate('created_at', $date);
        
        if ($url) {
            $query->where('url', $url);
        }

        $totalVisits = $query->count();
        $uniqueVisitors = $query->distinct('ip_address')->count();

        return [
            'visit_count' => $totalVisits,
            'unique_visitors' => $uniqueVisitors,
        ];
    }
} 