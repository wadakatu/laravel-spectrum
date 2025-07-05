<?php

namespace LaravelPrism\Generators;

use Illuminate\Support\Str;
use LaravelPrism\Analyzers\ControllerAnalyzer;
use LaravelPrism\Analyzers\FormRequestAnalyzer;
use LaravelPrism\Analyzers\ResourceAnalyzer;

class OpenApiGenerator
{
    protected FormRequestAnalyzer $requestAnalyzer;

    protected ResourceAnalyzer $resourceAnalyzer;

    protected ControllerAnalyzer $controllerAnalyzer;

    protected SchemaGenerator $schemaGenerator;

    public function __construct(
        FormRequestAnalyzer $requestAnalyzer,
        ResourceAnalyzer $resourceAnalyzer,
        ControllerAnalyzer $controllerAnalyzer,
        SchemaGenerator $schemaGenerator
    ) {
        $this->requestAnalyzer = $requestAnalyzer;
        $this->resourceAnalyzer = $resourceAnalyzer;
        $this->controllerAnalyzer = $controllerAnalyzer;
        $this->schemaGenerator = $schemaGenerator;
    }

    /**
     * OpenAPI仕様を生成
     */
    public function generate(array $routes): array
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('prism.title', config('app.name').' API'),
                'version' => config('prism.version', '1.0.0'),
                'description' => config('prism.description', ''),
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
                'securitySchemes' => $this->generateSecuritySchemes(),
            ],
        ];

        foreach ($routes as $route) {
            $path = $this->convertToOpenApiPath($route['uri']);

            foreach ($route['httpMethods'] as $method) {
                $operation = $this->generateOperation($route, strtolower($method));

                if ($operation) {
                    $openapi['paths'][$path][strtolower($method)] = $operation;
                }
            }
        }

        return $openapi;
    }

    /**
     * 単一のオペレーションを生成
     */
    protected function generateOperation(array $route, string $method): array
    {
        $controllerInfo = $this->controllerAnalyzer->analyze(
            $route['controller'],
            $route['method']
        );

        $operation = [
            'summary' => $this->generateSummary($route, $method),
            'operationId' => $this->generateOperationId($route, $method),
            'tags' => $this->generateTags($route),
            'parameters' => $this->generateParameters($route, $controllerInfo),
            'responses' => $this->generateResponses($route, $controllerInfo),
        ];

        // リクエストボディの生成
        if (in_array($method, ['post', 'put', 'patch'])) {
            $requestBody = $this->generateRequestBody($controllerInfo);
            if ($requestBody) {
                $operation['requestBody'] = $requestBody;
            }
        }

        // セキュリティの適用
        if ($this->requiresAuth($route)) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        return $operation;
    }

    /**
     * リクエストボディを生成
     */
    protected function generateRequestBody(array $controllerInfo): ?array
    {
        if (empty($controllerInfo['formRequest'])) {
            return null;
        }

        $parameters = $this->requestAnalyzer->analyze($controllerInfo['formRequest']);

        if (empty($parameters)) {
            return null;
        }

        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * レスポンスを生成
     */
    protected function generateResponses(array $route, array $controllerInfo): array
    {
        $responses = [];

        // 成功レスポンス
        $successResponse = $this->generateSuccessResponse($route, $controllerInfo);
        $responses[$successResponse['code']] = $successResponse['response'];

        // エラーレスポンス（MVP版では基本的なもののみ）
        $responses['401'] = [
            'description' => 'Unauthorized',
        ];

        $responses['404'] = [
            'description' => 'Not Found',
        ];

        if (in_array(strtolower($route['httpMethods'][0]), ['post', 'put', 'patch'])) {
            $responses['422'] = [
                'description' => 'Validation Error',
            ];
        }

        return $responses;
    }

    /**
     * 成功レスポンスを生成
     */
    protected function generateSuccessResponse(array $route, array $controllerInfo): array
    {
        $method = strtolower($route['httpMethods'][0]);

        // HTTPメソッドに基づくデフォルトのステータスコード
        $statusCode = match ($method) {
            'post' => '201',
            'delete' => '204',
            default => '200',
        };

        $response = [
            'description' => 'Successful response',
        ];

        // Resourceクラスがある場合
        if (! empty($controllerInfo['resource'])) {
            $resourceStructure = $this->resourceAnalyzer->analyze($controllerInfo['resource']);

            if (! empty($resourceStructure)) {
                $schema = $this->schemaGenerator->generateFromResource($resourceStructure);

                $response['content'] = [
                    'application/json' => [
                        'schema' => $controllerInfo['returnsCollection']
                            ? ['type' => 'array', 'items' => $schema]
                            : $schema,
                    ],
                ];
            }
        }

        return [
            'code' => $statusCode,
            'response' => $response,
        ];
    }

    /**
     * Laravel URIをOpenAPIパスに変換
     */
    protected function convertToOpenApiPath(string $uri): string
    {
        return '/'.preg_replace('/\{([^}]+)\?\}/', '{$1}', $uri);
    }

    /**
     * オペレーションIDを生成
     */
    protected function generateOperationId(array $route, string $method): string
    {
        if ($route['name']) {
            return Str::camel($route['name']);
        }

        $uri = str_replace(['/', '{', '}', '?'], ['_', '', '', ''], $route['uri']);

        return Str::camel($method.'_'.$uri);
    }

    /**
     * サマリーを生成
     */
    protected function generateSummary(array $route, string $method): string
    {
        $resource = $this->extractResourceName($route['uri']);

        return match ($method) {
            'get' => Str::contains($route['uri'], '{')
                ? "Get {$resource} by ID"
                : "List all {$resource}",
            'post' => "Create a new {$resource}",
            'put', 'patch' => "Update {$resource}",
            'delete' => "Delete {$resource}",
            default => ucfirst($method)." {$resource}",
        };
    }

    /**
     * タグを生成
     */
    protected function generateTags(array $route): array
    {
        $segments = explode('/', trim($route['uri'], '/'));

        // 'api/v1/users' -> 'Users'
        $tag = end($segments);
        $tag = Str::studly(Str::singular($tag));

        return [$tag];
    }

    /**
     * パラメータを生成
     */
    protected function generateParameters(array $route, array $controllerInfo): array
    {
        return $route['parameters'];
    }

    /**
     * 認証が必要かどうかを判定
     */
    protected function requiresAuth(array $route): bool
    {
        $authMiddleware = ['auth', 'auth:api', 'auth:sanctum'];

        return ! empty(array_intersect($route['middleware'], $authMiddleware));
    }

    /**
     * セキュリティスキームを生成
     */
    protected function generateSecuritySchemes(): array
    {
        return [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ];
    }

    /**
     * URIからリソース名を抽出
     */
    protected function extractResourceName(string $uri): string
    {
        $segments = explode('/', trim($uri, '/'));
        $resource = end($segments);

        // パラメータを削除
        $resource = preg_replace('/\\{[^}]+\\}/', '', $resource);

        return Str::studly(Str::singular($resource));
    }
}
