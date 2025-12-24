<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

/**
 * Standard error type constants for analyzers.
 *
 * These constants provide consistent error categorization across all analyzers.
 */
final class AnalyzerErrorType
{
    // File and parsing errors
    public const FILE_NOT_FOUND = 'file_not_found';

    public const FILE_READ_ERROR = 'file_read_error';

    public const PARSE_ERROR = 'parse_error';

    // Class and reflection errors
    public const CLASS_NOT_FOUND = 'class_not_found';

    public const CLASS_NODE_NOT_FOUND = 'class_node_not_found';

    public const INVALID_PARENT_CLASS = 'invalid_parent_class';

    public const REFLECTION_ERROR = 'reflection_error';

    // Method and structure errors
    public const METHOD_NOT_FOUND = 'method_not_found';

    public const METHOD_NODE_ERROR = 'method_node_error';

    public const INVALID_STRUCTURE = 'invalid_structure';

    // Analysis errors
    public const ANALYSIS_ERROR = 'analysis_error';

    public const CONDITIONAL_ANALYSIS_ERROR = 'conditional_analysis_error';

    public const INLINE_VALIDATION_ERROR = 'inline_validation_error';

    public const ROUTE_LOADING_ERROR = 'route_loading_error';

    // General errors
    public const UNEXPECTED_ERROR = 'unexpected_error';
}
