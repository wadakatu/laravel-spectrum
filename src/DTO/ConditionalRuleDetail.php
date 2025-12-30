<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents details of a conditional validation rule.
 *
 * Used to encapsulate information about conditional rules like required_if,
 * prohibited_unless, exclude_with, etc.
 */
final readonly class ConditionalRuleDetail
{
    /**
     * Conditional required rule names.
     */
    private const REQUIRED_RULES = [
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
     * @param  string  $type  The rule type (e.g., 'required_if', 'prohibited_unless')
     * @param  string  $parameters  The rule parameters (e.g., 'status,active')
     * @param  string  $fullRule  The complete rule string (e.g., 'required_if:status,active')
     */
    public function __construct(
        public string $type,
        public string $parameters,
        public string $fullRule,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? '',
            parameters: $data['parameters'] ?? '',
            fullRule: $data['full_rule'] ?? '',
        );
    }

    /**
     * Convert to array.
     *
     * @return array{type: string, parameters: string, full_rule: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'parameters' => $this->parameters,
            'full_rule' => $this->fullRule,
        ];
    }

    /**
     * Check if this is a required-type rule.
     */
    public function isRequiredRule(): bool
    {
        return in_array($this->type, self::REQUIRED_RULES, true);
    }

    /**
     * Check if this is a prohibited-type rule.
     */
    public function isProhibitedRule(): bool
    {
        return in_array($this->type, self::PROHIBITED_RULES, true);
    }

    /**
     * Check if this is an exclude-type rule.
     */
    public function isExcludeRule(): bool
    {
        return in_array($this->type, self::EXCLUDE_RULES, true);
    }

    /**
     * Get the parameters as an array.
     *
     * @return array<int, string>
     */
    public function getParametersArray(): array
    {
        if ($this->parameters === '') {
            return [];
        }

        return explode(',', $this->parameters);
    }
}
