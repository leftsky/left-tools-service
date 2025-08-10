<?php

namespace App\Services;

use CloudConvert\Laravel\Facades\CloudConvert;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;
use Illuminate\Support\Facades\Log;
use Exception;

class CloudConvertService
{
    /**
     * API 配置
     */
    private array $config;



    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = config('cloudconvert', []);

        if (empty($this->config['api_key'])) {
            throw new Exception('CloudConvert API密钥未配置');
        }
    }

    /**
     * 构建成功响应
     */
    private function buildSuccessResponse(array $data, int $code = 200): array
    {
        return [
            'success' => true,
            'data' => $data,
            'code' => $code
        ];
    }

    /**
     * 构建错误响应
     */
    private function buildErrorResponse(string $error, int $code = 500): array
    {
        return [
            'success' => false,
            'error' => $error,
            'code' => $code
        ];
    }

    /**
     * 安全获取对象属性
     */
    private function safeGet(object $object, string $method, mixed $default = null): mixed
    {
        return method_exists($object, $method) ? $object->$method() : $default;
    }

    /**
     * 创建基础转换任务
     */
    private function createBaseJob(string $tag, string $outputFormat, array $options): Job
    {
        return (new Job())
            ->setTag($tag)
            ->addTask(
                (new Task('convert', 'convert-file'))
                    ->set('output_format', $outputFormat)
                    ->set('options', $options)
            )
            ->addTask(
                (new Task('export/url', 'export-file'))
                    ->set('input', 'convert-file')
            );
    }

    /**
     * 查找指定名称的任务
     */
    private function findTaskByName($tasks, string $taskName): ?Task
    {
        foreach ($tasks as $task) {
            if ($this->safeGet($task, 'getName', '') === $taskName) {
                return $task;
            }
        }
        return null;
    }

    /**
     * 开始转换任务
     *
     * @param array $params 转换参数
     *   - input_url: string 输入文件URL（必需）
     *   - output_format: string 输出格式，如 'mp4', 'avi', 'gif' 等（默认: 'mp4'）
     *   - options: array 转换选项，如视频质量、分辨率等（可选）
     *   - tag: string 任务标签，用于标识任务（可选，默认自动生成）
     * @return array
     */
    public function startConversion(array $params): array
    {
        try {
            $inputUrl = $params['input_url'] ?? null;
            $outputFormat = $params['output_format'] ?? 'mp4';
            $options = $params['options'] ?? [];
            $tag = $params['tag'] ?? 'conversion-' . uniqid();

            if (empty($inputUrl)) {
                return $this->buildErrorResponse('输入URL不能为空', 400);
            }

            // 创建转换任务
            $job = $this->createBaseJob($tag, $outputFormat, $options)
                ->addTask(
                    (new Task('import/url', 'import-file'))
                        ->set('url', $inputUrl),
                    0 // 在开头插入导入任务
                );

            // 更新转换任务的输入源
            $tasks = $job->getTasks();
            foreach ($tasks as $task) {
                if ($this->safeGet($task, 'getName') === 'convert-file') {
                    $task->set('input', 'import-file');
                    break;
                }
            }

            $createdJob = CloudConvert::jobs()->create($job);
            $jobId = $this->safeGet($createdJob, 'getId', 'unknown');

            Log::info('CloudConvert转换任务已创建', [
                'job_id' => $jobId,
                'tag' => $tag,
                'input_url' => $inputUrl,
                'output_format' => $outputFormat
            ]);

            return $this->buildSuccessResponse([
                'job_id' => $jobId,
                'tag' => $tag,
                'status' => 'created',
                'tasks' => $this->safeGet($createdJob, 'getTasks', [])
            ]);
        } catch (Exception $e) {
            Log::error('CloudConvert转换任务创建失败', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return $this->buildErrorResponse('转换任务创建失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取转换状态
     *
     * @param string $jobId CloudConvert任务ID
     * @return array 返回任务状态信息，包括进度、错误信息等
     */
    public function getStatus(string $jobId): array
    {
        try {
            $job = CloudConvert::jobs()->get($jobId);
            $tasks = $this->safeGet($job, 'getTasks', collect());

            // 获取各个任务状态
            $importTask = $this->findTaskByName($tasks, 'import-file');
            $convertTask = $this->findTaskByName($tasks, 'convert-file');
            $exportTask = $this->findTaskByName($tasks, 'export-file');

            [$status, $progress, $error] = $this->calculateJobStatus($importTask, $convertTask, $exportTask);

            $data = [
                'job_id' => $jobId,
                'status' => $status,
                'progress' => $progress,
                'tag' => $this->safeGet($job, 'getTag'),
                'created_at' => $this->safeGet($job, 'getCreatedAt'),
                'started_at' => $this->safeGet($job, 'getStartedAt'),
                'finished_at' => $this->safeGet($job, 'getEndedAt'),
                'tasks' => $this->buildTasksStatus($importTask, $convertTask, $exportTask)
            ];

            if ($error) {
                $data['error'] = $error;
            }

            return $this->buildSuccessResponse($data);
        } catch (Exception $e) {
            Log::error('CloudConvert状态查询失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return $this->buildErrorResponse('状态查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 计算任务状态和进度
     */
    private function calculateJobStatus(?Task $importTask, ?Task $convertTask, ?Task $exportTask): array
    {
        $status = 'processing';
        $progress = 0;
        $error = null;

        // 检查错误状态
        if ($importTask && $this->safeGet($importTask, 'getStatus') === 'error') {
            $status = 'error';
            $error = $this->safeGet($importTask, 'getMessage', '导入任务失败');
        } elseif ($convertTask && $this->safeGet($convertTask, 'getStatus') === 'error') {
            $status = 'error';
            $error = $this->safeGet($convertTask, 'getMessage', '转换任务失败');
        } elseif ($exportTask && $this->safeGet($exportTask, 'getStatus') === 'error') {
            $status = 'error';
            $error = $this->safeGet($exportTask, 'getMessage', '导出任务失败');
        }
        // 检查完成状态
        elseif ($exportTask && $this->safeGet($exportTask, 'getStatus') === 'finished') {
            $status = 'finished';
            $progress = 100;
        } elseif ($convertTask && $this->safeGet($convertTask, 'getStatus') === 'finished') {
            $progress = 66;
        } elseif ($importTask && $this->safeGet($importTask, 'getStatus') === 'finished') {
            $progress = 33;
        }

        return [$status, $progress, $error];
    }

    /**
     * 构建任务状态信息
     */
    private function buildTasksStatus(?Task $importTask, ?Task $convertTask, ?Task $exportTask): array
    {
        return [
            'import' => $importTask ? [
                'status' => $this->safeGet($importTask, 'getStatus'),
                'message' => $this->safeGet($importTask, 'getMessage')
            ] : null,
            'convert' => $convertTask ? [
                'status' => $this->safeGet($convertTask, 'getStatus'),
                'message' => $this->safeGet($convertTask, 'getMessage')
            ] : null,
            'export' => $exportTask ? [
                'status' => $this->safeGet($exportTask, 'getStatus'),
                'message' => $this->safeGet($exportTask, 'getMessage'),
                'result' => $this->safeGet($exportTask, 'getResult')
            ] : null
        ];
    }

    /**
     * 等待任务完成
     *
     * @param string $jobId CloudConvert任务ID
     * @param int $timeout 超时时间（秒），默认300秒（5分钟）
     * @return array
     */
    public function waitForJob(string $jobId, int $timeout = 300): array
    {
        try {
            CloudConvert::jobs()->wait($jobId, $timeout);
            return $this->getStatus($jobId);
        } catch (Exception $e) {
            Log::error('CloudConvert任务等待失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return $this->buildErrorResponse('任务等待失败: ' . $e->getMessage());
        }
    }



    /**
     * 获取支持的格式
     *
     * @return array 返回所有支持的输入和输出格式，按文件类型分类
     */
    public function getSupportedFormats(): array
    {
        return [
            'document' => [
                'input' => ['pdf', 'doc', 'docx', 'docm', 'dot', 'dotx', 'xls', 'xlsx', 'xlsm', 'ppt', 'pptx', 'pptm', 'pot', 'potx', 'pps', 'ppsx', 'txt', 'rtf', 'csv', 'html', 'htm', 'md', 'odt', 'ods', 'odp', 'pub', 'pages', 'numbers', 'key', 'epub', 'mobi', 'azw', 'azw3', 'azw4', 'fb2', 'djvu', 'chm', 'hwp', 'wpd', 'wps', 'et', 'dps', 'abw', 'lwp', 'odd', 'odg', 'cbc', 'cbr', 'cbz', 'lit', 'lrf', 'pdb', 'pml', 'prc', 'rb', 'rst', 'snb', 'tcr', 'tex', 'txtz', 'htmlz', 'xps', 'zabw'],
                'output' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'html', 'htm', 'odt', 'ods', 'odp', 'pub', 'pages', 'numbers', 'key', 'epub', 'pot', 'potx', 'pps', 'ppsx', 'pptm', 'xlsm', 'odg', 'tex']
            ],
            'image' => [
                'input' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'ico', 'psd', 'heic', 'heif', 'avif', 'jfif', 'raw', 'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'raf', 'crw', 'dcr', 'erf', 'mos', 'mrw', 'x3f', 'xcf', '3fr', 'ppm'],
                'output' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'ico', 'psd', 'heic', 'heif', 'avif']
            ],
            'video' => [
                'input' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', '3gp', 'ogv', 'gif', 'mpeg', 'mpg', 'm2ts', 'mts', 'ts', 'vob', 'swf', 'rmvb', 'rm', 'mxf', 'dv', 'dvr', 'cavs', 'mod', 'wtv', '3g2', '3gpp'],
                'output' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', '3gp', 'ogv', 'gif', 'mpeg', 'mpg', '3g2', '3gpp']
            ],
            'audio' => [
                'input' => ['mp3', 'wav', 'aac', 'flac', 'ogg', 'wma', 'm4a', 'opus', 'ac3', 'aif', 'aiff', 'aifc', 'amr', 'au', 'caf', 'dss', 'm4b', 'oga', 'voc', 'weba'],
                'output' => ['mp3', 'wav', 'aac', 'flac', 'ogg', 'wma', 'm4a', 'opus', 'ac3', 'aif', 'aiff', 'aifc', 'amr', 'au', 'caf', 'm4b', 'oga']
            ],
            'archive' => [
                'input' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tar.gz', 'tar.bz2', 'tar.xz', 'tgz', 'tbz2', 'iso', 'dmg', 'jar', 'deb', 'rpm', 'cab', 'ace', 'lzma', 'lzo', 'arj', 'alz', 'arc', 'bz', 'cpio', 'img', 'lha', 'lz', 'rz', 'tar.7z', 'tar.bz', 'tar.lzo', 'tar.z', 'tbz', 'tz', 'tzo', 'z'],
                'output' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tar.gz', 'tar.bz2', 'tar.xz']
            ],
            'vector' => [
                'input' => ['svg', 'pdf', 'ai', 'eps', 'dwg', 'dxf', 'cdr', 'wmf', 'emf', 'vsd', 'cgm', 'ps', 'sk', 'sk1', 'svgz'],
                'output' => ['svg', 'pdf', 'ai', 'eps', 'wmf', 'ps']
            ],
            'font' => [
                'input' => ['ttf', 'otf', 'woff', 'woff2', 'eot'],
                'output' => ['ttf', 'otf', 'woff', 'woff2', 'eot']
            ],
            'icon' => [
                'input' => ['ico', 'icns'],
                'output' => ['ico', 'icns', 'png']
            ]
        ];
    }

    /**
     * 验证格式是否支持
     *
     * @param string $inputFormat 输入文件格式，支持压缩包、视频、音频、图像、文档、字体、图标等格式
     * @param string $outputFormat 输出文件格式，支持相应类型的输出格式
     * @return bool 返回是否支持该格式转换
     */
    public function validateFormat(string $inputFormat, string $outputFormat): bool
    {
        $formats = $this->getSupportedFormats();

        foreach ($formats as $category) {
            if (
                in_array(strtolower($inputFormat), $category['input']) &&
                in_array(strtolower($outputFormat), $category['output'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 下载转换结果
     *
     * @param string $jobId CloudConvert任务ID
     * @param string $outputPath 本地保存路径，如 '/path/to/downloads/'
     * @return array 返回下载结果，包含文件信息
     */
    public function downloadResult(string $jobId, string $outputPath): array
    {
        try {
            $job = CloudConvert::jobs()->get($jobId);
            $tasks = method_exists($job, 'getTasks') ? $job->getTasks() : collect();
            $exportTask = null;

            // 遍历任务找到导出任务
            foreach ($tasks as $task) {
                $taskName = method_exists($task, 'getName') ? $task->getName() : '';
                if ($taskName === 'export-file') {
                    $exportTask = $task;
                    break;
                }
            }

            if (!$exportTask || (method_exists($exportTask, 'getStatus') && $exportTask->getStatus() !== 'finished')) {
                return [
                    'success' => false,
                    'error' => '任务未完成或导出失败',
                    'code' => 400
                ];
            }

            $result = method_exists($exportTask, 'getResult') ? $exportTask->getResult() : null;
            $files = [];

            if ($result && is_object($result)) {
                $files = $result->files ?? [];
                if (is_array($files)) {
                    $files = $files;
                } else {
                    $files = [];
                }
            }

            if (empty($files)) {
                return [
                    'success' => false,
                    'error' => '没有可下载的文件',
                    'code' => 404
                ];
            }

            $downloadedFiles = [];

            foreach ($files as $file) {
                $url = $file['url'];
                $filename = $file['filename'] ?? basename($url);
                $filePath = rtrim($outputPath, '/') . '/' . $filename;

                // 确保目录存在
                $dir = dirname($filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // 下载文件
                $source = CloudConvert::getHttpTransport()->download($url)->detach();
                $dest = fopen($filePath, 'w');

                if ($source && $dest) {
                    stream_copy_to_stream($source, $dest);
                    fclose($dest);
                    fclose($source);

                    $downloadedFiles[] = [
                        'filename' => $filename,
                        'path' => $filePath,
                        'size' => filesize($filePath)
                    ];
                }
            }

            Log::info('CloudConvert文件下载完成', [
                'job_id' => $jobId,
                'files' => $downloadedFiles
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'files' => $downloadedFiles,
                    'count' => count($downloadedFiles)
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            Log::error('CloudConvert文件下载失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '文件下载失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 创建上传任务
     *
     * @param string $filename 文件名，包含扩展名
     * @param string $outputFormat 输出格式，如 'mp4', 'avi', 'gif' 等
     * @param array $options 转换选项
     *   - video_bitrate: int 视频比特率（可选）
     *   - video_resolution: string 视频分辨率，如 '1920x1080'（可选）
     *   - audio_bitrate: int 音频比特率（可选）
     *   - fps: int 帧率（可选）
     *   - quality: string 质量设置，如 'high', 'medium', 'low'（可选）
     * @return array
     */
    public function createUploadJob(string $filename, string $outputFormat, array $options = []): array
    {
        try {
            $tag = 'upload-' . uniqid();

            $job = $this->createBaseJob($tag, $outputFormat, $options)
                ->addTask(
                    (new Task('import/upload', 'upload-file'))
                        ->set('filename', $filename),
                    0 // 在开头插入上传任务
                );

            // 更新转换任务的输入源
            $tasks = $job->getTasks();
            foreach ($tasks as $task) {
                if ($this->safeGet($task, 'getName') === 'convert-file') {
                    $task->set('input', 'upload-file');
                    break;
                }
            }

            $createdJob = CloudConvert::jobs()->create($job);
            $tasks = $this->safeGet($createdJob, 'getTasks', collect());
            $uploadTask = $this->findTaskByName($tasks, 'upload-file');
            $jobId = $this->safeGet($createdJob, 'getId', 'unknown');

            Log::info('CloudConvert上传任务已创建', [
                'job_id' => $jobId,
                'filename' => $filename,
                'output_format' => $outputFormat
            ]);

            return $this->buildSuccessResponse([
                'job_id' => $jobId,
                'upload_task' => $uploadTask,
                'tag' => $tag
            ]);
        } catch (Exception $e) {
            Log::error('CloudConvert上传任务创建失败', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return $this->buildErrorResponse('上传任务创建失败: ' . $e->getMessage());
        }
    }

    /**
     * 上传文件
     *
     * @param Task $uploadTask CloudConvert上传任务对象
     * @param string $filePath 本地文件路径
     * @return array 返回上传结果
     */
    public function uploadFile(Task $uploadTask, string $filePath): array
    {
        try {
            if (!file_exists($filePath)) {
                return $this->buildErrorResponse('文件不存在: ' . $filePath, 404);
            }

            $inputStream = fopen($filePath, 'r');
            CloudConvert::tasks()->upload($uploadTask, $inputStream);

            $taskId = $this->safeGet($uploadTask, 'getId', 'unknown');

            Log::info('CloudConvert文件上传完成', [
                'task_id' => $taskId,
                'file_path' => $filePath
            ]);

            return $this->buildSuccessResponse([
                'task_id' => $taskId,
                'file_path' => $filePath
            ]);
        } catch (Exception $e) {
            Log::error('CloudConvert文件上传失败', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);

            return $this->buildErrorResponse('文件上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建客户端直传任务
     *
     * @param string $filename 文件名
     * @param string $outputFormat 输出格式
     * @param array $options 转换选项
     * @return array 返回直传信息
     */
    public function createDirectUploadJob(string $filename, string $outputFormat, array $options = []): array
    {
        try {
            $tag = 'direct-upload-' . uniqid();

            $job = $this->createBaseJob($tag, $outputFormat, $options)
                ->addTask(
                    (new Task('import/upload', 'upload-file'))
                        ->set('filename', $filename),
                    0 // 在开头插入上传任务
                );

            // 更新转换任务的输入源
            $tasks = $job->getTasks();
            foreach ($tasks as $task) {
                if ($this->safeGet($task, 'getName') === 'convert-file') {
                    $task->set('input', 'upload-file');
                    break;
                }
            }

            $createdJob = CloudConvert::jobs()->create($job);
            $tasks = $this->safeGet($createdJob, 'getTasks', collect());
            $uploadTask = $this->findTaskByName($tasks, 'upload-file');

            if (!$uploadTask) {
                throw new Exception('上传任务创建失败');
            }

            // 获取直传 URL 和表单数据
            [$uploadUrl, $formData] = $this->extractUploadInfo($uploadTask);
            $jobId = $this->safeGet($createdJob, 'getId', 'unknown');

            Log::info('CloudConvert直传任务已创建', [
                'job_id' => $jobId,
                'filename' => $filename,
                'output_format' => $outputFormat,
                'upload_url' => $uploadUrl
            ]);

            return $this->buildSuccessResponse([
                'job_id' => $jobId,
                'upload_url' => $uploadUrl,
                'form_data' => $formData,
                'tag' => $tag
            ]);
        } catch (Exception $e) {
            Log::error('CloudConvert直传任务创建失败', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return $this->buildErrorResponse('直传任务创建失败: ' . $e->getMessage());
        }
    }

    /**
     * 从上传任务中提取上传信息
     */
    private function extractUploadInfo(Task $uploadTask): array
    {
        $result = $this->safeGet($uploadTask, 'getResult');
        $uploadUrl = null;
        $formData = [];

        if ($result && is_object($result)) {
            $formDataObj = $result->form_data ?? null;
            if ($formDataObj && is_object($formDataObj)) {
                $uploadUrl = $formDataObj->url ?? null;
                $formData = (array) $formDataObj;
            }
        }

        return [$uploadUrl, $formData];
    }

    /**
     * 确认客户端直传完成
     *
     * @param string $jobId 任务ID
     * @return array 返回确认结果
     */
    public function confirmDirectUpload(string $jobId): array
    {
        try {
            $job = CloudConvert::jobs()->get($jobId);
            $tasks = $this->safeGet($job, 'getTasks', collect());
            $uploadTask = $this->findTaskByName($tasks, 'upload-file');

            if (!$uploadTask) {
                return $this->buildErrorResponse('上传任务不存在', 404);
            }

            $uploadStatus = $this->safeGet($uploadTask, 'getStatus');

            if ($uploadStatus === 'error') {
                $errorMessage = $this->safeGet($uploadTask, 'getMessage', '未知错误');
                return $this->buildErrorResponse('文件上传失败: ' . $errorMessage, 400);
            }

            if ($uploadStatus !== 'finished') {
                return $this->buildErrorResponse('文件上传尚未完成', 400);
            }

            Log::info('CloudConvert直传确认成功', [
                'job_id' => $jobId
            ]);

            return $this->buildSuccessResponse([
                'job_id' => $jobId,
                'status' => 'uploaded'
            ]);
        } catch (Exception $e) {
            Log::error('CloudConvert直传确认失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return $this->buildErrorResponse('直传确认失败: ' . $e->getMessage());
        }
    }

    /**
     * 估算转换时间
     *
     * @param string $inputFormat 输入文件格式，支持各种类型的文件格式
     * @param string $outputFormat 输出文件格式，支持各种类型的输出格式
     * @param int $fileSize 文件大小（字节）
     * @return int 估算转换时间（秒）
     */
    public function estimateConversionTime(string $inputFormat, string $outputFormat, int $fileSize): int
    {
        // 基础转换时间（秒）
        $baseTime = 30;

        // 根据文件大小调整
        $sizeFactor = max(1, $fileSize / (10 * 1024 * 1024)); // 10MB 基准

        // 根据格式复杂度调整
        $formatComplexity = 1.0;
        
        // 复杂视频格式
        if (in_array($inputFormat, ['avi', 'mov', 'mkv', 'mxf', 'rmvb'])) {
            $formatComplexity *= 1.5;
        }
        
        // 压缩包解压需要更多时间
        if (in_array($inputFormat, ['7z', 'rar', 'tar.xz', 'tar.bz2'])) {
            $formatComplexity *= 1.3;
        }
        
        // RAW图像格式处理复杂
        if (in_array($inputFormat, ['cr2', 'cr3', 'nef', 'arw', 'raw', 'dng'])) {
            $formatComplexity *= 1.4;
        }
        
        // 特定输出格式需要更多处理时间
        if (in_array($outputFormat, ['gif', 'webm', 'heic', 'avif'])) {
            $formatComplexity *= 1.3;
        }
        
        // 文档转换相对较快
        $documentFormats = ['doc', 'docx', 'pdf', 'txt', 'rtf', 'html'];
        if (in_array($inputFormat, $documentFormats) && in_array($outputFormat, $documentFormats)) {
            $formatComplexity *= 0.8;
        }

        return (int) ($baseTime * $sizeFactor * $formatComplexity);
    }
}
