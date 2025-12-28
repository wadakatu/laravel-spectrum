<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a set of conditional validation rules extracted from FormRequest.
 */
final readonly class ConditionalRuleSet
{
    /**
     * @param  array<int, array{condition: string, rules: array<string, mixed>}>  $ruleSets  Individual rule sets with conditions
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
        return new self(
            ruleSets: $data['rules_sets'] ?? [],
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
        return [
            'rules_sets' => $this->ruleSets,
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
     * Get all conditions from rule sets.
     *
     * @return array<int, string>
     */
    public function getAllConditions(): array
    {
        return array_map(
            fn (array $ruleSet) => $ruleSet['condition'],
            $this->ruleSets
        );
    }

    /**
     * Get rules for a specific condition.
     *
     * @return array<string, mixed>
     */
    public function getRulesForCondition(string $condition): array
    {
        foreach ($this->ruleSets as $ruleSet) {
            if ($ruleSet['condition'] === $condition) {
                return $ruleSet['rules'];
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
