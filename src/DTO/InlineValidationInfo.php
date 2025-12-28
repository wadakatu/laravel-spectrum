<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents inline validation rules detected in a controller method.
 *
 * This DTO encapsulates validation rules, custom messages, and custom attribute names
 * that are defined inline within controller methods using validate(), request()->validate(),
 * Validator::make(), or anonymous FormRequest patterns.
 */
final readonly class InlineValidationInfo
{
    /**
     * @param  array<string, string|array<int, string>>  $rules  Validation rules (field => rule string or array)
     * @param  array<string, string>  $messages  Custom validation messages
     * @param  array<string, string>  $attributes  Custom attribute names for error messages
     */
    public function __construct(
        public array $rules = [],
        public array $messages = [],
        public array $attributes = [],
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rules: $data['rules'] ?? [],
            messages: $data['messages'] ?? [],
            attributes: $data['attributes'] ?? [],
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
            'rules' => $this->rules,
            'messages' => $this->messages,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * Create an empty InlineValidationInfo instance.
     */
    public static function empty(): self
    {
        return new self(
            rules: [],
            messages: [],
            attributes: [],
        );
    }

    /**
     * Check if this validation info has rules.
     */
    public function hasRules(): bool
    {
        return count($this->rules) > 0;
    }

    /**
     * Check if this validation info has custom messages.
     */
    public function hasMessages(): bool
    {
        return count($this->messages) > 0;
    }

    /**
     * Check if this validation info has custom attributes.
     */
    public function hasAttributes(): bool
    {
        return count($this->attributes) > 0;
    }

    /**
     * Check if this validation info is empty.
     */
    public function isEmpty(): bool
    {
        return ! $this->hasRules() && ! $this->hasMessages() && ! $this->hasAttributes();
    }

    /**
     * Get all field names that have validation rules.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        return array_keys($this->rules);
    }

    /**
     * Get the rule for a specific field.
     *
     * @return string|array<int, string>|null
     */
    public function getRuleForField(string $field): string|array|null
    {
        return $this->rules[$field] ?? null;
    }

    /**
     * Check if a specific field has a rule.
     */
    public function hasRuleForField(string $field): bool
    {
        return isset($this->rules[$field]);
    }
}
