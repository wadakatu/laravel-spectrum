<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a detected query parameter from controller code analysis.
 *
 * This DTO captures information extracted from Request method calls
 * like $request->input(), $request->integer(), $request->boolean(), etc.
 */
final readonly class QueryParameterInfo
{
    /**
     * @param  string  $name  The parameter name
     * @param  string  $type  The OpenAPI type (string, integer, boolean, number, array)
     * @param  bool  $required  Whether the parameter is required
     * @param  mixed  $default  Default value if any
     * @param  string  $source  The source method (input, integer, boolean, etc.)
     * @param  string|null  $description  Human-readable description
     * @param  array<string>|null  $enum  Allowed enum values if any
     * @param  array<string>|null  $validationRules  Validation rules if detected
     * @param  array<string, mixed>  $context  Additional context from analysis
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $required = false,
        public mixed $default = null,
        public string $source = 'input',
        public ?string $description = null,
        public ?array $enum = null,
        public ?array $validationRules = null,
        public array $context = [],
    ) {}

    /**
     * Create from an array (for backward compatibility during migration).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'] ?? 'string',
            required: $data['required'] ?? false,
            default: $data['default'] ?? null,
            source: $data['source'] ?? 'input',
            description: $data['description'] ?? null,
            enum: $data['enum'] ?? null,
            validationRules: $data['validation_rules'] ?? null,
            context: $data['context'] ?? [],
        );
    }

    /**
     * Convert to array (for backward compatibility during migration).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'source' => $this->source,
            'context' => $this->context,
        ];

        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->enum !== null) {
            $result['enum'] = $this->enum;
        }

        if ($this->validationRules !== null) {
            $result['validation_rules'] = $this->validationRules;
        }

        return $result;
    }

    /**
     * Create a new instance with updated validation rules.
     *
     * @param  array<string>  $rules
     */
    public function withValidationRules(array $rules): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            required: $this->required,
            default: $this->default,
            source: $this->source,
            description: $this->description,
            enum: $this->enum,
            validationRules: $rules,
            context: $this->context,
        );
    }

    /**
     * Create a new instance with updated type.
     */
    public function withType(string $type): self
    {
        return new self(
            name: $this->name,
            type: $type,
            required: $this->required,
            default: $this->default,
            source: $this->source,
            description: $this->description,
            enum: $this->enum,
            validationRules: $this->validationRules,
            context: $this->context,
        );
    }

    /**
     * Create a new instance with required set.
     */
    public function withRequired(bool $required): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            required: $required,
            default: $this->default,
            source: $this->source,
            description: $this->description,
            enum: $this->enum,
            validationRules: $this->validationRules,
            context: $this->context,
        );
    }
}
