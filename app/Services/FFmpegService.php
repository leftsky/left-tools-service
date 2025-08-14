<?php

namespace App\Services;

use App\Jobs\FFmpegConversionJob;
use App\Models\FileConversionTask;
use Illuminate\Support\Facades\Log;
use Exception;

class FFmpegService
{
    /**
     * 支持的输出格式
     */
    const OUTPUT_FORMATS = [
        ['value' => 'mp4', 'label' => 'MP4 (H.264/H.265)'],
        ['value' => 'avi', 'label' => 'AVI (Xvid)'],
        ['value' => 'mov', 'label' => 'MOV (QuickTime)'],
        ['value' => 'mkv', 'label' => 'MKV (Matroska)'],
        ['value' => 'wmv', 'label' => 'WMV (Windows Media)'],
        ['value' => 'flv', 'label' => 'FLV (Flash Video)'],
        ['value' => 'webm', 'label' => 'WebM (VP8/VP9/AV1)'],
        ['value' => 'm4v', 'label' => 'M4V (iTunes)'],
        ['value' => '3gp', 'label' => '3GP (Mobile)'],
        ['value' => 'ogv', 'label' => 'OGV (Ogg Video)'],
        ['value' => 'ts', 'label' => 'TS (Transport Stream)'],
        ['value' => 'mts', 'label' => 'MTS (AVCHD)'],
        ['value' => 'asf', 'label' => 'ASF (Advanced Systems)'],
        ['value' => 'vob', 'label' => 'VOB (DVD Video)'],
        ['value' => 'mpg', 'label' => 'MPG (MPEG-1/2)'],
        ['value' => 'mpeg', 'label' => 'MPEG (MPEG-1/2)'],
        ['value' => 'divx', 'label' => 'DIVX (DivX)'],
        ['value' => 'xvid', 'label' => 'XVID (Xvid)'],
        ['value' => 'swf', 'label' => 'SWF (Flash)'],
        ['value' => 'f4v', 'label' => 'F4V (Flash Video)'],
        ['value' => 'm2ts', 'label' => 'M2TS (Blu-ray)'],
        ['value' => 'mxf', 'label' => 'MXF (Material Exchange)'],
        ['value' => 'gif', 'label' => 'GIF (Animated)'],
        ['value' => 'apng', 'label' => 'APNG (Animated PNG)'],
        ['value' => 'webp', 'label' => 'WebP (Web Picture)'],
        ['value' => 'avif', 'label' => 'AVIF (AV1 Image)'],
        ['value' => 'heic', 'label' => 'HEIC (HEIF)'],
        ['value' => 'heif', 'label' => 'HEIF (High Efficiency)']
    ];

    /**
     * 视频质量选项
     */
    const VIDEO_QUALITY_OPTIONS = [
        ['value' => 'high', 'label' => '高质量'],
        ['value' => 'medium', 'label' => '中等质量'],
        ['value' => 'low', 'label' => '低质量']
    ];

    /**
     * 分辨率选项
     */
    const RESOLUTION_OPTIONS = [
        ['value' => 'original', 'label' => '保持原分辨率'],
        ['value' => '4k', 'label' => '4K (3840x2160)'],
        ['value' => '1080p', 'label' => '1080p (1920x1080)'],
        ['value' => '720p', 'label' => '720p (1280x720)'],
        ['value' => '480p', 'label' => '480p (854x480)']
    ];

    /**
     * 帧率选项
     */
    const FRAMERATE_OPTIONS = [
        ['value' => 'original', 'label' => '保持原帧率'],
        ['value' => '60', 'label' => '60 FPS'],
        ['value' => '30', 'label' => '30 FPS'],
        ['value' => '25', 'label' => '25 FPS'],
        ['value' => '24', 'label' => '24 FPS']
    ];

    /**
     * 支持的文件类型
     */
    const SUPPORTED_FILE_TYPES = [
        'video/mp4',
        'video/avi',
        'video/quicktime',
        'video/x-matroska',
        'video/x-ms-wmv',
        'video/x-flv',
        'video/webm',
        'video/x-msvideo',
        'video/3gpp',
        'video/ogg',
        'video/mpeg',
        'video/x-m4v'
    ];

    /**
     * 支持的文件扩展名
     */
    const SUPPORTED_EXTENSIONS = [
        'mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm',
        'm4v', '3gp', 'ogv', 'ts', 'mts', 'rm', 'rmvb',
        'asf', 'vob', 'mpg', 'mpeg', 'divx', 'xvid',
        'swf', 'f4v', 'm2ts', 'mxf', 'gif', 'apng',
        'webp', 'avif', 'heic', 'heif'
    ];

    /**
     * 文件大小限制（100MB）
     */
    const MAX_FILE_SIZE = 100 * 1024 * 1024;

    /**
     * 创建FFmpeg转换任务
     *
     * @param array $data 任务数据
     * @return FileConversionTask
     * @throws Exception
     */
    public function createConversionTask(array $data): FileConversionTask
    {
        try {
            // 验证输出格式
            $outputFormats = array_column(self::OUTPUT_FORMATS, 'value');
            if (!in_array($data['output_format'], $outputFormats)) {
                throw new Exception("不支持的输出格式: {$data['output_format']}");
            }

            // 验证输入格式
            if (!in_array($data['input_format'], self::SUPPORTED_EXTENSIONS)) {
                throw new Exception("不支持的输入格式: {$data['input_format']}");
            }

            // 创建任务记录
            $task = FileConversionTask::create([
                'user_id' => $data['user_id'] ?? null,
                'conversion_engine' => 'ffmpeg',
                'input_method' => $data['input_method'],
                'input_file' => $data['input_file'],
                'filename' => $data['filename'],
                'input_format' => $data['input_format'],
                'file_size' => $data['file_size'] ?? null,
                'output_format' => $data['output_format'],
                'conversion_options' => $data['conversion_options'] ?? [],
                'status' => FileConversionTask::STATUS_WAIT,
                'step_percent' => 0,
                'callback_url' => $data['callback_url'] ?? null,
                'tag' => $data['tag'] ?? null,
            ]);

            Log::info('FFmpeg转换任务已创建', [
                'task_id' => $task->id,
                'input_format' => $data['input_format'],
                'output_format' => $data['output_format']
            ]);

            return $task;

        } catch (Exception $e) {
            Log::error('创建FFmpeg转换任务失败', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 调度FFmpeg转换任务
     *
     * @param FileConversionTask $task
     * @return void
     */
    public function dispatchConversionJob(FileConversionTask $task): void
    {
        try {
            // 调度异步任务
            FFmpegConversionJob::dispatch($task)->onQueue('local-conversion');

            Log::info('FFmpeg转换任务已调度', [
                'task_id' => $task->id
            ]);

        } catch (Exception $e) {
            Log::error('调度FFmpeg转换任务失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);

            // 更新任务状态为失败
            $task->update([
                'status' => FileConversionTask::STATUS_FAILED,
                'error_message' => '任务调度失败: ' . $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 创建并调度转换任务
     *
     * @param array $data 任务数据
     * @return FileConversionTask
     * @throws Exception
     */
    public function createAndDispatchTask(array $data): FileConversionTask
    {
        $task = $this->createConversionTask($data);
        $this->dispatchConversionJob($task);
        return $task;
    }

    /**
     * 获取支持的配置选项
     *
     * @return array
     */
    public function getSupportedConfigs(): array
    {
        return [
            'outputFormats' => self::OUTPUT_FORMATS,
            'videoQualityOptions' => self::VIDEO_QUALITY_OPTIONS,
            'resolutionOptions' => self::RESOLUTION_OPTIONS,
            'framerateOptions' => self::FRAMERATE_OPTIONS,
            'supportedFileTypes' => self::SUPPORTED_FILE_TYPES,
            'supportedExtensions' => self::SUPPORTED_EXTENSIONS,
            'maxFileSize' => self::MAX_FILE_SIZE
        ];
    }

    /**
     * 验证转换选项
     *
     * @param array $options
     * @return bool
     */
    public function validateConversionOptions(array $options): bool
    {
        // 验证视频质量
        if (isset($options['videoQuality'])) {
            $validQualities = array_column(self::VIDEO_QUALITY_OPTIONS, 'value');
            if (!in_array($options['videoQuality'], $validQualities)) {
                return false;
            }
        }

        // 验证分辨率
        if (isset($options['resolution'])) {
            $validResolutions = array_column(self::RESOLUTION_OPTIONS, 'value');
            if (!in_array($options['resolution'], $validResolutions)) {
                return false;
            }
        }

        // 验证帧率
        if (isset($options['framerate'])) {
            $validFramerates = array_column(self::FRAMERATE_OPTIONS, 'value');
            if (!in_array($options['framerate'], $validFramerates)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查FFmpeg是否可用
     *
     * @return bool
     */
    public function isFFmpegAvailable(): bool
    {
        try {
            $output = [];
            $returnCode = 0;
            exec('ffmpeg -version 2>&1', $output, $returnCode);
            return $returnCode === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取FFmpeg版本信息
     *
     * @return string|null
     */
    public function getFFmpegVersion(): ?string
    {
        try {
            $output = [];
            exec('ffmpeg -version 2>&1', $output);
            if (!empty($output)) {
                // 解析版本信息
                foreach ($output as $line) {
                    if (preg_match('/ffmpeg version ([^\s]+)/', $line, $matches)) {
                        return $matches[1];
                    }
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 获取文件信息（使用FFprobe）
     *
     * @param string $filePath
     * @return array|null
     */
    public function getFileInfo(string $filePath): ?array
    {
        try {
            $command = [
                'ffprobe',
                '-v', 'quiet',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                escapeshellarg($filePath)
            ];

            $commandStr = implode(' ', $command);
            $output = shell_exec($commandStr);

            if ($output) {
                $data = json_decode($output, true);
                if ($data && isset($data['format'])) {
                    return $this->parseFileInfo($data);
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('获取文件信息失败', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 解析文件信息
     *
     * @param array $data
     * @return array
     */
    protected function parseFileInfo(array $data): array
    {
        $format = $data['format'] ?? [];
        $streams = $data['streams'] ?? [];

        $videoStream = null;
        $audioStream = null;

        // 查找视频和音频流
        foreach ($streams as $stream) {
            if ($stream['codec_type'] === 'video' && !$videoStream) {
                $videoStream = $stream;
            } elseif ($stream['codec_type'] === 'audio' && !$audioStream) {
                $audioStream = $stream;
            }
        }

        $result = [
            'filename' => basename($format['filename'] ?? ''),
            'size' => (int)($format['size'] ?? 0),
            'duration' => (float)($format['duration'] ?? 0),
            'format_name' => $format['format_name'] ?? '',
            'bit_rate' => (int)($format['bit_rate'] ?? 0),
            'streams' => count($streams)
        ];

        // 视频信息
        if ($videoStream) {
            $result['video'] = [
                'codec' => $videoStream['codec_name'] ?? '',
                'width' => (int)($videoStream['width'] ?? 0),
                'height' => (int)($videoStream['height'] ?? 0),
                'framerate' => $this->parseFrameRate($videoStream['r_frame_rate'] ?? ''),
                'bit_rate' => (int)($videoStream['bit_rate'] ?? 0)
            ];
        }

        // 音频信息
        if ($audioStream) {
            $result['audio'] = [
                'codec' => $audioStream['codec_name'] ?? '',
                'channels' => (int)($audioStream['channels'] ?? 0),
                'sample_rate' => (int)($audioStream['sample_rate'] ?? 0),
                'bit_rate' => (int)($audioStream['bit_rate'] ?? 0)
            ];
        }

        return $result;
    }

    /**
     * 解析帧率
     *
     * @param string $frameRate
     * @return float
     */
    protected function parseFrameRate(string $frameRate): float
    {
        if (strpos($frameRate, '/') !== false) {
            $parts = explode('/', $frameRate);
            if (count($parts) === 2 && $parts[1] != 0) {
                return round((float)$parts[0] / (float)$parts[1], 2);
            }
        }
        return (float)$frameRate;
    }
}
