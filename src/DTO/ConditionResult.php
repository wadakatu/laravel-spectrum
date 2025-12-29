<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a detected condition from validation rule analysis.
 *
 * This DTO encapsulates information about conditions that affect
 * validation rules, such as HTTP method checks, user permission checks,
 * request field checks, and Rule::when() patterns.
 */
final readonly class ConditionResult
{
    private function __construct(
        public ConditionType $type,
        public string $expression,
        public ?string $method = null,
        public ?string $check = null,
        public ?string $field = null,
    ) {}

    /**
     * Create an HTTP method condition.
     *
     * Used when validation rules are conditional on the HTTP method
     * (e.g., $request->isMethod('POST')).
     */
    public static function httpMethod(?string $method, string $expression): self
    {
        return new self(
            type: ConditionType::HttpMethod,
            expression: $expression,
            method: $method,
        );
    }

    /**
     * Create a user check condition.
     *
     * Used when validation rules are conditional on user state
     * (e.g., $user->hasRole('admin'), auth()->check()).
     */
    public static function userCheck(string $method, string $expression): self
    {
        return new self(
            type: ConditionType::UserCheck,
            expression: $expression,
            method: $method,
        );
    }

    /**
     * Create a request field condition.
     *
     * Used when validation rules are conditional on request field presence
     * (e.g., $request->has('email'), $request->filled('name')).
     */
    public static function requestField(?string $check, ?string $field, string $expression): self
    {
        return new self(
            type: ConditionType::RequestField,
            expression: $expression,
            check: $check,
            field: $field,
        );
    }

    /**
     * Create a Rule::when condition.
     *
     * Used when validation rules use Laravel's Rule::when() helper.
     */
    public static function ruleWhen(string $expression): self
    {
        return new self(
            type: ConditionType::RuleWhen,
            expression: $expression,
        );
    }

    /**
     * Create a custom condition.
     *
     * Used for conditions that don't match other known patterns.
     */
    public static function custom(string $expression): self
    {
        return new self(
            type: ConditionType::Custom,
            expression: $expression,
        );
    }

    /**
     * Create an else branch condition.
     *
     * Used for else blocks in conditional validation rules.
     */
    public static function elseBranch(string $description = 'Default case'): self
    {
        return new self(
            type: ConditionType::ElseBranch,
            expression: $description,
        );
    }

    /**
     * Check if this is an HTTP method condition.
     */
    public function isHttpMethod(): bool
    {
        return $this->type === ConditionType::HttpMethod;
    }

    /**
     * Check if this is a user check condition.
     */
    public function isUserCheck(): bool
    {
        return $this->type === ConditionType::UserCheck;
    }

    /**
     * Check if this is a request field condition.
     */
    public function isRequestField(): bool
    {
        return $this->type === ConditionType::RequestField;
    }

    /**
     * Check if this is a Rule::when condition.
     */
    public function isRuleWhen(): bool
    {
        return $this->type === ConditionType::RuleWhen;
    }

    /**
     * Check if this is a custom condition.
     */
    public function isCustom(): bool
    {
        return $this->type === ConditionType::Custom;
    }

    /**
     * Check if this is an else branch condition.
     */
    public function isElseBranch(): bool
    {
        return $this->type === ConditionType::ElseBranch;
    }

    /**
     * Get the type as a string value.
     */
    public function getTypeAsString(): string
    {
        return $this->type->value;
    }

    /**
     * Convert to array format for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type->value,
        ];

        // Else branch uses 'description' instead of 'expression' for backward compatibility
        if ($this->type === ConditionType::ElseBranch) {
            $result['description'] = $this->expression;
        } else {
            $result['expression'] = $this->expression;
        }

        if ($this->method !== null) {
            $result['method'] = $this->method;
        }

        if ($this->check !== null) {
            $result['check'] = $this->check;
        }

        if ($this->field !== null) {
            $result['field'] = $this->field;
        }

        return $result;
    }

    /**
     * Create from array representation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = ConditionType::from($data['type']);

        // Else branch uses 'description' instead of 'expression'
        $expression = $type === ConditionType::ElseBranch
            ? ($data['description'] ?? 'Default case')
            : $data['expression'];

        return new self(
            type: $type,
            expression: $expression,
            method: $data['method'] ?? null,
            check: $data['check'] ?? null,
            field: $data['field'] ?? null,
        );
    }
}
