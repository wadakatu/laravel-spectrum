<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of validation analysis from FormRequest.
 */
final readonly class ValidationAnalysisResult
{
    /**
     * @param  array<ParameterDefinition>  $parameters  Extracted parameters as DTOs
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
        $parameters = $data['parameters'] ?? [];

        // Convert parameter arrays to ParameterDefinition DTOs
        $parameterDtos = array_map(
            fn (array|ParameterDefinition $param) => $param instanceof ParameterDefinition
                ? $param
                : ParameterDefinition::fromArray($param),
            $parameters
        );

        return new self(
            parameters: $parameterDtos,
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
            'parameters' => array_map(
                fn (ParameterDefinition $param) => $param->toArray(),
                $this->parameters
            ),
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
     */
    public function getParameterByName(string $name): ?ParameterDefinition
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->name === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Get all required parameters.
     *
     * @return array<ParameterDefinition>
     */
    public function getRequiredParameters(): array
    {
        return array_values(array_filter(
            $this->parameters,
            fn (ParameterDefinition $param) => $param->required === true
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
            fn (ParameterDefinition $param) => $param->name,
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
