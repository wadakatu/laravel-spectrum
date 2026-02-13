<?php

declare(strict_types=1);

namespace LaravelSpectrum\Attributes;

use Attribute;

/**
 * PHP 8 Attribute for defining OpenAPI response links on controller methods.
 *
 * Example:
 * #[OpenApiResponseLink(
 *     statusCode: 201,
 *     name: 'GetUserById',
 *     operationId: 'getUser',
 *     parameters: ['userId' => '$response.body#/id'],
 * )]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class OpenApiResponseLink
{
    /**
     * @param  int|string  $statusCode  Response status code to attach this link to
     * @param  string  $name  Link name
     * @param  string|null  $operationId  Target operationId
     * @param  string|null  $operationRef  Target operationRef
     * @param  array<string, mixed>|null  $parameters  Runtime expression parameter map
     * @param  mixed  $requestBody  Runtime expression request body
     * @param  string|null  $description  Link description
     * @param  array<string, mixed>|null  $server  Optional server override
     */
    public function __construct(
        public int|string $statusCode,
        public string $name,
        public ?string $operationId = null,
        public ?string $operationRef = null,
        public ?array $parameters = null,
        public mixed $requestBody = null,
        public ?string $description = null,
        public ?array $server = null,
    ) {}
}
