<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents field pattern configuration for example generation.
 *
 * Used by FieldPatternRegistry to provide type-safe configuration for
 * generating field examples using Faker or static values.
 */
final readonly class FieldPatternConfig
{
    /**
     * @param  string  $type  The semantic type of the field (e.g., 'email', 'name', 'id')
     * @param  string|null  $format  The format hint (e.g., 'email', 'datetime', 'uuid')
     * @param  string|null  $fakerMethod  The Faker method to use (e.g., 'safeEmail', 'unique->numberBetween')
     * @param  array<int, mixed>  $fakerArgs  Arguments to pass to the Faker method
     * @param  mixed  $staticValue  The static value to use when not using Faker
     */
    public function __construct(
        public string $type,
        public ?string $format,
        public ?string $fakerMethod,
        public array $fakerArgs,
        public mixed $staticValue,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'string',
            format: $data['format'] ?? null,
            fakerMethod: $data['fakerMethod'] ?? null,
            fakerArgs: $data['fakerArgs'] ?? [],
            staticValue: $data['staticValue'] ?? null,
        );
    }

    /**
     * Convert to array.
     *
     * @return array{type: string, format: string|null, fakerMethod: string|null, fakerArgs: array<int, mixed>, staticValue: mixed}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'format' => $this->format,
            'fakerMethod' => $this->fakerMethod,
            'fakerArgs' => $this->fakerArgs,
            'staticValue' => $this->staticValue,
        ];
    }

    /**
     * Check if a Faker method is configured.
     */
    public function hasFakerMethod(): bool
    {
        return $this->fakerMethod !== null;
    }

    /**
     * Check if a format is configured.
     */
    public function hasFormat(): bool
    {
        return $this->format !== null;
    }

    /**
     * Check if a static value is configured (not null).
     */
    public function hasStaticValue(): bool
    {
        return $this->staticValue !== null;
    }

    /**
     * Check if Faker arguments are configured.
     */
    public function hasFakerArgs(): bool
    {
        return count($this->fakerArgs) > 0;
    }

    /**
     * Check if the Faker method is a chained method (e.g., 'unique->numberBetween').
     */
    public function isChainedFakerMethod(): bool
    {
        return $this->fakerMethod !== null && str_contains($this->fakerMethod, '->');
    }
}
