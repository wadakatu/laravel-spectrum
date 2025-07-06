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

    protected ErrorResponseGenerator $errorResponseGenerator;

    public function __construct(
        FormRequestAnalyzer $requestAnalyzer,
        ResourceAnalyzer $resourceAnalyzer,
        ControllerAnalyzer $controllerAnalyzer,
        SchemaGenerator $schemaGenerator,
        ErrorResponseGenerator $errorResponseGenerator
    ) {
        $this->requestAnalyzer = $requestAnalyzer;
        $this->resourceAnalyzer = $resourceAnalyzer;
        $this->controllerAnalyzer = $controllerAnalyzer;
        $this->schemaGenerator = $schemaGenerator;
        $this->errorResponseGenerator = $errorResponseGenerator;
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

        // エラーレスポンスを生成
        $errorResponses = $this->generateErrorResponses($route, $controllerInfo);
        
        // マージ（array_mergeは数値キーを再インデックスするので + を使用）
        return $responses + $errorResponses;
    }
    
    /**
     * エラーレスポンスを生成
     */
    protected function generateErrorResponses(array $route, array $controllerInfo): array
    {
        $method = strtolower($route['httpMethods'][0]);
        $requiresAuth = $this->requiresAuth($route);
        
        // FormRequestがある場合は、そのルールから422エラーを生成
        $formRequestData = null;
        if (!empty($controllerInfo['formRequest'])) {
            $formRequestData = $this->requestAnalyzer->analyzeWithDetails($controllerInfo['formRequest']);
        }
        
        // エラーレスポンスを生成
        $allErrorResponses = $this->errorResponseGenerator->generateErrorResponses($formRequestData);
        
        // デフォルトのエラーレスポンスを取得
        $defaultErrorResponses = $this->errorResponseGenerator->getDefaultErrorResponses(
            $method,
            $requiresAuth,
            !empty($formRequestData)
        );
        
        // FormRequestがない場合は、デフォルトのエラーレスポンスのみを使用
        if (empty($formRequestData)) {
            return $defaultErrorResponses;
        }
        
        // FormRequestがある場合は、422エラーも含める
        $responses = $defaultErrorResponses;
        if (isset($allErrorResponses['422'])) {
            $responses['422'] = $allErrorResponses['422'];
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
