<?php

namespace LaravelPrism;

use Illuminate\Support\ServiceProvider;
use LaravelPrism\Analyzers\AuthenticationAnalyzer;
use LaravelPrism\Analyzers\ControllerAnalyzer;
use LaravelPrism\Analyzers\FormRequestAnalyzer;
use LaravelPrism\Analyzers\ResourceAnalyzer;
use LaravelPrism\Analyzers\RouteAnalyzer;
use LaravelPrism\Console\GenerateDocsCommand;
use LaravelPrism\Generators\ErrorResponseGenerator;
use LaravelPrism\Generators\OpenApiGenerator;
use LaravelPrism\Generators\SchemaGenerator;
use LaravelPrism\Generators\SecuritySchemeGenerator;
use LaravelPrism\Generators\ValidationMessageGenerator;
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
        $this->app->singleton(TypeInference::class);
        $this->app->singleton(RouteAnalyzer::class);
        $this->app->singleton(FormRequestAnalyzer::class);
        $this->app->singleton(ResourceAnalyzer::class);
        $this->app->singleton(ControllerAnalyzer::class);
        $this->app->singleton(SchemaGenerator::class);
        $this->app->singleton(ValidationMessageGenerator::class);
        $this->app->singleton(ErrorResponseGenerator::class);
        $this->app->singleton(AuthenticationAnalyzer::class);
        $this->app->singleton(SecuritySchemeGenerator::class);
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
