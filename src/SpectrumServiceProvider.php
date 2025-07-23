<?php

namespace LaravelSpectrum;

use Illuminate\Support\ServiceProvider;
use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\FractalTransformerAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Analyzers\PaginationAnalyzer;
use LaravelSpectrum\Analyzers\QueryParameterAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Console\CacheCommand;
use LaravelSpectrum\Console\GenerateDocsCommand;
use LaravelSpectrum\Console\WatchCommand;
use LaravelSpectrum\Generators\ErrorResponseGenerator;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Generators\PaginationSchemaGenerator;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Generators\SecuritySchemeGenerator;
use LaravelSpectrum\Generators\ValidationMessageGenerator;
use LaravelSpectrum\Services\FileWatcher;
use LaravelSpectrum\Services\LiveReloadServer;
use LaravelSpectrum\Support\FieldNameInference;
use LaravelSpectrum\Support\PaginationDetector;
use LaravelSpectrum\Support\QueryParameterDetector;
use LaravelSpectrum\Support\QueryParameterTypeInference;
use LaravelSpectrum\Support\TypeInference;

class SpectrumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/spectrum.php',
            'spectrum'
        );

        // シングルトンとして登録
        $this->app->singleton(DocumentationCache::class);
        $this->app->singleton(TypeInference::class);
        $this->app->singleton(FieldNameInference::class);
        $this->app->singleton(PaginationDetector::class);
        $this->app->singleton(QueryParameterDetector::class);
        $this->app->singleton(QueryParameterTypeInference::class);
        $this->app->singleton(RouteAnalyzer::class);
        $this->app->singleton(FormRequestAnalyzer::class);
        $this->app->singleton(InlineValidationAnalyzer::class);
        $this->app->singleton(PaginationAnalyzer::class);
        $this->app->singleton(QueryParameterAnalyzer::class);
        $this->app->singleton(ResourceAnalyzer::class);
        $this->app->singleton(FractalTransformerAnalyzer::class);
        $this->app->singleton(EnumAnalyzer::class);
        $this->app->singleton(ControllerAnalyzer::class);
        $this->app->singleton(SchemaGenerator::class);
        $this->app->singleton(PaginationSchemaGenerator::class);
        $this->app->singleton(ValidationMessageGenerator::class);
        $this->app->singleton(ErrorResponseGenerator::class);
        $this->app->singleton(AuthenticationAnalyzer::class);
        $this->app->singleton(SecuritySchemeGenerator::class);
        $this->app->singleton(ExampleValueFactory::class);
        $this->app->singleton(ExampleGenerator::class);
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
            __DIR__.'/../config/spectrum.php' => config_path('spectrum.php'),
        ], 'spectrum-config');

        // ビューの登録
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'spectrum');

        // ビューの公開
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/spectrum'),
        ], 'spectrum-views');
    }
}
