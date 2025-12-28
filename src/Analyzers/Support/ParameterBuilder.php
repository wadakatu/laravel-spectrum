<?php

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\TypeInference;

/**
 * Builds parameter arrays from validation rules.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 */
class ParameterBuilder
{
    public function __construct(
        protected TypeInference $typeInference,
        protected RuleRequirementAnalyzer $ruleRequirementAnalyzer,
        protected FormatInferrer $formatInferrer,
        protected ValidationDescriptionGenerator $descriptionGenerator,
        protected EnumAnalyzer $enumAnalyzer,
        protected FileUploadAnalyzer $fileUploadAnalyzer,
        protected ?ErrorCollector $errorCollector = null
    ) {}

    /**
     * Build parameters from validation rules.
     *
     * @param  array  $rules  The validation rules
     * @param  array  $attributes  Custom field attributes/descriptions
     * @param  string|null  $namespace  The namespace for enum resolution
     * @param  array  $useStatements  Use statements for enum resolution
     * @return array<array> The built parameters
     */
    public function buildFromRules(array $rules, array $attributes = [], ?string $namespace = null, array $useStatements = []): array
    {
        $parameters = [];

        // Analyze file upload fields
        $fileFields = $this->fileUploadAnalyzer->analyzeRules($rules);

        foreach ($rules as $field => $rule) {
            // Skip special fields (like _notice)
            if (str_starts_with($field, '_')) {
                continue;
            }

            $ruleArray = is_array($rule) ? $rule : explode('|', $rule);

            // Check if this is a file upload field
            if (isset($fileFields[$field])) {
                $parameters[] = $this->buildFileParameter($field, $fileFields[$field], $ruleArray, $attributes);

                continue;
            }

            $parameters[] = $this->buildStandardParameter($field, $ruleArray, $attributes, $namespace, $useStatements);
        }

        return $parameters;
    }

    /**
     * Build parameters from conditional rules.
     *
     * @param  array  $conditionalRules  The conditional rules structure
     * @param  array  $attributes  Custom field attributes/descriptions
     * @param  string  $namespace  The namespace for enum resolution
     * @param  array  $useStatements  Use statements for enum resolution
     * @return array<array> The built parameters
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
                    'rules' => is_array($rule) ? $rule : explode('|', $rule),
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

            $parameters[] = $this->buildConditionalParameter(
                $field,
                $mergedRules,
                $processedFields[$field],
                $attributes,
                $namespace,
                $useStatements
            );
        }

        return $parameters;
    }

    /**
     * Build a file upload parameter.
     */
    protected function buildFileParameter(string $field, array $fileInfo, array $ruleArray, array $attributes): array
    {
        return [
            'name' => $field,
            'in' => 'body',
            'required' => $this->ruleRequirementAnalyzer->isRequired($ruleArray),
            'type' => 'file',
            'format' => 'binary',
            'file_info' => $fileInfo,
            'description' => $this->descriptionGenerator->generateFileDescriptionWithAttribute(
                $field,
                $fileInfo,
                $attributes[$field] ?? null
            ),
            'validation' => $ruleArray,
        ];
    }

    /**
     * Build a standard (non-file) parameter.
     */
    protected function buildStandardParameter(
        string $field,
        array $ruleArray,
        array $attributes,
        ?string $namespace,
        array $useStatements
    ): array {
        // Check for enum rules
        $enumInfo = $this->findEnumInfo($ruleArray, $namespace, $useStatements);

        $parameter = [
            'name' => $field,
            'in' => 'body',
            'required' => $this->ruleRequirementAnalyzer->isRequired($ruleArray),
            'type' => $this->typeInference->inferFromRules($ruleArray),
            'description' => $attributes[$field] ?? $this->descriptionGenerator->generateDescription(
                $field,
                $ruleArray,
                $namespace,
                $useStatements
            ),
            'example' => $this->typeInference->generateExample($field, $ruleArray),
            'validation' => $ruleArray,
        ];

        // Add format for various field types
        $format = $this->formatInferrer->inferFormat($ruleArray);
        if ($format) {
            $parameter['format'] = $format;
        }

        // Add conditional rule information
        if ($this->ruleRequirementAnalyzer->hasConditionalRequired($ruleArray)) {
            $parameter['conditional_required'] = true;
            $parameter['conditional_rules'] = $this->ruleRequirementAnalyzer->extractConditionalRuleDetails($ruleArray);
        }

        // Add enum information if found
        if ($enumInfo) {
            $parameter['enum'] = $enumInfo;
        }

        return $parameter;
    }

    /**
     * Build a conditional parameter.
     */
    protected function buildConditionalParameter(
        string $field,
        array $mergedRules,
        array $processedField,
        array $attributes,
        string $namespace,
        array $useStatements
    ): array {
        // Check for enum rules
        $enumInfo = $this->findEnumInfo($mergedRules, $namespace, $useStatements);

        // Extract rules_by_condition with fallback for safety
        $rulesByCondition = $processedField['rules_by_condition'] ?? [];

        $parameter = [
            'name' => $field,
            'in' => 'body',
            'required' => $this->ruleRequirementAnalyzer->isRequiredInAnyCondition($rulesByCondition),
            'type' => $this->typeInference->inferFromRules($mergedRules),
            'description' => $attributes[$field] ?? $this->descriptionGenerator->generateConditionalDescription(
                $field,
                $processedField
            ),
            'conditional_rules' => $rulesByCondition,
            'validation' => $mergedRules,
            'example' => $this->typeInference->generateExample($field, $mergedRules),
        ];

        // Add enum information if found
        if ($enumInfo) {
            $parameter['enum'] = $enumInfo;
        }

        return $parameter;
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
}
