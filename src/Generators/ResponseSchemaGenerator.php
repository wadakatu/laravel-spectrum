<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\DTO\ResponseType;
use LaravelSpectrum\Generators\Support\SchemaPropertyMapper;

/**
 * @phpstan-type ResponseData array{
 *     type?: string,
 *     class?: string,
 *     properties?: array<string, array<string, mixed>>,
 *     items?: array<string, mixed>,
 *     description?: string,
 *     format?: string,
 *     additionalProperties?: array<string, mixed>,
 *     contentType?: string,
 *     fileName?: string
 * }
 * @phpstan-type OpenApiResponse array{description: string, content?: array<string, array<string, array<string, mixed>>>}
 * @phpstan-type PropertySchema array{
 *     type?: string,
 *     format?: string,
 *     description?: string,
 *     properties?: array<string, array<string, mixed>>,
 *     items?: array<string, mixed>,
 *     nullable?: bool,
 *     readOnly?: bool,
 *     enum?: array<int, mixed>
 * }
 */
class ResponseSchemaGenerator
{
    protected SchemaPropertyMapper $propertyMapper;

    public function __construct(?SchemaPropertyMapper $propertyMapper = null)
    {
        $this->propertyMapper = $propertyMapper ?? new SchemaPropertyMapper;
    }

    /**
     * @param  ResponseData  $responseData
     * @return array<int, OpenApiResponse>
     */
    public function generate(array $responseData, int $statusCode = 200): array
    {
        if (empty($responseData) || $responseData['type'] === 'void') {
            return $this->generateVoidResponse($statusCode);
        }

        if ($responseData['type'] === 'unknown') {
            return $this->generateUnknownResponse($statusCode);
        }

        if ($responseData['type'] === 'resource') {
            return $this->generateResourceResponse($responseData, $statusCode);
        }

        // Handle non-JSON response types
        $responseType = ResponseType::tryFrom($responseData['type'] ?? '');
        if ($responseType !== null && $responseType->isNonJsonResponse()) {
            return $this->generateNonJsonResponse($responseData, $statusCode, $responseType);
        }

        $schema = $this->convertToOpenApiSchema($responseData);

        return [
            $statusCode => [
                'description' => $this->getStatusDescription($statusCode),
                'content' => [
                    'application/json' => [
                        'schema' => $schema,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  ResponseData  $data
     * @return array<string, mixed>
     */
    private function convertToOpenApiSchema(array $data): array
    {
        $schema = [];

        if (isset($data['type'])) {
            $schema['type'] = $data['type'];
        }

        if (isset($data['properties'])) {
            $schema['properties'] = [];
            foreach ($data['properties'] as $key => $property) {
                $schema['properties'][$key] = $this->convertPropertyToOpenApi($property);
            }
            $schema['required'] = $this->extractRequiredFields($data);
        }

        if (isset($data['items'])) {
            $schema['items'] = $this->convertToOpenApiSchema($data['items']);
        }

        if (isset($data['description'])) {
            $schema['description'] = $data['description'];
        }

        if (isset($data['format'])) {
            $schema['format'] = $data['format'];
        }

        if (isset($data['additionalProperties'])) {
            $schema['additionalProperties'] = $this->convertToOpenApiSchema($data['additionalProperties']);
        }

        return $schema;
    }

    /**
     * @param  PropertySchema  $property
     * @return array<string, mixed>
     */
    private function convertPropertyToOpenApi(array $property): array
    {
        $openApiProperty = $this->propertyMapper->mapType($property, [], 'object');
        $openApiProperty = $this->propertyMapper->mapSimpleProperties($property, $openApiProperty);
        $openApiProperty = $this->propertyMapper->mapEnum($property, $openApiProperty);
        $openApiProperty = $this->propertyMapper->mapBooleanProperties($property, $openApiProperty);

        // Handle nested properties recursively
        if (isset($property['properties'])) {
            $openApiProperty['properties'] = [];
            foreach ($property['properties'] as $key => $subProperty) {
                $openApiProperty['properties'][$key] = $this->convertPropertyToOpenApi($subProperty);
            }
        }

        // Handle array items recursively
        if (isset($property['items'])) {
            $openApiProperty['items'] = $this->convertPropertyToOpenApi($property['items']);
        }

        return $openApiProperty;
    }

    /**
     * @param  ResponseData  $data
     * @return array<int, string>
     */
    private function extractRequiredFields(array $data): array
    {
        $required = [];

        if (isset($data['properties'])) {
            foreach ($data['properties'] as $key => $property) {
                // readOnlyフィールドは必須から除外
                if (! isset($property['readOnly']) || ! $property['readOnly']) {
                    // nullableでないフィールドを必須とする
                    if (! isset($property['nullable']) || ! $property['nullable']) {
                        $required[] = $key;
                    }
                }
            }
        }

        return $required;
    }

    /**
     * @return array<int, OpenApiResponse>
     */
    private function generateVoidResponse(int $statusCode): array
    {
        return [
            $statusCode => [
                'description' => $this->getStatusDescription($statusCode),
            ],
        ];
    }

    /**
     * @return array<int, OpenApiResponse>
     */
    private function generateUnknownResponse(int $statusCode): array
    {
        return [
            $statusCode => [
                'description' => $this->getStatusDescription($statusCode),
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'description' => 'Response structure could not be determined automatically',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  ResponseData  $data
     * @return array<int, OpenApiResponse>
     */
    private function generateResourceResponse(array $data, int $statusCode): array
    {
        return [
            $statusCode => [
                'description' => $this->getStatusDescription($statusCode).' - '.($data['class'] ?? 'Resource'),
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'description' => 'Response handled by '.($data['class'] ?? 'Resource class'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate response for non-JSON content types (binary files, streams, XML, etc.)
     *
     * @param  ResponseData  $data
     * @return array<int, OpenApiResponse>
     */
    private function generateNonJsonResponse(array $data, int $statusCode, ResponseType $responseType): array
    {
        $contentType = $data['contentType'] ?? $this->getDefaultContentType($responseType);
        $description = $this->getNonJsonDescription($responseType, $data);

        // Binary responses use string type with binary format
        if ($responseType->isBinaryResponse()) {
            return [
                $statusCode => [
                    'description' => $description,
                    'content' => [
                        $contentType => [
                            'schema' => [
                                'type' => 'string',
                                'format' => 'binary',
                            ],
                        ],
                    ],
                ],
            ];
        }

        // Text-based non-JSON responses (XML, plain text, HTML)
        return [
            $statusCode => [
                'description' => $description,
                'content' => [
                    $contentType => [
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get default content type based on response type.
     */
    private function getDefaultContentType(ResponseType $responseType): string
    {
        return match ($responseType) {
            ResponseType::BINARY_FILE, ResponseType::STREAMED => 'application/octet-stream',
            ResponseType::PLAIN_TEXT => 'text/plain',
            ResponseType::XML => 'application/xml',
            ResponseType::HTML => 'text/html',
            default => 'application/octet-stream',
        };
    }

    /**
     * Get description for non-JSON response.
     *
     * @param  ResponseData  $data
     */
    private function getNonJsonDescription(ResponseType $responseType, array $data): string
    {
        $fileName = $data['fileName'] ?? null;

        return match ($responseType) {
            ResponseType::BINARY_FILE => $fileName
                ? "File download: {$fileName}"
                : 'File download',
            ResponseType::STREAMED => $fileName
                ? "Streamed response: {$fileName}"
                : 'Streamed response',
            ResponseType::PLAIN_TEXT => 'Plain text response',
            ResponseType::XML => 'XML response',
            ResponseType::HTML => 'HTML response',
            ResponseType::CUSTOM => 'Custom content type response',
            default => 'Response',
        };
    }

    private function getStatusDescription(int $statusCode): string
    {
        $descriptions = [
            200 => 'Successful response',
            201 => 'Resource created successfully',
            204 => 'No content',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            422 => 'Validation error',
            500 => 'Internal server error',
        ];

        return $descriptions[$statusCode] ?? 'Response';
    }
}
