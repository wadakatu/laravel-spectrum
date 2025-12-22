<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Converters\OpenApi31Converter;
use LaravelSpectrum\Support\PaginationDetector;

/**
 * Main OpenAPI specification generator.
 *
 * Orchestrates the generation of OpenAPI documentation by delegating
 * to specialized generators for different aspects of the specification.
 */
class OpenApiGenerator
{
    public function __construct(
        protected ControllerAnalyzer $controllerAnalyzer,
        protected AuthenticationAnalyzer $authenticationAnalyzer,
        protected SecuritySchemeGenerator $securitySchemeGenerator,
        protected TagGenerator $tagGenerator,
        protected OperationMetadataGenerator $metadataGenerator,
        protected ParameterGenerator $parameterGenerator,
        protected RequestBodyGenerator $requestBodyGenerator,
        protected ResourceAnalyzer $resourceAnalyzer,
        protected SchemaGenerator $schemaGenerator,
        protected ErrorResponseGenerator $errorResponseGenerator,
        protected ExampleGenerator $exampleGenerator,
        protected ResponseSchemaGenerator $responseSchemaGenerator,
        protected PaginationSchemaGenerator $paginationSchemaGenerator,
        protected PaginationDetector $paginationDetector,
        protected FormRequestAnalyzer $requestAnalyzer,
        protected OpenApi31Converter $openApi31Converter
    ) {}

    /**
     * Generate OpenAPI specification from routes.
     *
     * @param  array  $routes  Route definitions
     * @return array OpenAPI specification
     */
    public function generate(array $routes): array
    {
        // Load custom authentication schemes
        $this->authenticationAnalyzer->loadCustomSchemes();

        // Analyze authentication
        $authenticationInfo = $this->authenticationAnalyzer->analyze($routes);

        $openapi = $this->buildBaseStructure($authenticationInfo);

        // Apply global authentication if configured
        $globalAuth = $this->authenticationAnalyzer->getGlobalAuthentication();
        if ($globalAuth && $globalAuth['required']) {
            $openapi['security'] = $this->securitySchemeGenerator->generateEndpointSecurity($globalAuth);
        }

        // Generate paths
        foreach ($routes as $index => $route) {
            $path = $this->metadataGenerator->convertToOpenApiPath($route['uri']);

            foreach ($route['httpMethods'] as $method) {
                $operation = $this->generateOperation(
                    $route,
                    strtolower($method),
                    $authenticationInfo['routes'][$index] ?? null
                );

                if ($operation) {
                    $openapi['paths'][$path][strtolower($method)] = $operation;
                }
            }
        }

        // Convert to OpenAPI 3.1.0 format if enabled
        if (config('spectrum.openapi.version') === '3.1.0') {
            $openapi = $this->openApi31Converter->convert($openapi);
        }

        return $openapi;
    }

    /**
     * Build the base OpenAPI structure.
     */
    protected function buildBaseStructure(array $authenticationInfo): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('spectrum.title', config('app.name').' API'),
                'version' => config('spectrum.version', '1.0.0'),
                'description' => config('spectrum.description', ''),
            ],
            'servers' => [
                [
                    'url' => rtrim(config('app.url'), '/').'/api',
                    'description' => 'API Server',
                ],
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => $this->securitySchemeGenerator->generateSecuritySchemes(
                    $authenticationInfo['schemes']
                ),
            ],
        ];
    }

    /**
     * Generate a single operation.
     */
    protected function generateOperation(array $route, string $method, ?array $authentication = null): array
    {
        $controllerInfo = $this->controllerAnalyzer->analyze(
            $route['controller'],
            $route['method']
        );

        $operation = [
            'summary' => $this->metadataGenerator->generateSummary($route, $method),
            'operationId' => $this->metadataGenerator->generateOperationId($route, $method),
            'tags' => $this->tagGenerator->generate($route),
            'parameters' => $this->parameterGenerator->generate($route, $controllerInfo),
            'responses' => $this->generateResponses($route, $controllerInfo),
        ];

        // Generate request body for POST, PUT, PATCH
        if (in_array($method, ['post', 'put', 'patch'])) {
            $requestBody = $this->requestBodyGenerator->generate($controllerInfo, $route);
            if ($requestBody) {
                $operation['requestBody'] = $requestBody;

                // Add consumes for file uploads
                if (isset($requestBody['content']['multipart/form-data'])) {
                    $operation['consumes'] = ['multipart/form-data'];
                }
            }
        }

        // Apply security
        if ($authentication) {
            $security = $this->securitySchemeGenerator->generateEndpointSecurity($authentication);
            if (! empty($security)) {
                $operation['security'] = $security;
            }
        }

        return $operation;
    }

    /**
     * Generate responses for an operation.
     */
    protected function generateResponses(array $route, array $controllerInfo): array
    {
        $responses = [];

        // Success response
        $successResponse = $this->generateSuccessResponse($route, $controllerInfo);
        $responses[$successResponse['code']] = $successResponse['response'];

        // Error responses
        $errorResponses = $this->generateErrorResponses($route, $controllerInfo);

        return $responses + $errorResponses;
    }

    /**
     * Generate error responses.
     */
    protected function generateErrorResponses(array $route, array $controllerInfo): array
    {
        $method = strtolower($route['httpMethods'][0]);
        $requiresAuth = $this->requiresAuth($route);

        // Get validation data
        $validationData = null;

        if (! empty($controllerInfo['formRequest'])) {
            $validationData = $this->requestAnalyzer->analyzeWithDetails($controllerInfo['formRequest']);
        } elseif (! empty($controllerInfo['inlineValidation'])) {
            $validationData = [
                'rules' => $controllerInfo['inlineValidation']['rules'] ?? [],
                'messages' => $controllerInfo['inlineValidation']['messages'] ?? [],
                'attributes' => $controllerInfo['inlineValidation']['attributes'] ?? [],
            ];
        }

        // Generate error responses
        $allErrorResponses = $this->errorResponseGenerator->generateErrorResponses($validationData);

        // Get default error responses
        $defaultErrorResponses = $this->errorResponseGenerator->getDefaultErrorResponses(
            $method,
            $requiresAuth,
            ! empty($validationData)
        );

        if (empty($validationData)) {
            foreach ($defaultErrorResponses as $statusCode => &$errorResponse) {
                $errorExample = $this->exampleGenerator->generateErrorExample((int) $statusCode);
                if (isset($errorResponse['content']['application/json'])) {
                    $errorResponse['content']['application/json']['example'] = $errorExample;
                }
            }

            return $defaultErrorResponses;
        }

        $responses = $defaultErrorResponses;
        if (isset($allErrorResponses['422'])) {
            $responses['422'] = $allErrorResponses['422'];
            $validationExample = $this->exampleGenerator->generateErrorExample(422, $validationData['rules'] ?? []);
            if (isset($responses['422']['content']['application/json'])) {
                $responses['422']['content']['application/json']['example'] = $validationExample;
            }
        }

        foreach ($responses as $statusCode => &$errorResponse) {
            if ($statusCode !== '422' && isset($errorResponse['content']['application/json'])) {
                $errorExample = $this->exampleGenerator->generateErrorExample((int) $statusCode);
                $errorResponse['content']['application/json']['example'] = $errorExample;
            }
        }

        return $responses;
    }

    /**
     * Generate success response.
     */
    protected function generateSuccessResponse(array $route, array $controllerInfo): array
    {
        $method = strtolower($route['httpMethods'][0]);

        $statusCode = match ($method) {
            'post' => '201',
            'delete' => '204',
            default => '200',
        };

        $response = [
            'description' => 'Successful response',
        ];

        // Response from ResponseAnalyzer (excluding Resource type)
        if (! empty($controllerInfo['response'])
            && $controllerInfo['response']['type'] !== 'resource'
            && config('spectrum.response_detection.enabled', true)) {
            $responseSchema = $this->responseSchemaGenerator->generate($controllerInfo['response'], (int) $statusCode);
            if (! empty($responseSchema[$statusCode])) {
                return [
                    'code' => $statusCode,
                    'response' => $responseSchema[$statusCode],
                ];
            }
        }

        // Resource class response
        if (! empty($controllerInfo['resource'])) {
            $resourceStructure = $this->resourceAnalyzer->analyze($controllerInfo['resource']);

            if (! empty($resourceStructure)) {
                $schema = $this->schemaGenerator->generateFromResource($resourceStructure);

                $example = $this->exampleGenerator->generateFromResource(
                    $resourceStructure,
                    $controllerInfo['resource']
                );

                if (! empty($controllerInfo['pagination'])) {
                    $paginationType = $this->paginationDetector->getPaginationType($controllerInfo['pagination']['type']);
                    $paginatedSchema = $this->paginationSchemaGenerator->generate($paginationType, $schema);

                    $response['content'] = [
                        'application/json' => [
                            'schema' => $paginatedSchema,
                            'examples' => [
                                'default' => [
                                    'value' => $this->exampleGenerator->generateCollectionExample($example, true),
                                ],
                            ],
                        ],
                    ];
                } else {
                    $response['content'] = [
                        'application/json' => [
                            'schema' => $controllerInfo['returnsCollection']
                                ? ['type' => 'array', 'items' => $schema]
                                : $schema,
                            'examples' => [
                                'default' => [
                                    'value' => $controllerInfo['returnsCollection']
                                        ? $this->exampleGenerator->generateCollectionExample($example, false)
                                        : $example,
                                ],
                            ],
                        ],
                    ];
                }
            }
        }
        // Pagination only (without Resource)
        elseif (! empty($controllerInfo['pagination'])) {
            $modelClass = $controllerInfo['pagination']['model'];
            $basicSchema = $this->generateBasicModelSchema($modelClass);

            $paginationType = $this->paginationDetector->getPaginationType($controllerInfo['pagination']['type']);
            $paginatedSchema = $this->paginationSchemaGenerator->generate($paginationType, $basicSchema);

            $response['content'] = [
                'application/json' => [
                    'schema' => $paginatedSchema,
                ],
            ];
        }

        return [
            'code' => $statusCode,
            'response' => $response,
        ];
    }

    /**
     * Check if route requires authentication.
     */
    protected function requiresAuth(array $route): bool
    {
        $authMiddleware = ['auth', 'auth:api', 'auth:sanctum', 'passport', 'auth.basic'];

        return ! empty(array_filter($route['middleware'], function ($mw) use ($authMiddleware) {
            foreach ($authMiddleware as $auth) {
                if ($mw === $auth || \Illuminate\Support\Str::startsWith($mw, $auth.':')) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Generate basic model schema.
     */
    protected function generateBasicModelSchema(string $modelClass): array
    {
        $modelName = class_basename($modelClass);

        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
            'description' => $modelName.' object',
        ];
    }
}
