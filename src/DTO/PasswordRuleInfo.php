<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * DTO containing password validation constraints extracted from Password:: rules.
 */
final readonly class PasswordRuleInfo
{
    public function __construct(
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public bool $requiresMixedCase = false,
        public bool $requiresNumbers = false,
        public bool $requiresSymbols = false,
        public bool $requiresLetters = false,
        public bool $requiresUncompromised = false,
    ) {}

    /**
     * Check if any password requirements are set.
     */
    public function hasRequirements(): bool
    {
        return $this->requiresMixedCase
            || $this->requiresNumbers
            || $this->requiresSymbols
            || $this->requiresLetters
            || $this->requiresUncompromised;
    }

    /**
     * Get a list of requirement descriptions.
     *
     * @return array<string>
     */
    public function getRequirementDescriptions(): array
    {
        $descriptions = [];

        if ($this->requiresMixedCase) {
            $descriptions[] = 'mixed case';
        }
        if ($this->requiresNumbers) {
            $descriptions[] = 'numbers';
        }
        if ($this->requiresSymbols) {
            $descriptions[] = 'symbols';
        }
        if ($this->requiresLetters) {
            $descriptions[] = 'letters';
        }
        if ($this->requiresUncompromised) {
            $descriptions[] = 'not compromised';
        }

        return $descriptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'min_length' => $this->minLength,
            'max_length' => $this->maxLength,
            'requires_mixed_case' => $this->requiresMixedCase,
            'requires_numbers' => $this->requiresNumbers,
            'requires_symbols' => $this->requiresSymbols,
            'requires_letters' => $this->requiresLetters,
            'requires_uncompromised' => $this->requiresUncompromised,
        ];
    }
}
