<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\DTO\ConditionalRuleDetail;

/**
 * Analyzes validation rules for requirement conditions.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 */
class RuleRequirementAnalyzer
{
    /**
     * Conditional required rule names.
     */
    private const CONDITIONAL_REQUIRED_RULES = [
        'required_if',
        'required_unless',
        'required_with',
        'required_without',
        'required_with_all',
        'required_without_all',
    ];

    /**
     * Prohibited rule names.
     */
    private const PROHIBITED_RULES = [
        'prohibited_if',
        'prohibited_unless',
        'prohibited_with',
        'prohibited_without',
    ];

    /**
     * Exclude rule names.
     */
    private const EXCLUDE_RULES = [
        'exclude_if',
        'exclude_unless',
        'exclude_with',
        'exclude_without',
    ];

    /**
     * Check if field is unconditionally required.
     *
     * @param  string|array  $rules
     */
    public function isRequired($rules): bool
    {
        $rules = $this->normalizeRules($rules);

        foreach ($rules as $rule) {
            $ruleName = is_string($rule) ? explode(':', $rule)[0] : '';
            // Only 'required' without conditions makes a field truly required
            // Conditional required rules (required_if, etc.) don't make a field unconditionally required
            if ($ruleName === 'required') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if field has any conditional required rules.
     *
     * @param  string|array  $rules
     */
    public function hasConditionalRequired($rules): bool
    {
        $rules = $this->normalizeRules($rules);

        foreach ($rules as $rule) {
            $ruleName = is_string($rule) ? explode(':', $rule)[0] : '';
            if (in_array($ruleName, self::CONDITIONAL_REQUIRED_RULES)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract details of conditional rules.
     *
     * @param  string|array<mixed>  $rules
     * @return array<int, ConditionalRuleDetail>
     */
    public function extractConditionalRuleDetails($rules): array
    {
        $rules = $this->normalizeRules($rules);
        $conditionalRules = [];

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $parts = explode(':', $rule, 2);
            $ruleName = $parts[0];
            $ruleParams = $parts[1] ?? '';

            $allConditionalRules = array_merge(
                self::CONDITIONAL_REQUIRED_RULES,
                self::PROHIBITED_RULES,
                self::EXCLUDE_RULES
            );

            if (in_array($ruleName, $allConditionalRules)) {
                $conditionalRules[] = new ConditionalRuleDetail(
                    type: $ruleName,
                    parameters: $ruleParams,
                    fullRule: $rule,
                );
            }
        }

        return $conditionalRules;
    }

    /**
     * Check if field is required in any condition.
     */
    public function isRequiredInAnyCondition(array $rulesByCondition): bool
    {
        foreach ($rulesByCondition as $conditionRules) {
            if (! isset($conditionRules['rules'])) {
                continue;
            }
            if ($this->isRequired($conditionRules['rules'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize rules to array format.
     *
     * @param  string|array|null  $rules
     */
    private function normalizeRules($rules): array
    {
        if (is_string($rules)) {
            return $rules === '' ? [] : explode('|', $rules);
        }

        if (is_array($rules)) {
            return $rules;
        }

        return [];
    }
}
