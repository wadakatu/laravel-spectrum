<?php

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\DTO\Collections\ValidationRuleCollection;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\DTO\FileUploadInfo;
use LaravelSpectrum\DTO\ParameterDefinition;
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
            type: $this->typeInference->inferFromRules($ruleArray),
            description: $attributes[$field] ?? $this->descriptionGenerator->generateDescription(
                $field,
                $ruleArray,
                $namespace,
                $useStatements
            ),
            example: $this->typeInference->generateExample($field, $ruleArray),
            validation: $ruleArray,
            format: $format,
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

        // Extract rules_by_condition with fallback for safety
        $rulesByCondition = $processedField['rules_by_condition'] ?? [];

        return new ParameterDefinition(
            name: $field,
            in: 'body',
            required: $this->ruleRequirementAnalyzer->isRequiredInAnyCondition($rulesByCondition),
            type: $this->typeInference->inferFromRules($mergedRules),
            description: $attributes[$field] ?? $this->descriptionGenerator->generateConditionalDescription(
                $field,
                $processedField
            ),
            example: $this->typeInference->generateExample($field, $mergedRules),
            validation: $mergedRules,
            format: $format,
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
}
