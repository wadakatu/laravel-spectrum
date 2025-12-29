<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use LaravelSpectrum\DTO\ErrorEntry;

class ErrorCollector
{
    /** @var array<int, ErrorEntry> */
    private array $errors = [];

    /** @var array<int, ErrorEntry> */
    private array $warnings = [];

    private bool $failOnError = false;

    public function __construct(bool $failOnError = false)
    {
        $this->failOnError = $failOnError;
    }

    /**
     * Add an error entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function addError(string $context, string $message, array $metadata = []): void
    {
        $this->errors[] = ErrorEntry::error(
            context: $context,
            message: $message,
            metadata: $metadata,
        );

        if ($this->failOnError) {
            throw new \RuntimeException("Error in {$context}: {$message}");
        }
    }

    /**
     * Add a warning entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function addWarning(string $context, string $message, array $metadata = []): void
    {
        $this->warnings[] = ErrorEntry::warning(
            context: $context,
            message: $message,
            metadata: $metadata,
        );
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get all error entries.
     *
     * @return array<int, ErrorEntry>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warning entries.
     *
     * @return array<int, ErrorEntry>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get all errors as arrays (for backward compatibility).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getErrorsAsArray(): array
    {
        return array_map(fn (ErrorEntry $e) => $e->toArray(), $this->errors);
    }

    /**
     * Get all warnings as arrays (for backward compatibility).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWarningsAsArray(): array
    {
        return array_map(fn (ErrorEntry $e) => $e->toArray(), $this->warnings);
    }

    /**
     * Generate a report with all errors and warnings.
     *
     * @return array{summary: array{total_errors: int, total_warnings: int, generated_at: string}, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}
     */
    public function generateReport(): array
    {
        return [
            'summary' => [
                'total_errors' => count($this->errors),
                'total_warnings' => count($this->warnings),
                'generated_at' => now()->toIso8601String(),
            ],
            'errors' => $this->getErrorsAsArray(),
            'warnings' => $this->getWarningsAsArray(),
        ];
    }
}
