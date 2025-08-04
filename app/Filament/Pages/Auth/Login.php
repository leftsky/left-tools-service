<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Leftsky\AuthClient\Facades\SSO;
use Illuminate\Support\Facades\Auth;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (! Auth::check()) {
            // 用户未登录时直接重定向到SSO
            $ssoUrl = SSO::getAuthUrl(route('filament.admin.pages.dashboard'));
            $this->redirect($ssoUrl);
            return;
        }
        
        // 用户已登录，继续父类的mount逻辑
        parent::mount();
    }
    
    public function getTitle(): string|Htmlable
    {
        return '登录';
    }

    protected function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'ssoLoginUrl' => SSO::getAuthUrl(route('filament.admin.pages.dashboard')),
        ];
    }
    
    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }
} 