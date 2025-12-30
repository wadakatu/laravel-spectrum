<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a set of conditional validation rules extracted from FormRequest.
 */
final readonly class ConditionalRuleSet
{
    /**
     * @param  array<int, ConditionalRule>  $ruleSets  Individual rule sets with conditions, rules, and probability
     * @param  array<string, mixed>  $mergedRules  All rules merged together
     * @param  bool  $hasConditions  Whether any conditional rules exist
     */
    public function __construct(
        public array $ruleSets,
        public array $mergedRules,
        public bool $hasConditions,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $ruleSets = [];
        foreach ($data['rules_sets'] ?? [] as $ruleSet) {
            $ruleSets[] = $ruleSet instanceof ConditionalRule
                ? $ruleSet
                : ConditionalRule::fromArray($ruleSet);
        }

        return new self(
            ruleSets: $ruleSets,
            mergedRules: $data['merged_rules'] ?? [],
            hasConditions: $data['has_conditions'] ?? false,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $ruleSets = array_map(
            fn (ConditionalRule $ruleSet) => $ruleSet->toArray(),
            $this->ruleSets
        );

        return [
            'rules_sets' => $ruleSets,
            'merged_rules' => $this->mergedRules,
            'has_conditions' => $this->hasConditions,
        ];
    }

    /**
     * Create an empty instance.
     */
    public static function empty(): self
    {
        return new self(
            ruleSets: [],
            mergedRules: [],
            hasConditions: false,
        );
    }

    /**
     * Check if this rule set is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->ruleSets) === 0;
    }

    /**
     * Get the first condition from each rule set.
     *
     * Returns the first ConditionResult from each ConditionalRule,
     * useful for iterating over all primary conditions.
     *
     * @return array<int, ConditionResult>
     */
    public function getAllConditions(): array
    {
        $conditions = [];
        foreach ($this->ruleSets as $ruleSet) {
            if (! empty($ruleSet->conditions)) {
                $conditions[] = $ruleSet->conditions[0];
            }
        }

        return $conditions;
    }

    /**
     * Get rules for a specific HTTP method condition.
     *
     * @return array<string, mixed>
     */
    public function getRulesForHttpMethod(string $method): array
    {
        foreach ($this->ruleSets as $ruleSet) {
            if ($ruleSet->isHttpMethodCondition() && $ruleSet->getHttpMethod() === $method) {
                return $ruleSet->rules;
            }
        }

        return [];
    }

    /**
     * Count the number of rule sets.
     */
    public function count(): int
    {
        return count($this->ruleSets);
    }
}
