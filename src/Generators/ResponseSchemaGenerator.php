<?php

namespace LaravelSpectrum\Generators;

class ResponseSchemaGenerator
{
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

    private function convertPropertyToOpenApi(array $property): array
    {
        $openApiProperty = [];

        if (isset($property['type'])) {
            $openApiProperty['type'] = $property['type'];
        }

        if (isset($property['properties'])) {
            $openApiProperty['properties'] = [];
            foreach ($property['properties'] as $key => $subProperty) {
                $openApiProperty['properties'][$key] = $this->convertPropertyToOpenApi($subProperty);
            }
        }

        if (isset($property['items'])) {
            $openApiProperty['items'] = $this->convertPropertyToOpenApi($property['items']);
        }

        if (isset($property['format'])) {
            $openApiProperty['format'] = $property['format'];
        }

        if (isset($property['description'])) {
            $openApiProperty['description'] = $property['description'];
        }

        if (isset($property['readOnly']) && $property['readOnly']) {
            $openApiProperty['readOnly'] = true;
        }

        if (isset($property['nullable']) && $property['nullable']) {
            $openApiProperty['nullable'] = true;
        }

        if (isset($property['enum'])) {
            $openApiProperty['enum'] = $property['enum'];
        }

        if (isset($property['example'])) {
            $openApiProperty['example'] = $property['example'];
        }

        return $openApiProperty;
    }

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

    private function generateVoidResponse(int $statusCode): array
    {
        return [
            $statusCode => [
                'description' => $this->getStatusDescription($statusCode),
            ],
        ];
    }

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
