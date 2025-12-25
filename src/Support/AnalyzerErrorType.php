<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

/**
 * Standard error type enum for analyzers.
 *
 * These enum cases provide consistent error categorization across all analyzers
 * with compile-time type safety.
 */
enum AnalyzerErrorType: string
{
    // File and parsing errors
    case FileNotFound = 'file_not_found';
    case FileReadError = 'file_read_error';
    case ParseError = 'parse_error';

    // Class and reflection errors
    case ClassNotFound = 'class_not_found';
    case ClassNodeNotFound = 'class_node_not_found';
    case InvalidParentClass = 'invalid_parent_class';
    case ReflectionError = 'reflection_error';

    // Method and structure errors
    case MethodNotFound = 'method_not_found';
    case MethodNodeError = 'method_node_error';
    case InvalidStructure = 'invalid_structure';

    // Analysis errors
    case AnalysisError = 'analysis_error';
    case ConditionalAnalysisError = 'conditional_analysis_error';
    case InlineValidationError = 'inline_validation_error';
    case RouteLoadingError = 'route_loading_error';

    // General errors
    case UnexpectedError = 'unexpected_error';
    case UnsupportedFeature = 'unsupported_feature';
}
