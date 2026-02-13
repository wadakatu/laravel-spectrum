<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Attributes\OpenApiCallback;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\CallbackInfo;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;
use ReflectionMethod;

/**
 * Analyzes controller methods for OpenAPI callback definitions.
 *
 * Detects callbacks from two sources:
 * 1. PHP 8 OpenApiCallback attributes on methods
 * 2. Config-based callback definitions keyed by Controller@method
 */
class CallbackAnalyzer implements HasErrors
{
    use HasErrorCollection;

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $configCallbacks  Config-based callbacks keyed by Controller@method
     */
    public function __construct(
        protected array $configCallbacks = [],
        ?ErrorCollector $errorCollector = null,
    ) {
        $this->initializeErrorCollector($errorCollector);
    }

    /**
     * Analyze a method for callback definitions.
     *
     * @return array<int, CallbackInfo>
     */
    public function analyze(ReflectionMethod $method): array
    {
        $callbacks = [];

        // 1. Detect from PHP 8 attributes
        $callbacks = $this->detectFromAttributes($method);

        // 2. Merge config-based callbacks
        $configKey = $method->getDeclaringClass()->getName().'@'.$method->getName();
        if (isset($this->configCallbacks[$configKey])) {
            foreach ($this->configCallbacks[$configKey] as $configCallback) {
                try {
                    $callbacks[] = CallbackInfo::fromArray($configCallback);
                } catch (\Throwable $e) {
                    $this->logWarning(
                        "Failed to parse config callback for {$configKey}: {$e->getMessage()}",
                        AnalyzerErrorType::AnalysisError,
                        ['config_key' => $configKey],
                    );
                }
            }
        }

        return $callbacks;
    }

    /**
     * Detect callbacks from PHP 8 attributes.
     *
     * @return array<int, CallbackInfo>
     */
    private function detectFromAttributes(ReflectionMethod $method): array
    {
        $callbacks = [];

        $attributes = $method->getAttributes(OpenApiCallback::class);

        foreach ($attributes as $attribute) {
            try {
                $instance = $attribute->newInstance();
                $callbacks[] = new CallbackInfo(
                    name: $instance->name,
                    expression: $instance->expression,
                    method: $instance->method,
                    requestBody: $instance->requestBody,
                    responses: $instance->responses,
                    description: $instance->description,
                    summary: $instance->summary,
                    ref: $instance->ref,
                );
            } catch (\Throwable $e) {
                $this->logWarning(
                    "Failed to parse OpenApiCallback attribute on {$method->getDeclaringClass()->getName()}::{$method->getName()}: {$e->getMessage()}",
                    AnalyzerErrorType::AnalysisError,
                    [
                        'class' => $method->getDeclaringClass()->getName(),
                        'method' => $method->getName(),
                    ],
                );
            }
        }

        return $callbacks;
    }
}
