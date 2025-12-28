<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of query parameter analysis.
 *
 * Contains a collection of detected query parameters from controller code analysis.
 */
final readonly class QueryParameterAnalysisResult
{
    /**
     * @param  array<int, QueryParameterInfo>  $parameters  The detected query parameters
     */
    public function __construct(
        public array $parameters = [],
    ) {}

    /**
     * Create an empty result.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Create from an array of QueryParameterInfo objects.
     *
     * @param  array<int, QueryParameterInfo>  $parameters
     */
    public static function fromParameters(array $parameters): self
    {
        return new self($parameters);
    }

    /**
     * Create from legacy array format (for backward compatibility).
     *
     * @param  array{parameters: array<int, array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        $parameters = array_map(
            fn (array $param) => QueryParameterInfo::fromArray($param),
            $data['parameters'] ?? []
        );

        return new self($parameters);
    }

    /**
     * Convert to legacy array format (for backward compatibility).
     *
     * @return array{parameters: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'parameters' => array_map(
                fn (QueryParameterInfo $param) => $param->toArray(),
                $this->parameters
            ),
        ];
    }

    /**
     * Check if any parameters were detected.
     */
    public function hasParameters(): bool
    {
        return count($this->parameters) > 0;
    }

    /**
     * Get the number of parameters.
     */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * Get a parameter by name.
     */
    public function getByName(string $name): ?QueryParameterInfo
    {
        foreach ($this->parameters as $param) {
            if ($param->name === $name) {
                return $param;
            }
        }

        return null;
    }

    /**
     * Get all parameter names.
     *
     * @return array<int, string>
     */
    public function getNames(): array
    {
        return array_map(fn (QueryParameterInfo $param) => $param->name, $this->parameters);
    }

    /**
     * Merge with validation rules.
     *
     * @param  array<string, string|array<string>>  $validationRules
     * @param  callable(array<string>): ?string  $typeInferenceFn
     */
    public function mergeWithValidation(array $validationRules, callable $typeInferenceFn): self
    {
        $paramsByName = [];

        // Index existing parameters by name
        foreach ($this->parameters as $param) {
            $paramsByName[$param->name] = $param;
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
                $param = $paramsByName[$field];
                $inferredType = $typeInferenceFn($rulesArray);

                $paramsByName[$field] = new QueryParameterInfo(
                    name: $param->name,
                    type: $inferredType ?? $param->type,
                    required: in_array('required', $rulesArray),
                    default: $param->default,
                    source: $param->source,
                    description: $param->description,
                    enum: $param->enum,
                    validationRules: $rulesArray,
                    context: $param->context,
                );
            } else {
                // Add new parameter from validation
                $paramsByName[$field] = new QueryParameterInfo(
                    name: $field,
                    type: $typeInferenceFn($rulesArray) ?? 'string',
                    required: in_array('required', $rulesArray),
                    default: null,
                    source: 'validation',
                    description: self::generateDescription($field),
                    validationRules: $rulesArray,
                );
            }
        }

        return new self(array_values($paramsByName));
    }

    /**
     * Generate human-readable description from parameter name.
     */
    private static function generateDescription(string $name): string
    {
        $words = explode('_', $name);
        $words = array_map(function ($word) {
            $upperWords = ['id', 'api', 'url', 'ip'];
            if (in_array(strtolower($word), $upperWords)) {
                return strtoupper($word);
            }

            return ucfirst($word);
        }, $words);

        return implode(' ', $words);
    }
}
