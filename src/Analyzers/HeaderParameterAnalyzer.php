<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\DTO\HeaderParameterInfo;
use LaravelSpectrum\Support\HeaderParameterDetector;
use ReflectionMethod;

/**
 * Analyzes controller methods to detect HTTP header parameters.
 */
class HeaderParameterAnalyzer
{
    public function __construct(
        private HeaderParameterDetector $detector
    ) {}

    /**
     * Analyze a controller method and return header parameters as arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function analyze(ReflectionMethod $method): array
    {
        return array_map(
            fn (HeaderParameterInfo $param) => $param->toArray(),
            $this->analyzeToResult($method)
        );
    }

    /**
     * Analyze a controller method and return typed result.
     *
     * @return array<int, HeaderParameterInfo>
     */
    public function analyzeToResult(ReflectionMethod $method): array
    {
        $ast = $this->detector->parseMethod($method);
        $detectedHeaders = $this->detector->detectHeaderCalls($ast);

        $parameters = [];

        foreach ($detectedHeaders as $detected) {
            $parameters[] = new HeaderParameterInfo(
                name: $detected->name,
                required: $this->isRequired($detected),
                type: 'string',
                default: $detected->default,
                source: $detected->method,
                description: $this->generateDescription($detected->name, $detected->isBearerToken()),
                isBearerToken: $detected->isBearerToken(),
                context: $detected->context,
            );
        }

        return $parameters;
    }

    /**
     * Determine if header is required based on detected usage.
     */
    private function isRequired(\LaravelSpectrum\DTO\DetectedHeaderParameter $detected): bool
    {
        // If there's a default value, it's not required
        if ($detected->hasDefault()) {
            return false;
        }

        // hasHeader() checks imply the header is optional
        if ($detected->isHasHeaderCheck()) {
            return false;
        }

        // By default, headers used without defaults are considered optional
        // (unlike body parameters which might be required)
        // This is because controllers often use headers conditionally
        return false;
    }

    /**
     * Generate a description for the header.
     */
    private function generateDescription(string $name, bool $isBearerToken): string
    {
        if ($isBearerToken) {
            return 'Bearer token for authentication';
        }

        // Map common header names to descriptions
        $descriptions = [
            'Idempotency-Key' => 'Unique key to ensure idempotent requests',
            'X-Request-Id' => 'Unique identifier for request tracing',
            'X-Correlation-Id' => 'Correlation ID for distributed tracing',
            'X-Tenant-Id' => 'Tenant identifier for multi-tenant applications',
            'X-Api-Key' => 'API key for authentication',
            'Accept-Language' => 'Preferred language for the response',
            'X-Custom-Auth' => 'Custom authentication header',
        ];

        return $descriptions[$name] ?? "Request header: {$name}";
    }
}
