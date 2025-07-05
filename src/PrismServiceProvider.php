<?php

namespace LaravelPrism;

use Illuminate\Support\ServiceProvider;
use LaravelPrism\Support\TypeInference;
use LaravelPrism\Analyzers\RouteAnalyzer;
use LaravelPrism\Analyzers\FormRequestAnalyzer;
use LaravelPrism\Analyzers\ResourceAnalyzer;
use LaravelPrism\Analyzers\ControllerAnalyzer;
use LaravelPrism\Generators\OpenApiGenerator;
use LaravelPrism\Generators\SchemaGenerator;
use LaravelPrism\Console\GenerateDocsCommand;

class PrismServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/prism.php', 
            'prism'
        );
        
        // シングルトンとして登録
        $this->app->singleton(TypeInference::class);
        $this->app->singleton(RouteAnalyzer::class);
        $this->app->singleton(FormRequestAnalyzer::class);
        $this->app->singleton(ResourceAnalyzer::class);
        $this->app->singleton(ControllerAnalyzer::class);
        $this->app->singleton(SchemaGenerator::class);
        $this->app->singleton(OpenApiGenerator::class);
        
        // コマンドの登録
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocsCommand::class,
            ]);
        }
    }
    
    public function boot(): void
    {
        // 設定ファイルの公開
        $this->publishes([
            __DIR__.'/../config/prism.php' => config_path('prism.php'),
        ], 'prism-config');
    }
}