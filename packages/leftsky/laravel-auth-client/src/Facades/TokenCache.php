<?php

namespace Leftsky\AuthClient\Facades;

use Illuminate\Support\Facades\Facade;

class TokenCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'auth-client.token-cache';
    }
} 