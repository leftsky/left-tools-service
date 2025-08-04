<?php

namespace Leftsky\AuthClient\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Leftsky\AuthClient\OAuth2\AuthServerProvider;
use Leftsky\AuthClient\OAuth2\ResourceOwner;
use Leftsky\AuthClient\AuthClientServiceProvider;
use Leftsky\AuthClient\Facades\SSO;
use Illuminate\Support\Facades\Config;

class OAuth2Test extends TestCase
{
    /**
     * 获取包服务提供者
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            AuthClientServiceProvider::class,
        ];
    }

    /**
     * 获取包别名
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'SSO' => SSO::class,
        ];
    }
    
    /**
     * 定义环境变量
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // 设置测试环境变量
        Config::set('auth-client.auth_server.url', 'http://auth-test.example.com');
        Config::set('auth-client.auth_server.client_id', 'test-client-id');
        Config::set('auth-client.auth_server.client_secret', 'test-client-secret');
        Config::set('auth-client.sso.scopes', ['profile', 'email']);
    }

    /** @test */
    public function oauth2_provider_can_be_instantiated()
    {
        $provider = new AuthServerProvider([
            'clientId' => 'test-client',
            'clientSecret' => 'test-secret',
            'redirectUri' => 'http://example.com/callback',
        ]);
        
        $this->assertInstanceOf(AuthServerProvider::class, $provider);
    }

    /** @test */
    public function resource_owner_can_be_instantiated()
    {
        $resourceOwner = new ResourceOwner([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        $this->assertInstanceOf(ResourceOwner::class, $resourceOwner);
        $this->assertEquals(1, $resourceOwner->getId());
        $this->assertEquals('Test User', $resourceOwner->toArray()['name']);
    }

    /** @test */
    public function oauth2_provider_service_is_registered()
    {
        $this->assertTrue($this->app->bound('auth-client.oauth2-provider'));
        
        $provider = $this->app->make('auth-client.oauth2-provider');
        $this->assertInstanceOf(AuthServerProvider::class, $provider);
    }

    /** @test */
    public function auth_url_contains_required_parameters()
    {
        // 使用门面获取认证URL
        $authUrl = SSO::getAuthUrl('http://example.com/return');
        
        // 验证URL结构
        $this->assertStringContainsString('client_id=', $authUrl);
        $this->assertStringContainsString('redirect_uri=', $authUrl);
        $this->assertStringContainsString('state=', $authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
    }
} 