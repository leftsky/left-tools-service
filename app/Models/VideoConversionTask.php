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
    const STATUS_PENDING = 0;      // 等待中
    const STATUS_PROCESSING = 1;   // 处理中
    const STATUS_COMPLETED = 2;    // 已完成
    const STATUS_FAILED = 3;       // 失败
    const STATUS_CANCELLED = 4;    // 已取消

    /**
     * 状态映射
     */
    const STATUS_MAP = [
        self::STATUS_PENDING => '等待中',
        self::STATUS_PROCESSING => '处理中',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_FAILED => '失败',
        self::STATUS_CANCELLED => '已取消',
    ];

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'user_id',
        'input_file_path',
        'output_file_path',
        'input_file_info',
        'output_file_info',
        'conversion_params',
        'status',
        'job_id',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * 属性转换
     */
    protected $casts = [
        'input_file_info' => 'array',
        'output_file_info' => 'array',
        'conversion_params' => 'array',
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
     * 保存前的处理
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->processFileInfo();
        });
    }

    /**
     * 处理文件信息，确保不超过512字符限制
     */
    protected function processFileInfo(): void
    {
        // 处理输入文件信息
        if ($this->input_file_info && is_array($this->input_file_info)) {
            $this->input_file_info = $this->truncateFileInfo($this->input_file_info, 'input');
        }

        // 处理输出文件信息
        if ($this->output_file_info && is_array($this->output_file_info)) {
            $this->output_file_info = $this->truncateFileInfo($this->output_file_info, 'output');
        }

        // 处理转换参数
        if ($this->conversion_params && is_array($this->conversion_params)) {
            $this->conversion_params = $this->truncateConversionParams($this->conversion_params);
        }
    }

    /**
     * 截断文件信息，确保不超过512字符
     */
    protected function truncateFileInfo(array $fileInfo, string $type): array
    {
        $jsonString = json_encode($fileInfo, JSON_UNESCAPED_UNICODE);
        
        if (strlen($jsonString) <= 512) {
            return $fileInfo;
        }

        // 如果超过512字符，简化文件名
        if (isset($fileInfo['filename'])) {
            $originalFilename = $fileInfo['filename'];
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $randomName = $this->generateRandomFilename($extension);
            
            $fileInfo['filename'] = $randomName;
            $fileInfo['original_filename'] = $originalFilename; // 保存原始文件名
            
            // 重新检查长度
            $newJsonString = json_encode($fileInfo, JSON_UNESCAPED_UNICODE);
            if (strlen($newJsonString) <= 512) {
                return $fileInfo;
            }
        }

        // 如果还是超过，保留核心信息
        $coreInfo = [
            'filename' => $fileInfo['filename'] ?? $this->generateRandomFilename('mp4'),
            'file_size' => $fileInfo['file_size'] ?? 0,
            'format' => $fileInfo['format'] ?? 'unknown',
            'duration' => $fileInfo['duration'] ?? null,
            'resolution' => $fileInfo['resolution'] ?? null,
        ];

        // 如果原始文件名被修改，保存它
        if (isset($fileInfo['original_filename'])) {
            $coreInfo['original_filename'] = $fileInfo['original_filename'];
        }

        return $coreInfo;
    }

    /**
     * 截断转换参数，确保不超过512字符
     */
    protected function truncateConversionParams(array $params): array
    {
        $jsonString = json_encode($params, JSON_UNESCAPED_UNICODE);
        
        if (strlen($jsonString) <= 512) {
            return $params;
        }

        // 如果超过512字符，保留核心参数
        $coreParams = [
            'target_format' => $params['target_format'] ?? 'mp4',
            'target_resolution' => $params['target_resolution'] ?? null,
            'target_framerate' => $params['target_framerate'] ?? null,
            'target_video_bitrate' => $params['target_video_bitrate'] ?? null,
            'target_audio_bitrate' => $params['target_audio_bitrate'] ?? null,
        ];

        return $coreParams;
    }

    /**
     * 生成随机文件名
     */
    protected function generateRandomFilename(string $extension): string
    {
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid()), 0, 8);
        return "file_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::STATUS_MAP[$this->status] ?? '未知状态';
    }

    /**
     * 获取输入文件大小
     */
    public function getInputFileSizeAttribute(): ?int
    {
        return $this->input_file_info['file_size'] ?? null;
    }

    /**
     * 获取输出文件大小
     */
    public function getOutputFileSizeAttribute(): ?int
    {
        return $this->output_file_info['file_size'] ?? null;
    }

    /**
     * 获取输入文件格式化的文件大小
     */
    public function getFormattedInputFileSizeAttribute(): string
    {
        $fileSize = $this->input_file_size;
        return $fileSize ? $this->formatFileSize($fileSize) : '未知';
    }

    /**
     * 获取输出文件格式化的文件大小
     */
    public function getFormattedOutputFileSizeAttribute(): string
    {
        $fileSize = $this->output_file_size;
        return $fileSize ? $this->formatFileSize($fileSize) : '未知';
    }

    /**
     * 获取输入文件时长
     */
    public function getInputDurationAttribute(): ?int
    {
        return $this->input_file_info['duration'] ?? null;
    }

    /**
     * 获取输出文件时长
     */
    public function getOutputDurationAttribute(): ?int
    {
        return $this->output_file_info['duration'] ?? null;
    }

    /**
     * 获取输入文件格式化的时长
     */
    public function getFormattedInputDurationAttribute(): string
    {
        $duration = $this->input_duration;
        return $duration ? $this->formatDuration($duration) : '未知';
    }

    /**
     * 获取输出文件格式化的时长
     */
    public function getFormattedOutputDurationAttribute(): string
    {
        $duration = $this->output_duration;
        return $duration ? $this->formatDuration($duration) : '未知';
    }

    /**
     * 获取输入文件原始文件名
     */
    public function getInputOriginalFilenameAttribute(): ?string
    {
        return $this->input_file_info['original_filename'] ?? $this->input_file_info['filename'] ?? null;
    }

    /**
     * 获取输出文件原始文件名
     */
    public function getOutputOriginalFilenameAttribute(): ?string
    {
        return $this->output_file_info['original_filename'] ?? $this->output_file_info['filename'] ?? null;
    }

    /**
     * 检查任务是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 检查任务是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 检查任务是否可取消
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * 开始处理任务
     */
    public function startProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * 完成任务
     */
    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
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
     * 取消任务
     */
    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * 格式化时长
     */
    protected function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        
        return sprintf('%02d:%02d', $minutes, $secs);
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
     * 作用域：最近的任务
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * 作用域：等待中的任务
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * 作用域：处理中的任务
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * 作用域：已完成的任务
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * 作用域：失败的任务
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
