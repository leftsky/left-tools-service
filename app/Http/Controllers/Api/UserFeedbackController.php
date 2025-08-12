<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFeedback;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: '用户反馈',
    description: '用户反馈相关接口'
)]
class UserFeedbackController extends Controller
{
    use ApiResponseTrait;

    #[OA\Post(
        path: '/api/feedback',
        summary: '提交用户反馈',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'title', 'content'],
                properties: [
                    new OA\Property(property: 'type', type: 'integer', description: '反馈类型：1-错误报告，2-功能建议，3-改进建议，4-其他', example: 1),
                    new OA\Property(property: 'title', type: 'string', description: '反馈标题', example: '发现一个bug'),
                    new OA\Property(property: 'content', type: 'string', description: '反馈内容', example: '详细描述问题...'),
                    new OA\Property(property: 'contact_phone', type: 'string', description: '联系电话', example: '13800138000'),
                    new OA\Property(property: 'attachments', type: 'array', description: '附件信息', items: new OA\Items(type: 'object'))
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '提交成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '反馈提交成功'),
                        new OA\Property(property: 'data', type: 'object')
                    ]
                )
            ),
            new OA\Response(response: 422, description: '参数验证失败')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|integer|in:1,2,3,4',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'contact_phone' => 'nullable|string|max:20',
            'attachments' => 'nullable|array',
        ], [
            'type.required' => '反馈类型不能为空',
            'type.in' => '反馈类型无效',
            'title.required' => '反馈标题不能为空',
            'title.max' => '反馈标题不能超过255个字符',
            'content.required' => '反馈内容不能为空',
            'contact_phone.max' => '联系电话不能超过20个字符',
            'attachments.array' => '附件信息格式错误',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $data['user_id'] = Auth::id();
        $data['status'] = UserFeedback::STATUS_PENDING;

        $feedback = UserFeedback::create($data);

        return $this->success($feedback, '反馈提交成功');
    }

    #[OA\Get(
        path: '/api/feedback',
        summary: '获取用户反馈列表',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'type', in: 'query', description: '反馈类型', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '获取成功'),
                        new OA\Property(property: 'data', type: 'object')
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = UserFeedback::with('user:id,name,email')
            ->when(Auth::check(), function ($q) {
                return $q->where('user_id', Auth::id());
            })
            ->when($request->type, function ($q, $type) {
                return $q->where('type', $type);
            })
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->orderBy('created_at', 'desc');

        $perPage = min($request->get('per_page', 15), 50);
        $feedbacks = $query->paginate($perPage);

        return $this->success($feedbacks, '获取成功');
    }

    #[OA\Get(
        path: '/api/feedback/{id}',
        summary: '获取反馈详情',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '反馈ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '获取成功'),
                        new OA\Property(property: 'data', type: 'object')
                    ]
                )
            ),
            new OA\Response(response: 404, description: '反馈不存在')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $feedback = UserFeedback::with('user:id,name,email')
            ->when(Auth::check(), function ($q) {
                return $q->where('user_id', Auth::id());
            })
            ->find($id);

        if (!$feedback) {
            return $this->error('反馈不存在', 404);
        }

        return $this->success($feedback, '获取成功');
    }

    #[OA\Get(
        path: '/api/feedback/types',
        summary: '获取反馈类型选项',
        responses: [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '获取成功'),
                        new OA\Property(property: 'data', type: 'object')
                    ]
                )
            )
        ]
    )]
    public function types(): JsonResponse
    {
        return $this->success([
            'types' => UserFeedback::getTypeOptions(),
            'statuses' => UserFeedback::getStatusOptions(),
        ], '获取成功');
    }
}