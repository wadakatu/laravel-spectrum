<?php

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Support\QueryParameterDetector;
use LaravelSpectrum\Support\QueryParameterTypeInference;
use ReflectionMethod;

class QueryParameterAnalyzer
{
    public function __construct(
        private QueryParameterDetector $detector,
        private QueryParameterTypeInference $typeInference
    ) {}

    /**
     * Analyze a controller method to detect query parameters
     */
    public function analyze(ReflectionMethod $method): array
    {
        $ast = $this->detector->parseMethod($method);
        
        if (!$ast) {
            return ['parameters' => []];
        }
        
        $detectedParams = $this->detector->detectRequestCalls($ast);
        
        $parameters = [];
        
        foreach ($detectedParams as $param) {
            $parameter = [
                'name' => $param['name'],
                'type' => $this->inferType($param),
                'required' => $this->isRequired($param),
                'default' => $param['default'] ?? null,
                'source' => $param['method'],
                'context' => $param['context'] ?? [],
            ];
            
            // Generate description from parameter name
            $parameter['description'] = $this->generateDescription($param['name']);
            
            // Check for enum values
            if ($enum = $this->detectEnumValues($param)) {
                $parameter['enum'] = $enum;
            }
            
            // Check for validation constraints from context
            if (isset($param['context']['validation'])) {
                $parameter['validation_rules'] = $param['context']['validation'];
            }
            
            $parameters[] = $parameter;
        }
        
        return ['parameters' => $parameters];
    }

    /**
     * Merge query parameters with validation rules
     */
    public function mergeWithValidation(array $queryParams, array $validationRules): array
    {
        $parameters = $queryParams['parameters'] ?? [];
        $paramsByName = [];
        
        // Index existing parameters by name
        foreach ($parameters as $param) {
            $paramsByName[$param['name']] = $param;
        }
        
        // Process validation rules
        foreach ($validationRules as $field => $rules) {
            // Skip if it's not a query parameter (e.g., nested fields)
            if (str_contains($field, '.') || str_contains($field, '*')) {
                continue;
            }
            
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;
            
            if (isset($paramsByName[$field])) {
                // Update existing parameter with validation info
                $paramsByName[$field]['validation_rules'] = $rulesArray;
                $paramsByName[$field]['type'] = $this->typeInference->inferFromValidationRules($rulesArray) 
                    ?? $paramsByName[$field]['type'];
                $paramsByName[$field]['required'] = in_array('required', $rulesArray);
            } else {
                // Add new parameter from validation
                $paramsByName[$field] = [
                    'name' => $field,
                    'type' => $this->typeInference->inferFromValidationRules($rulesArray) ?? 'string',
                    'required' => in_array('required', $rulesArray),
                    'default' => null,
                    'source' => 'validation',
                    'validation_rules' => $rulesArray,
                    'description' => $this->generateDescription($field),
                ];
            }
        }
        
        return ['parameters' => array_values($paramsByName)];
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
        if (!isset($param['context']['enum_values'])) {
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
        $words = array_map(function($word) {
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