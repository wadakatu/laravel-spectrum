<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use Illuminate\Support\Str;

/**
 * Maps Laravel validation rules to OpenAPI type information.
 *
 * Centralizes the validation rule → type mapping logic used across
 * TypeInference and QueryParameterTypeInference classes.
 */
final class ValidationRuleTypeMapper
{
    /**
     * Rules that map to 'integer' type.
     *
     * @var array<string>
     */
    private const INTEGER_RULES = [
        'integer',
        'int',
        'digits',
        'digits_between',
    ];

    /**
     * Rules that map to 'number' type.
     *
     * @var array<string>
     */
    private const NUMBER_RULES = [
        'numeric',
        'decimal',
    ];

    /**
     * Rules that map to 'boolean' type.
     *
     * @var array<string>
     */
    private const BOOLEAN_RULES = [
        'boolean',
        'bool',
        'accepted',
        'accepted_if',
        'declined',
        'declined_if',
    ];

    /**
     * Rules that imply 'string' type with a specific format.
     *
     * @var array<string, string>
     */
    private const FORMAT_RULES = [
        'email' => 'email',
        'url' => 'uri',
        'uuid' => 'uuid',
        'ip' => 'ipv4',
        'ipv4' => 'ipv4',
        'ipv6' => 'ipv6',
        'mac_address' => 'mac',
        'date' => 'date',
        'datetime' => 'date-time',
    ];

    /**
     * Rule prefixes that imply date/time handling.
     *
     * @var array<string>
     */
    private const DATE_RULE_PREFIXES = [
        'date_format:',
        'after:',
        'before:',
        'after_or_equal:',
        'before_or_equal:',
        'date_equals:',
    ];

    /**
     * Infer OpenAPI type from validation rules.
     *
     * @param  array<mixed>  $rules  Laravel validation rules
     * @return string OpenAPI type (string, integer, number, boolean, array, object)
     */
    public function inferType(array $rules): string
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $ruleName = $this->extractRuleName($rule);

            // Integer types
            if (in_array($ruleName, self::INTEGER_RULES, true)) {
                return 'integer';
            }

            // Number types
            if (in_array($ruleName, self::NUMBER_RULES, true)) {
                return 'number';
            }

            // Boolean types
            if (in_array($ruleName, self::BOOLEAN_RULES, true)) {
                return 'boolean';
            }

            // Array type
            if ($ruleName === 'array') {
                return 'array';
            }

            // JSON/Object type
            if ($ruleName === 'json') {
                return 'object';
            }

            // Date-related rules (return string with date format)
            if ($this->isDateRule($rule)) {
                return 'string';
            }

            // Format rules (email, url, uuid, etc.)
            if (isset(self::FORMAT_RULES[$ruleName])) {
                return 'string';
            }

            // File types
            if ($ruleName === 'file' || $ruleName === 'image') {
                return 'string';
            }

            // Timezone
            if ($ruleName === 'timezone') {
                return 'string';
            }

            // Explicit string rule
            if ($ruleName === 'string') {
                return 'string';
            }
        }

        return 'string';
    }

    /**
     * Infer OpenAPI format from validation rules.
     *
     * @param  array<mixed>  $rules  Laravel validation rules
     * @return string|null OpenAPI format (email, uri, uuid, date, date-time, etc.) or null
     */
    public function inferFormat(array $rules): ?string
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $ruleName = $this->extractRuleName($rule);

            // Check format rules
            if (isset(self::FORMAT_RULES[$ruleName])) {
                return self::FORMAT_RULES[$ruleName];
            }

            // Date format rule
            if (Str::startsWith($rule, 'date_format:')) {
                return 'date-time';
            }

            // Other date-related rules
            if ($this->isDateRule($rule) && $ruleName !== 'date') {
                return 'date-time';
            }
        }

        return null;
    }

    /**
     * Check if rules contain an enum rule.
     *
     * @param  array<mixed>  $rules
     */
    public function hasEnumRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'in:')) {
                return true;
            }

            // Check for Rule::in() or Enum rule objects
            if (is_object($rule)) {
                $className = class_basename($rule);
                if (in_array($className, ['In', 'Enum'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract enum values from 'in:' rule.
     *
     * @param  array<mixed>  $rules
     * @return array<string>|null
     */
    public function extractEnumValues(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'in:')) {
                $values = Str::after($rule, 'in:');

                return explode(',', $values);
            }
        }

        return null;
    }

    /**
     * Extract constraints from validation rules.
     *
     * @param  array<mixed>  $rules
     * @return array<string, mixed> Constraints like minimum, maximum, minLength, maxLength, pattern, enum
     */
    public function extractConstraints(array $rules): array
    {
        $constraints = [];

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $parts = explode(':', $rule);
            $ruleName = $parts[0];
            $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

            switch ($ruleName) {
                case 'min':
                    if (isset($parameters[0])) {
                        $constraints['minimum'] = (int) $parameters[0];
                    }
                    break;

                case 'max':
                    if (isset($parameters[0])) {
                        $constraints['maximum'] = (int) $parameters[0];
                    }
                    break;

                case 'between':
                    if (isset($parameters[0], $parameters[1])) {
                        $constraints['minimum'] = (int) $parameters[0];
                        $constraints['maximum'] = (int) $parameters[1];
                    }
                    break;

                case 'size':
                    if (isset($parameters[0])) {
                        $constraints['minLength'] = (int) $parameters[0];
                        $constraints['maxLength'] = (int) $parameters[0];
                    }
                    break;

                case 'in':
                    $constraints['enum'] = $parameters;
                    break;

                case 'regex':
                case 'pattern':
                    if (isset($parameters[0])) {
                        $constraints['pattern'] = $parameters[0];
                    }
                    break;

                case 'digits':
                    if (isset($parameters[0])) {
                        $constraints['minLength'] = (int) $parameters[0];
                        $constraints['maxLength'] = (int) $parameters[0];
                    }
                    break;

                case 'digits_between':
                    if (isset($parameters[0], $parameters[1])) {
                        $constraints['minLength'] = (int) $parameters[0];
                        $constraints['maxLength'] = (int) $parameters[1];
                    }
                    break;
            }
        }

        return $constraints;
    }

    /**
     * Extract the base rule name from a rule string.
     *
     * @param  string  $rule  Rule string like 'max:255' or 'email'
     * @return string Base rule name
     */
    private function extractRuleName(string $rule): string
    {
        return explode(':', $rule)[0];
    }

    /**
     * Check if a rule is date-related.
     */
    private function isDateRule(string $rule): bool
    {
        $ruleName = $this->extractRuleName($rule);

        if (in_array($ruleName, ['date', 'datetime'], true)) {
            return true;
        }

        foreach (self::DATE_RULE_PREFIXES as $prefix) {
            if (Str::startsWith($rule, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
