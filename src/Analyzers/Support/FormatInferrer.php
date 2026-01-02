<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers\Support;

use LaravelSpectrum\Analyzers\PasswordRuleAnalyzer;
use LaravelSpectrum\Support\ValidationRuleTypeMapper;

/**
 * Infers OpenAPI formats from Laravel validation rules.
 *
 * Extracted from FormRequestAnalyzer to improve single responsibility.
 * Delegates to ValidationRuleTypeMapper for centralized format inference.
 */
class FormatInferrer
{
    public function __construct(
        protected ?ValidationRuleTypeMapper $ruleTypeMapper = null,
        protected ?PasswordRuleAnalyzer $passwordRuleAnalyzer = null,
    ) {
        $this->ruleTypeMapper = $ruleTypeMapper ?? new ValidationRuleTypeMapper;
        $this->passwordRuleAnalyzer = $passwordRuleAnalyzer ?? new PasswordRuleAnalyzer;
    }

    /**
     * Infer date format from validation rules.
     *
     * @param  string|array<mixed>  $rules
     */
    public function inferDateFormat($rules): ?string
    {
        $normalizedRules = $this->ruleTypeMapper->normalizeRules($rules);
        $format = $this->ruleTypeMapper->inferFormat($normalizedRules);

        // Return only date-related formats
        return in_array($format, ['date', 'date-time'], true) ? $format : null;
    }

    /**
     * Infer format from validation rules (email, url, uuid, password, etc.)
     *
     * @param  string|array<mixed>  $rules
     */
    public function inferFormat($rules): ?string
    {
        $normalizedRules = $this->ruleTypeMapper->normalizeRules($rules);

        // Check for Password:: rules first
        if ($this->passwordRuleAnalyzer->findPasswordRule($normalizedRules) !== null) {
            return 'password';
        }

        return $this->ruleTypeMapper->inferFormat($normalizedRules);
    }
}
