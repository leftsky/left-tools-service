# API响应格式说明

## 统一响应格式

所有API接口都使用统一的响应格式，包含以下字段：

### 成功响应格式
```json
{
    "code": 1,
    "status": "success",
    "message": "操作成功",
    "data": {
        // 具体数据
    }
}
```

### 错误响应格式
```json
{
    "code": 0,
    "status": "error",
    "message": "错误信息",
    "errors": {
        // 详细错误信息（可选）
    }
}
```

## 字段说明

- **code**: 业务状态码
  - `1`: 成功
  - `0`: 失败
- **status**: 状态标识
  - `"success"`: 成功
  - `"error"`: 错误
- **message**: 响应消息
- **data**: 响应数据（成功时）
- **errors**: 错误详情（失败时，可选）

## HTTP状态码

- `200`: 成功
- `400`: 请求错误
- `401`: 未授权
- `403`: 禁止访问
- `404`: 资源不存在
- `422`: 验证失败
- `500`: 服务器错误

## 小程序登录接口示例

### 成功响应
```json
{
    "code": 1,
    "status": "success",
    "message": "登录成功",
    "data": {
        "token": "1|abc123...",
        "user": {
            "id": 1,
            "name": "微信用户_12345678",
            "email": "openid123@wechat.local",
            "phone": null,
            "weixin_mini_openid": "openid123",
            "weixin_unionid": "unionid123",
            "email_verified_at": null,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    }
}
```

### 验证失败响应
```json
{
    "code": 0,
    "status": "error",
    "message": "参数错误",
    "errors": {
        "code": ["code字段是必需的"]
    }
}
```

### 微信登录失败响应
```json
{
    "code": 0,
    "status": "error",
    "message": "微信登录失败: code无效"
}
```

## 使用EasyWeChat的优势

1. **更简洁的代码**: 使用EasyWeChat库替代原生HTTP请求
2. **更好的错误处理**: EasyWeChat提供统一的错误处理机制
3. **配置管理**: 通过配置文件统一管理微信相关配置
4. **扩展性**: 支持微信公众号、开放平台等其他微信服务
5. **维护性**: 减少重复代码，提高代码可维护性

## 环境变量配置

```env
# 微信小程序配置
WECHAT_MINI_APP_ID=your_mini_app_id
WECHAT_MINI_APP_SECRET=your_mini_app_secret

# 可选配置
WECHAT_MINI_APP_TOKEN=your_token
WECHAT_MINI_APP_AES_KEY=your_aes_key
WECHAT_LOG_LEVEL=debug
``` 