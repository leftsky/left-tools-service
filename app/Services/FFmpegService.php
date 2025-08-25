<?php

namespace App\Services;

use App\Models\FileConversionTask;
use Exception;
use Illuminate\Support\Facades\Log;

class FFmpegService extends ConversionServiceBase
{
    /**
     * 文件信息存储
     */
    private ?array $fileInfo = null;
    public static string $serviceName = 'ffmpeg';

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
     * 文件大小限制（100MB）
     */
    const MAX_FILE_SIZE = 100 * 1024 * 1024;

    /**
     * 编码器映射配置
     */
    const ENCODER_MAPPING = [
        'webm' => [
            'video' => ['libvpx', 'libvpx-vp9', 'libaom-av1'],
            'audio' => ['libvorbis', 'libopus']
        ],
        'mp4' => [
            'video' => ['libx264', 'libx265', 'libaom-av1'],
            'audio' => ['aac', 'libmp3lame', 'libopus']
        ],
        'avi' => [
            'video' => ['libx264', 'libxvid', 'mpeg4'],
            'audio' => ['aac', 'libmp3lame', 'ac3']
        ],
        'mkv' => [
            'video' => ['libx264', 'libx265', 'libvpx-vp9'],
            'audio' => ['aac', 'libmp3lame', 'libopus']
        ],
        'mpg' => [
            'video' => ['libx264', 'mpeg2video', 'mpeg4'],
            'audio' => ['libmp3lame', 'mp2', 'ac3']
        ],
        'mpeg' => [
            'video' => ['libx264', 'mpeg2video', 'mpeg4'],
            'audio' => ['libmp3lame', 'mp2', 'ac3']
        ],
        'mov' => [
            'video' => ['libx264', 'libx265', 'libaom-av1'],
            'audio' => ['aac', 'libmp3lame', 'libopus']
        ],
        'mp3' => [
            'video' => [],
            'audio' => ['libmp3lame', 'libshine']
        ],
        'aac' => [
            'video' => [],
            'audio' => ['aac']
        ],
        'ogg' => [
            'video' => [],
            'audio' => ['libvorbis']
        ],
        'opus' => [
            'video' => [],
            'audio' => ['libopus']
        ],
        'flac' => [
            'video' => [],
            'audio' => ['flac']
        ],
        'wav' => [
            'video' => [],
            'audio' => ['pcm_s16le']
        ],
        'm4a' => [
            'video' => [],
            'audio' => ['aac', 'libmp3lame']
        ],
        'gif' => [
            'video' => ['gif'],
            'audio' => []
        ],
        'png' => [
            'video' => ['png'],
            'audio' => []
        ],
        'jpg' => [
            'video' => ['mjpeg', 'jpeg'],
            'audio' => []
        ],
        'jpeg' => [
            'video' => ['mjpeg', 'jpeg'],
            'audio' => []
        ],
        'wmv' => [
            'video' => ['wmv2'],
            'audio' => ['wmav2']
        ],
        'flv' => [
            'video' => ['libx264', 'flv'],
            'audio' => ['aac', 'libmp3lame']
        ],
        'ts' => [
            'video' => ['mpeg2video', 'libx264'],
            'audio' => ['aac', 'mp2', 'ac3']
        ],
        'mts' => [
            'video' => ['mpeg2video', 'libx264'],
            'audio' => ['aac', 'mp2', 'ac3']
        ],
        'm2ts' => [
            'video' => ['mpeg2video', 'libx264'],
            'audio' => ['aac', 'mp2', 'ac3']
        ]
    ];

    /**
     * 默认编码器
     */
    const DEFAULT_ENCODERS = [
        'video' => 'libx264',
        'audio' => 'aac'
    ];

    /**
     * 检查是否支持特定格式转换
     *
     * @param string $inputFormat 输入格式
     * @param string $outputFormat 输出格式
     * @return bool 是否支持该转换
     */
    public function supportsConversion(string $inputFormat, string $outputFormat): bool
    {
        Log::info('FFmpegService::supportsConversion', [
            'inputFormat' => $inputFormat,
            'outputFormat' => $outputFormat
        ]);

        // 直接检测输入格式是否支持
        try {
            // 使用 ffprobe 直接检测格式是否支持
            $output = [];
            $returnCode = 0;

            // 创建一个测试命令，检查格式是否被识别
            exec("ffprobe -v quiet -f {$inputFormat} -i /dev/null 2>&1", $output, $returnCode);

            // 如果返回码是 0 或者错误信息不包含 "Invalid data"，说明格式被识别
            $outputStr = implode(' ', $output);
            $inputSupported = $returnCode === 0 ||
                strpos($outputStr, 'Invalid data') === false ||
                strpos($outputStr, 'No such file or directory') !== false; // 这是正常的，因为我们用的是 /dev/null

        } catch (Exception $e) {
            Log::info('FFmpegService::supportsConversion inputFormat not supported', [
                'inputFormat' => $inputFormat
            ]);
            return false;
        }

        try {
            // 使用 ffmpeg 直接检测编码器是否支持
            $output = [];
            $returnCode = 0;

            // 检查是否有对应的编码器
            $mapping = self::ENCODER_MAPPING[strtolower($outputFormat)] ?? [];
            $encoders = array_merge($mapping['video'] ?? [], $mapping['audio'] ?? []);

            foreach ($encoders as $encoder) {
                exec("ffmpeg -hide_banner -encoders 2>/dev/null | grep -w '{$encoder}'", $output, $returnCode);
                if ($returnCode === 0 && !empty($output)) {
                    $outputSupported = true;
                    break;
                }
            }

            // 对于 MPG/MPEG 格式，还需要检查音频编码器
            if (in_array(strtolower($outputFormat), ['mpg', 'mpeg'])) {
                $audioEncoders = ['mp2', 'libtwolame', 'ac3'];
                foreach ($audioEncoders as $audioEncoder) {
                    exec("ffmpeg -hide_banner -encoders 2>/dev/null | grep -w '{$audioEncoder}'", $output, $returnCode);
                    if ($returnCode === 0 && !empty($output)) {
                        $outputSupported = true;
                        break;
                    }
                }
            }

            Log::info('FFmpegService::isOutputFormatSupported outputFormat not supported', [
                'encoders' => $encoders,
                'outputFormat' => $outputFormat
            ]);
            // 如果没有找到特定编码器，检查是否是基本支持的格式
            return false;
        } catch (Exception $e) {
            Log::info('FFmpegService::supportsConversion outputFormat not supported', [
                'outputFormat' => $outputFormat
            ]);
            return false;
        }

        return $inputSupported && $outputSupported;
    }

    /**
     * 根据音频编码器获取文件扩展名
     *
     * @param string $encoder 音频编码器名称
     * @return string 文件扩展名
     */
    protected function getAudioFileExtension(string $encoder): string
    {
        $extensionMap = [
            'aac' => 'aac',
            'libmp3lame' => 'mp3',
            'mp3' => 'mp3',
            'mp2' => 'mp2',
            'ac3' => 'ac3',
            'libvorbis' => 'ogg',
            'libopus' => 'opus',
            'flac' => 'flac',
            'wav' => 'wav'
        ];

        return $extensionMap[$encoder] ?? 'aac';
    }

    /**
     * 检查输入文件是否包含音频流
     *
     * @return bool 是否包含音频流
     */
    protected function hasAudioStream(): bool
    {
        try {
            $this->loadFileInfo();
            $streams = $this->fileInfo['streams'] ?? [];

            foreach ($streams as $stream) {
                if (isset($stream['codec_type']) && $stream['codec_type'] === 'audio') {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 检查编码器是否可用
     *
     * @param string $encoder 编码器名称
     * @return bool 是否可用
     */
    protected function isEncoderAvailable(string $encoder): bool
    {
        try {
            $output = [];
            $returnCode = 0;
            // 使用单词边界匹配编码器名称
            exec("ffmpeg -hide_banner -encoders 2>/dev/null | grep -w '{$encoder}'", $output, $returnCode);
            return $returnCode === 0 && !empty($output);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 智能选择视频编码器
     *
     * @param string $outputFormat 输出格式
     * @param array $options 转换选项
     * @return string 选择的编码器
     */
    protected function selectVideoEncoder(string $outputFormat, array $options = []): string
    {
        $mapping = self::ENCODER_MAPPING[$outputFormat] ?? [];
        $videoEncoders = $mapping['video'] ?? [];

        // 按优先级尝试编码器
        foreach ($videoEncoders as $encoder) {
            if ($this->isEncoderAvailable($encoder)) {
                return $encoder;
            }
        }

        // 对于 WebM 格式，如果专用编码器都不可用，抛出异常
        if ($outputFormat === 'webm') {
            throw new Exception('WebM格式需要VP8、VP9或AV1编码器，但系统中都不可用。请安装相应的编码器支持。');
        }

        // 对于 MPG/MPEG 格式，如果专用编码器都不可用，抛出异常
        if (in_array($outputFormat, ['mpg', 'mpeg'])) {
            throw new Exception('MPG/MPEG格式需要MP3、MP2或AC3音频编码器，但系统中都不可用。请安装相应的编码器支持。');
        }

        // 如果指定格式的编码器都不可用，使用默认编码器
        return self::DEFAULT_ENCODERS['video'];
    }

    /**
     * 智能选择音频编码器
     *
     * @param string $outputFormat 输出格式
     * @param array $options 转换选项
     * @return string 选择的编码器
     */
    protected function selectAudioEncoder(string $outputFormat, array $options = []): string
    {
        $mapping = self::ENCODER_MAPPING[$outputFormat] ?? [];
        $audioEncoders = $mapping['audio'] ?? [];

        // 按优先级尝试编码器
        foreach ($audioEncoders as $encoder) {
            if ($this->isEncoderAvailable($encoder)) {
                return $encoder;
            }
        }

        // 对于 WebM 格式，如果专用编码器都不可用，抛出异常
        if ($outputFormat === 'webm') {
            throw new Exception('WebM格式需要Vorbis或Opus音频编码器，但系统中都不可用。请安装相应的编码器支持。');
        }

        // 对于 MPG/MPEG 格式，如果专用编码器都不可用，抛出异常
        if (in_array($outputFormat, ['mpg', 'mpeg'])) {
            throw new Exception('MPG/MPEG格式需要MP3、MP2或AC3音频编码器，但系统中都不可用。请安装相应的编码器支持。');
        }

        // 为音频格式提供智能默认值
        if ($this->isAudioFormat($outputFormat)) {
            switch (strtolower($outputFormat)) {
                case 'mp3':
                    return 'libmp3lame';
                case 'aac':
                    return 'aac';
                case 'ogg':
                    return 'libvorbis';
                case 'opus':
                    return 'libopus';
                case 'flac':
                    return 'flac';
                case 'wav':
                    return 'pcm_s16le';
                default:
                    return 'aac';
            }
        }

        // 如果指定格式的编码器都不可用，使用默认编码器
        return self::DEFAULT_ENCODERS['audio'];
    }

    /**
     * 获取视频编码参数
     *
     * @param string $encoder 编码器名称
     * @param array $options 转换选项
     * @return array 编码参数
     */
    protected function getVideoEncodingParams(string $encoder, array $options = []): array
    {
        $params = [];

        switch ($encoder) {
            case 'libvpx':
            case 'libvpx-vp9':
                $params = [
                    '-c:v',
                    $encoder,
                    '-crf',
                    '30',
                    '-b:v',
                    '0'
                ];
                break;

            case 'libx264':
                $params = [
                    '-c:v',
                    $encoder,
                    '-preset',
                    'medium',
                    '-crf',
                    '23'
                ];
                break;

            case 'libx265':
                $params = [
                    '-c:v',
                    $encoder,
                    '-preset',
                    'medium',
                    '-crf',
                    '28'
                ];
                break;

            default:
                $params = ['-c:v', $encoder];
        }

        // 添加分辨率参数
        if (isset($options['resolution']) && $options['resolution'] !== 'original') {
            $params = array_merge($params, $this->getResolutionParams($options['resolution']));
        }

        // 添加帧率参数
        if (isset($options['framerate']) && $options['framerate'] !== 'original') {
            $params = array_merge($params, ['-r', $options['framerate']]);
        }

        return $params;
    }

    /**
     * 获取音频编码参数
     *
     * @param string $encoder 编码器名称
     * @param array $options 转换选项
     * @return array 编码参数
     */
    protected function getAudioEncodingParams(string $encoder, array $options = []): array
    {
        $params = [];

        switch ($encoder) {
            case 'libvorbis':
                $params = ['-c:a', $encoder, '-b:a', '128k'];
                break;

            case 'libopus':
                $params = ['-c:a', $encoder, '-b:a', '128k'];
                break;

            case 'aac':
                $params = ['-c:a', $encoder, '-b:a', '128k'];
                break;

            case 'libmp3lame':
                $params = ['-c:a', $encoder, '-b:a', '192k'];
                break;

            default:
                $params = ['-c:a', $encoder];
        }

        return $params;
    }

    /**
     * 获取分辨率参数
     *
     * @param string $resolution 分辨率设置
     * @return array 分辨率参数
     */
    protected function getResolutionParams(string $resolution): array
    {
        $resolutions = [
            '4k' => ['-vf', 'scale=3840:2160'],
            '1080p' => ['-vf', 'scale=1920:1080'],
            '720p' => ['-vf', 'scale=1280:720'],
            '480p' => ['-vf', 'scale=854:480']
        ];

        return $resolutions[$resolution] ?? [];
    }

    /**
     * 提交转换任务
     *
     * @param FileConversionTask $task
     * @return array 提交结果
     */
    public function submitConversionTask(FileConversionTask $task): array
    {
        // 设置任务
        $this->setTask($task);
        try {
            // 检测文件，验证文件大小
            $this->loadFileInfo();

            return $this->convertFile();
        } catch (Exception $e) {
            Log::error('FFmpeg转换任务提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->buildErrorResponse('FFmpeg转换任务提交失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 执行FFmpeg文件转换
     *
     * @return array 转换结果
     */
    public function convertFile(): array
    {
        try {
            $task = $this->getTask();

            // 记录选择的编码器信息
            $outputFormat = $task->output_format;
            $videoEncoder = $this->selectVideoEncoder($outputFormat, $task->getConversionOptions());
            $audioEncoder = $this->selectAudioEncoder($outputFormat, $task->getConversionOptions());

            // 将编码器信息存储到任务的conversion_options中
            $currentOptions = $task->getConversionOptions();
            $currentOptions['selected_video_encoder'] = $videoEncoder;
            $currentOptions['selected_audio_encoder'] = $audioEncoder;
            $currentOptions['selected_output_format'] = $outputFormat;
            $task->update([
                'conversion_options' => $currentOptions,
            ]);

            Log::info('FFmpeg编码器选择', [
                'task_id' => $task->id,
                'output_format' => $outputFormat,
                'video_encoder' => $videoEncoder,
                'audio_encoder' => $audioEncoder,
                'encoder_mapping' => self::ENCODER_MAPPING[$outputFormat] ?? 'not_found',
                'available_encoders' => [
                    'libvpx' => $this->isEncoderAvailable('libvpx'),
                    'libvpx-vp9' => $this->isEncoderAvailable('libvpx-vp9'),
                    'libaom-av1' => $this->isEncoderAvailable('libaom-av1'),
                    'libvorbis' => $this->isEncoderAvailable('libvorbis'),
                    'libopus' => $this->isEncoderAvailable('libopus')
                ],
                'encoder_selection_debug' => [
                    'mapping_found' => isset(self::ENCODER_MAPPING[$outputFormat]),
                    'video_encoders_to_try' => self::ENCODER_MAPPING[$outputFormat]['video'] ?? [],
                    'audio_encoders_to_try' => self::ENCODER_MAPPING[$outputFormat]['audio'] ?? [],
                    'final_video_encoder' => $videoEncoder,
                    'final_audio_encoder' => $audioEncoder
                ]
            ]);

            // 更新任务状态为转换中
            $task->startProcessing();

            // 执行转换
            $outputFilePath = $this->performConversion();

            // 上传结果文件
            $outputUrl = $this->uploadOutputFile($outputFilePath);

            // 更新任务状态为完成
            $outputSize = filesize($outputFilePath);
            $task->update([
                'status' => FileConversionTask::STATUS_FINISH,
                'output_url' => $outputUrl,
                'output_size' => $outputSize,
                'step_percent' => 100,
                'completed_at' => now(),
                'processing_time' => $task->started_at->diffInSeconds(now())
            ]);

            // 清理临时文件
            $this->cleanupFiles();

            Log::info('FFmpeg转换任务完成', [
                'task_id' => $task->id,
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ]);

            return $this->buildSuccessResponse([
                'task_id' => $task->id,
                'status' => 'finished',
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ], 'FFmpeg转换任务完成');
        } catch (Exception $e) {
            Log::error('FFmpeg转换任务失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 更新任务状态为失败
            $task->update([
                'status' => FileConversionTask::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);

            $this->cleanupFiles();

            return $this->buildErrorResponse('FFmpeg转换任务失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 重写服务可用性检查
     *
     * @return bool
     */
    public function isAvailable(): bool
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
     * 重写转换选项验证
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
     * 执行FFmpeg转换
     *
     * @return string 输出文件路径
     */
    protected function performConversion(): string
    {
        $task = $this->getTask();
        $tempDir = dirname($this->getTempInputFile());
        $outputExt = $task->output_format;
        $outputFilePath = $tempDir . '/output_' . $task->id . '_' . time() . '.' . $outputExt;

        // 检查是否为图像、音频格式
        if ($this->isImageFormat($outputExt) || $outputExt === 'webm' || $this->isAudioFormat($outputExt)) {
            $this->performDirectConversion($outputFilePath);
        } else {
            // 其他视频格式使用分离式转码
            $this->performSeparateTranscode($outputFilePath);
        }

        if (!file_exists($outputFilePath)) {
            throw new Exception('转换失败，输出文件不存在');
        }

        return $outputFilePath;
    }

    /**
     * 分离式转码：分别处理视频和音频
     *
     * @param string $outputFilePath
     */
    protected function performSeparateTranscode(string $outputFilePath): void
    {
        $task = $this->getTask();
        $tempDir = dirname($this->getTempInputFile());
        $outputExt = $task->output_format;
        $options = $task->getConversionOptions();

        // 临时文件路径
        $videoOnlyFile = $tempDir . '/video_only_' . $task->id . '.' . $outputExt;

        // 根据音频编码器选择正确的文件扩展名
        $audioEncoder = $options['selected_audio_encoder'] ?? 'aac';
        $audioExt = $this->getAudioFileExtension($audioEncoder);
        $audioFile = $tempDir . '/audio_' . $task->id . '.' . $audioExt;

        try {
            if ($this->hasAudioStream()) {
                $this->convertVideoOnly($videoOnlyFile, $options);
                $this->extractAudio($audioFile, $outputExt);
                $this->mergeVideoAndAudio($videoOnlyFile, $audioFile, $outputFilePath);
            } else {
                $this->convertVideoOnly($outputFilePath, $options);
            }
        } finally {
            // 清理临时文件
            if (file_exists($videoOnlyFile)) {
                unlink($videoOnlyFile);
            }
            if (file_exists($audioFile)) {
                unlink($audioFile);
            }
        }
    }

    /**
     * 转码视频（无音频）
     *
     * @param string $outputFile
     * @param array $options
     */
    protected function convertVideoOnly(string $outputFile, array $options): void
    {
        $task = $this->getTask();
        $command = ['ffmpeg', '-i', $this->getTempInputFile()];

        // 分辨率设置
        if (!empty($options['resolution']) && $options['resolution'] !== 'original') {
            $command = array_merge($command, $this->getResolutionParams($options['resolution']));
        }

        // 帧率设置
        if (!empty($options['framerate']) && $options['framerate'] !== 'original') {
            $command = array_merge($command, ['-r', $options['framerate']]);
        }

        // 使用智能选择的视频编码器
        $videoEncoder = $options['selected_video_encoder'] ?? 'libx264';

        // 记录编码器选择
        Log::info('convertVideoOnly使用编码器', [
            'task_id' => $task->id,
            'video_encoder' => $videoEncoder,
            'options' => $options
        ]);

        $command = array_merge($command, $this->getVideoEncodingParams($videoEncoder, $options));

        // 跳过音频
        $command = array_merge($command, ['-an', '-y', $outputFile]);

        $this->executeFFmpegCommand($command);
    }

    /**
     * 提取音频
     *
     * @param string $audioFile
     * @param string $outputExt
     */
    protected function extractAudio(string $audioFile, string $outputExt): void
    {
        $task = $this->getTask();

        // 确保有音频流（调用此方法前已经检查过了）
        if (!$this->hasAudioStream()) {
            throw new Exception('无法提取音频：输入文件不包含音频流');
        }

        $command = ['ffmpeg', '-i', $this->getTempInputFile(), '-vn']; // 跳过视频

        // 使用智能选择的音频编码器
        $options = $task->getConversionOptions();
        $audioEncoder = $options['selected_audio_encoder'] ?? 'aac';

        // 记录编码器选择
        Log::info('extractAudio使用编码器', [
            'task_id' => $task->id,
            'audio_encoder' => $audioEncoder,
            'options' => $options
        ]);

        // 根据编码器选择音频参数
        $command = array_merge($command, $this->getAudioEncodingParams($audioEncoder, $options));

        // 设置采样率
        if (in_array($audioEncoder, ['mp3', 'libmp3lame'])) {
            $command = array_merge($command, ['-ar', '44100']);
        } else {
            $command = array_merge($command, ['-ar', '48000']);
        }

        $command = array_merge($command, ['-y', $audioFile]);

        $this->executeFFmpegCommand($command);
    }

    /**
     * 合并视频和音频
     *
     * @param string $videoFile
     * @param string $audioFile
     * @param string $outputFile
     */
    protected function mergeVideoAndAudio(string $videoFile, string $audioFile, string $outputFile): void
    {
        // 根据输出文件扩展名确定格式
        $outputFormat = strtolower(pathinfo($outputFile, PATHINFO_EXTENSION));

        // 这些格式通常需要特定的音频编码器，不能直接复制
        $needsAudioTranscode =  in_array(
            strtolower($outputFormat),
            ['mpg', 'mpeg', 'vob', 'ts', 'mts', 'm2ts']
        );

        $command = [
            'ffmpeg',
            '-i',
            $videoFile,
            '-i',
            $audioFile,
            '-c:v',
            'copy'
        ];

        if ($needsAudioTranscode) {
            // 需要转码音频，选择合适的编码器
            $audioEncoder = $this->selectAudioEncoder($outputFormat);
            $command = array_merge($command, $this->getAudioEncodingParams($audioEncoder, []));
        } else {
            // 可以直接复制音频
            $command[] = '-c:a';
            $command[] = 'copy';
        }

        $command = array_merge($command, [
            '-shortest',
            '-y',
            $outputFile
        ]);

        $this->executeFFmpegCommand($command);
    }

    /**
     * 执行FFmpeg命令
     *
     * @param array $command
     */
    protected function executeFFmpegCommand(array $command): void
    {
        $commandStr = implode(' ', array_map('escapeshellarg', $command));

        Log::info('执行FFmpeg命令', ['command' => $commandStr]);

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($commandStr, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new Exception('无法启动FFmpeg进程');
        }

        // 关闭stdin
        fclose($pipes[0]);

        // 读取stdout和stderr
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        // 等待进程结束
        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            Log::error('FFmpeg命令执行失败', [
                'command' => $commandStr,
                'return_code' => $returnCode,
                'stdout' => $stdout,
                'stderr' => $stderr
            ]);
            throw new Exception("FFmpeg转换失败: {$stderr}");
        }

        Log::info('FFmpeg命令执行成功', ['command' => $commandStr]);
    }

    /**
     * 判断是否为图像格式
     *
     * @param string $format
     * @return bool
     */
    protected function isImageFormat(string $format): bool
    {
        $imageFormats = ['gif', 'apng', 'webp', 'avif', 'heic', 'heif'];
        return in_array(strtolower($format), $imageFormats);
    }

    /**
     * 判断是否为音频格式
     *
     * @param string $format
     * @return bool
     */
    protected function isAudioFormat(string $format): bool
    {
        $audioFormats = ['mp3', 'aac', 'ogg', 'opus', 'flac', 'wav', 'ac3', 'mp2', 'm4a'];
        return in_array(strtolower($format), $audioFormats);
    }

    /**
     * 直接转换（用于图像格式）
     *
     * @param string $outputFilePath
     */
    protected function performDirectConversion(string $outputFilePath): void
    {
        $task = $this->getTask();
        $outputExt = $task->output_format;
        $options = $task->getConversionOptions();

        $command = ['ffmpeg', '-i', $this->getTempInputFile()];

        // 根据输出格式设置特定参数
        switch (strtolower($outputExt)) {
            case 'mp3':
                // MP3格式：使用智能选择的编码器
                $audioEncoder = $this->selectAudioEncoder('mp3', $options);
                $command = array_merge($command, [
                    '-vn', // 忽略视频
                    '-c:a',
                    $audioEncoder,
                    '-b:a',
                    '192k', // 比特率
                    '-ar',
                    '44100'  // 采样率
                ]);
                break;

            case 'aac':
                // AAC格式
                $command = array_merge($command, [
                    '-vn', // 忽略视频
                    '-c:a',
                    'aac',
                    '-b:a',
                    '128k',
                    '-ar',
                    '48000'
                ]);
                break;

            case 'ogg':
                // OGG格式
                $command = array_merge($command, [
                    '-vn', // 忽略视频
                    '-c:a',
                    'libvorbis',
                    '-b:a',
                    '128k',
                    '-ar',
                    '48000'
                ]);
                break;

            case 'flac':
                // FLAC格式
                $command = array_merge($command, [
                    '-vn', // 忽略视频
                    '-c:a',
                    'flac',
                    '-compression_level',
                    '8'
                ]);
                break;

            case 'wav':
                // WAV格式
                $command = array_merge($command, [
                    '-vn', // 忽略视频
                    '-c:a',
                    'pcm_s16le',
                    '-ar',
                    '44100'
                ]);
                break;

            case 'm4a':
                // M4A格式：使用AAC编码器
                $command = array_merge($command, [
                    '-vn', // 忽略视频
                    '-c:a',
                    'aac',
                    '-b:a',
                    '128k',
                    '-ar',
                    '48000'
                ]);
                break;

            case 'gif':
                // GIF特殊处理：使用简化的单步转换
                $fps = $options['framerate'] ?? '10'; // GIF默认10fps

                // 获取分辨率设置
                $scaleFilter = '320:-1'; // 默认宽度320px
                if (!empty($options['resolution']) && $options['resolution'] !== 'original') {
                    $resolution = $this->getResolutionParams($options['resolution']);
                    if ($resolution) {
                        $scaleFilter = $resolution[1]; // 获取分辨率值
                    }
                }

                // 使用单步转换，生成高质量GIF
                $command = [
                    'ffmpeg',
                    '-i',
                    $this->getTempInputFile(),
                    '-an', // 忽略音频
                    '-vf',
                    "fps={$fps},scale={$scaleFilter}:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse",
                    '-y',
                    $outputFilePath
                ];

                $this->executeFFmpegCommand($command);
                break;

            case 'webm':
                // WebM格式：使用智能选择的编码器
                $videoEncoder = $options['selected_video_encoder'] ?? 'libvpx';
                $audioEncoder = $options['selected_audio_encoder'] ?? 'libvorbis';

                Log::info('WebM转换使用编码器', [
                    'video_encoder' => $videoEncoder,
                    'audio_encoder' => $audioEncoder,
                    'options' => $options
                ]);

                $command = array_merge($command, $this->getVideoEncodingParams($videoEncoder, $options));
                $command = array_merge($command, $this->getAudioEncodingParams($audioEncoder, $options));
                break;

            case 'webp':
                // WebP格式
                $quality = $this->getQualityValue($options['videoQuality'] ?? 'medium');
                $command = array_merge($command, [
                    '-an', // 忽略音频
                    '-c:v',
                    'libwebp',
                    '-quality',
                    (string)$quality,
                    '-preset',
                    'default'
                ]);
                break;

            case 'apng':
                // APNG格式
                $command = array_merge($command, [
                    '-an', // 忽略音频
                    '-c:v',
                    'apng',
                    '-plays',
                    '0' // 无限循环
                ]);
                break;

            default:
                // 其他格式的通用处理
                if ($this->isImageFormat($outputExt)) {
                    // 图像格式：忽略音频
                    $command = array_merge($command, ['-an']);

                    if (isset($options['selected_video_encoder'])) {
                        $videoEncoder = $options['selected_video_encoder'];
                        $command = array_merge($command, $this->getVideoEncodingParams($videoEncoder, $options));
                    } else {
                        $command = array_merge($command, ['-c:v', 'copy']);
                    }
                } elseif ($this->isAudioFormat($outputExt)) {
                    // 音频格式：忽略视频，使用智能选择的音频编码器
                    $command = array_merge($command, ['-vn']);

                    if (isset($options['selected_audio_encoder'])) {
                        $audioEncoder = $options['selected_audio_encoder'];
                        $command = array_merge($command, $this->getAudioEncodingParams($audioEncoder, $options));
                    } else {
                        // 如果没有选择编码器，使用默认的AAC编码器
                        $command = array_merge($command, ['-c:a', 'aac', '-b:a', '128k']);
                    }
                } else {
                    // 视频格式：使用智能选择的编码器
                    if (isset($options['selected_video_encoder'])) {
                        $videoEncoder = $options['selected_video_encoder'];
                        $command = array_merge($command, $this->getVideoEncodingParams($videoEncoder, $options));
                    } else {
                        $command = array_merge($command, ['-c:v', 'copy']);
                    }
                }
                break;
        }

        // 分辨率设置
        if (!empty($options['resolution']) && $options['resolution'] !== 'original') {
            $resolution = $this->getResolutionParams($options['resolution']);
            if ($resolution && $outputExt !== 'gif') { // GIF已经在上面处理了缩放
                $command = array_merge($command, $resolution);
            }
        }

        // 添加输出文件（如果还没添加的话）
        if (!in_array($outputFilePath, $command)) {
            $command = array_merge($command, ['-y', $outputFilePath]);
        }

        try {
            $this->executeFFmpegCommand($command);
        } catch (Exception $e) {
            // 检查是否是 FLAC 文件问题
            if (
                strpos($e->getMessage(), '0 channels') !== false ||
                strpos($e->getMessage(), 'unspecified sample format') !== false
            ) {
                throw new Exception("FLAC 文件损坏或格式不正确。请检查文件是否完整，或尝试使用其他 FLAC 文件。原始错误: " . $e->getMessage());
            }

            // 检查是否是编码器问题
            if (
                strpos($e->getMessage(), 'codec not found') !== false ||
                strpos($e->getMessage(), 'encoder not found') !== false
            ) {
                throw new Exception("编码器不可用。请检查系统是否安装了相应的编解码器。原始错误: " . $e->getMessage());
            }

            // 其他错误
            throw $e;
        }
    }

    /**
     * 获取质量值（用于WebP等格式）
     *
     * @param string $quality
     * @return int
     */
    protected function getQualityValue(string $quality): int
    {
        switch ($quality) {
            case 'high':
                return 90;
            case 'medium':
                return 75;
            case 'low':
                return 50;
            default:
                return 75;
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

    /**
     * 获取并存储文件信息
     */
    protected function loadFileInfo(): void
    {
        if ($this->fileInfo !== null) return;

        try {
            $command = [
                'ffprobe',
                '-v',
                'quiet',
                '-show_format',
                '-show_streams',
                '-of',
                'json',
                escapeshellarg($this->getTempInputFile())
            ];

            $commandStr = implode(' ', $command);
            $output = [];
            $returnCode = 0;

            exec($commandStr, $output, $returnCode);

            if ($returnCode !== 0 || empty($output)) {
                throw new Exception("无法读取文件信息，文件可能损坏或格式不支持");
            }

            // 解析 JSON 输出
            $jsonOutput = implode('', $output);
            $data = json_decode($jsonOutput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("无法解析文件信息，JSON 解析失败");
            }

            $data = $this->parseFileInfo($data);

            // $data['file_type'] = match ($data['format']['format_name']) {
            //     'mov,mp4,m4a,3gp,3g2,mj2' => 'video',
            //     'mp3' => 'audio',
            //     default => 'unknown'
            // };

            // $data['output_format'] = match ($this->getOutputFormat()) {
            //     'gif', 'apng', 'webp', 'avif', 'heic', 'heif' => 'image',
            //     'mp4', 'm4v' => 'video',
            //     'm4a', 'aac', 'flac', 'ogg', 'wav' => 'audio',
            //     default => $this->getOutputFormat()
            // };

            $this->fileInfo = $data;

            // 验证文件大小
            $fileSize = filesize($this->getTempInputFile());
            if ($fileSize > self::MAX_FILE_SIZE) {
                throw new Exception("文件过大，请选择小于100MB的文件");
            }

            Log::info('文件信息加载成功', [
                'file_path' => $this->getTempInputFile(),
                'fileInfo' => $this->fileInfo
            ]);
        } catch (Exception $e) {
            Log::error('文件信息加载失败', [
                'file_path' => $this->getTempInputFile(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
