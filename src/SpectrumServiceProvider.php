<?php

namespace LaravelSpectrum;

use Illuminate\Support\ServiceProvider;
use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\CallbackAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\FractalTransformerAnalyzer;
use LaravelSpectrum\Analyzers\HeaderParameterAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Analyzers\PaginationAnalyzer;
use LaravelSpectrum\Analyzers\QueryParameterAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Analyzers\ResponseAnalyzer;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Analyzers\Support\AnonymousClassAnalyzer;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Analyzers\Support\FormatInferrer;
use LaravelSpectrum\Analyzers\Support\FormRequestAstExtractor;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Analyzers\Support\RuleRequirementAnalyzer;
use LaravelSpectrum\Analyzers\Support\ValidationDescriptionGenerator;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Console\CacheCommand;
use LaravelSpectrum\Console\Commands\ExportInsomniaCommand;
use LaravelSpectrum\Console\Commands\ExportPostmanCommand;
use LaravelSpectrum\Console\Commands\MockServerCommand;
use LaravelSpectrum\Console\Commands\OptimizedGenerateCommand;
use LaravelSpectrum\Console\GenerateDocsCommand;
use LaravelSpectrum\Console\WatchCommand;
use LaravelSpectrum\Converters\OpenApi31Converter;
use LaravelSpectrum\Exporters\InsomniaExporter;
use LaravelSpectrum\Exporters\PostmanExporter;
use LaravelSpectrum\Formatters\InsomniaFormatter;
use LaravelSpectrum\Formatters\PostmanFormatter;
use LaravelSpectrum\Formatters\RequestExampleFormatter;
use LaravelSpectrum\Generators\CallbackGenerator;
use LaravelSpectrum\Generators\ErrorResponseGenerator;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Generators\OperationMetadataGenerator;
use LaravelSpectrum\Generators\PaginationSchemaGenerator;
use LaravelSpectrum\Generators\ParameterGenerator;
use LaravelSpectrum\Generators\RequestBodyGenerator;
use LaravelSpectrum\Generators\ResponseSchemaGenerator;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Generators\SecuritySchemeGenerator;
use LaravelSpectrum\Generators\TagGenerator;
use LaravelSpectrum\Generators\TagGroupGenerator;
use LaravelSpectrum\Generators\ValidationMessageGenerator;
use LaravelSpectrum\Services\FileWatcher;
use LaravelSpectrum\Services\LiveReloadServer;
use LaravelSpectrum\Support\CollectionAnalyzer;
use LaravelSpectrum\Support\Example\FieldPatternRegistry;
use LaravelSpectrum\Support\FieldNameInference;
use LaravelSpectrum\Support\HeaderParameterDetector;
use LaravelSpectrum\Support\ModelSchemaExtractor;
use LaravelSpectrum\Support\PaginationDetector;
use LaravelSpectrum\Support\QueryParameterDetector;
use LaravelSpectrum\Support\QueryParameterTypeInference;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class SpectrumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/spectrum.php',
            'spectrum'
        );

        // PHP-Parser のシングルトン登録（AST解析で共有）
        $this->app->singleton(Parser::class, function () {
            return (new ParserFactory)->createForNewestSupportedVersion();
        });

        // AST関連のシングルトン登録
        $this->app->singleton(PrettyPrinter\Standard::class);
        $this->app->singleton(AstHelper::class);
        $this->app->singleton(FormRequestAstExtractor::class);
        $this->app->singleton(RuleRequirementAnalyzer::class);
        $this->app->singleton(FormatInferrer::class);
        $this->app->singleton(ValidationDescriptionGenerator::class);
        $this->app->singleton(ParameterBuilder::class);
        $this->app->singleton(AnonymousClassAnalyzer::class);

        // シングルトンとして登録
        $this->app->singleton(DocumentationCache::class);
        $this->app->singleton(TypeInference::class);
        $this->app->singleton(FieldPatternRegistry::class);
        $this->app->singleton(FieldNameInference::class);
        $this->app->singleton(PaginationDetector::class);
        $this->app->singleton(QueryParameterDetector::class);
        $this->app->singleton(HeaderParameterDetector::class);
        $this->app->singleton(QueryParameterTypeInference::class);
        $this->app->singleton(RouteAnalyzer::class);
        $this->app->singleton(FormRequestAnalyzer::class);
        $this->app->singleton(InlineValidationAnalyzer::class);
        $this->app->singleton(PaginationAnalyzer::class);
        $this->app->singleton(QueryParameterAnalyzer::class);
        $this->app->singleton(HeaderParameterAnalyzer::class);
        $this->app->singleton(ResourceAnalyzer::class);
        $this->app->singleton(FractalTransformerAnalyzer::class);
        $this->app->singleton(EnumAnalyzer::class);
        $this->app->singleton(ModelSchemaExtractor::class);
        $this->app->singleton(CollectionAnalyzer::class);
        $this->app->singleton(ResponseAnalyzer::class);
        $this->app->singleton(CallbackAnalyzer::class, function ($app) {
            return new CallbackAnalyzer(
                configCallbacks: config('spectrum.callbacks', []),
            );
        });
        $this->app->singleton(ControllerAnalyzer::class);
        $this->app->singleton(SchemaGenerator::class);
        $this->app->singleton(PaginationSchemaGenerator::class);
        $this->app->singleton(ResponseSchemaGenerator::class);
        $this->app->singleton(ValidationMessageGenerator::class);
        $this->app->singleton(ErrorResponseGenerator::class);
        $this->app->singleton(AuthenticationAnalyzer::class);
        $this->app->singleton(SecuritySchemeGenerator::class);
        $this->app->singleton(ExampleValueFactory::class);
        $this->app->singleton(ExampleGenerator::class);
        $this->app->singleton(TagGenerator::class);
        $this->app->singleton(TagGroupGenerator::class);
        $this->app->singleton(OperationMetadataGenerator::class);
        $this->app->singleton(ParameterGenerator::class);
        $this->app->singleton(RequestBodyGenerator::class);
        $this->app->singleton(CallbackGenerator::class);
        $this->app->singleton(OpenApi31Converter::class);
        $this->app->singleton(OpenApiGenerator::class);
        $this->app->singleton(FileWatcher::class);
        $this->app->singleton(LiveReloadServer::class);

        // Export services
        $this->app->singleton(PostmanExporter::class);
        $this->app->singleton(InsomniaExporter::class);
        $this->app->singleton(PostmanFormatter::class);
        $this->app->singleton(InsomniaFormatter::class);
        $this->app->singleton(RequestExampleFormatter::class);

        // コマンドの登録
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocsCommand::class,
                CacheCommand::class,
                WatchCommand::class,
                OptimizedGenerateCommand::class,
                ExportPostmanCommand::class,
                ExportInsomniaCommand::class,
                MockServerCommand::class,
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
