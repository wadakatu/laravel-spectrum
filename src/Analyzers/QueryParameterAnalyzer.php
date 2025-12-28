<?php

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Contracts\Analyzers\ReflectionMethodAnalyzer;
use LaravelSpectrum\DTO\QueryParameterAnalysisResult;
use LaravelSpectrum\DTO\QueryParameterInfo;
use LaravelSpectrum\Support\QueryParameterDetector;
use LaravelSpectrum\Support\QueryParameterTypeInference;
use ReflectionMethod;

class QueryParameterAnalyzer implements ReflectionMethodAnalyzer
{
    public function __construct(
        private QueryParameterDetector $detector,
        private QueryParameterTypeInference $typeInference
    ) {}

    /**
     * Analyze a controller method to detect query parameters.
     *
     * @return array{parameters: array<int, array<string, mixed>>}
     */
    public function analyze(ReflectionMethod $method): array
    {
        return $this->analyzeToResult($method)->toArray();
    }

    /**
     * Analyze a controller method and return a typed result.
     */
    public function analyzeToResult(ReflectionMethod $method): QueryParameterAnalysisResult
    {
        $ast = $this->detector->parseMethod($method);

        if (! $ast) {
            return QueryParameterAnalysisResult::empty();
        }

        $detectedParams = $this->detector->detectRequestCalls($ast);

        $parameters = [];

        foreach ($detectedParams as $param) {
            $parameters[] = new QueryParameterInfo(
                name: $param['name'],
                type: $this->inferType($param),
                required: $this->isRequired($param),
                default: $param['default'] ?? null,
                source: $param['method'],
                description: $this->generateDescription($param['name']),
                enum: $this->detectEnumValues($param),
                validationRules: $param['context']['validation'] ?? null,
                context: $param['context'] ?? [],
            );
        }

        return QueryParameterAnalysisResult::fromParameters($parameters);
    }

    /**
     * Merge query parameters with validation rules.
     *
     * @param  array{parameters: array<int, array<string, mixed>>}  $queryParams
     * @param  array<string, string|array<string>>  $validationRules
     * @return array{parameters: array<int, array<string, mixed>>}
     */
    public function mergeWithValidation(array $queryParams, array $validationRules): array
    {
        $result = QueryParameterAnalysisResult::fromArray($queryParams);

        return $this->mergeWithValidationToResult($result, $validationRules)->toArray();
    }

    /**
     * Merge query parameters with validation rules and return a typed result.
     *
     * @param  array<string, string|array<string>>  $validationRules
     */
    public function mergeWithValidationToResult(
        QueryParameterAnalysisResult $queryParams,
        array $validationRules
    ): QueryParameterAnalysisResult {
        return $queryParams->mergeWithValidation(
            $validationRules,
            fn (array $rules): ?string => $this->typeInference->inferFromValidationRules($rules)
        );
    }

    /**
     * Infer type from parameter information
     */
    private function inferType(array $param): string
    {
        // 1. Method-based type inference
        if ($type = $this->typeInference->inferFromMethod($param['method'])) {
            return $type;
        }

        // 2. Default value type inference
        if (isset($param['default'])) {
            return $this->typeInference->inferFromDefaultValue($param['default']);
        }

        // 3. Context-based type inference
        if (isset($param['context']) && $type = $this->typeInference->inferFromContext($param['context'])) {
            return $type;
        }

        // 4. Fallback to string
        return 'string';
    }

    /**
     * Determine if parameter is required based on usage
     */
    private function isRequired(array $param): bool
    {
        // If has() is used, it suggests the parameter might be required
        if (isset($param['context']['has_check']) && $param['context']['has_check']) {
            return true;
        }

        // If filled() is used, parameter is optional but expected to have value
        if (isset($param['context']['filled_check']) && $param['context']['filled_check']) {
            return false;
        }

        // Default to optional
        return false;
    }

    /**
     * Detect enum values from parameter context
     */
    private function detectEnumValues(array $param): ?array
    {
        if (! isset($param['context']['enum_values'])) {
            return null;
        }

        return $param['context']['enum_values'];
    }

    /**
     * Generate human-readable description from parameter name
     */
    private function generateDescription(string $name): string
    {
        // Convert snake_case to Title Case
        $words = explode('_', $name);
        $words = array_map(function ($word) {
            // Special handling for common abbreviations
            $upperWords = ['id', 'api', 'url', 'ip'];
            if (in_array(strtolower($word), $upperWords)) {
                return strtoupper($word);
            }

            return ucfirst($word);
        }, $words);

        return implode(' ', $words);
    }
}
