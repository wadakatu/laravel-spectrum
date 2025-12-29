<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the type of FormRequest analysis context.
 *
 * This enum categorizes the analysis state of a FormRequest class:
 * - Skip: Class doesn't exist, is not a FormRequest, AST failed, or class node not found
 * - Anonymous: Class is anonymous or file path unavailable (requires reflection-based analysis)
 * - Ready: Full analysis context available with AST and reflection
 */
enum FormRequestAnalysisContextType: string
{
    case Skip = 'skip';
    case Anonymous = 'anonymous';
    case Ready = 'ready';
}
