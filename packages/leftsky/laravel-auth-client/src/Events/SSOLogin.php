<?php

namespace Leftsky\AuthClient\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class SSOLogin
{
    use Dispatchable, SerializesModels;
    
    /**
     * 用户数据
     *
     * @var array
     */
    public $user;
    
    /**
     * 创建新的事件实例
     *
     * @param array $user
     * @return void
     */
    public function __construct(array $user)
    {
        $this->user = $user;
    }
}
