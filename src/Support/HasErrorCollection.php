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
     * Get the error collector instance.
     */
    public function getErrorCollector(): ErrorCollector
    {
        return $this->errorCollector;
    }

    /**
     * Log an error with standardized metadata.
     *
     * @param  string  $message  The error message
     * @param  string  $errorType  The error type constant from AnalyzerErrorType
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    protected function logError(string $message, string $errorType, array $metadata = []): void
    {
        $this->errorCollector->addError(
            static::class,
            $message,
            array_merge(['error_type' => $errorType], $metadata)
        );
    }

    /**
     * Log a warning with standardized metadata.
     *
     * @param  string  $message  The warning message
     * @param  string  $errorType  The error type constant from AnalyzerErrorType
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    protected function logWarning(string $message, string $errorType, array $metadata = []): void
    {
        $this->errorCollector->addWarning(
            static::class,
            $message,
            array_merge(['error_type' => $errorType], $metadata)
        );
    }

    /**
     * Log an exception as an error with standardized metadata.
     *
     * @param  \Throwable  $exception  The exception that occurred
     * @param  string  $errorType  The error type constant from AnalyzerErrorType
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    protected function logException(\Throwable $exception, string $errorType, array $metadata = []): void
    {
        $this->errorCollector->addError(
            static::class,
            $exception->getMessage(),
            array_merge([
                'error_type' => $errorType,
                'exception_class' => $exception::class,
            ], $metadata)
        );
    }
}
