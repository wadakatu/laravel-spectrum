<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a diagnostic report containing errors and warnings from analysis.
 *
 * This DTO provides a type-safe way to handle diagnostic information
 * generated during code analysis.
 */
final readonly class DiagnosticReport
{
    /**
     * @param  array<int, ErrorEntry>  $errors
     * @param  array<int, ErrorEntry>  $warnings
     */
    private function __construct(
        public array $errors,
        public array $warnings,
        public int $totalErrors,
        public int $totalWarnings,
        public string $generatedAt,
    ) {}

    /**
     * Create a new diagnostic report from errors and warnings.
     *
     * @param  array<int, ErrorEntry>  $errors
     * @param  array<int, ErrorEntry>  $warnings
     */
    public static function create(array $errors, array $warnings): self
    {
        return new self(
            errors: array_values($errors),
            warnings: array_values($warnings),
            totalErrors: count($errors),
            totalWarnings: count($warnings),
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Check if the report contains any errors.
     */
    public function hasErrors(): bool
    {
        return $this->totalErrors > 0;
    }

    /**
     * Check if the report contains any warnings.
     */
    public function hasWarnings(): bool
    {
        return $this->totalWarnings > 0;
    }

    /**
     * Check if the report contains any issues (errors or warnings).
     */
    public function hasIssues(): bool
    {
        return $this->hasErrors() || $this->hasWarnings();
    }

    /**
     * Get the total count of all issues (errors + warnings).
     */
    public function totalIssues(): int
    {
        return $this->totalErrors + $this->totalWarnings;
    }

    /**
     * Get errors filtered by context.
     *
     * @return array<int, ErrorEntry>
     */
    public function getErrorsByContext(string $context): array
    {
        return array_values(
            array_filter(
                $this->errors,
                fn (ErrorEntry $entry): bool => $entry->context === $context
            )
        );
    }

    /**
     * Get warnings filtered by context.
     *
     * @return array<int, ErrorEntry>
     */
    public function getWarningsByContext(string $context): array
    {
        return array_values(
            array_filter(
                $this->warnings,
                fn (ErrorEntry $entry): bool => $entry->context === $context
            )
        );
    }

    /**
     * Create a DiagnosticReport from an array representation.
     *
     * @param  array{summary: array{total_errors: int, total_warnings: int, generated_at: string}, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        $errors = array_map(
            fn (array $error): ErrorEntry => ErrorEntry::fromArray($error),
            $data['errors']
        );

        $warnings = array_map(
            fn (array $warning): ErrorEntry => ErrorEntry::fromArray($warning),
            $data['warnings']
        );

        // Recalculate counts from actual arrays to maintain invariants
        return new self(
            errors: $errors,
            warnings: $warnings,
            totalErrors: count($errors),
            totalWarnings: count($warnings),
            generatedAt: $data['summary']['generated_at'],
        );
    }

    /**
     * Convert to array format for backward compatibility.
     *
     * @return array{summary: array{total_errors: int, total_warnings: int, generated_at: string}, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'summary' => [
                'total_errors' => $this->totalErrors,
                'total_warnings' => $this->totalWarnings,
                'generated_at' => $this->generatedAt,
            ],
            'errors' => array_map(
                fn (ErrorEntry $entry): array => $entry->toArray(),
                $this->errors
            ),
            'warnings' => array_map(
                fn (ErrorEntry $entry): array => $entry->toArray(),
                $this->warnings
            ),
        ];
    }
}
