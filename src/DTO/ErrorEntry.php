<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

use LaravelSpectrum\Support\AnalyzerErrorType;

/**
 * Represents a single error or warning entry in error collection.
 *
 * Used by ErrorCollector to store structured error/warning information
 * with context, metadata, and optional stack trace.
 */
final readonly class ErrorEntry
{
    private const TYPE_ERROR = 'error';

    private const TYPE_WARNING = 'warning';

    /**
     * @param  string  $context  The context where the error occurred (e.g., analyzer name)
     * @param  string  $message  The error/warning message
     * @param  array<string, mixed>  $metadata  Additional metadata about the error
     * @param  string  $type  The entry type ('error' or 'warning')
     * @param  string  $timestamp  ISO 8601 timestamp when the entry was created
     * @param  array<int, array<string, mixed>>|null  $trace  Stack trace (only for errors)
     * @param  AnalyzerErrorType|null  $errorType  Categorized error type
     */
    private function __construct(
        public string $context,
        public string $message,
        public array $metadata,
        public string $type,
        public string $timestamp,
        public ?array $trace,
        public ?AnalyzerErrorType $errorType,
    ) {}

    /**
     * Create an error entry with stack trace.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function error(
        string $context,
        string $message,
        array $metadata = [],
        ?AnalyzerErrorType $errorType = null,
    ): self {
        return new self(
            context: $context,
            message: $message,
            metadata: $metadata,
            type: self::TYPE_ERROR,
            timestamp: now()->toIso8601String(),
            trace: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
            errorType: $errorType,
        );
    }

    /**
     * Create a warning entry without stack trace.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function warning(
        string $context,
        string $message,
        array $metadata = [],
        ?AnalyzerErrorType $errorType = null,
    ): self {
        return new self(
            context: $context,
            message: $message,
            metadata: $metadata,
            type: self::TYPE_WARNING,
            timestamp: now()->toIso8601String(),
            trace: null,
            errorType: $errorType,
        );
    }

    /**
     * Check if this entry is an error.
     */
    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    /**
     * Check if this entry is a warning.
     */
    public function isWarning(): bool
    {
        return $this->type === self::TYPE_WARNING;
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $errorType = null;
        if (isset($data['errorType']) && is_string($data['errorType'])) {
            $errorType = AnalyzerErrorType::tryFrom($data['errorType']);
        }

        return new self(
            context: $data['context'] ?? '',
            message: $data['message'] ?? '',
            metadata: $data['metadata'] ?? [],
            type: $data['type'] ?? self::TYPE_ERROR,
            timestamp: $data['timestamp'] ?? now()->toIso8601String(),
            trace: $data['trace'] ?? null,
            errorType: $errorType,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'context' => $this->context,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'type' => $this->type,
            'timestamp' => $this->timestamp,
        ];

        if ($this->trace !== null) {
            $result['trace'] = $this->trace;
        }

        if ($this->errorType !== null) {
            $result['errorType'] = $this->errorType->value;
        }

        return $result;
    }
}
