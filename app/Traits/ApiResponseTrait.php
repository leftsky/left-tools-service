<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * 返回成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $code HTTP状态码
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data = [], string $message = '操作成功', int $code = 200)
    {
        return response()->json([
            'code' => 1,
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * 返回错误响应
     *
     * @param string $message 错误消息
     * @param int $code HTTP状态码
     * @param mixed $errors 详细错误信息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message = '操作失败', int $code = 400, $errors = null)
    {
        $response = [
            'code' => 0,
            'status' => 'error',
            'message' => $message
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
    
    /**
     * 返回验证失败响应
     *
     * @param mixed $errors 验证错误信息
     * @param string $message 错误消息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationError($errors, string $message = '验证失败')
    {
        return $this->error($message, 422, $errors);
    }
    
    /**
     * 返回未授权响应
     *
     * @param string $message 未授权消息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorized(string $message = '未授权访问')
    {
        return $this->error($message, 401);
    }
    
    /**
     * 返回禁止访问响应
     *
     * @param string $message 禁止访问消息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbidden(string $message = '禁止访问')
    {
        return $this->error($message, 403);
    }
    
    /**
     * 返回资源不存在响应
     *
     * @param string $message 资源不存在消息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFound(string $message = '资源不存在')
    {
        return $this->error($message, 404);
    }
    
    /**
     * 返回服务器错误响应
     *
     * @param string $message 服务器错误消息
     * @param mixed $errors 详细错误信息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function serverError(string $message = '服务器错误', $errors = null)
    {
        return $this->error($message, 500, $errors);
    }
} 