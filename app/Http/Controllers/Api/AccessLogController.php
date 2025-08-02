<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccessLogController extends Controller
{
    use ApiResponseTrait;
    /**
     * 记录访问日志
     */
    public function store(Request $request)
    {
        try {
            // 验证请求数据
            $validated = $request->validate([
                'browser_fingerprint' => 'nullable|string|max:64',
                'device_type' => 'nullable|string|in:mobile,desktop,tablet,unknown',
                'screen_resolution' => 'nullable|string|max:20',
                'url' => 'required|string',
                'referer' => 'nullable|string',
                'user_agent' => 'nullable|string',
                'language' => 'nullable|string',
                'timezone' => 'nullable|string',
                'timestamp' => 'nullable|string'
            ]);

            // 获取当前用户ID（如果已登录）
            $userId = null;

            // 获取客户端IP
            $ipAddress = $request->ip();

            // 生成一个简单的会话标识符（基于IP和时间）
            $sessionId = md5($ipAddress . date('Y-m-d-H'));

            // 准备访问日志数据
            $accessLogData = [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
                'url' => $validated['url'],
                'referer' => $validated['referer'],
                'session_id' => $sessionId,
                'browser_fingerprint' => $validated['browser_fingerprint'],
                'device_type' => $validated['device_type'] ?? 'unknown',
                'screen_resolution' => $validated['screen_resolution'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // 插入访问日志
            DB::table('access_logs')->insert($accessLogData);

            // 更新访问统计（异步处理，避免影响响应速度）
            $this->updateAccessStats($validated['url'], $validated['device_type'] ?? 'unknown');

            return $this->success([], '访问日志已记录');

        } catch (\Exception $e) {
            Log::error('记录访问日志失败', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return $this->serverError('记录访问日志失败');
        }
    }

    /**
     * 更新访问统计
     */
    private function updateAccessStats(string $url, string $deviceType)
    {
        try {
            $date = now()->toDateString();
            
            // 使用事务确保数据一致性
            DB::transaction(function () use ($date, $url, $deviceType) {
                // 查找或创建统计记录
                $stats = DB::table('access_stats')
                    ->where('date', $date)
                    ->where('url', $url)
                    ->lockForUpdate()
                    ->first();

                if ($stats) {
                    // 更新现有记录
                    DB::table('access_stats')
                        ->where('id', $stats->id)
                        ->update([
                            'visit_count' => $stats->visit_count + 1,
                            'updated_at' => now()
                        ]);
                } else {
                    // 创建新记录
                    DB::table('access_stats')->insert([
                        'date' => $date,
                        'url' => $url,
                        'visit_count' => 1,
                        'unique_visitors' => 0, // 需要根据指纹计算
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        } catch (\Exception $e) {
            Log::error('更新访问统计失败', [
                'error' => $e->getMessage(),
                'url' => $url,
                'device_type' => $deviceType
            ]);
        }
    }

    /**
     * 记录转换错误日志
     */
    public function logError(Request $request)
    {
        try {
            // 验证请求数据
            $validated = $request->validate([
                'error_type' => 'required|string|max:100',
                'error_message' => 'required|string|max:1000',
                'input_format' => 'nullable|string|max:20',
                'output_format' => 'nullable|string|max:20',
                'file_size' => 'nullable|integer',
                'user_agent' => 'nullable|string',
                'browser_fingerprint' => 'nullable|string|max:64',
                'device_type' => 'nullable|string|in:mobile,desktop,tablet,unknown',
                'screen_resolution' => 'nullable|string|max:20',
            ]);

            // 获取客户端IP
            $ipAddress = $request->ip();

            // 记录详细的错误信息到日志文件
            Log::error('视频转换失败', [
                'error_type' => $validated['error_type'],
                'error_message' => $validated['error_message'],
                'input_format' => $validated['input_format'],
                'output_format' => $validated['output_format'],
                'file_size' => $validated['file_size'],
                'ip_address' => $ipAddress,
                'user_agent' => $validated['user_agent'],
                'browser_fingerprint' => $validated['browser_fingerprint'],
                'device_type' => $validated['device_type'],
                'screen_resolution' => $validated['screen_resolution'],
            ]);

            return $this->success([], '错误日志已记录');

        } catch (\Exception $e) {
            Log::error('记录错误日志失败', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return $this->serverError('记录错误日志失败');
        }
    }

    /**
     * 获取访问统计
     */
    public function stats(Request $request)
    {
        try {
            $date = $request->get('date', now()->toDateString());
            $url = $request->get('url');

            $query = DB::table('access_stats')
                ->where('date', $date);

            if ($url) {
                $query->where('url', $url);
            }

            $stats = $query->get();

            return $this->success($stats, '获取访问统计成功');

        } catch (\Exception $e) {
            Log::error('获取访问统计失败', [
                'error' => $e->getMessage()
            ]);

            return $this->serverError('获取访问统计失败');
        }
    }
} 