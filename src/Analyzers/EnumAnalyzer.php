<?php

namespace LaravelSpectrum\Analyzers;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use LaravelSpectrum\Support\EnumExtractor;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class EnumAnalyzer
{
    private EnumExtractor $enumExtractor;

    public function __construct(?EnumExtractor $enumExtractor = null)
    {
        $this->enumExtractor = $enumExtractor ?? new EnumExtractor;
    }

    /**
     * Analyze validation rule for enum constraints
     *
     * @param  mixed  $rule
     * @param  string|null  $namespace  Optional namespace for resolving relative class names
     * @param  array  $useStatements  Use statements for resolving class names
     * @return array|null ['class' => string, 'values' => array, 'type' => string]
     */
    public function analyzeValidationRule($rule, ?string $namespace = null, array $useStatements = []): ?array
    {
        if ($rule === null) {
            return null;
        }

        // Handle Rule::enum() static method
        if (is_object($rule)) {
            $reflection = new ReflectionClass($rule);

            // Check if it's Laravel's Enum rule class
            if ($reflection->getName() === Enum::class || $reflection->isSubclassOf(Enum::class)) {
                $property = $reflection->getProperty('type');
                $property->setAccessible(true);
                $enumClass = $property->getValue($rule);

                return $this->extractEnumInfo($enumClass);
            }

            // Check if it's the result of Rule::enum()
            if ($reflection->getName() === 'Illuminate\Validation\Rules\Enum' ||
                (method_exists($rule, '__toString') && str_contains($rule->__toString(), 'Illuminate\Contracts\Validation\Rule'))) {
                // Extract enum class from the rule object
                $property = $reflection->getProperty('type');
                $property->setAccessible(true);
                $enumClass = $property->getValue($rule);

                return $this->extractEnumInfo($enumClass);
            }
        }

        // Handle string format 'enum:ClassName'
        if (is_string($rule) && str_starts_with($rule, 'enum:')) {
            $enumClass = substr($rule, 5); // Remove 'enum:' prefix

            // Handle relative class names with namespace
            if ($namespace && ! str_contains($enumClass, '\\') && ! class_exists($enumClass)) {
                $enumClass = $namespace.'\\'.$enumClass;
            }

            return $this->extractEnumInfo($enumClass);
        }

        // Handle AST extracted string like 'Rule::enum(StatusEnum::class)'
        if (is_string($rule) && preg_match('/Rule::enum\((.+?)::class\)/', $rule, $matches)) {
            $enumClass = $matches[1];

            // Remove quotes if present
            $enumClass = trim($enumClass, "'\"");

            // Try to resolve using use statements first
            if (! str_contains($enumClass, '\\') && isset($useStatements[$enumClass])) {
                $enumClass = $useStatements[$enumClass];
            }

            // Handle relative class names with namespace
            if ($namespace && ! str_contains($enumClass, '\\') && ! class_exists($enumClass)) {
                $enumClass = $namespace.'\\'.$enumClass;
            }

            return $this->extractEnumInfo($enumClass);
        }

        // Handle AST extracted string like 'new \\Illuminate\\Validation\\Rules\\Enum(StatusEnum::class)'
        if (is_string($rule) && preg_match('/new\s+(?:\\\\)?(?:Illuminate\\\\Validation\\\\Rules\\\\)?Enum\((.+?)::class\)/', $rule, $matches)) {
            $enumClass = $matches[1];

            // Remove quotes if present
            $enumClass = trim($enumClass, "'\"");

            // Try to resolve using use statements first
            if (! str_contains($enumClass, '\\') && isset($useStatements[$enumClass])) {
                $enumClass = $useStatements[$enumClass];
            }

            // Handle relative class names with namespace
            if ($namespace && ! str_contains($enumClass, '\\') && ! class_exists($enumClass)) {
                $enumClass = $namespace.'\\'.$enumClass;
            }

            return $this->extractEnumInfo($enumClass);
        }

        // Handle concatenated string like 'required|enum:' . UserTypeEnum::class
        if (is_string($rule) && preg_match("/'[^']*enum:'\s*\.\s*(.+?)::class/", $rule, $matches)) {
            $enumClass = $matches[1];

            // Remove quotes if present
            $enumClass = trim($enumClass, "'\"");

            // Try to resolve using use statements first
            if (! str_contains($enumClass, '\\') && isset($useStatements[$enumClass])) {
                $enumClass = $useStatements[$enumClass];
            }

            // Handle relative class names with namespace
            if ($namespace && ! str_contains($enumClass, '\\') && ! class_exists($enumClass)) {
                $enumClass = $namespace.'\\'.$enumClass;
            }

            return $this->extractEnumInfo($enumClass);
        }

        return null;
    }

    /**
     * Analyze Eloquent model casts for enum types
     *
     * @return array Array of field => enum info
     */
    public function analyzeEloquentCasts(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        $reflection = new ReflectionClass($modelClass);
        $castsProperty = $reflection->getProperty('casts');
        $castsProperty->setAccessible(true);

        $model = $reflection->newInstanceWithoutConstructor();
        $casts = $castsProperty->getValue($model);

        $enumCasts = [];

        foreach ($casts as $field => $castType) {
            // Handle direct enum class cast
            if (is_string($castType) && enum_exists($castType)) {
                $enumInfo = $this->extractEnumInfo($castType);
                if ($enumInfo) {
                    $enumCasts[$field] = $enumInfo;
                }
            }

            // Handle enum cast with format 'EnumClass::class:type'
            if (is_string($castType) && str_contains($castType, ':')) {
                [$enumClass] = explode(':', $castType, 2);
                if (enum_exists($enumClass)) {
                    $enumInfo = $this->extractEnumInfo($enumClass);
                    if ($enumInfo) {
                        $enumCasts[$field] = $enumInfo;
                    }
                }
            }
        }

        return $enumCasts;
    }

    /**
     * Analyze method signature for enum parameters and return types
     *
     * @return array ['parameters' => [...], 'return' => ...]
     */
    public function analyzeMethodSignature(ReflectionMethod $method): array
    {
        $result = [
            'parameters' => [],
            'return' => null,
        ];

        // Analyze parameters
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $typeName = $type->getName();

                if (enum_exists($typeName)) {
                    $enumInfo = $this->extractEnumInfo($typeName);
                    if ($enumInfo) {
                        $result['parameters'][$parameter->getName()] = $enumInfo;
                    }
                }
            }
        }

        // Analyze return type
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType && ! $returnType->isBuiltin()) {
            $typeName = $returnType->getName();

            if (enum_exists($typeName)) {
                $enumInfo = $this->extractEnumInfo($typeName);
                if ($enumInfo) {
                    $result['return'] = $enumInfo;
                }
            }
        }

        return $result;
    }

    /**
     * Extract enum information from class name
     */
    private function extractEnumInfo(string $enumClass): ?array
    {
        if (! enum_exists($enumClass)) {
            return null;
        }

        try {
            $values = $this->enumExtractor->extractValues($enumClass);
            $type = $this->enumExtractor->getEnumType($enumClass);

            return [
                'class' => $enumClass,
                'values' => $values,
                'type' => $type,
            ];
        } catch (\Exception $e) {
            // Log error and return null
            return null;
        }
    }
}
