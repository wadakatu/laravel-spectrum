<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

/**
 * Type inference for query parameters.
 *
 * Provides type inference from Request methods, default values,
 * context, and validation rules.
 */
class QueryParameterTypeInference
{
    protected ValidationRuleTypeMapper $ruleTypeMapper;

    public function __construct(?ValidationRuleTypeMapper $ruleTypeMapper = null)
    {
        $this->ruleTypeMapper = $ruleTypeMapper ?? new ValidationRuleTypeMapper;
    }

    /**
     * Infer type from Request method name.
     */
    public function inferFromMethod(string $methodName): ?string
    {
        return match ($methodName) {
            'boolean', 'bool' => 'boolean',
            'integer', 'int' => 'integer',
            'float', 'double' => 'number',
            'string' => 'string',
            'array' => 'array',
            'date' => 'string', // with format: date
            'json' => 'object',
            default => null
        };
    }

    /**
     * Infer type from default value.
     */
    public function inferFromDefaultValue(mixed $defaultValue): string
    {
        if (is_bool($defaultValue)) {
            return 'boolean';
        }

        if (is_int($defaultValue)) {
            return 'integer';
        }

        if (is_float($defaultValue)) {
            return 'number';
        }

        if (is_array($defaultValue)) {
            return 'array';
        }

        if (is_object($defaultValue)) {
            return 'object';
        }

        return 'string';
    }

    /**
     * Infer type from usage context.
     *
     * @param  array<string, mixed>  $context
     */
    public function inferFromContext(array $context): ?string
    {
        // Check for numeric operations
        if (isset($context['numeric_operation'])) {
            return in_array($context['numeric_operation'], ['float', 'double'], true) ? 'number' : 'integer';
        }

        // Check for array operations
        if (isset($context['array_operation'])) {
            return 'array';
        }

        // Check for boolean context
        if (isset($context['boolean_context'])) {
            return 'boolean';
        }

        // Check for date operations
        if (isset($context['date_operation'])) {
            return 'string'; // with format annotation
        }

        // Check WHERE clause usage
        if (isset($context['where_clause'])) {
            $column = $context['where_clause']['column'] ?? '';

            // Common patterns
            if (str_ends_with($column, '_id') || str_ends_with($column, '_count')) {
                return 'integer';
            }

            if (str_ends_with($column, '_at')) {
                return 'string'; // datetime
            }

            if (in_array($column, ['price', 'amount', 'total', 'balance'], true)) {
                return 'number';
            }

            if (in_array($column, ['active', 'enabled', 'deleted', 'published'], true)) {
                return 'boolean';
            }
        }

        return null;
    }

    /**
     * Infer type from validation rules.
     *
     * @param  array<mixed>  $rules
     */
    public function inferFromValidationRules(array $rules): ?string
    {
        $type = $this->ruleTypeMapper->inferType($rules);

        // Return null if we got the default 'string' to indicate no specific type was found
        // This preserves the original behavior where null was returned for unrecognized rules
        if ($type === 'string' && ! $this->hasExplicitStringRule($rules)) {
            // Check for enum rule as a fallback
            if ($this->ruleTypeMapper->hasEnumRule($rules)) {
                return 'string';
            }

            return null;
        }

        return $type;
    }

    /**
     * Detect enum values from context.
     *
     * @param  array<string, mixed>  $context
     * @return array<mixed>|null
     */
    public function detectEnumValues(array $context): ?array
    {
        if (isset($context['enum_values']) && is_array($context['enum_values'])) {
            return $context['enum_values'];
        }

        // Check for in_array usage
        if (isset($context['in_array']) && is_array($context['in_array'])) {
            return $context['in_array'];
        }

        // Check for switch/match cases
        if (isset($context['switch_cases']) && is_array($context['switch_cases'])) {
            return $context['switch_cases'];
        }

        return null;
    }

    /**
     * Get format annotation for type.
     *
     * @param  array<string, mixed>  $context
     */
    public function getFormatForType(string $type, array $context = []): ?string
    {
        if ($type === 'string') {
            // Check for date context
            if (isset($context['date_operation']) || isset($context['date_format'])) {
                return 'date-time';
            }

            // Check validation rules for format hints
            if (isset($context['validation_rules']) && is_array($context['validation_rules'])) {
                return $this->ruleTypeMapper->inferFormat($context['validation_rules']);
            }
        }

        return null;
    }

    /**
     * Get constraints from validation rules.
     *
     * @param  array<mixed>  $rules
     * @return array<string, mixed>
     */
    public function getConstraintsFromRules(array $rules): array
    {
        return $this->ruleTypeMapper->extractConstraints($rules);
    }

    /**
     * Check if rules contain enum constraint.
     *
     * @param  array<mixed>  $rules
     */
    public function hasEnumRule(array $rules): bool
    {
        return $this->ruleTypeMapper->hasEnumRule($rules);
    }

    /**
     * Check if rules contain an explicit string rule.
     *
     * @param  array<mixed>  $rules
     */
    private function hasExplicitStringRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $ruleName = explode(':', $rule)[0];
                if (in_array($ruleName, ['string', 'email', 'url', 'uuid', 'ip', 'date', 'date_format'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
