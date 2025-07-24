<?php

namespace LaravelSpectrum\Support;

class ErrorCollector
{
    private array $errors = [];

    private array $warnings = [];

    private bool $failOnError = false;

    public function __construct(bool $failOnError = false)
    {
        $this->failOnError = $failOnError;
    }

    public function addError(string $context, string $message, array $metadata = []): void
    {
        $this->errors[] = [
            'context' => $context,
            'message' => $message,
            'metadata' => $metadata,
            'timestamp' => now()->toIso8601String(),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ];

        if ($this->failOnError) {
            throw new \RuntimeException("Error in {$context}: {$message}");
        }
    }

    public function addWarning(string $context, string $message, array $metadata = []): void
    {
        $this->warnings[] = [
            'context' => $context,
            'message' => $message,
            'metadata' => $metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function generateReport(): array
    {
        return [
            'summary' => [
                'total_errors' => count($this->errors),
                'total_warnings' => count($this->warnings),
                'generated_at' => now()->toIso8601String(),
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
