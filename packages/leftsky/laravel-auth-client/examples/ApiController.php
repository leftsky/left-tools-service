<?php

namespace YourApp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    /**
     * 需要认证但不需要特定作用域
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'message' => '您已通过认证'
        ]);
    }
    
    /**
     * 需要管理员作用域
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function admin(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'message' => '您拥有管理员权限'
        ]);
    }
} 