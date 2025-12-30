<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a parameter definition for API documentation.
 *
 * This DTO encapsulates all information about a single parameter including
 * its type, validation rules, and optional metadata like enums and file info.
 */
final readonly class ParameterDefinition
{
    /**
     * @param  string  $name  The parameter name
     * @param  string  $in  Where the parameter appears (e.g., 'body', 'query', 'path')
     * @param  bool  $required  Whether the parameter is required
     * @param  string  $type  The OpenAPI type (string, integer, number, boolean, array, file)
     * @param  string  $description  Human-readable description
     * @param  mixed  $example  Example value for documentation
     * @param  array<string>  $validation  Validation rules from Laravel
     * @param  string|null  $format  OpenAPI format (email, date, date-time, uuid, binary, etc.)
     * @param  bool|null  $conditionalRequired  Whether conditionally required (only set when true)
     * @param  array<int, ConditionalRuleDetail|array<string, mixed>>|null  $conditionalRules  Conditional rule details
     * @param  EnumInfo|null  $enum  Enum information if this is an enum field
     * @param  FileUploadInfo|null  $fileInfo  File upload metadata if this is a file field
     */
    public function __construct(
        public string $name,
        public string $in,
        public bool $required,
        public string $type,
        public string $description,
        public mixed $example,
        public array $validation,
        public ?string $format = null,
        public ?bool $conditionalRequired = null,
        public ?array $conditionalRules = null,
        public ?EnumInfo $enum = null,
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
     * Check if this parameter has conditional rules.
     */
    public function hasConditionalRules(): bool
    {
        return $this->conditionalRules !== null && count($this->conditionalRules) > 0;
    }

    /**
     * Check if this parameter has enum information.
     */
    public function hasEnum(): bool
    {
        return $this->enum !== null;
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $enum = null;
        if (isset($data['enum'])) {
            if ($data['enum'] instanceof EnumInfo) {
                $enum = $data['enum'];
            } elseif (is_array($data['enum'])) {
                $enum = EnumInfo::fromArray($data['enum']);
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

        $conditionalRules = null;
        if (isset($data['conditional_rules']) && is_array($data['conditional_rules'])) {
            $conditionalRules = [];
            foreach ($data['conditional_rules'] as $rule) {
                $conditionalRules[] = $rule instanceof ConditionalRuleDetail
                    ? $rule
                    : ConditionalRuleDetail::fromArray($rule);
            }
        }

        return new self(
            name: $data['name'],
            in: $data['in'] ?? 'body',
            required: $data['required'] ?? false,
            type: $data['type'] ?? 'string',
            description: $data['description'] ?? '',
            example: $data['example'] ?? null,
            validation: $data['validation'] ?? [],
            format: $data['format'] ?? null,
            conditionalRequired: $data['conditional_required'] ?? null,
            conditionalRules: $conditionalRules,
            enum: $enum,
            fileInfo: $fileInfo,
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
            'name' => $this->name,
            'in' => $this->in,
            'required' => $this->required,
            'type' => $this->type,
            'description' => $this->description,
            'example' => $this->example,
            'validation' => $this->validation,
        ];

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        if ($this->conditionalRequired !== null) {
            $result['conditional_required'] = $this->conditionalRequired;
        }

        if ($this->conditionalRules !== null) {
            $result['conditional_rules'] = array_map(
                fn (ConditionalRuleDetail|array $rule) => $rule instanceof ConditionalRuleDetail
                    ? $rule->toArray()
                    : $rule,
                $this->conditionalRules
            );
        }

        if ($this->enum !== null) {
            $result['enum'] = $this->enum->toArray();
        }

        if ($this->fileInfo !== null) {
            $result['file_info'] = $this->fileInfo->toArray();
        }

        return $result;
    }
}
