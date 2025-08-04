<?php

namespace Leftsky\AuthClient\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserSyncService
{
    /**
     * 根据SSO用户查找或创建本地用户
     *
     * @param array $ssoUser SSO用户数据
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function findOrCreateUser(array $ssoUser)
    {
        // 获取配置的用户模型
        $userModel = config('auth-client.sso.user_model', \App\Models\User::class);

        // 获取配置的用户查找字段
        $findBy = config('auth-client.sso.user_find_by', 'email');

        // 尝试查找用户
        $user = $userModel::where($findBy, $ssoUser[$findBy] ?? null)->first();

        // 如果id不相等，则删除用户
        if ($user && $user->id !== $ssoUser['id']) {
            $user->delete();
            $user = null;
        }

        // 如果用户不存在且配置允许创建
        if (!$user && config('auth-client.sso.create_missing_users', false)) {
            $user = $this->createUser($userModel, $ssoUser);
        }

        // 如果用户存在且配置允许同步属性
        if ($user && config('auth-client.sso.sync_user_attributes', true)) {
            $this->syncUserAttributes($user, $ssoUser);
        }

        return $user;
    }

    /**
     * 创建新用户
     *
     * @param string $userModel 用户模型类名
     * @param array $ssoUser SSO用户数据
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function createUser($userModel, array $ssoUser)
    {
        // 获取映射配置
        $attributeMap = config('auth-client.sso.attribute_map', [
            'name' => 'name',
            'email' => 'email',
            'phone_number' => 'phone_number',
        ]);

        $userData = [];
        foreach ($attributeMap as $localField => $ssoField) {
            $userData[$localField] = $ssoUser[$ssoField] ?? null;
        }

        // 为新用户设置一个随机密码
        $userData['password'] = bcrypt(Str::random(16));

        // 创建用户实例
        $user = new $userModel($userData);
        // 明确设置ID
        $user->id = $ssoUser['id'];
        $user->save();
        
        return $user;
    }

    /**
     * 同步用户属性
     *
     * @param \Illuminate\Database\Eloquent\Model $user 本地用户模型
     * @param array $ssoUser SSO用户数据
     * @return void
     */
    protected function syncUserAttributes(Model $user, array $ssoUser)
    {
        // 获取映射配置
        $attributeMap = config('auth-client.sso.attribute_map', [
            'name' => 'name',
            'email' => 'email',
        ]);

        // 只同步配置了映射的字段
        $needsSave = false;
        foreach ($attributeMap as $localField => $ssoField) {
            if (isset($ssoUser[$ssoField]) && $user->{$localField} !== $ssoUser[$ssoField]) {
                $user->{$localField} = $ssoUser[$ssoField];
                $needsSave = true;
            }
        }

        if ($needsSave) {
            $user->save();
        }
    }
}
