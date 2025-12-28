<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\DTO\OpenApiRequestBody;

/**
 * Generates OpenAPI request body definitions from validation rules.
 *
 * Handles:
 * - FormRequest validation rules
 * - Inline controller validation
 * - Conditional validation rules
 * - File upload parameters
 */
class RequestBodyGenerator
{
    public function __construct(
        protected FormRequestAnalyzer $requestAnalyzer,
        protected InlineValidationAnalyzer $inlineValidationAnalyzer,
        protected SchemaGenerator $schemaGenerator
    ) {}

    /**
     * Generate request body for an API operation.
     *
     * @param  array{formRequest?: string, inlineValidation?: array}  $controllerInfo  Controller analysis result
     * @param  array  $route  Route information
     * @return OpenApiRequestBody|null Request body DTO or null if no validation
     */
    public function generate(array $controllerInfo, array $route): ?OpenApiRequestBody
    {
        $parameters = [];
        $conditionalRules = null;

        // FormRequest validation
        if (! empty($controllerInfo['formRequest'])) {
            $analysisResult = $this->requestAnalyzer->analyzeWithConditionalRules($controllerInfo['formRequest']);

            if (! empty($analysisResult['conditional_rules']['rules_sets'])) {
                $conditionalRules = $analysisResult['conditional_rules'];
                $parameters = $analysisResult['parameters'] ?? [];
            } else {
                $parameters = $this->requestAnalyzer->analyze($controllerInfo['formRequest']);
            }
        }
        // Inline validation
        elseif (! empty($controllerInfo['inlineValidation'])) {
            $parameters = $this->inlineValidationAnalyzer->generateParameters(
                $controllerInfo['inlineValidation']
            );
        }

        if (empty($parameters) && empty($conditionalRules)) {
            return null;
        }

        // Check for file uploads
        if ($this->hasFileUploadParameters($parameters)) {
            return $this->generateFileUploadRequestBody($parameters);
        }

        // Generate schema
        $schema = $this->generateSchema($parameters, $conditionalRules);

        // Check if schema already has content structure
        if (isset($schema['content'])) {
            return new OpenApiRequestBody(
                content: $schema['content'],
                required: true,
            );
        }

        return new OpenApiRequestBody(
            content: [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
            required: true,
        );
    }

    /**
     * Check if parameters contain file uploads.
     *
     * @param  array  $parameters  Request parameters
     * @return bool True if file upload parameters exist
     */
    protected function hasFileUploadParameters(array $parameters): bool
    {
        foreach ($parameters as $param) {
            if (isset($param['type']) && $param['type'] === 'file') {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate request body for file upload endpoints.
     *
     * @param  array  $parameters  Request parameters
     * @return OpenApiRequestBody|null Request body with multipart/form-data content
     */
    protected function generateFileUploadRequestBody(array $parameters): ?OpenApiRequestBody
    {
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        if (! isset($schema['content'])) {
            return null;
        }

        $description = $this->generateFileUploadDescription($parameters);

        return new OpenApiRequestBody(
            content: $schema['content'],
            required: true,
            description: $description ?: null,
        );
    }

    /**
     * Generate schema from parameters, handling conditional rules.
     *
     * @param  array  $parameters  Request parameters
     * @param  array|null  $conditionalRules  Conditional validation rules
     * @return array Generated schema
     */
    protected function generateSchema(array $parameters, ?array $conditionalRules): array
    {
        if ($conditionalRules && ! empty($conditionalRules['rules_sets'])) {
            return $this->schemaGenerator->generateConditionalSchema($conditionalRules, $parameters);
        }

        return $this->schemaGenerator->generateFromParameters($parameters);
    }

    /**
     * Generate description for file upload endpoints.
     *
     * @param  array  $parameters  Request parameters
     * @return string Description text
     */
    protected function generateFileUploadDescription(array $parameters): string
    {
        $fileParams = array_filter($parameters, fn ($p) => isset($p['type']) && $p['type'] === 'file');

        if (empty($fileParams)) {
            return '';
        }

        $parts = ['This endpoint accepts file uploads.'];

        foreach ($fileParams as $param) {
            if (isset($param['file_info']['multiple']) && $param['file_info']['multiple']) {
                $parts[] = "- {$param['name']}: Multiple files allowed";
            }
        }

        return implode("\n", $parts);
    }
}
