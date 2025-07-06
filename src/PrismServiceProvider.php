<?php

namespace LaravelPrism;

use Illuminate\Support\ServiceProvider;
use LaravelPrism\Analyzers\AuthenticationAnalyzer;
use LaravelPrism\Analyzers\ControllerAnalyzer;
use LaravelPrism\Analyzers\FormRequestAnalyzer;
use LaravelPrism\Analyzers\FractalTransformerAnalyzer;
use LaravelPrism\Analyzers\ResourceAnalyzer;
use LaravelPrism\Analyzers\RouteAnalyzer;
use LaravelPrism\Console\CacheCommand;
use LaravelPrism\Console\GenerateDocsCommand;
use LaravelPrism\Console\WatchCommand;
use LaravelPrism\Generators\ErrorResponseGenerator;
use LaravelPrism\Generators\OpenApiGenerator;
use LaravelPrism\Generators\SchemaGenerator;
use LaravelPrism\Generators\SecuritySchemeGenerator;
use LaravelPrism\Generators\ValidationMessageGenerator;
use LaravelPrism\Services\DocumentationCache;
use LaravelPrism\Services\FileWatcher;
use LaravelPrism\Services\LiveReloadServer;
use LaravelPrism\Support\TypeInference;

class PrismServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/prism.php',
            'prism'
        );

        // シングルトンとして登録
        $this->app->singleton(DocumentationCache::class);
        $this->app->singleton(TypeInference::class);
        $this->app->singleton(RouteAnalyzer::class);
        $this->app->singleton(FormRequestAnalyzer::class);
        $this->app->singleton(ResourceAnalyzer::class);
        $this->app->singleton(FractalTransformerAnalyzer::class);
        $this->app->singleton(ControllerAnalyzer::class);
        $this->app->singleton(SchemaGenerator::class);
        $this->app->singleton(ValidationMessageGenerator::class);
        $this->app->singleton(ErrorResponseGenerator::class);
        $this->app->singleton(AuthenticationAnalyzer::class);
        $this->app->singleton(SecuritySchemeGenerator::class);
        $this->app->singleton(OpenApiGenerator::class);
        $this->app->singleton(FileWatcher::class);
        $this->app->singleton(LiveReloadServer::class);

        // コマンドの登録
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocsCommand::class,
                CacheCommand::class,
                WatchCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // 設定ファイルの公開
        $this->publishes([
            __DIR__.'/../config/prism.php' => config_path('prism.php'),
        ], 'prism-config');

        // ビューの登録
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'prism');

        // ビューの公開
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/prism'),
        ], 'prism-views');
    }
}
