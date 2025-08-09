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
     * 超时时间（秒）
     */
    private int $timeout;

    /**
     * 最大重试次数
     */
    private int $maxRetries;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = config('cloudconvert', []);
        $this->timeout = 30; // 默认超时时间
        $this->maxRetries = 3; // 默认重试次数

        if (empty($this->config['api_key'])) {
            throw new Exception('CloudConvert API密钥未配置');
        }
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
                return [
                    'success' => false,
                    'error' => '输入URL不能为空',
                    'code' => 400
                ];
            }

            // 创建转换任务
            $job = (new Job())
                ->setTag($tag)
                ->addTask(
                    (new Task('import/url', 'import-file'))
                        ->set('url', $inputUrl)
                )
                ->addTask(
                    (new Task('convert', 'convert-file'))
                        ->set('input', 'import-file')
                        ->set('output_format', $outputFormat)
                        ->set('options', $options)
                )
                ->addTask(
                    (new Task('export/url', 'export-file'))
                        ->set('input', 'convert-file')
                );

            $createdJob = CloudConvert::jobs()->create($job);

            Log::info('CloudConvert转换任务已创建', [
                'job_id' => method_exists($createdJob, 'getId') ? $createdJob->getId() : 'unknown',
                'tag' => $tag,
                'input_url' => $inputUrl,
                'output_format' => $outputFormat
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => method_exists($createdJob, 'getId') ? $createdJob->getId() : 'unknown',
                    'tag' => $tag,
                    'status' => 'created',
                    'tasks' => method_exists($createdJob, 'getTasks') && $createdJob->getTasks()
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            Log::error('CloudConvert转换任务创建失败', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => '转换任务创建失败: ' . $e->getMessage(),
                'code' => 500
            ];
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
            $tasks = method_exists($job, 'getTasks') ? $job->getTasks() : collect();

            // 获取各个任务状态
            $importTask = null;
            $convertTask = null;
            $exportTask = null;

            foreach ($tasks as $task) {
                $taskName = method_exists($task, 'getName') ? $task->getName() : '';
                switch ($taskName) {
                    case 'import-file':
                        $importTask = $task;
                        break;
                    case 'convert-file':
                        $convertTask = $task;
                        break;
                    case 'export-file':
                        $exportTask = $task;
                        break;
                }
            }

            $status = 'processing';
            $progress = 0;
            $error = null;

            // 检查任务状态
            if ($importTask && method_exists($importTask, 'getStatus') && $importTask->getStatus() === 'error') {
                $status = 'error';
                $error = method_exists($importTask, 'getMessage') ? $importTask->getMessage() : '导入任务失败';
            } elseif ($convertTask && method_exists($convertTask, 'getStatus') && $convertTask->getStatus() === 'error') {
                $status = 'error';
                $error = method_exists($convertTask, 'getMessage') ? $convertTask->getMessage() : '转换任务失败';
            } elseif ($exportTask && method_exists($exportTask, 'getStatus') && $exportTask->getStatus() === 'error') {
                $status = 'error';
                $error = method_exists($exportTask, 'getMessage') ? $exportTask->getMessage() : '导出任务失败';
            } elseif ($exportTask && method_exists($exportTask, 'getStatus') && $exportTask->getStatus() === 'finished') {
                $status = 'finished';
                $progress = 100;
            } elseif ($convertTask && method_exists($convertTask, 'getStatus') && $convertTask->getStatus() === 'finished') {
                $progress = 66;
            } elseif ($importTask && method_exists($importTask, 'getStatus') && $importTask->getStatus() === 'finished') {
                $progress = 33;
            }

            $result = [
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => $status,
                    'progress' => $progress,
                    'tag' => method_exists($job, 'getTag') ? $job->getTag() : null,
                    'created_at' => method_exists($job, 'getCreatedAt') ? $job->getCreatedAt() : null,
                    'started_at' => method_exists($job, 'getStartedAt') ? $job->getStartedAt() : null,
                    'finished_at' => method_exists($job, 'getEndedAt') ? $job->getEndedAt() : null,
                    'tasks' => [
                        'import' => $importTask ? [
                            'status' => method_exists($importTask, 'getStatus') ? $importTask->getStatus() : null,
                            'message' => method_exists($importTask, 'getMessage') ? $importTask->getMessage() : null
                        ] : null,
                        'convert' => $convertTask ? [
                            'status' => method_exists($convertTask, 'getStatus') ? $convertTask->getStatus() : null,
                            'message' => method_exists($convertTask, 'getMessage') ? $convertTask->getMessage() : null
                        ] : null,
                        'export' => $exportTask ? [
                            'status' => method_exists($exportTask, 'getStatus') ? $exportTask->getStatus() : null,
                            'message' => method_exists($exportTask, 'getMessage') ? $exportTask->getMessage() : null,
                            'result' => method_exists($exportTask, 'getResult') ? $exportTask->getResult() : null
                        ] : null
                    ]
                ],
                'code' => 200
            ];

            if ($error) {
                $result['data']['error'] = $error;
            }

            return $result;
        } catch (Exception $e) {
            Log::error('CloudConvert状态查询失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '状态查询失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
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
            $job = CloudConvert::jobs()->wait($jobId, $timeout);
            return $this->getStatus($jobId);
        } catch (Exception $e) {
            Log::error('CloudConvert任务等待失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '任务等待失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 取消转换任务
     *
     * @param string $jobId CloudConvert任务ID
     * @return array 返回取消结果
     */
    public function cancelConversion(string $jobId): array
    {
        try {
            CloudConvert::jobs()->cancel($jobId);

            Log::info('CloudConvert任务已取消', [
                'job_id' => $jobId
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'cancelled'
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            Log::error('CloudConvert任务取消失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '任务取消失败: ' . $e->getMessage(),
                'code' => 500
            ];
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
            'video' => [
                'input' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', '3gp', 'ogv'],
                'output' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', '3gp', 'ogv', 'gif']
            ],
            'audio' => [
                'input' => ['mp3', 'wav', 'aac', 'ogg', 'wma', 'flac', 'm4a', 'opus'],
                'output' => ['mp3', 'wav', 'aac', 'ogg', 'wma', 'flac', 'm4a', 'opus']
            ],
            'image' => [
                'input' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'svg'],
                'output' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'svg', 'ico']
            ],
            'document' => [
                'input' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'],
                'output' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'html']
            ]
        ];
    }

    /**
     * 验证格式是否支持
     *
     * @param string $inputFormat 输入文件格式，如 'mp4', 'avi', 'mov' 等
     * @param string $outputFormat 输出文件格式，如 'mp4', 'gif', 'webm' 等
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

            $job = (new Job())
                ->setTag($tag)
                ->addTask(
                    (new Task('import/upload', 'upload-file'))
                        ->set('filename', $filename)
                )
                ->addTask(
                    (new Task('convert', 'convert-file'))
                        ->set('input', 'upload-file')
                        ->set('output_format', $outputFormat)
                        ->set('options', $options)
                )
                ->addTask(
                    (new Task('export/url', 'export-file'))
                        ->set('input', 'convert-file')
                );

            $createdJob = CloudConvert::jobs()->create($job);
            $tasks = method_exists($createdJob, 'getTasks') ? $createdJob->getTasks() : collect();
            $uploadTask = null;

            // 遍历任务找到上传任务
            foreach ($tasks as $task) {
                $taskName = method_exists($task, 'getName') ? $task->getName() : '';
                if ($taskName === 'upload-file') {
                    $uploadTask = $task;
                    break;
                }
            }

            Log::info('CloudConvert上传任务已创建', [
                'job_id' => method_exists($createdJob, 'getId') ? $createdJob->getId() : 'unknown',
                'filename' => $filename,
                'output_format' => $outputFormat
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => method_exists($createdJob, 'getId') ? $createdJob->getId() : 'unknown',
                    'upload_task' => $uploadTask,
                    'tag' => $tag
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            Log::error('CloudConvert上传任务创建失败', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return [
                'success' => false,
                'error' => '上传任务创建失败: ' . $e->getMessage(),
                'code' => 500
            ];
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
                return [
                    'success' => false,
                    'error' => '文件不存在: ' . $filePath,
                    'code' => 404
                ];
            }

            $inputStream = fopen($filePath, 'r');
            CloudConvert::tasks()->upload($uploadTask, $inputStream);

            Log::info('CloudConvert文件上传完成', [
                'task_id' => method_exists($uploadTask, 'getId') ? $uploadTask->getId() : 'unknown',
                'file_path' => $filePath
            ]);

            return [
                'success' => true,
                'data' => [
                    'task_id' => method_exists($uploadTask, 'getId') ? $uploadTask->getId() : 'unknown',
                    'file_path' => $filePath
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            Log::error('CloudConvert文件上传失败', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);

            return [
                'success' => false,
                'error' => '文件上传失败: ' . $e->getMessage(),
                'code' => 500
            ];
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

            $job = (new Job())
                ->setTag($tag)
                ->addTask(
                    (new Task('import/upload', 'upload-file'))
                        ->set('filename', $filename)
                )
                ->addTask(
                    (new Task('convert', 'convert-file'))
                        ->set('input', 'upload-file')
                        ->set('output_format', $outputFormat)
                        ->set('options', $options)
                )
                ->addTask(
                    (new Task('export/url', 'export-file'))
                        ->set('input', 'convert-file')
                );

            $createdJob = CloudConvert::jobs()->create($job);
            $tasks = method_exists($createdJob, 'getTasks') ? $createdJob->getTasks() : collect();
            $uploadTask = null;

            // 遍历任务找到上传任务
            foreach ($tasks as $task) {
                $taskName = method_exists($task, 'getName') ? $task->getName() : '';
                if ($taskName === 'upload-file') {
                    $uploadTask = $task;
                    break;
                }
            }

            if (!$uploadTask) {
                throw new Exception('上传任务创建失败');
            }

            // 获取直传 URL
            $result = method_exists($uploadTask, 'getResult') ? $uploadTask->getResult() : null;
            $uploadUrl = null;
            $formData = [];

            if ($result && is_object($result)) {
                $formDataObj = $result->form_data ?? null;
                if ($formDataObj && is_object($formDataObj)) {
                    $uploadUrl = $formDataObj->url ?? null;
                    // 将对象转换为数组
                    $formData = (array) $formDataObj;
                }
            }

            Log::info('CloudConvert直传任务已创建', [
                'job_id' => method_exists($createdJob, 'getId') ? $createdJob->getId() : 'unknown',
                'filename' => $filename,
                'output_format' => $outputFormat,
                'upload_url' => $uploadUrl
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => method_exists($createdJob, 'getId') ? $createdJob->getId() : 'unknown',
                    'upload_url' => $uploadUrl,
                    'form_data' => $formData,
                    'tag' => $tag
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            Log::error('CloudConvert直传任务创建失败', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return [
                'success' => false,
                'error' => '直传任务创建失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
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

            // 检查上传任务状态
            $tasks = method_exists($job, 'getTasks') ? $job->getTasks() : collect();
            $uploadTask = null;

            foreach ($tasks as $task) {
                $taskName = method_exists($task, 'getName') ? $task->getName() : '';
                if ($taskName === 'upload-file') {
                    $uploadTask = $task;
                    break;
                }
            }

            if (!$uploadTask) {
                return [
                    'success' => false,
                    'error' => '上传任务不存在',
                    'code' => 404
                ];
            }

            if (method_exists($uploadTask, 'getStatus') && $uploadTask->getStatus() === 'error') {
                return [
                    'success' => false,
                    'error' => '文件上传失败: ' . (method_exists($uploadTask, 'getMessage') ? $uploadTask->getMessage() : '未知错误'),
                    'code' => 400
                ];
            }

            if (method_exists($uploadTask, 'getStatus') && $uploadTask->getStatus() !== 'finished') {
                return [
                    'success' => false,
                    'error' => '文件上传尚未完成',
                    'code' => 400
                ];
            }

            Log::info('CloudConvert直传确认成功', [
                'job_id' => $jobId
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'uploaded'
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            Log::error('CloudConvert直传确认失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '直传确认失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 估算转换时间
     *
     * @param string $inputFormat 输入文件格式，如 'mp4', 'avi', 'mov' 等
     * @param string $outputFormat 输出文件格式，如 'mp4', 'gif', 'webm' 等
     * @param int $fileSize 文件大小（字节）
     * @return int 估算转换时间（秒）
     */
    public function estimateConversionTime(string $inputFormat, string $outputFormat, int $fileSize): int
    {
        // 基础转换时间（秒）
        $baseTime = 30;

        // 根据文件大小调整
        $sizeFactor = $fileSize / (10 * 1024 * 1024); // 10MB 基准

        // 根据格式复杂度调整
        $formatComplexity = 1.0;
        if (in_array($inputFormat, ['avi', 'mov', 'mkv'])) {
            $formatComplexity = 1.5;
        }
        if (in_array($outputFormat, ['gif', 'webm'])) {
            $formatComplexity = 1.3;
        }

        return (int) ($baseTime * $sizeFactor * $formatComplexity);
    }
}
