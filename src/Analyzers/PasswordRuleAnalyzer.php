<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\DTO\PasswordRuleInfo;

/**
 * Analyzes Password:: static call strings extracted from AST.
 *
 * Laravel's Password rule object (Password::min(8)->mixedCase()->...) is converted
 * to a string by the AST extractor. This analyzer parses those strings to extract
 * password constraints like min/max length and requirements.
 */
final class PasswordRuleAnalyzer
{
    /**
     * Password class patterns to match.
     */
    private const PASSWORD_CLASS_PATTERNS = [
        'Password::',
        '\\Illuminate\\Validation\\Rules\\Password::',
        'Illuminate\\Validation\\Rules\\Password::',
    ];

    /**
     * Check if a rule is a Password:: static call string.
     *
     * @param  mixed  $rule
     */
    public function isPasswordRule($rule): bool
    {
        if (! is_string($rule)) {
            return false;
        }

        foreach (self::PASSWORD_CLASS_PATTERNS as $pattern) {
            if (str_starts_with($rule, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find a Password:: rule string in an array of rules.
     *
     * @param  array<mixed>  $rules
     */
    public function findPasswordRule(array $rules): ?string
    {
        foreach ($rules as $rule) {
            if ($this->isPasswordRule($rule)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Analyze a Password:: rule string and extract constraints.
     *
     * @param  array<mixed>  $rules  Array of validation rules
     */
    public function analyze(array $rules): ?PasswordRuleInfo
    {
        $passwordRule = $this->findPasswordRule($rules);

        if ($passwordRule === null) {
            return null;
        }

        return $this->parsePasswordRule($passwordRule);
    }

    /**
     * Parse a Password:: rule string to extract constraints.
     */
    private function parsePasswordRule(string $rule): PasswordRuleInfo
    {
        $minLength = $this->extractMin($rule);
        $maxLength = $this->extractMax($rule);
        $requirements = $this->extractRequirements($rule);

        return new PasswordRuleInfo(
            minLength: $minLength,
            maxLength: $maxLength,
            requiresMixedCase: in_array('mixedCase', $requirements, true),
            requiresNumbers: in_array('numbers', $requirements, true),
            requiresSymbols: in_array('symbols', $requirements, true),
            requiresLetters: in_array('letters', $requirements, true),
            requiresUncompromised: in_array('uncompromised', $requirements, true),
        );
    }

    /**
     * Extract min length from Password::min(n).
     */
    private function extractMin(string $rule): ?int
    {
        // Match min(n) or ::min(n)
        if (preg_match('/(?:^|::)min\((\d+)\)/', $rule, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract max length from Password::...->max(n).
     */
    private function extractMax(string $rule): ?int
    {
        // Match ->max(n) in method chain
        if (preg_match('/->max\((\d+)\)/', $rule, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract password requirements from method chain.
     *
     * @return array<string>
     */
    private function extractRequirements(string $rule): array
    {
        $requirements = [];

        $methods = [
            'mixedCase',
            'numbers',
            'symbols',
            'letters',
            'uncompromised',
        ];

        foreach ($methods as $method) {
            if (str_contains($rule, '->'.$method.'(')) {
                $requirements[] = $method;
            }
        }

        return $requirements;
    }
}
