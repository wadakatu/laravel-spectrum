<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI Parameter Object.
 *
 * @see https://spec.openapis.org/oas/v3.0.3#parameter-object
 */
final readonly class OpenApiParameter
{
    public const IN_QUERY = 'query';

    public const IN_PATH = 'path';

    public const IN_HEADER = 'header';

    public const IN_COOKIE = 'cookie';

    public const STYLE_FORM = 'form';

    public const STYLE_SIMPLE = 'simple';

    public const STYLE_MATRIX = 'matrix';

    public const STYLE_LABEL = 'label';

    public const STYLE_SPACE_DELIMITED = 'spaceDelimited';

    public const STYLE_PIPE_DELIMITED = 'pipeDelimited';

    public const STYLE_DEEP_OBJECT = 'deepObject';

    /**
     * @param  string  $name  The name of the parameter
     * @param  string  $in  The location of the parameter (query, path, header, cookie)
     * @param  bool  $required  Whether the parameter is required
     * @param  OpenApiSchema  $schema  The parameter's schema
     * @param  string|null  $description  A brief description
     * @param  string|null  $style  How the parameter value will be serialized
     * @param  bool|null  $explode  Whether to generate separate params for arrays/objects
     * @param  bool|null  $deprecated  Whether the parameter is deprecated
     * @param  bool|null  $allowEmptyValue  Allow empty value (only for query params)
     */
    public function __construct(
        public string $name,
        public string $in,
        public bool $required,
        public OpenApiSchema $schema,
        public ?string $description = null,
        public ?string $style = null,
        public ?bool $explode = null,
        public ?bool $deprecated = null,
        public ?bool $allowEmptyValue = null,
    ) {}

    /**
     * Create a query parameter.
     */
    public static function query(
        string $name,
        OpenApiSchema $schema,
        bool $required = false,
        ?string $description = null,
    ): self {
        return new self(
            name: $name,
            in: self::IN_QUERY,
            required: $required,
            schema: $schema,
            description: $description,
        );
    }

    /**
     * Create a path parameter.
     */
    public static function path(
        string $name,
        OpenApiSchema $schema,
        ?string $description = null,
    ): self {
        return new self(
            name: $name,
            in: self::IN_PATH,
            required: true, // Path parameters are always required
            schema: $schema,
            description: $description,
        );
    }

    /**
     * Create a header parameter.
     */
    public static function header(
        string $name,
        OpenApiSchema $schema,
        bool $required = false,
        ?string $description = null,
    ): self {
        return new self(
            name: $name,
            in: self::IN_HEADER,
            required: $required,
            schema: $schema,
            description: $description,
        );
    }

    /**
     * Create from QueryParameterInfo.
     */
    public static function fromQueryParameterInfo(QueryParameterInfo $info): self
    {
        $schema = OpenApiSchema::fromType($info->type, $info->default);

        if ($info->enum !== null) {
            $schema = $schema->withEnum($info->enum);
        }

        return new self(
            name: $info->name,
            in: self::IN_QUERY,
            required: $info->required,
            schema: $schema,
            description: $info->description,
        );
    }

    /**
     * Create from an array (for backward compatibility).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $schemaData = $data['schema'] ?? ['type' => 'string'];
        $schema = $schemaData instanceof OpenApiSchema
            ? $schemaData
            : OpenApiSchema::fromArray($schemaData);

        return new self(
            name: $data['name'],
            in: $data['in'] ?? self::IN_QUERY,
            required: $data['required'] ?? false,
            schema: $schema,
            description: $data['description'] ?? null,
            style: $data['style'] ?? null,
            explode: $data['explode'] ?? null,
            deprecated: $data['deprecated'] ?? null,
            allowEmptyValue: $data['allowEmptyValue'] ?? null,
        );
    }

    /**
     * Convert to array for OpenAPI output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'in' => $this->in,
            'required' => $this->required,
            'schema' => $this->schema->toArray(),
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->style !== null) {
            $result['style'] = $this->style;
        }

        if ($this->explode !== null) {
            $result['explode'] = $this->explode;
        }

        if ($this->deprecated !== null) {
            $result['deprecated'] = $this->deprecated;
        }

        if ($this->allowEmptyValue !== null) {
            $result['allowEmptyValue'] = $this->allowEmptyValue;
        }

        return $result;
    }

    /**
     * Create a new instance with style and explode.
     */
    public function withStyleAndExplode(string $style, bool $explode): self
    {
        return new self(
            name: $this->name,
            in: $this->in,
            required: $this->required,
            schema: $this->schema,
            description: $this->description,
            style: $style,
            explode: $explode,
            deprecated: $this->deprecated,
            allowEmptyValue: $this->allowEmptyValue,
        );
    }

    /**
     * Create a new instance with updated schema.
     */
    public function withSchema(OpenApiSchema $schema): self
    {
        return new self(
            name: $this->name,
            in: $this->in,
            required: $this->required,
            schema: $schema,
            description: $this->description,
            style: $this->style,
            explode: $this->explode,
            deprecated: $this->deprecated,
            allowEmptyValue: $this->allowEmptyValue,
        );
    }

    /**
     * Check if this is an array type parameter.
     */
    public function isArrayType(): bool
    {
        return $this->schema->type === 'array';
    }

    /**
     * Check if this is a query parameter.
     */
    public function isQueryParameter(): bool
    {
        return $this->in === self::IN_QUERY;
    }

    /**
     * Check if this is a path parameter.
     */
    public function isPathParameter(): bool
    {
        return $this->in === self::IN_PATH;
    }
}
