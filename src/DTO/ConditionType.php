<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the type of condition detected in validation rules.
 */
enum ConditionType: string
{
    case HttpMethod = 'http_method';
    case UserCheck = 'user_check';
    case RequestField = 'request_field';
    case RuleWhen = 'rule_when';
    case ElseBranch = 'else';
    case Custom = 'custom';
}
