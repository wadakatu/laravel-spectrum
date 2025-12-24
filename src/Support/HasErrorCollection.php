<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

/**
 * Trait for standardized error collection in analyzers.
 *
 * This trait provides consistent error handling across all analyzer classes,
 * ensuring non-nullable ErrorCollector instances and standardized error metadata.
 */
trait HasErrorCollection
{
    protected ErrorCollector $errorCollector;

    /**
     * Initialize the error collector.
     *
     * Should be called in the constructor with an optional ErrorCollector.
     * If null is passed, a new instance is created.
     */
    protected function initializeErrorCollector(?ErrorCollector $errorCollector = null): void
    {
        $this->errorCollector = $errorCollector ?? new ErrorCollector;
    }

    /**
     * Ensure the error collector is initialized.
     *
     * @throws \LogicException if initializeErrorCollector() was not called
     */
    private function ensureInitialized(): void
    {
        if (! isset($this->errorCollector)) {
            throw new \LogicException(
                'ErrorCollector not initialized. Call initializeErrorCollector() in constructor of '.static::class
            );
        }
    }

    /**
     * Get the error collector instance.
     */
    public function getErrorCollector(): ErrorCollector
    {
        $this->ensureInitialized();

        return $this->errorCollector;
    }

    /**
     * Log an error with standardized metadata.
     *
     * @param  string  $message  The error message
     * @param  AnalyzerErrorType  $errorType  The error type from AnalyzerErrorType enum
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    protected function logError(string $message, AnalyzerErrorType $errorType, array $metadata = []): void
    {
        $this->ensureInitialized();
        $this->errorCollector->addError(
            static::class,
            $message,
            array_merge(['error_type' => $errorType->value], $metadata)
        );
    }

    /**
     * Log a warning with standardized metadata.
     *
     * @param  string  $message  The warning message
     * @param  AnalyzerErrorType  $errorType  The error type from AnalyzerErrorType enum
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    protected function logWarning(string $message, AnalyzerErrorType $errorType, array $metadata = []): void
    {
        $this->ensureInitialized();
        $this->errorCollector->addWarning(
            static::class,
            $message,
            array_merge(['error_type' => $errorType->value], $metadata)
        );
    }

    /**
     * Log an exception as an error with standardized metadata.
     *
     * Automatically captures file, line, and stack trace from the exception.
     *
     * @param  \Throwable  $exception  The exception that occurred
     * @param  AnalyzerErrorType  $errorType  The error type from AnalyzerErrorType enum
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    protected function logException(\Throwable $exception, AnalyzerErrorType $errorType, array $metadata = []): void
    {
        $this->ensureInitialized();
        $this->errorCollector->addError(
            static::class,
            $exception->getMessage(),
            array_merge([
                'error_type' => $errorType->value,
                'exception_class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ], $metadata)
        );
    }
}
