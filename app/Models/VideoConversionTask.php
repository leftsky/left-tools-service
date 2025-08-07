<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoConversionTask extends Model
{
    use HasFactory;

    /**
     * 任务状态常量
     */
    const STATUS_WAIT = 0;         // 等待中
    const STATUS_CONVERT = 1;      // 转换中
    const STATUS_FINISH = 2;       // 已完成
    const STATUS_FAILED = 3;       // 失败

    /**
     * 状态映射
     */
    const STATUS_MAP = [
        self::STATUS_WAIT => '等待中',
        self::STATUS_CONVERT => '转换中',
        self::STATUS_FINISH => '已完成',
        self::STATUS_FAILED => '失败',
    ];

    /**
     * 输入方式常量
     */
    const INPUT_METHOD_URL = 'url';
    const INPUT_METHOD_RAW = 'raw';
    const INPUT_METHOD_BASE64 = 'base64';
    const INPUT_METHOD_UPLOAD = 'upload';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'user_id',
        'convertio_id',
        'input_method',
        'input_file',
        'filename',
        'file_size',
        'output_format',
        'conversion_options',
        'status',
        'step_percent',
        'minutes_used',
        'output_url',
        'output_size',
        'callback_url',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * 属性转换
     */
    protected $casts = [
        'conversion_options' => 'array',
        'file_size' => 'integer',
        'output_size' => 'integer',
        'step_percent' => 'integer',
        'minutes_used' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => 'integer',
    ];

    /**
     * 用户关联
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::STATUS_MAP[$this->status] ?? '未知状态';
    }

    /**
     * 获取格式化的文件大小
     */
    public function getFormattedFileSizeAttribute(): string
    {
        return $this->file_size ? $this->formatFileSize($this->file_size) : '未知';
    }

    /**
     * 获取格式化的输出文件大小
     */
    public function getFormattedOutputSizeAttribute(): string
    {
        return $this->output_size ? $this->formatFileSize($this->output_size) : '未知';
    }

    /**
     * 检查任务是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_FINISH;
    }

    /**
     * 检查任务是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 检查任务是否正在处理
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_CONVERT;
    }

    /**
     * 检查任务是否等待中
     */
    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAIT;
    }

    /**
     * 检查任务是否可取消
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_WAIT, self::STATUS_CONVERT]);
    }

    /**
     * 开始处理任务
     */
    public function startProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_CONVERT,
            'started_at' => now(),
            'step_percent' => 0,
        ]);
    }

    /**
     * 更新转换进度
     */
    public function updateProgress(int $stepPercent): void
    {
        $this->update([
            'step_percent' => $stepPercent,
        ]);
    }

    /**
     * 完成任务
     */
    public function complete(string $outputUrl, int $outputSize, int $minutesUsed): void
    {
        $this->update([
            'status' => self::STATUS_FINISH,
            'output_url' => $outputUrl,
            'output_size' => $outputSize,
            'minutes_used' => $minutesUsed,
            'step_percent' => 100,
            'completed_at' => now(),
        ]);
    }

    /**
     * 标记任务失败
     */
    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * 设置Convertio ID
     */
    public function setConvertioId(string $convertioId): void
    {
        $this->update(['convertio_id' => $convertioId]);
    }

    /**
     * 获取OCR设置
     */
    public function getOcrSettings(): ?array
    {
        return $this->conversion_options['ocr_settings'] ?? null;
    }

    /**
     * 检查是否启用OCR
     */
    public function isOcrEnabled(): bool
    {
        return $this->conversion_options['ocr_enabled'] ?? false;
    }

    /**
     * 获取回调URL
     */
    public function getCallbackUrl(): ?string
    {
        return $this->callback_url;
    }

    /**
     * 格式化文件大小
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 作用域：按状态筛选
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 作用域：按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 作用域：按Convertio ID筛选
     */
    public function scopeByConvertioId($query, string $convertioId)
    {
        return $query->where('convertio_id', $convertioId);
    }

    /**
     * 作用域：按输入方式筛选
     */
    public function scopeByInputMethod($query, string $inputMethod)
    {
        return $query->where('input_method', $inputMethod);
    }

    /**
     * 作用域：按输出格式筛选
     */
    public function scopeByOutputFormat($query, string $outputFormat)
    {
        return $query->where('output_format', $outputFormat);
    }

    /**
     * 作用域：最近的任务
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * 作用域：等待中的任务
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAIT);
    }

    /**
     * 作用域：转换中的任务
     */
    public function scopeConverting($query)
    {
        return $query->where('status', self::STATUS_CONVERT);
    }

    /**
     * 作用域：已完成的任务
     */
    public function scopeFinished($query)
    {
        return $query->where('status', self::STATUS_FINISH);
    }

    /**
     * 作用域：失败的任务
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * 作用域：有Convertio ID的任务
     */
    public function scopeWithConvertioId($query)
    {
        return $query->whereNotNull('convertio_id');
    }

    /**
     * 作用域：没有Convertio ID的任务
     */
    public function scopeWithoutConvertioId($query)
    {
        return $query->whereNull('convertio_id');
    }

    /**
     * 作用域：启用OCR的任务
     */
    public function scopeWithOcr($query)
    {
        return $query->whereJsonContains('conversion_options->ocr_enabled', true);
    }

    /**
     * 作用域：有回调URL的任务
     */
    public function scopeWithCallback($query)
    {
        return $query->whereNotNull('callback_url');
    }
}
