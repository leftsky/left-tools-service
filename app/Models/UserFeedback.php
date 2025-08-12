<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedback extends Model
{
    use HasFactory;

    protected $table = 'user_feedback';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'contact_phone',
        'type',
        'title',
        'content',
        'attachments',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attachments' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 反馈类型常量
     */
    public const TYPE_BUG = 1;
    public const TYPE_FEATURE = 2;
    public const TYPE_IMPROVEMENT = 3;
    public const TYPE_OTHER = 4;

    /**
     * 状态常量
     */
    public const STATUS_PENDING = 1;
    public const STATUS_PROCESSING = 2;
    public const STATUS_RESOLVED = 3;
    public const STATUS_CLOSED = 4;

    /**
     * 获取反馈类型文本
     *
     * @return string
     */
    public function getTypeTextAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_BUG => '错误报告',
            self::TYPE_FEATURE => '功能建议',
            self::TYPE_IMPROVEMENT => '改进建议',
            self::TYPE_OTHER => '其他',
            default => '未知',
        };
    }

    /**
     * 获取状态文本
     *
     * @return string
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_RESOLVED => '已解决',
            self::STATUS_CLOSED => '已关闭',
            default => '未知',
        };
    }

    /**
     * 获取反馈类型选项
     *
     * @return array
     */
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_BUG => '错误报告',
            self::TYPE_FEATURE => '功能建议',
            self::TYPE_IMPROVEMENT => '改进建议',
            self::TYPE_OTHER => '其他',
        ];
    }

    /**
     * 获取状态选项
     *
     * @return array
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_RESOLVED => '已解决',
            self::STATUS_CLOSED => '已关闭',
        ];
    }

    /**
     * 关联用户
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}