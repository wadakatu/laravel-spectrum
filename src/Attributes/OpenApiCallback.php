<?php

declare(strict_types=1);

namespace LaravelSpectrum\Attributes;

use Attribute;

/**
 * PHP 8 Attribute for defining OpenAPI callback operations on controller methods.
 *
 * Use this attribute to declare webhook/asynchronous callback definitions
 * that will be included in the generated OpenAPI specification.
 *
 * Example:
 * ```php
 * #[OpenApiCallback(
 *     name: 'onOrderStatusChange',
 *     expression: '{$request.body#/callbackUrl}',
 *     method: 'post',
 *     requestBody: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
 * )]
 * public function store(StoreOrderRequest $request): OrderResource
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class OpenApiCallback
{
    /**
     * @param  string  $name  The callback name (e.g., 'onOrderStatusChange')
     * @param  string  $expression  The runtime expression for the callback URL (e.g., '{$request.body#/callbackUrl}')
     * @param  string  $method  The HTTP method for the callback (default: 'post')
     * @param  array<string, mixed>|null  $requestBody  The request body schema for the callback
     * @param  array<string, mixed>|null  $responses  The response definitions for the callback
     * @param  string|null  $description  A description of the callback
     * @param  string|null  $summary  A short summary of the callback
     * @param  string|null  $ref  Reference name for components/callbacks
     */
    public function __construct(
        public string $name,
        public string $expression,
        public string $method = 'post',
        public ?array $requestBody = null,
        public ?array $responses = null,
        public ?string $description = null,
        public ?string $summary = null,
        public ?string $ref = null,
    ) {}
}
