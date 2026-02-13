<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Attributes\OpenApiResponseLink;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\ResponseLinkInfo;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;
use ReflectionMethod;

/**
 * Analyzes controller methods for OpenAPI response link definitions.
 *
 * Detects links from:
 * 1. PHP 8 OpenApiResponseLink attributes on methods
 * 2. Config-based definitions keyed by Controller@method
 */
class ResponseLinkAnalyzer implements HasErrors
{
    use HasErrorCollection;

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $configLinks  Config-based links keyed by Controller@method
     */
    public function __construct(
        protected array $configLinks = [],
        ?ErrorCollector $errorCollector = null,
    ) {
        $this->initializeErrorCollector($errorCollector);
    }

    /**
     * Analyze a method for response links.
     *
     * @return array<int, ResponseLinkInfo>
     */
    public function analyze(ReflectionMethod $method): array
    {
        $links = $this->detectFromAttributes($method);

        $configKey = $method->getDeclaringClass()->getName().'@'.$method->getName();
        if (isset($this->configLinks[$configKey])) {
            foreach ($this->configLinks[$configKey] as $index => $configLink) {
                try {
                    $links[] = ResponseLinkInfo::fromArray($configLink);
                } catch (\Exception $e) {
                    $this->logError(
                        "Failed to parse config response link for {$configKey}[{$index}]: {$e->getMessage()}",
                        AnalyzerErrorType::AnalysisError,
                        ['config_key' => $configKey, 'index' => $index],
                    );
                }
            }
        }

        return $links;
    }

    /**
     * Detect response links from PHP 8 attributes.
     *
     * @return array<int, ResponseLinkInfo>
     */
    private function detectFromAttributes(ReflectionMethod $method): array
    {
        $links = [];
        $attributes = $method->getAttributes(OpenApiResponseLink::class);

        foreach ($attributes as $attribute) {
            try {
                $instance = $attribute->newInstance();
                $links[] = ResponseLinkInfo::fromArray([
                    'statusCode' => $instance->statusCode,
                    'name' => $instance->name,
                    'operationId' => $instance->operationId,
                    'operationRef' => $instance->operationRef,
                    'parameters' => $instance->parameters,
                    'requestBody' => $instance->requestBody,
                    'description' => $instance->description,
                    'server' => $instance->server,
                ]);
            } catch (\Exception $e) {
                $this->logError(
                    "Failed to parse OpenApiResponseLink attribute on {$method->getDeclaringClass()->getName()}::{$method->getName()}: {$e->getMessage()}",
                    AnalyzerErrorType::AnalysisError,
                    [
                        'class' => $method->getDeclaringClass()->getName(),
                        'method' => $method->getName(),
                    ],
                );
            }
        }

        return $links;
    }
}
