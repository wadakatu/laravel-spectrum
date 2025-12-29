<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents parameter information generated from inline validation rules.
 *
 * This DTO encapsulates OpenAPI schema constraints extracted from Laravel validation rules,
 * including type information, format, length/value constraints, and enum values.
 */
final readonly class InlineParameterInfo
{
    /**
     * @param  string  $name  The field name
     * @param  string  $type  OpenAPI type (string, integer, number, boolean, array, file)
     * @param  bool  $required  Whether the field is required
     * @param  string|array<string>  $rules  Original validation rules
     * @param  string  $description  Human-readable description
     * @param  string|null  $format  OpenAPI format (email, uri, uuid, date, date-time, binary)
     * @param  int|null  $minLength  Minimum string length
     * @param  int|null  $maxLength  Maximum string length
     * @param  int|null  $minimum  Minimum numeric value
     * @param  int|null  $maximum  Maximum numeric value
     * @param  array<string>|null  $inlineEnum  Enum values from in:a,b,c rule
     * @param  EnumInfo|null  $enumInfo  Backed enum information
     * @param  FileUploadInfo|null  $fileInfo  File upload metadata
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public string|array $rules,
        public string $description,
        public ?string $format = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?int $minimum = null,
        public ?int $maximum = null,
        public ?array $inlineEnum = null,
        public ?EnumInfo $enumInfo = null,
        public ?FileUploadInfo $fileInfo = null,
    ) {}

    /**
     * Check if this is a file upload parameter.
     */
    public function isFileUpload(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Check if this parameter has enum values (inline or backed).
     */
    public function hasEnum(): bool
    {
        return $this->inlineEnum !== null || $this->enumInfo !== null;
    }

    /**
     * Check if this parameter has any schema constraints.
     */
    public function hasConstraints(): bool
    {
        return $this->minLength !== null
            || $this->maxLength !== null
            || $this->minimum !== null
            || $this->maximum !== null;
    }

    /**
     * Check if this parameter has string length constraints.
     */
    public function hasLengthConstraints(): bool
    {
        return $this->minLength !== null || $this->maxLength !== null;
    }

    /**
     * Check if this parameter has numeric value constraints.
     */
    public function hasNumericConstraints(): bool
    {
        return $this->minimum !== null || $this->maximum !== null;
    }

    /**
     * Get the enum values (either inline or from EnumInfo).
     *
     * @return array<string|int>|null
     */
    public function getEnumValues(): ?array
    {
        if ($this->inlineEnum !== null) {
            return $this->inlineEnum;
        }

        if ($this->enumInfo !== null) {
            return $this->enumInfo->values;
        }

        return null;
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $enumInfo = null;
        $inlineEnum = null;

        // Handle enum field which can be array or EnumInfo
        if (isset($data['enum'])) {
            if ($data['enum'] instanceof EnumInfo) {
                $enumInfo = $data['enum'];
            } elseif (is_array($data['enum'])) {
                // Check if it's EnumInfo array format or inline enum values
                if (isset($data['enum']['class'])) {
                    $enumInfo = EnumInfo::fromArray($data['enum']);
                } else {
                    $inlineEnum = $data['enum'];
                }
            }
        }

        $fileInfo = null;
        if (isset($data['file_info'])) {
            if ($data['file_info'] instanceof FileUploadInfo) {
                $fileInfo = $data['file_info'];
            } elseif (is_array($data['file_info'])) {
                $fileInfo = FileUploadInfo::fromArray($data['file_info']);
            }
        }

        return new self(
            name: $data['name'],
            type: $data['type'] ?? 'string',
            required: $data['required'] ?? false,
            rules: $data['rules'] ?? [],
            description: $data['description'] ?? '',
            format: $data['format'] ?? null,
            minLength: $data['minLength'] ?? null,
            maxLength: $data['maxLength'] ?? null,
            minimum: $data['minimum'] ?? null,
            maximum: $data['maximum'] ?? null,
            inlineEnum: $inlineEnum,
            enumInfo: $enumInfo,
            fileInfo: $fileInfo,
        );
    }

    /**
     * Convert to array (backward compatible format).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'rules' => $this->rules,
            'description' => $this->description,
        ];

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        if ($this->minLength !== null) {
            $result['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }

        if ($this->minimum !== null) {
            $result['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $result['maximum'] = $this->maximum;
        }

        // Output enum as either inline values or EnumInfo serialized to array
        if ($this->inlineEnum !== null) {
            $result['enum'] = $this->inlineEnum;
        } elseif ($this->enumInfo !== null) {
            $result['enum'] = $this->enumInfo->toArray();
        }

        if ($this->fileInfo !== null) {
            $result['file_info'] = $this->fileInfo->toArray();
        }

        return $result;
    }
}
