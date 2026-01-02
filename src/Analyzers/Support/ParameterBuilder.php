<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\PasswordRuleAnalyzer;
use LaravelSpectrum\DTO\Collections\ValidationRuleCollection;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\DTO\FileUploadInfo;
use LaravelSpectrum\DTO\ParameterDefinition;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Support\ValidationRules;

/**
 * Builds parameter arrays from validation rules.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 */
class ParameterBuilder
{
    protected PasswordRuleAnalyzer $passwordRuleAnalyzer;

    public function __construct(
        protected TypeInference $typeInference,
        protected RuleRequirementAnalyzer $ruleRequirementAnalyzer,
        protected FormatInferrer $formatInferrer,
        protected ValidationDescriptionGenerator $descriptionGenerator,
        protected EnumAnalyzer $enumAnalyzer,
        protected FileUploadAnalyzer $fileUploadAnalyzer,
        protected ?ErrorCollector $errorCollector = null,
        ?PasswordRuleAnalyzer $passwordRuleAnalyzer = null,
    ) {
        $this->passwordRuleAnalyzer = $passwordRuleAnalyzer ?? new PasswordRuleAnalyzer;
    }

    /**
     * Build parameters from validation rules.
     *
     * @param  array<string, mixed>  $rules  The validation rules
     * @param  array<string, string>  $attributes  Custom field attributes/descriptions
     * @param  string|null  $namespace  The namespace for enum resolution
     * @param  array<string, string>  $useStatements  Use statements for enum resolution
     * @return array<ParameterDefinition> The built parameters
     */
    public function buildFromRules(array $rules, array $attributes = [], ?string $namespace = null, array $useStatements = []): array
    {
        $parameters = [];

        // Analyze file upload fields (returns FileUploadInfo DTOs)
        $fileFields = $this->fileUploadAnalyzer->analyzeRulesToResult($rules);

        foreach ($rules as $field => $rule) {
            // Skip special fields (like _notice)
            if (str_starts_with($field, '_')) {
                continue;
            }

            $ruleArray = ValidationRuleCollection::from($rule)->all();

            // Skip fields with exclude rules (they are never included in validated data)
            if ($this->hasExcludeRule($ruleArray)) {
                continue;
            }

            // Check if this is a file upload field
            if (isset($fileFields[$field])) {
                $parameters[] = $this->buildFileParameter($field, $fileFields[$field], $ruleArray, $attributes);

                continue;
            }

            $parameter = $this->buildStandardParameter($field, $ruleArray, $attributes, $namespace, $useStatements);
            $parameters[] = $parameter;

            // Generate confirmation field if 'confirmed' rule is present
            if ($this->hasConfirmedRule($ruleArray)) {
                $parameters[] = $this->buildConfirmationField($field, $parameter, $attributes);
            }
        }

        return $parameters;
    }

    /**
     * Build parameters from conditional rules.
     *
     * @param  array<string, mixed>  $conditionalRules  The conditional rules structure
     * @param  array<string, string>  $attributes  Custom field attributes/descriptions
     * @param  string  $namespace  The namespace for enum resolution
     * @param  array<string, string>  $useStatements  Use statements for enum resolution
     * @return array<ParameterDefinition> The built parameters
     */
    public function buildFromConditionalRules(array $conditionalRules, array $attributes = [], string $namespace = '', array $useStatements = []): array
    {
        // Validate required structure
        if (! isset($conditionalRules['rules_sets']) || ! is_array($conditionalRules['rules_sets'])) {
            $this->errorCollector?->addWarning(
                'ParameterBuilder',
                'Invalid conditional rules structure: missing or invalid "rules_sets" key',
                ['received_keys' => array_keys($conditionalRules)]
            );

            return [];
        }

        if (! isset($conditionalRules['merged_rules']) || ! is_array($conditionalRules['merged_rules'])) {
            $this->errorCollector?->addWarning(
                'ParameterBuilder',
                'Invalid conditional rules structure: missing or invalid "merged_rules" key',
                ['received_keys' => array_keys($conditionalRules)]
            );

            return [];
        }

        $parameters = [];
        $processedFields = [];

        // Analyze file upload fields from merged rules
        $fileFields = $this->fileUploadAnalyzer->analyzeRulesToResult($conditionalRules['merged_rules']);

        // Process all rule sets
        foreach ($conditionalRules['rules_sets'] as $index => $ruleSet) {
            // Skip malformed rule sets
            if (! isset($ruleSet['rules']) || ! is_array($ruleSet['rules'])) {
                $this->errorCollector?->addWarning(
                    'ParameterBuilder',
                    'Skipping malformed rule set at index '.$index,
                    [
                        'has_rules_key' => isset($ruleSet['rules']),
                        'is_array' => is_array($ruleSet['rules'] ?? null),
                        'available_keys' => is_array($ruleSet) ? array_keys($ruleSet) : [],
                    ]
                );

                continue;
            }

            foreach ($ruleSet['rules'] as $field => $rule) {
                if (! isset($processedFields[$field])) {
                    $processedFields[$field] = [
                        'name' => $field,
                        'in' => 'body',
                        'rules_by_condition' => [],
                    ];
                }

                $processedFields[$field]['rules_by_condition'][] = [
                    'conditions' => $ruleSet['conditions'] ?? [],
                    'rules' => ValidationRuleCollection::from($rule)->all(),
                ];
            }
        }

        // Generate parameters with merged rules for default type info
        foreach ($conditionalRules['merged_rules'] as $field => $mergedRules) {
            // Skip fields not found in processed fields (inconsistent data)
            if (! isset($processedFields[$field])) {
                $this->errorCollector?->addWarning(
                    'ParameterBuilder',
                    'Skipping orphaned field in merged_rules: '.$field,
                    [
                        'field' => $field,
                        'processed_field_names' => array_keys($processedFields),
                    ]
                );

                continue;
            }

            // Skip fields with exclude rules (they are never included in validated data)
            if ($this->hasExcludeRule($mergedRules)) {
                continue;
            }

            $normalizedRules = ValidationRuleCollection::from($mergedRules)->all();

            // Check if this is a file upload field
            if (isset($fileFields[$field])) {
                $parameter = $this->buildFileParameter($field, $fileFields[$field], $normalizedRules, $attributes);
            } else {
                $parameter = $this->buildConditionalParameter(
                    $field,
                    $mergedRules,
                    $processedFields[$field],
                    $attributes,
                    $namespace,
                    $useStatements
                );
            }
            $parameters[] = $parameter;

            // Generate confirmation field if 'confirmed' rule is present
            if ($this->hasConfirmedRule($normalizedRules)) {
                $parameters[] = $this->buildConfirmationField($field, $parameter, $attributes);
            }
        }

        return $parameters;
    }

    /**
     * Build a file upload parameter.
     *
     * @param  array<string>  $ruleArray
     * @param  array<string, string>  $attributes
     */
    protected function buildFileParameter(string $field, FileUploadInfo $fileInfo, array $ruleArray, array $attributes): ParameterDefinition
    {
        return new ParameterDefinition(
            name: $field,
            in: 'body',
            required: $this->ruleRequirementAnalyzer->isRequired($ruleArray),
            type: 'file',
            description: $this->descriptionGenerator->generateFileDescriptionWithAttribute(
                $field,
                $fileInfo->toArray(),
                $attributes[$field] ?? null
            ),
            example: null,
            validation: $ruleArray,
            format: 'binary',
            fileInfo: $fileInfo,
        );
    }

    /**
     * Build a standard (non-file) parameter.
     *
     * @param  array<string>  $ruleArray
     * @param  array<string, string>  $attributes
     * @param  array<string, string>  $useStatements
     */
    protected function buildStandardParameter(
        string $field,
        array $ruleArray,
        array $attributes,
        ?string $namespace,
        array $useStatements
    ): ParameterDefinition {
        // Check for enum rules
        $enumInfo = $this->findEnumInfo($ruleArray, $namespace, $useStatements);

        // Determine format
        $format = $this->formatInferrer->inferFormat($ruleArray);

        // Extract regex pattern (without PCRE delimiters)
        $pattern = $this->extractPattern($ruleArray);

        // Determine the type first so we can extract type-specific constraints
        $type = $this->typeInference->inferFromRules($ruleArray);

        // Extract string length constraints (only for string types)
        [$minLength, $maxLength] = $this->extractStringLengthConstraints($ruleArray, $type);

        // Extract numeric constraints (only for integer/number types)
        [$minimum, $maximum, $exclusiveMinimum, $exclusiveMaximum] = $this->extractNumericConstraints($ruleArray, $type);

        // Extract array items constraints (only for array types)
        [$minItems, $maxItems] = $this->extractArrayItemsConstraints($ruleArray, $type);

        // Determine conditional rule information
        $conditionalRequired = null;
        $conditionalRules = null;
        if ($this->ruleRequirementAnalyzer->hasConditionalRequired($ruleArray)) {
            $conditionalRequired = true;
            $conditionalRules = $this->ruleRequirementAnalyzer->extractConditionalRuleDetails($ruleArray);
        }

        return new ParameterDefinition(
            name: $field,
            in: 'body',
            required: $this->ruleRequirementAnalyzer->isRequired($ruleArray),
            type: $type,
            description: $attributes[$field] ?? $this->descriptionGenerator->generateDescription(
                $field,
                $ruleArray,
                $namespace,
                $useStatements
            ),
            example: $this->typeInference->generateExample($field, $ruleArray),
            validation: $ruleArray,
            format: $format,
            pattern: $pattern,
            minLength: $minLength,
            maxLength: $maxLength,
            minimum: $minimum,
            maximum: $maximum,
            exclusiveMinimum: $exclusiveMinimum,
            exclusiveMaximum: $exclusiveMaximum,
            minItems: $minItems,
            maxItems: $maxItems,
            conditionalRequired: $conditionalRequired,
            conditionalRules: $conditionalRules,
            enum: $enumInfo,
        );
    }

    /**
     * Build a conditional parameter.
     *
     * @param  array<string>  $mergedRules
     * @param  array<string, mixed>  $processedField
     * @param  array<string, string>  $attributes
     * @param  array<string, string>  $useStatements
     */
    private function buildConditionalParameter(
        string $field,
        array $mergedRules,
        array $processedField,
        array $attributes,
        string $namespace,
        array $useStatements
    ): ParameterDefinition {
        // Check for enum rules
        $enumInfo = $this->findEnumInfo($mergedRules, $namespace, $useStatements);

        // Determine format
        $format = $this->formatInferrer->inferFormat($mergedRules);

        // Extract regex pattern (without PCRE delimiters)
        $pattern = $this->extractPattern($mergedRules);

        // Determine the type first so we can extract type-specific constraints
        $type = $this->typeInference->inferFromRules($mergedRules);

        // Extract string length constraints (only for string types)
        [$minLength, $maxLength] = $this->extractStringLengthConstraints($mergedRules, $type);

        // Extract numeric constraints (only for integer/number types)
        [$minimum, $maximum, $exclusiveMinimum, $exclusiveMaximum] = $this->extractNumericConstraints($mergedRules, $type);

        // Extract array items constraints (only for array types)
        [$minItems, $maxItems] = $this->extractArrayItemsConstraints($mergedRules, $type);

        // Extract rules_by_condition with fallback for safety
        $rulesByCondition = $processedField['rules_by_condition'] ?? [];

        return new ParameterDefinition(
            name: $field,
            in: 'body',
            required: $this->ruleRequirementAnalyzer->isRequiredInAnyCondition($rulesByCondition),
            type: $type,
            description: $attributes[$field] ?? $this->descriptionGenerator->generateConditionalDescription(
                $field,
                $processedField
            ),
            example: $this->typeInference->generateExample($field, $mergedRules),
            validation: $mergedRules,
            format: $format,
            pattern: $pattern,
            minLength: $minLength,
            maxLength: $maxLength,
            minimum: $minimum,
            maximum: $maximum,
            exclusiveMinimum: $exclusiveMinimum,
            exclusiveMaximum: $exclusiveMaximum,
            minItems: $minItems,
            maxItems: $maxItems,
            conditionalRules: $rulesByCondition,
            enum: $enumInfo,
        );
    }

    /**
     * Find enum information from rules.
     *
     * @param  array<int|string, mixed>  $rules
     * @param  array<string, string>  $useStatements
     */
    protected function findEnumInfo(array $rules, ?string $namespace, array $useStatements = []): ?EnumInfo
    {
        foreach ($rules as $rule) {
            $enumResult = $this->enumAnalyzer->analyzeValidationRule($rule, $namespace, $useStatements);
            if ($enumResult) {
                return $enumResult;
            }
        }

        return null;
    }

    /**
     * Check if rules contain an unconditional exclude rule.
     *
     * Only the plain 'exclude' rule always excludes the field.
     * Conditional excludes (exclude_if, exclude_unless, exclude_with, exclude_without)
     * may still include the field depending on conditions, so they should remain in the schema.
     *
     * @param  array<int|string, mixed>  $rules
     */
    private function hasExcludeRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // Only check for plain 'exclude' rule (no parameters)
            if ($rule === 'exclude') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if rules contain a 'confirmed' rule.
     *
     * @param  array<int|string, mixed>  $rules
     */
    private function hasConfirmedRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && $rule === 'confirmed') {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a confirmation field for a field with 'confirmed' rule.
     *
     * The confirmation field copies the original field's type and constraints,
     * with '_confirmation' suffix added to the name.
     *
     * @param  string  $field  The original field name
     * @param  ParameterDefinition  $original  The original field's parameter definition
     * @param  array<string, string>  $attributes  Custom field attributes/descriptions
     * @return ParameterDefinition The generated confirmation field parameter
     */
    private function buildConfirmationField(string $field, ParameterDefinition $original, array $attributes): ParameterDefinition
    {
        $confirmationField = $field.'_confirmation';

        $fieldLabel = $attributes[$confirmationField]
            ?? ucwords(str_replace('_', ' ', $field)).' Confirmation';

        $validation = $original->required
            ? ['required', 'same:'.$field]
            : ['nullable', 'same:'.$field];

        return new ParameterDefinition(
            name: $confirmationField,
            in: $original->in,
            required: $original->required,
            type: $original->type,
            description: $fieldLabel,
            example: $original->example,
            validation: $validation,
            format: $original->format,
            pattern: $original->pattern,
            minLength: $original->minLength,
            maxLength: $original->maxLength,
            minimum: $original->minimum,
            maximum: $original->maximum,
            exclusiveMinimum: $original->exclusiveMinimum,
            exclusiveMaximum: $original->exclusiveMaximum,
            minItems: $original->minItems,
            maxItems: $original->maxItems,
            enum: $original->enum,
        );
    }

    /**
     * Extract regex pattern from validation rules.
     *
     * Returns the pattern without PCRE delimiters for OpenAPI compatibility.
     *
     * @param  array<int|string, mixed>  $rules
     */
    private function extractPattern(array $rules): ?string
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // Check for regex: or pattern: prefix
            if (str_starts_with($rule, 'regex:') || str_starts_with($rule, 'pattern:')) {
                $rawPattern = substr($rule, strpos($rule, ':') + 1);

                return ValidationRules::stripPcreDelimiters($rawPattern);
            }
        }

        return null;
    }

    /**
     * Extract string length constraints from validation rules.
     *
     * Converts Laravel's min/max/size rules to OpenAPI minLength/maxLength.
     * Also handles Password:: rules with min()/max() method chains.
     * Only applies when the type is 'string'.
     *
     * @param  array<int|string, mixed>  $rules
     * @return array{0: int|null, 1: int|null} [minLength, maxLength]
     */
    private function extractStringLengthConstraints(array $rules, string $type): array
    {
        // Only extract length constraints for string types
        if ($type !== 'string') {
            return [null, null];
        }

        $minLength = null;
        $maxLength = null;

        // Check for Password:: rules first
        $passwordInfo = $this->passwordRuleAnalyzer->analyze($rules);
        if ($passwordInfo !== null) {
            $minLength = $passwordInfo->minLength;
            $maxLength = $passwordInfo->maxLength;

            return [$minLength, $maxLength];
        }

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // Handle min:n
            if (str_starts_with($rule, 'min:')) {
                $value = substr($rule, 4);
                if (is_numeric($value)) {
                    $minLength = (int) $value;
                }
            }

            // Handle max:n
            if (str_starts_with($rule, 'max:')) {
                $value = substr($rule, 4);
                if (is_numeric($value)) {
                    $maxLength = (int) $value;
                }
            }

            // Handle size:n (sets both min and max)
            if (str_starts_with($rule, 'size:')) {
                $value = substr($rule, 5);
                if (is_numeric($value)) {
                    $minLength = (int) $value;
                    $maxLength = (int) $value;
                }
            }
        }

        return [$minLength, $maxLength];
    }

    /**
     * Extract numeric constraints from validation rules.
     *
     * Converts Laravel's min/max/between/gte/gt/lte/lt rules to OpenAPI minimum/maximum.
     * Only applies when the type is 'integer' or 'number'.
     *
     * @param  array<int|string, mixed>  $rules
     * @return array{0: int|float|null, 1: int|float|null, 2: int|float|null, 3: int|float|null} [minimum, maximum, exclusiveMinimum, exclusiveMaximum]
     */
    private function extractNumericConstraints(array $rules, string $type): array
    {
        // Only extract numeric constraints for integer/number types
        if ($type !== 'integer' && $type !== 'number') {
            return [null, null, null, null];
        }

        $minimum = null;
        $maximum = null;
        $exclusiveMinimum = null;
        $exclusiveMaximum = null;

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // Handle min:n and gte:n (both set minimum)
            if (str_starts_with($rule, 'min:') || str_starts_with($rule, 'gte:')) {
                $colonPos = strpos($rule, ':');
                $value = substr($rule, $colonPos + 1);
                if (is_numeric($value)) {
                    $minimum = str_contains($value, '.') ? (float) $value : (int) $value;
                }
            }

            // Handle max:n and lte:n (both set maximum)
            if (str_starts_with($rule, 'max:') || str_starts_with($rule, 'lte:')) {
                $colonPos = strpos($rule, ':');
                $value = substr($rule, $colonPos + 1);
                if (is_numeric($value)) {
                    $maximum = str_contains($value, '.') ? (float) $value : (int) $value;
                }
            }

            // Handle gt:n (sets exclusiveMinimum)
            if (str_starts_with($rule, 'gt:')) {
                $value = substr($rule, 3);
                if (is_numeric($value)) {
                    $exclusiveMinimum = str_contains($value, '.') ? (float) $value : (int) $value;
                }
            }

            // Handle lt:n (sets exclusiveMaximum)
            if (str_starts_with($rule, 'lt:')) {
                $value = substr($rule, 3);
                if (is_numeric($value)) {
                    $exclusiveMaximum = str_contains($value, '.') ? (float) $value : (int) $value;
                }
            }

            // Handle between:a,b (sets both minimum and maximum)
            if (str_starts_with($rule, 'between:')) {
                $params = substr($rule, 8);
                $parts = explode(',', $params);
                if (count($parts) === 2) {
                    if (is_numeric($parts[0])) {
                        $minimum = str_contains($parts[0], '.') ? (float) $parts[0] : (int) $parts[0];
                    }
                    if (is_numeric($parts[1])) {
                        $maximum = str_contains($parts[1], '.') ? (float) $parts[1] : (int) $parts[1];
                    }
                }
            }
        }

        return [$minimum, $maximum, $exclusiveMinimum, $exclusiveMaximum];
    }

    /**
     * Extract array items constraints from validation rules.
     *
     * Converts Laravel's min/max/size/between rules to OpenAPI minItems/maxItems.
     * Only applies when the type is 'array'.
     *
     * @param  array<int|string, mixed>  $rules
     * @return array{0: int|null, 1: int|null} [minItems, maxItems]
     */
    private function extractArrayItemsConstraints(array $rules, string $type): array
    {
        // Only extract array constraints for array types
        if ($type !== 'array') {
            return [null, null];
        }

        $minItems = null;
        $maxItems = null;

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // Handle min:n
            if (str_starts_with($rule, 'min:')) {
                $value = substr($rule, 4);
                if (is_numeric($value)) {
                    $minItems = (int) $value;
                }
            }

            // Handle max:n
            if (str_starts_with($rule, 'max:')) {
                $value = substr($rule, 4);
                if (is_numeric($value)) {
                    $maxItems = (int) $value;
                }
            }

            // Handle size:n (sets both min and max)
            if (str_starts_with($rule, 'size:')) {
                $value = substr($rule, 5);
                if (is_numeric($value)) {
                    $minItems = (int) $value;
                    $maxItems = (int) $value;
                }
            }

            // Handle between:a,b (sets both min and max)
            if (str_starts_with($rule, 'between:')) {
                $params = substr($rule, 8);
                $parts = explode(',', $params);
                if (count($parts) === 2) {
                    if (is_numeric($parts[0])) {
                        $minItems = (int) $parts[0];
                    }
                    if (is_numeric($parts[1])) {
                        $maxItems = (int) $parts[1];
                    }
                }
            }
        }

        return [$minItems, $maxItems];
    }
}
