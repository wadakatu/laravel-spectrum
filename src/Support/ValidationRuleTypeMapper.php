<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use LaravelSpectrum\Analyzers\CustomRuleAnalyzer;
use LaravelSpectrum\DTO\OpenApiSchema;

/**
 * Maps Laravel validation rules to OpenAPI type information.
 *
 * Centralizes the validation rule → type mapping logic used across
 * TypeInference and QueryParameterTypeInference classes.
 */
final class ValidationRuleTypeMapper
{
    private ?CustomRuleAnalyzer $customRuleAnalyzer = null;

    /**
     * Cached custom rule schema results from analyze calls.
     *
     * @var array<string, OpenApiSchema|null>
     */
    private array $customRuleCache = [];

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
        'ulid' => 'ulid',
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
     * Get or create the CustomRuleAnalyzer instance.
     */
    private function getCustomRuleAnalyzer(): CustomRuleAnalyzer
    {
        if ($this->customRuleAnalyzer === null) {
            $this->customRuleAnalyzer = new CustomRuleAnalyzer;
        }

        return $this->customRuleAnalyzer;
    }

    /**
     * Analyze a custom validation rule and cache the result.
     */
    private function analyzeCustomRule(ValidationRule $rule): ?OpenApiSchema
    {
        $key = spl_object_id($rule);

        if (! array_key_exists($key, $this->customRuleCache)) {
            $this->customRuleCache[$key] = $this->getCustomRuleAnalyzer()->analyze($rule);
        }

        return $this->customRuleCache[$key];
    }

    /**
     * Check if a rule is an AST-extracted custom rule array.
     *
     * @param  mixed  $rule
     */
    private function isAstCustomRule($rule): bool
    {
        return is_array($rule)
            && isset($rule['type'])
            && $rule['type'] === 'custom_rule'
            && isset($rule['class']);
    }

    /**
     * Try to instantiate and analyze an AST-extracted custom rule.
     *
     * @param  array{type: string, class: string, args: array<mixed>}  $ruleData
     */
    private function analyzeAstCustomRule(array $ruleData): ?OpenApiSchema
    {
        $className = $ruleData['class'];
        $args = $ruleData['args'] ?? [];

        // Generate cache key from class name and args
        $cacheKey = 'ast:'.$className.':'.md5(serialize($args));

        if (array_key_exists($cacheKey, $this->customRuleCache)) {
            return $this->customRuleCache[$cacheKey];
        }

        // Try to resolve the full class name
        $fullClassName = $this->resolveClassName($className);

        if ($fullClassName === null || ! class_exists($fullClassName)) {
            $this->customRuleCache[$cacheKey] = null;

            return null;
        }

        // Verify it implements ValidationRule
        if (! is_subclass_of($fullClassName, ValidationRule::class)) {
            $this->customRuleCache[$cacheKey] = null;

            return null;
        }

        try {
            // Try to instantiate with the provided arguments
            $instance = $this->instantiateWithArgs($fullClassName, $args);
            if ($instance === null) {
                $this->customRuleCache[$cacheKey] = null;

                return null;
            }

            $schema = $this->getCustomRuleAnalyzer()->analyze($instance);
            $this->customRuleCache[$cacheKey] = $schema;

            return $schema;
        } catch (\Throwable) {
            $this->customRuleCache[$cacheKey] = null;

            return null;
        }
    }

    /**
     * Resolve a short class name to its fully qualified name.
     */
    private function resolveClassName(string $className): ?string
    {
        // If already fully qualified, return as-is
        if (class_exists($className)) {
            return $className;
        }

        // Try common Laravel validation rule namespaces
        $candidates = [
            'App\\Rules\\'.$className,
            'Illuminate\\Validation\\Rules\\'.$className,
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Instantiate a class with the given arguments.
     *
     * @param  class-string  $className
     * @param  array<mixed>  $args
     */
    private function instantiateWithArgs(string $className, array $args): ?ValidationRule
    {
        try {
            $reflection = new \ReflectionClass($className);

            if (empty($args)) {
                /** @var ValidationRule */
                return $reflection->newInstance();
            }

            // Check if args are named or positional
            $hasNamedArgs = array_keys($args) !== range(0, count($args) - 1);

            if ($hasNamedArgs) {
                // For named args, we need to map them to constructor parameter positions
                $constructor = $reflection->getConstructor();
                if ($constructor === null) {
                    /** @var ValidationRule */
                    return $reflection->newInstance();
                }

                $orderedArgs = [];
                foreach ($constructor->getParameters() as $param) {
                    $name = $param->getName();
                    if (array_key_exists($name, $args)) {
                        $orderedArgs[] = $args[$name];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $orderedArgs[] = $param->getDefaultValue();
                    } else {
                        // Required parameter not provided
                        return null;
                    }
                }

                /** @var ValidationRule */
                return $reflection->newInstanceArgs($orderedArgs);
            }

            /** @var ValidationRule */
            return $reflection->newInstanceArgs($args);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Infer OpenAPI type from validation rules.
     *
     * @param  array<mixed>  $rules  Laravel validation rules
     * @return string OpenAPI type (string, integer, number, boolean, array, object)
     */
    public function inferType(array $rules): string
    {
        foreach ($rules as $rule) {
            // Handle custom ValidationRule objects
            if ($rule instanceof ValidationRule) {
                $schema = $this->analyzeCustomRule($rule);
                if ($schema !== null) {
                    return $schema->type;
                }

                continue;
            }

            // Handle AST-extracted custom rule arrays
            if ($this->isAstCustomRule($rule)) {
                $schema = $this->analyzeAstCustomRule($rule);
                if ($schema !== null) {
                    return $schema->type;
                }

                continue;
            }

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
            // Handle custom ValidationRule objects
            if ($rule instanceof ValidationRule) {
                $schema = $this->analyzeCustomRule($rule);
                if ($schema !== null && $schema->format !== null) {
                    return $schema->format;
                }

                continue;
            }

            // Handle AST-extracted custom rule arrays
            if ($this->isAstCustomRule($rule)) {
                $schema = $this->analyzeAstCustomRule($rule);
                if ($schema !== null && $schema->format !== null) {
                    return $schema->format;
                }

                continue;
            }

            if (! is_string($rule)) {
                continue;
            }

            $ruleName = $this->extractRuleName($rule);

            // Check format rules
            if (isset(self::FORMAT_RULES[$ruleName])) {
                return self::FORMAT_RULES[$ruleName];
            }

            // Date format rule - check if format includes time components
            if (Str::startsWith($rule, 'date_format:')) {
                $format = Str::after($rule, 'date_format:');
                // Check if format includes time components (H, i, s, G, u)
                if (preg_match('/[HisGu]/', $format)) {
                    return 'date-time';
                }

                return 'date';
            }

            // Other date-related rules (after:, before:, etc.)
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
     * Extract enum values from 'in:' rule or Rule::in() object.
     *
     * @param  array<mixed>  $rules
     * @return array<string>|null
     */
    public function extractEnumValues(array $rules): ?array
    {
        foreach ($rules as $rule) {
            // Handle string 'in:value1,value2' format
            if (is_string($rule) && Str::startsWith($rule, 'in:')) {
                $values = Str::after($rule, 'in:');

                return $this->parseInRuleValues($values);
            }

            // Handle Rule::in() object (Illuminate\Validation\Rules\In)
            if (is_object($rule) && class_basename($rule) === 'In') {
                $stringRule = (string) $rule;
                if (Str::startsWith($stringRule, 'in:')) {
                    $values = Str::after($stringRule, 'in:');

                    return $this->parseInRuleValues($values);
                }
            }
        }

        return null;
    }

    /**
     * Parse values from 'in:' rule string.
     *
     * Handles both unquoted (in:a,b,c) and quoted (in:"a","b","c") formats.
     *
     * @return array<string>
     */
    private function parseInRuleValues(string $values): array
    {
        // Check if values are quoted (from Rule::in() object)
        if (Str::contains($values, '"')) {
            // Extract values between quotes
            preg_match_all('/"([^"]*)"/', $values, $matches);

            return $matches[1];
        }

        // Simple comma-separated values
        return explode(',', $values);
    }

    /**
     * Extract constraints from validation rules.
     *
     * Automatically infers the type from rules to determine correct constraint keys:
     * - For string types: min/max/between → minLength/maxLength
     * - For numeric types: min/max/between → minimum/maximum
     *
     * @param  array<mixed>  $rules
     * @param  string|null  $type  Optional explicit type, if null will be inferred from rules
     * @return array<string, mixed> Constraints like minimum, maximum, minLength, maxLength, pattern, enum
     */
    public function extractConstraints(array $rules, ?string $type = null): array
    {
        $constraints = [];
        $inferredType = $type ?? $this->inferType($rules);
        $useNumericConstraints = in_array($inferredType, ['integer', 'number'], true);

        foreach ($rules as $rule) {
            // Handle custom ValidationRule objects
            if ($rule instanceof ValidationRule) {
                $schema = $this->analyzeCustomRule($rule);
                if ($schema !== null) {
                    $constraints = $this->mergeSchemaConstraints($constraints, $schema);
                }

                continue;
            }

            // Handle AST-extracted custom rule arrays
            if ($this->isAstCustomRule($rule)) {
                $schema = $this->analyzeAstCustomRule($rule);
                if ($schema !== null) {
                    $constraints = $this->mergeSchemaConstraints($constraints, $schema);
                }

                continue;
            }

            if (! is_string($rule)) {
                continue;
            }

            $parts = explode(':', $rule);
            $ruleName = $parts[0];
            $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

            switch ($ruleName) {
                case 'min':
                    if (isset($parameters[0])) {
                        if ($useNumericConstraints) {
                            $constraints['minimum'] = (int) $parameters[0];
                        } else {
                            $constraints['minLength'] = (int) $parameters[0];
                        }
                    }
                    break;

                case 'max':
                    if (isset($parameters[0])) {
                        if ($useNumericConstraints) {
                            $constraints['maximum'] = (int) $parameters[0];
                        } else {
                            $constraints['maxLength'] = (int) $parameters[0];
                        }
                    }
                    break;

                case 'between':
                    if (isset($parameters[0], $parameters[1])) {
                        if ($useNumericConstraints) {
                            $constraints['minimum'] = (int) $parameters[0];
                            $constraints['maximum'] = (int) $parameters[1];
                        } else {
                            $constraints['minLength'] = (int) $parameters[0];
                            $constraints['maxLength'] = (int) $parameters[1];
                        }
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
                    // Handle regex patterns that may contain colons
                    if (isset($parts[1])) {
                        // Rejoin all parts after the first colon to preserve the full pattern
                        $rawPattern = implode(':', array_slice($parts, 1));
                        $constraints['pattern'] = ValidationRules::stripPcreDelimiters($rawPattern);
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
     * Merge OpenApiSchema constraints into the constraints array.
     *
     * @param  array<string, mixed>  $constraints
     * @return array<string, mixed>
     */
    private function mergeSchemaConstraints(array $constraints, OpenApiSchema $schema): array
    {
        if ($schema->minimum !== null) {
            $constraints['minimum'] = $schema->minimum;
        }
        if ($schema->maximum !== null) {
            $constraints['maximum'] = $schema->maximum;
        }
        if ($schema->minLength !== null) {
            $constraints['minLength'] = $schema->minLength;
        }
        if ($schema->maxLength !== null) {
            $constraints['maxLength'] = $schema->maxLength;
        }
        if ($schema->pattern !== null) {
            $constraints['pattern'] = $schema->pattern;
        }
        if ($schema->enum !== null) {
            $constraints['enum'] = $schema->enum;
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

    /**
     * Normalize rules to array format.
     *
     * @param  string|array<mixed>|null  $rules
     * @return array<mixed>
     */
    public function normalizeRules(string|array|null $rules): array
    {
        if ($rules === null) {
            return [];
        }

        if (is_string($rules)) {
            return $rules === '' ? [] : explode('|', $rules);
        }

        return $rules;
    }
}
