<?php

namespace Leftsky\AuthClient\Facades;

use Illuminate\Support\Facades\Facade;

class Token extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'auth-client.token';
    }
} 