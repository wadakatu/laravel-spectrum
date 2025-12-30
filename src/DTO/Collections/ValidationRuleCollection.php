<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO\Collections;

use Illuminate\Validation\Rules\File;

/**
 * Immutable collection of Laravel validation rules.
 *
 * Handles string rules (e.g., 'required|email'), rule objects
 * (e.g., File::types(['jpg', 'png']), Enum), and arrays (nested rules).
 * Provides convenient methods for file upload rule detection.
 *
 * @extends AbstractCollection<mixed>
 */
final readonly class ValidationRuleCollection extends AbstractCollection
{
    /**
     * Laravel validation rules that indicate file uploads.
     *
     * @var array<int, string>
     */
    private const array FILE_RULES = ['file', 'image', 'mimes', 'mimetypes'];

    /**
     * Create an empty collection.
     */
    public static function empty(): static
    {
        return new self([]);
    }

    /**
     * Create a collection from a pipe-separated string.
     *
     * @param  string  $rules  Pipe-separated rules (e.g., 'required|email|max:255')
     */
    public static function fromString(string $rules): self
    {
        if ($rules === '') {
            return self::empty();
        }

        return new self(explode('|', $rules));
    }

    /**
     * Create a collection from an array of rules.
     *
     * @param  array<mixed>  $rules
     */
    public static function fromArray(array $rules): self
    {
        return new self(array_values($rules));
    }

    /**
     * Create a collection from either string or array input.
     *
     * @param  string|array<mixed>  $rules
     */
    public static function from(string|array $rules): self
    {
        return is_string($rules)
            ? self::fromString($rules)
            : self::fromArray($rules);
    }

    /**
     * Check if the collection contains any file upload rules.
     */
    public function hasFileRule(): bool
    {
        return $this->any($this->isFileRule(...));
    }

    /**
     * Get only the file upload rules from this collection.
     */
    public function getFileRules(): self
    {
        return $this->filter($this->isFileRule(...));
    }

    /**
     * Get only the non-file rules from this collection.
     */
    public function getNonFileRules(): self
    {
        return $this->filter(fn (mixed $rule): bool => ! $this->isFileRule($rule));
    }

    /**
     * Check if a rule indicates a file upload.
     */
    private function isFileRule(mixed $rule): bool
    {
        if ($rule instanceof File) {
            return true;
        }

        if (! is_string($rule)) {
            return false;
        }

        $ruleName = explode(':', $rule)[0];

        return in_array($ruleName, self::FILE_RULES, true);
    }
}
