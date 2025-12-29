<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a single conditional validation rule set with its conditions and rules.
 *
 * Used within ConditionalRuleSet to represent individual rule sets
 * that apply under specific conditions (e.g., HTTP method, request field).
 */
final readonly class ConditionalRule
{
    /**
     * @param  array<int, ConditionResult>  $conditions  The conditions under which rules apply
     * @param  array<string, mixed>  $rules  Validation rules that apply when conditions are met
     * @param  float  $probability  Probability that this rule set will be executed
     */
    public function __construct(
        public array $conditions,
        public array $rules,
        public float $probability = 1.0,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $conditions = [];
        foreach ($data['conditions'] ?? [] as $condition) {
            $conditions[] = $condition instanceof ConditionResult
                ? $condition
                : ConditionResult::fromArray($condition);
        }

        return new self(
            conditions: $conditions,
            rules: $data['rules'] ?? [],
            probability: $data['probability'] ?? 1.0,
        );
    }

    /**
     * Convert to array (preserves ConditionResult objects for backward compatibility).
     *
     * @return array{conditions: array<int, ConditionResult>, rules: array<string, mixed>, probability: float}
     */
    public function toArray(): array
    {
        return [
            'conditions' => $this->conditions,
            'rules' => $this->rules,
            'probability' => $this->probability,
        ];
    }

    /**
     * Check if this rule has any rules defined.
     */
    public function hasRules(): bool
    {
        return count($this->rules) > 0;
    }

    /**
     * Check if this rule has any conditions defined.
     */
    public function hasConditions(): bool
    {
        return count($this->conditions) > 0;
    }

    /**
     * Check if this rule's first condition is an HTTP method condition.
     */
    public function isHttpMethodCondition(): bool
    {
        if (empty($this->conditions)) {
            return false;
        }

        return $this->conditions[0]->isHttpMethod();
    }

    /**
     * Get the HTTP method if this is an HTTP method condition.
     */
    public function getHttpMethod(): ?string
    {
        if (! $this->isHttpMethodCondition()) {
            return null;
        }

        return $this->conditions[0]->method;
    }

    /**
     * Get the number of rules in this conditional rule.
     */
    public function getRuleCount(): int
    {
        return count($this->rules);
    }

    /**
     * Get the field names from the rules.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        return array_keys($this->rules);
    }
}
