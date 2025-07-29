<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\AccessLog;
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
        // 跳过一些不需要记录的请求
        if (!in_array($request->path(), self::$recordedUris)) {
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
                AccessLog::create($data);
            } catch (\Exception $e) {
                Log::error('创建访问记录失败', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        })->afterResponse();
    }
}
