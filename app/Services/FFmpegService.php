<?php

namespace App\Services;

use App\Models\FileConversionTask;
use Exception;

class FFmpegService extends ConversionServiceBase
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
        'mp4',
        'avi',
        'mov',
        'mkv',
        'wmv',
        'flv',
        'webm',
        'm4v',
        '3gp',
        'ogv',
        'ts',
        'mts',
        'rm',
        'rmvb',
        'asf',
        'vob',
        'mpg',
        'mpeg',
        'divx',
        'xvid',
        'swf',
        'f4v',
        'm2ts',
        'mxf',
        'gif',
        'apng',
        'webp',
        'avif',
        'heic',
        'heif'
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
        // 检查输入格式是否支持
        if (!in_array(strtolower($inputFormat), self::SUPPORTED_EXTENSIONS)) {
            return false;
        }

        // 检查输出格式是否支持
        $supportedOutputFormats = array_column(self::OUTPUT_FORMATS, 'value');
        if (!in_array(strtolower($outputFormat), $supportedOutputFormats)) {
            return false;
        }

        return true;
    }

    /**
     * 检查特定格式是否需要音频转码
     *
     * @param string $format 输出格式
     * @return bool 是否需要音频转码
     */
    protected function needsAudioTranscode(string $format): bool
    {
        // 这些格式通常需要特定的音频编码器，不能直接复制
        $formatsNeedingTranscode = ['mpg', 'mpeg', 'vob', 'ts', 'mts', 'm2ts'];
        
        return in_array(strtolower($format), $formatsNeedingTranscode);
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
     * @param string $inputFile 输入文件路径
     * @return bool 是否包含音频流
     */
    protected function hasAudioStream(string $inputFile): bool
    {
        try {
            $output = [];
            $returnCode = 0;
            
            // 使用 ffprobe 检查音频流
            exec("ffprobe -v quiet -select_streams a -show_entries stream=codec_type -of csv=p=0 '{$inputFile}'", $output, $returnCode);
            
            // 如果找到音频流，输出应该包含 "audio"
            return $returnCode === 0 && !empty($output) && in_array('audio', $output);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 创建静音音频文件
     *
     * @param string $audioFile 音频文件路径
     * @param FileConversionTask $task 转换任务
     */
    protected function createSilentAudio(string $audioFile, FileConversionTask $task): void
    {
        $options = $task->getConversionOptions();
        $audioEncoder = $options['selected_audio_encoder'] ?? 'aac';
        $duration = $this->getVideoDuration($task->input_file) ?? 10; // 默认10秒
        
        // 创建静音音频
        $command = [
            'ffmpeg',
            '-f', 'lavfi',
            '-i', "anullsrc=channel_layout=stereo:sample_rate=48000:duration={$duration}",
            '-c:a', $audioEncoder
        ];
        
        // 添加音频编码参数
        $command = array_merge($command, $this->getAudioEncodingParams($audioEncoder, $options));
        
        $command = array_merge($command, ['-y', $audioFile]);
        
        $this->executeFFmpegCommand($command);
    }

    /**
     * 获取视频时长
     *
     * @param string $inputFile 输入文件路径
     * @return float|null 视频时长（秒），如果获取失败返回null
     */
    protected function getVideoDuration(string $inputFile): ?float
    {
        try {
            $output = [];
            $returnCode = 0;
            
            // 使用 ffprobe 获取视频时长
            exec("ffprobe -v quiet -show_entries format=duration -of csv=p=0 '{$inputFile}'", $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output) && is_numeric($output[0])) {
                return (float) $output[0];
            }
            
            return null;
        } catch (Exception $e) {
            return null;
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
                    '-c:v', $encoder,
                    '-crf', '30',
                    '-b:v', '0'
                ];
                break;
                
            case 'libx264':
                $params = [
                    '-c:v', $encoder,
                    '-preset', 'medium',
                    '-crf', '23'
                ];
                break;
                
            case 'libx265':
                $params = [
                    '-c:v', $encoder,
                    '-preset', 'medium',
                    '-crf', '28'
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
        try {
            // 设置任务
            $this->setTask($task);

            // 验证转换选项
            if (!$this->validateConversionOptions($task->getConversionOptions())) {
                return $this->buildErrorResponse('转换选项验证失败', 400);
            }

            // 直接执行转换任务
            $result = $this->convertFile($task);

            $this->logInfo('FFmpeg转换任务已提交', [
                'input_format' => $task->input_format,
                'output_format' => $task->output_format
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logError('FFmpeg转换任务提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->buildErrorResponse('FFmpeg转换任务提交失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 执行FFmpeg文件转换
     *
     * @param FileConversionTask $task
     * @return array 转换结果
     */
    public function convertFile(FileConversionTask $task): array
    {
        try {
            $this->setTask($task);
            
            // 记录选择的编码器信息
            $outputFormat = $task->output_format;
            $videoEncoder = $this->selectVideoEncoder($outputFormat, $task->getConversionOptions());
            $audioEncoder = $this->selectAudioEncoder($outputFormat, $task->getConversionOptions());
            
            // 将编码器信息存储到任务的conversion_options中
            $currentOptions = $task->getConversionOptions();
            $currentOptions['selected_video_encoder'] = $videoEncoder;
            $currentOptions['selected_audio_encoder'] = $audioEncoder;
            $currentOptions['selected_output_format'] = $outputFormat;
            $task->update(['conversion_options' => $currentOptions]);
            
            $this->logInfo('FFmpeg编码器选择', [
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

            // 下载输入文件
            $inputFilePath = $this->downloadInputFile($task);

            // 验证文件大小
            $fileSize = filesize($inputFilePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                throw new Exception("文件过大，请选择小于100MB的文件");
            }

            // 执行转换
            $outputFilePath = $this->performConversion($task, $inputFilePath);

            // 上传结果文件
            $outputUrl = $this->uploadOutputFile($outputFilePath);

            // 更新任务状态为完成
            $outputSize = filesize($outputFilePath);
            $task->update([
                'status' => FileConversionTask::STATUS_FINISH,
                'output_url' => $outputUrl,
                'output_size' => $outputSize,
                'step_percent' => 100,
                'completed_at' => now()
            ]);

            // 清理临时文件
            $this->cleanupFiles($inputFilePath, $outputFilePath);

            $this->logInfo('FFmpeg转换任务完成', [
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
            $this->logError('FFmpeg转换任务失败', [
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

            // 清理可能的临时文件
            if (isset($inputFilePath) && file_exists($inputFilePath)) {
                unlink($inputFilePath);
            }
            if (isset($outputFilePath) && file_exists($outputFilePath)) {
                unlink($outputFilePath);
            }

            return $this->buildErrorResponse('FFmpeg转换任务失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取支持的配置选项（保持向后兼容）
     *
     * @return array
     * @deprecated 使用 getSupportedFormats() 方法替代
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
     * 重写服务名称
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return 'ffmpeg';
    }

    /**
     * 重写服务可用性检查
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isFFmpegAvailable();
    }

    /**
     * 重写支持的格式列表
     *
     * @return array
     */
    public function getSupportedFormats(): array
    {
        return [
            'input_formats' => self::SUPPORTED_EXTENSIONS,
            'output_formats' => array_column(self::OUTPUT_FORMATS, 'value'),
            'video_quality_options' => array_column(self::VIDEO_QUALITY_OPTIONS, 'value'),
            'resolution_options' => array_column(self::RESOLUTION_OPTIONS, 'value'),
            'framerate_options' => array_column(self::FRAMERATE_OPTIONS, 'value'),
            'max_file_size' => self::MAX_FILE_SIZE
        ];
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
     * 下载输入文件到临时目录
     *
     * @param FileConversionTask $task
     * @return string 临时文件路径
     */
    protected function downloadInputFile(FileConversionTask $task): string
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $inputExt = $task->input_format;
        $tempInputFile = $tempDir . '/input_' . $task->id . '_' . time() . '.' . $inputExt;

        // 根据输入方式下载文件
        switch ($task->input_method) {
            case FileConversionTask::INPUT_METHOD_URL:
                $this->downloadFromUrl($task->input_file, $tempInputFile);
                break;

            case FileConversionTask::INPUT_METHOD_UPLOAD:
            case FileConversionTask::INPUT_METHOD_DIRECT_UPLOAD:
                // 从存储中复制文件
                $content = \Illuminate\Support\Facades\Storage::get($task->input_file);
                file_put_contents($tempInputFile, $content);
                break;

            default:
                throw new Exception("不支持的输入方式: {$task->input_method}");
        }

        if (!file_exists($tempInputFile)) {
            throw new Exception('输入文件下载失败');
        }

        return $tempInputFile;
    }

    /**
     * 从URL下载文件
     *
     * @param string $url
     * @param string $targetPath
     */
    protected function downloadFromUrl(string $url, string $targetPath): void
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 300, // 5分钟超时
                'user_agent' => 'Mozilla/5.0 (compatible; FFmpegConverter/1.0)'
            ]
        ]);

        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new Exception("无法从URL下载文件: {$url}");
        }

        file_put_contents($targetPath, $content);
    }

    /**
     * 执行FFmpeg转换
     *
     * @param FileConversionTask $task
     * @param string $inputFilePath
     * @return string 输出文件路径
     */
    protected function performConversion(FileConversionTask $task, string $inputFilePath): string
    {
        $tempDir = dirname($inputFilePath);
        $outputExt = $task->output_format;
        $outputFilePath = $tempDir . '/output_' . $task->id . '_' . time() . '.' . $outputExt;

        // 检查是否为图像格式（GIF、APNG、WebP等）
        if ($this->isImageFormat($outputExt)) {
            // 图像格式使用直接转换
            $this->performDirectConversion($task, $inputFilePath, $outputFilePath);
        } elseif ($outputExt === 'webm') {
            // WebM格式使用直接转换（因为需要特殊的编码器组合）
            $this->performDirectConversion($task, $inputFilePath, $outputFilePath);
        } else {
            // 其他视频格式使用分离式转码
            $this->performSeparateTranscode($task, $inputFilePath, $outputFilePath);
        }

        if (!file_exists($outputFilePath)) {
            throw new Exception('转换失败，输出文件不存在');
        }

        return $outputFilePath;
    }

    /**
     * 分离式转码：分别处理视频和音频
     *
     * @param FileConversionTask $task
     * @param string $inputFilePath
     * @param string $outputFilePath
     */
    protected function performSeparateTranscode(FileConversionTask $task, string $inputFilePath, string $outputFilePath): void
    {
        $tempDir = dirname($inputFilePath);
        $outputExt = $task->output_format;
        $options = $task->getConversionOptions();

        // 临时文件路径
        $videoOnlyFile = $tempDir . '/video_only_' . $task->id . '.' . $outputExt;
        
        // 根据音频编码器选择正确的文件扩展名
        $audioEncoder = $options['selected_audio_encoder'] ?? 'aac';
        $audioExt = $this->getAudioFileExtension($audioEncoder);
        $audioFile = $tempDir . '/audio_' . $task->id . '.' . $audioExt;

        try {
            // 第一步：转码视频（无音频）
            $this->convertVideoOnly($task, $inputFilePath, $videoOnlyFile, $options);

            // 第二步：提取并转码音频
            $this->extractAudio($task, $inputFilePath, $audioFile, $outputExt);

            // 第三步：合并视频和音频
            $this->mergeVideoAndAudio($videoOnlyFile, $audioFile, $outputFilePath);

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
     * @param FileConversionTask $task
     * @param string $inputFile
     * @param string $outputFile
     * @param array $options
     */
    protected function convertVideoOnly(FileConversionTask $task, string $inputFile, string $outputFile, array $options): void
    {
        $command = ['ffmpeg', '-i', $inputFile];

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
        $this->logInfo('convertVideoOnly使用编码器', [
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
     * @param FileConversionTask $task
     * @param string $inputFile
     * @param string $audioFile
     * @param string $outputExt
     */
    protected function extractAudio(FileConversionTask $task, string $inputFile, string $audioFile, string $outputExt): void
    {
        // 首先检查输入文件是否包含音频流
        if (!$this->hasAudioStream($inputFile)) {
            $this->logInfo('输入文件没有音频流，创建静音音频', [
                'task_id' => $task->id,
                'input_file' => $inputFile
            ]);
            
            // 创建静音音频文件
            $this->createSilentAudio($audioFile, $task);
            return;
        }

        $command = ['ffmpeg', '-i', $inputFile, '-vn']; // 跳过视频

        // 使用智能选择的音频编码器
        $options = $task->getConversionOptions();
        $audioEncoder = $options['selected_audio_encoder'] ?? 'aac';
        
        // 记录编码器选择
        $this->logInfo('extractAudio使用编码器', [
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
        
        // 检查是否需要转码音频（某些格式不支持直接复制）
        $needsAudioTranscode = $this->needsAudioTranscode($outputFormat);
        
        $command = [
            'ffmpeg',
            '-i', $videoFile,
            '-i', $audioFile,
            '-c:v', 'copy'
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
            '-y', $outputFile
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
        
        $this->logInfo('执行FFmpeg命令', ['command' => $commandStr]);

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
            $this->logError('FFmpeg命令执行失败', [
                'command' => $commandStr,
                'return_code' => $returnCode,
                'stdout' => $stdout,
                'stderr' => $stderr
            ]);
            throw new Exception("FFmpeg转换失败: {$stderr}");
        }

        $this->logInfo('FFmpeg命令执行成功', ['command' => $commandStr]);
    }

    /**
     * 上传输出文件到OSS
     *
     * @param string $outputFilePath
     * @return string 文件URL
     */
    protected function uploadOutputFile(string $outputFilePath): string
    {
        $extension = pathinfo($outputFilePath, PATHINFO_EXTENSION);
        $randomNumber = rand(10000, 99999);
        $timestamp = date('Y-m-d H:i:s');
        $fileName = "格式转换大王 {$timestamp} {$randomNumber}.{$extension}";
        $folder = 'conversions';
        $filePath = $folder . '/' . $fileName;

        // 上传到OSS
        $disk = \Illuminate\Support\Facades\Storage::disk('oss');
        $content = file_get_contents($outputFilePath);
        $disk->put($filePath, $content);

        $this->logInfo('文件上传到OSS完成', [
            'filename' => $fileName,
            'file_size' => strlen($content)
        ]);

        return \Illuminate\Support\Facades\Storage::url($filePath);
    }

    /**
     * 清理临时文件
     *
     * @param string ...$files
     */
    protected function cleanupFiles(string ...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
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
     * 直接转换（用于图像格式）
     *
     * @param FileConversionTask $task
     * @param string $inputFilePath
     * @param string $outputFilePath
     */
    protected function performDirectConversion(FileConversionTask $task, string $inputFilePath, string $outputFilePath): void
    {
        $outputExt = $task->output_format;
        $options = $task->getConversionOptions();

        $command = ['ffmpeg', '-i', $inputFilePath];

        // 根据输出格式设置特定参数
        switch (strtolower($outputExt)) {
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
                    '-i', $inputFilePath,
                    '-an', // 忽略音频
                    '-vf', "fps={$fps},scale={$scaleFilter}:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse",
                    '-y', $outputFilePath
                ];
                
                $this->executeFFmpegCommand($command);
                break;

            case 'webm':
                // WebM格式：使用智能选择的编码器
                $videoEncoder = $options['selected_video_encoder'] ?? 'libvpx';
                $audioEncoder = $options['selected_audio_encoder'] ?? 'libvorbis';
                
                $this->logInfo('WebM转换使用编码器', [
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
                    '-c:v', 'libwebp',
                    '-quality', (string)$quality,
                    '-preset', 'default'
                ]);
                break;

            case 'apng':
                // APNG格式
                $command = array_merge($command, [
                    '-an', // 忽略音频
                    '-c:v', 'apng',
                    '-plays', '0' // 无限循环
                ]);
                break;

            default:
                // 其他图像格式的通用处理：使用智能选择的编码器
                // 检查是否为图像格式，如果是则忽略音频
                if ($this->isImageFormat($outputExt)) {
                    $command = array_merge($command, ['-an']); // 忽略音频
                }
                
                if (isset($options['selected_video_encoder'])) {
                    $videoEncoder = $options['selected_video_encoder'];
                    $command = array_merge($command, $this->getVideoEncodingParams($videoEncoder, $options));
                } else {
                    $command = array_merge($command, ['-c:v', 'copy']);
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

        $this->executeFFmpegCommand($command);
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
                '-v',
                'quiet',
                '-print_format',
                'json',
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
            $this->logError('获取文件信息失败', [
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
