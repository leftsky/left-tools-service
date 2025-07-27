<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolUsageStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_id',
        'date',
        'usage_count',
        'user_count',
    ];

    protected $casts = [
        'date' => 'date',
        'usage_count' => 'integer',
        'user_count' => 'integer',
    ];

    /**
     * 获取关联的工具
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
} 