<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use LaravelSpectrum\Support\Example\FieldPatternRegistry;

/**
 * Infers field type and format based on field name patterns.
 *
 * Delegates to FieldPatternRegistry for pattern matching.
 */
class FieldNameInference
{
    public function __construct(
        private ?FieldPatternRegistry $registry = null
    ) {
        $this->registry = $registry ?? new FieldPatternRegistry;
    }

    /**
     * Infer field type and format from field name.
     *
     * @return array{type: string, format: string}
     */
    public function inferFieldType(string $fieldName): array
    {
        $config = $this->registry->getConfig($fieldName);

        if ($config !== null) {
            return [
                'type' => $config['type'],
                'format' => $config['format'] ?? 'text',
            ];
        }

        // Default to string
        return ['type' => 'string', 'format' => 'text'];
    }
}
