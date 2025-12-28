<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents file upload metadata extracted from validation rules.
 */
final readonly class FileUploadInfo
{
    /**
     * @param  bool  $isImage  Whether this is an image upload
     * @param  array<int, string>  $mimes  File extensions (e.g., ['jpeg', 'png'])
     * @param  array<int, string>  $mimeTypes  MIME types (e.g., ['image/jpeg'])
     * @param  int|null  $maxSize  Maximum file size in kilobytes
     * @param  int|null  $minSize  Minimum file size in kilobytes
     * @param  FileDimensions|null  $dimensions  Image dimension constraints
     * @param  bool  $multiple  Whether multiple files are allowed
     */
    public function __construct(
        public bool $isImage = false,
        public array $mimes = [],
        public array $mimeTypes = [],
        public ?int $maxSize = null,
        public ?int $minSize = null,
        public ?FileDimensions $dimensions = null,
        public bool $multiple = false,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $dimensions = null;
        if (isset($data['dimensions']) && is_array($data['dimensions']) && count($data['dimensions']) > 0) {
            $dimensions = FileDimensions::fromArray($data['dimensions']);
        }

        return new self(
            isImage: $data['is_image'] ?? false,
            mimes: $data['mimes'] ?? [],
            mimeTypes: $data['mime_types'] ?? [],
            maxSize: $data['max_size'] ?? null,
            minSize: $data['min_size'] ?? null,
            dimensions: $dimensions,
            multiple: $data['multiple'] ?? false,
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
            'type' => 'file',
        ];

        if ($this->isImage) {
            $result['is_image'] = $this->isImage;
        }

        if (count($this->mimes) > 0) {
            $result['mimes'] = $this->mimes;
        }

        if (count($this->mimeTypes) > 0) {
            $result['mime_types'] = $this->mimeTypes;
        }

        if ($this->maxSize !== null) {
            $result['max_size'] = $this->maxSize;
        }

        if ($this->minSize !== null) {
            $result['min_size'] = $this->minSize;
        }

        if ($this->dimensions !== null && ! $this->dimensions->isEmpty()) {
            $result['dimensions'] = $this->dimensions->toArray();
        }

        if ($this->multiple) {
            $result['multiple'] = $this->multiple;
        }

        return $result;
    }

    /**
     * Create an image upload info.
     */
    public static function image(): self
    {
        return new self(isImage: true);
    }

    /**
     * Create a generic file upload info.
     */
    public static function file(): self
    {
        return new self;
    }

    /**
     * Check if this is an image upload.
     */
    public function isImageUpload(): bool
    {
        return $this->isImage;
    }

    /**
     * Check if size constraints are defined.
     */
    public function hasSizeConstraints(): bool
    {
        return $this->maxSize !== null || $this->minSize !== null;
    }

    /**
     * Check if dimension constraints are defined.
     */
    public function hasDimensions(): bool
    {
        return $this->dimensions !== null && ! $this->dimensions->isEmpty();
    }

    /**
     * Check if MIME type restrictions are defined.
     */
    public function hasMimeRestrictions(): bool
    {
        return count($this->mimes) > 0 || count($this->mimeTypes) > 0;
    }

    /**
     * Check if multiple file uploads are allowed.
     */
    public function isMultipleUpload(): bool
    {
        return $this->multiple;
    }

    /**
     * Get accepted MIME types as a comma-separated string.
     */
    public function getAcceptedMimeTypesString(): string
    {
        return implode(', ', $this->mimeTypes);
    }
}
