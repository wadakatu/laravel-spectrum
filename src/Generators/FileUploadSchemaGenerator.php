<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

class FileUploadSchemaGenerator
{
    /**
     * @param array<string, mixed> $fileField
     * @return array<string, mixed>
     */
    public function generate(array $fileField): array
    {
        $schema = [
            'type' => 'string',
            'format' => 'binary',
        ];

        $description = $this->generateDescription($fileField);
        if ($description !== '') {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $fileFields
     * @return array<string, mixed>
     */
    public function generateMultipartSchema(array $fields, array $fileFields): array
    {
        $properties = $this->mergeProperties($fields, $fileFields);
        $required = $this->extractRequired($fields, $fileFields);

        return [
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fileField
     */
    private function generateDescription(array $fileField): string
    {
        $parts = [];

        // Allowed types
        if (!empty($fileField['mimes'])) {
            $parts[] = 'Allowed types: ' . implode(', ', $fileField['mimes']);
        }

        // File size constraints
        if (isset($fileField['max_size'])) {
            $parts[] = 'Max size: ' . $this->formatFileSize($fileField['max_size']);
        }
        
        if (isset($fileField['min_size'])) {
            $parts[] = 'Min size: ' . $this->formatFileSize($fileField['min_size']);
        }

        // Dimension constraints
        if (!empty($fileField['dimensions'])) {
            $dimensionParts = $this->formatDimensions($fileField['dimensions']);
            $parts = array_merge($parts, $dimensionParts);
        }

        return implode('. ', $parts);
    }

    /**
     * @param array<string, mixed> $dimensions
     * @return array<string>
     */
    private function formatDimensions(array $dimensions): array
    {
        $parts = [];

        // Exact dimensions
        if (isset($dimensions['width']) && isset($dimensions['height'])) {
            $parts[] = sprintf('Required dimensions: %dx%d', $dimensions['width'], $dimensions['height']);
        }

        // Min dimensions
        if (isset($dimensions['min_width']) && isset($dimensions['min_height'])) {
            $parts[] = sprintf('Min dimensions: %dx%d', $dimensions['min_width'], $dimensions['min_height']);
        } elseif (isset($dimensions['min_width'])) {
            $parts[] = sprintf('Min width: %d', $dimensions['min_width']);
        } elseif (isset($dimensions['min_height'])) {
            $parts[] = sprintf('Min height: %d', $dimensions['min_height']);
        }

        // Max dimensions
        if (isset($dimensions['max_width']) && isset($dimensions['max_height'])) {
            $parts[] = sprintf('Max dimensions: %dx%d', $dimensions['max_width'], $dimensions['max_height']);
        } elseif (isset($dimensions['max_width'])) {
            $parts[] = sprintf('Max width: %d', $dimensions['max_width']);
        } elseif (isset($dimensions['max_height'])) {
            $parts[] = sprintf('Max height: %d', $dimensions['max_height']);
        }

        // Aspect ratio
        if (isset($dimensions['ratio'])) {
            $parts[] = sprintf('Aspect ratio: %s', $dimensions['ratio']);
        }

        return $parts;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            $size = $bytes / 1073741824;
            return $size == (int) $size ? sprintf('%dGB', (int) $size) : sprintf('%.1fGB', $size);
        }
        
        if ($bytes >= 1048576) {
            $size = $bytes / 1048576;
            return $size == (int) $size ? sprintf('%dMB', (int) $size) : sprintf('%.1fMB', $size);
        }
        
        if ($bytes >= 1024) {
            $size = $bytes / 1024;
            return $size == (int) $size ? sprintf('%dKB', (int) $size) : sprintf('%.1fKB', $size);
        }
        
        return sprintf('%dB', $bytes);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $fileFields
     * @return array<string, mixed>
     */
    private function mergeProperties(array $fields, array $fileFields): array
    {
        $properties = [];

        // Add regular fields
        foreach ($fields as $name => $field) {
            $properties[$name] = [
                'type' => $field['type'] ?? 'string',
            ];
            
            if (isset($field['maxLength'])) {
                $properties[$name]['maxLength'] = $field['maxLength'];
            }
            
            // Add other properties as needed
            foreach (['minLength', 'pattern', 'enum', 'format', 'minimum', 'maximum'] as $prop) {
                if (isset($field[$prop])) {
                    $properties[$name][$prop] = $field[$prop];
                }
            }
        }

        // Add file fields
        foreach ($fileFields as $name => $field) {
            $properties[$name] = $field;
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $fileFields
     * @return array<string>
     */
    private function extractRequired(array $fields, array $fileFields): array
    {
        $required = [];

        foreach ($fields as $name => $field) {
            if (isset($field['required']) && $field['required']) {
                $required[] = $name;
            }
        }

        foreach ($fileFields as $name => $field) {
            if (isset($field['required']) && $field['required']) {
                $required[] = $name;
            }
        }

        return array_values(array_unique($required));
    }
}