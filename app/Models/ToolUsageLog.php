<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_id',
        'user_id',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /**
     * 获取关联的工具
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * 获取关联的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 记录工具使用
     *
     * @param string $toolName 工具名称
     * @param int|null $userId 用户ID（可为空）
     * @param string|null $usedAt 使用时间（默认为当前时间）
     * @return ToolUsageLog|null
     */
    public static function recordUsage(string $toolName, ?int $userId = null, ?string $usedAt = null): ?ToolUsageLog
    {
        // 根据工具名称查找工具ID
        $tool = \App\Models\Tool::where('name', $toolName)->first();
        
        if (!$tool) {
            \Illuminate\Support\Facades\Log::warning('工具不存在，无法记录使用', [
                'tool_name' => $toolName,
                'user_id' => $userId,
            ]);
            return null;
        }

        return self::create([
            'tool_id' => $tool->id,
            'user_id' => $userId,
            'used_at' => $usedAt ?? now(),
        ]);
    }
}
