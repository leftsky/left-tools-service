<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserToolFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tool_id',
        'weight',
    ];

    protected $casts = [
        'weight' => 'integer',
    ];

    /**
     * 获取关联的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取关联的工具
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
} 