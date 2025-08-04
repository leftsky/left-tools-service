<?php

namespace Leftsky\AuthClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupCommand extends Command
{
    protected $signature = 'auth-client:setup 
                          {--force : 强制覆盖已有文件}
                          {--skip-config : 跳过发布配置文件}
                          {--env : 自动添加环境变量到.env文件}';
                          
    protected $description = '设置认证客户端包';
    
    /**
     * 执行命令
     *
     * @return int
     */
    public function handle()
    {
        $this->info('正在设置认证客户端包...');
        
        // 1. 发布配置文件
        if (!$this->option('skip-config')) {
            $this->publishConfig();
        }
        
        // 2. 添加环境变量
        if ($this->option('env')) {
            $this->addEnvVariables();
        }
        
        // 3. 显示下一步操作
        $this->info('设置完成! 接下来您需要:');
        $this->info('1. 配置您的.env文件，设置认证服务器的URL和凭据');
        $this->info('2. 根据需要修改配置文件config/auth-client.php');
        
        return 0;
    }
    
    /**
     * 发布配置文件
     *
     * @return void
     */
    protected function publishConfig()
    {
        $params = ['--provider' => 'Leftsky\AuthClient\AuthClientServiceProvider'];
        
        if ($this->option('force')) {
            $params['--force'] = true;
        }
        
        $this->call('vendor:publish', array_merge($params, [
            '--tag' => 'auth-client-config',
        ]));
    }
    
    /**
     * 添加环境变量到.env文件
     *
     * @return void
     */
    protected function addEnvVariables()
    {
        $envFile = $this->laravel->environmentFilePath();
        
        if (!File::exists($envFile)) {
            $this->error('.env文件不存在!');
            return;
        }
        
        $envContents = File::get($envFile);
        
        // 定义要添加的环境变量
        $variables = [
            'AUTH_SERVER_URL' => 'http://auth.example.com',
            'AUTH_CLIENT_ID' => 'client-id-here',
            'AUTH_CLIENT_SECRET' => 'client-secret-here',
            'AUTH_API_ENABLED' => 'true',
            'AUTH_SSO_ENABLED' => 'false',
            'AUTH_CACHE_ENABLED' => 'true',
        ];
        
        // 检查环境变量是否已存在
        foreach ($variables as $key => $value) {
            if (!preg_match("/^{$key}=/m", $envContents)) {
                File::append($envFile, "\n{$key}={$value}");
                $this->info("已添加环境变量 {$key}");
            }
        }
    }
}
