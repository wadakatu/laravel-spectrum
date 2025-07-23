<?php

namespace LaravelSpectrum\Support;

class QueryParameterTypeInference
{
    /**
     * Infer type from Request method name
     */
    public function inferFromMethod(string $methodName): ?string
    {
        return match($methodName) {
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
     * Infer type from default value
     */
    public function inferFromDefaultValue($defaultValue): string
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
     * Infer type from usage context
     */
    public function inferFromContext(array $context): ?string
    {
        // Check for numeric operations
        if (isset($context['numeric_operation'])) {
            return in_array($context['numeric_operation'], ['float', 'double']) ? 'number' : 'integer';
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
            
            if (in_array($column, ['price', 'amount', 'total', 'balance'])) {
                return 'number';
            }
            
            if (in_array($column, ['active', 'enabled', 'deleted', 'published'])) {
                return 'boolean';
            }
        }
        
        return null;
    }

    /**
     * Infer type from validation rules
     */
    public function inferFromValidationRules(array $rules): ?string
    {
        foreach ($rules as $rule) {
            $ruleName = is_string($rule) ? explode(':', $rule)[0] : (is_object($rule) ? class_basename($rule) : '');
            
            switch ($ruleName) {
                case 'integer':
                case 'int':
                case 'digits':
                case 'digits_between':
                    return 'integer';
                    
                case 'numeric':
                case 'decimal':
                    return 'number';
                    
                case 'boolean':
                case 'bool':
                case 'accepted':
                    return 'boolean';
                    
                case 'array':
                    return 'array';
                    
                case 'json':
                    return 'object';
                    
                case 'date':
                case 'date_format':
                case 'before':
                case 'after':
                    return 'string'; // with date format
                    
                case 'email':
                case 'url':
                case 'ip':
                case 'uuid':
                case 'string':
                    return 'string';
            }
        }
        
        // Check for enum rule
        if ($this->hasEnumRule($rules)) {
            return 'string';
        }
        
        return null;
    }

    /**
     * Detect enum values from context
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
     * Get format annotation for type
     */
    public function getFormatForType(string $type, array $context = []): ?string
    {
        if ($type === 'string') {
            // Check for date context
            if (isset($context['date_operation']) || isset($context['date_format'])) {
                return 'date-time';
            }
            
            // Check validation rules for format hints
            if (isset($context['validation_rules'])) {
                foreach ($context['validation_rules'] as $rule) {
                    if (str_starts_with($rule, 'email')) return 'email';
                    if (str_starts_with($rule, 'url')) return 'uri';
                    if (str_starts_with($rule, 'uuid')) return 'uuid';
                    if (str_starts_with($rule, 'date_format')) return 'date-time';
                    if ($rule === 'date') return 'date';
                }
            }
        }
        
        return null;
    }

    /**
     * Get constraints from validation rules
     */
    public function getConstraintsFromRules(array $rules): array
    {
        $constraints = [];
        
        foreach ($rules as $rule) {
            if (!is_string($rule)) continue;
            
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
                    if (isset($parameters[0]) && isset($parameters[1])) {
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
            }
        }
        
        return $constraints;
    }

    /**
     * Check if rules contain enum constraint
     */
    private function hasEnumRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return true;
            }
            
            if (is_object($rule) && method_exists($rule, '__toString')) {
                $ruleString = (string) $rule;
                if (str_contains($ruleString, 'Enum') || str_contains($ruleString, 'In')) {
                    return true;
                }
            }
        }
        
        return false;
    }
}