<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\AccessLog;
use App\Models\AccessStats;
use Symfony\Component\HttpFoundation\Response;

class AccessLogMiddleware
{
    /**
     * 需要记录访问的URI列表
     */
    private static array $recordedUris = [
        '/',
        'video-converter',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 异步记录访问日志，不阻塞响应
        try {
            $this->recordAccessLog($request);
        } catch (\Exception $e) {
            Log::warning('记录访问日志失败', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }

    /**
     * 记录访问日志
     */
    private function recordAccessLog(Request $request): void
    {
        $path = $request->path();

        // 只记录在数组中的URI
        if (!in_array($path, self::$recordedUris)) {
            return;
        }

        $data = [
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->path(),
            'referer' => $request->header('referer'),
            'session_id' => $request->session()->getId(),
        ];

        // 异步记录，避免阻塞响应
        dispatch(function () use ($data) {
            try {
                // 创建访问记录
                AccessLog::create($data);

                // 更新访问统计
                $this->updateAccessStats($data);
            } catch (\Exception $e) {
                Log::error('创建访问记录失败', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        })->afterResponse();
    }

    /**
     * 更新访问统计
     */
    private function updateAccessStats(array $data): void
    {
        try {
            $date = now()->toDateString();
            $url = $data['url'];

            // 生成唯一访客标识
            $visitorId = $this->getVisitorId($data);

            // 缓存键：用于判断是否为独立访客
            $cacheKey = "visitor_{$date}_{$url}_{$visitorId}";

            // 检查是否已经记录过这个访客
            $isNewVisitor = !Cache::has($cacheKey);

            // 获取或创建统计记录
            $stats = AccessStats::firstOrCreate(
                ['date' => $date, 'url' => $url],
                [
                    'visit_count' => 0,
                    'unique_visitors' => 0,
                ]
            );

            // 更新访问次数
            $stats->increment('visit_count');

            // 如果是新访客，更新独立访客数
            if ($isNewVisitor) {
                $stats->increment('unique_visitors');
                // 缓存访客标识，有效期24小时
                Cache::put($cacheKey, true, now()->addDay());
            }
        } catch (\Exception $e) {
            Log::error('更新访问统计失败', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * 获取访客唯一标识
     */
    private function getVisitorId(array $data): string
    {
        // 如果用户已登录，使用用户ID
        if ($data['user_id']) {
            return "user_{$data['user_id']}";
        }

        // 如果用户未登录，使用IP地址
        return "ip_{$data['ip_address']}";
    }
}
