<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of validation analysis from FormRequest.
 */
final readonly class ValidationAnalysisResult
{
    /**
     * @param  array<int, array<string, mixed>>  $parameters  Extracted parameters
     * @param  ConditionalRuleSet  $conditionalRules  Conditional validation rules
     * @param  array<string, string>  $attributes  Custom attribute names
     * @param  array<string, string>  $messages  Custom validation messages
     */
    public function __construct(
        public array $parameters,
        public ConditionalRuleSet $conditionalRules,
        public array $attributes = [],
        public array $messages = [],
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $conditionalRules = $data['conditional_rules'] ?? [];

        return new self(
            parameters: $data['parameters'] ?? [],
            conditionalRules: $conditionalRules instanceof ConditionalRuleSet
                ? $conditionalRules
                : ConditionalRuleSet::fromArray($conditionalRules),
            attributes: $data['attributes'] ?? [],
            messages: $data['messages'] ?? [],
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
            'parameters' => $this->parameters,
            'conditional_rules' => $this->conditionalRules->toArray(),
            'attributes' => $this->attributes,
            'messages' => $this->messages,
        ];
    }

    /**
     * Create an empty instance.
     */
    public static function empty(): self
    {
        return new self(
            parameters: [],
            conditionalRules: ConditionalRuleSet::empty(),
            attributes: [],
            messages: [],
        );
    }

    /**
     * Check if this result has conditional rules.
     */
    public function hasConditionalRules(): bool
    {
        return $this->conditionalRules->hasConditions;
    }

    /**
     * Check if this result has parameters.
     */
    public function hasParameters(): bool
    {
        return count($this->parameters) > 0;
    }

    /**
     * Get a parameter by name.
     *
     * @return array<string, mixed>|null
     */
    public function getParameterByName(string $name): ?array
    {
        foreach ($this->parameters as $parameter) {
            if (($parameter['name'] ?? null) === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Get all required parameters.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRequiredParameters(): array
    {
        return array_values(array_filter(
            $this->parameters,
            fn (array $param) => ($param['required'] ?? false) === true
        ));
    }

    /**
     * Get all parameter names.
     *
     * @return array<int, string>
     */
    public function getParameterNames(): array
    {
        return array_map(
            fn (array $param) => $param['name'],
            $this->parameters
        );
    }

    /**
     * Check if this result is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->parameters) === 0 && $this->conditionalRules->isEmpty();
    }

    /**
     * Count the number of parameters.
     */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * Get custom attribute name for a parameter.
     */
    public function getAttributeFor(string $parameterName): ?string
    {
        return $this->attributes[$parameterName] ?? null;
    }

    /**
     * Get custom message for a rule.
     */
    public function getMessageFor(string $ruleKey): ?string
    {
        return $this->messages[$ruleKey] ?? null;
    }
}
