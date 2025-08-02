<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccessLogController extends Controller
{
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

            return response()->json([
                'success' => true,
                'message' => '访问日志已记录'
            ]);

        } catch (\Exception $e) {
            Log::error('记录访问日志失败', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '记录访问日志失败'
            ], 500);
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

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('获取访问统计失败', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取访问统计失败'
            ], 500);
        }
    }
} 