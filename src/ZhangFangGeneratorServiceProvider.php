<?php

namespace InfyOm\Generator;

use Illuminate\Support\ServiceProvider;
use ZhangFang\Generator\Commands\APIScaffoldGeneratorCommand;
use ZhangFang\Generator\Commands\RollbackGeneratorCommand;
use ZhangFang\Generator\Commands\Scaffold\ScaffoldGeneratorCommand;

class ZhangFangGeneratorServiceProvider extends ServiceProvider
{
    /**
     * 应用加载时添加配置文件
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/laravel_generator.php';

        $this->publishes([
            $configPath => config_path('infyom/laravel_generator.php'),
        ]);
    }

    /**
     * 服务注册注册命令
     */
    public function register()
    {
        $this->app->singleton('infyom.scaffold', function ($app) {
            return new ScaffoldGeneratorCommand();
        });

        $this->commands([
            'infyom.scaffold',
        ]);
    }
}
