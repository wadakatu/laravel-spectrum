<?php

namespace LaravelPrism\Generators;

use Illuminate\Support\Str;
use LaravelPrism\Analyzers\AuthenticationAnalyzer;
use LaravelPrism\Analyzers\ControllerAnalyzer;
use LaravelPrism\Analyzers\FormRequestAnalyzer;
use LaravelPrism\Analyzers\InlineValidationAnalyzer;
use LaravelPrism\Analyzers\ResourceAnalyzer;

class OpenApiGenerator
{
    protected FormRequestAnalyzer $requestAnalyzer;

    protected ResourceAnalyzer $resourceAnalyzer;

    protected ControllerAnalyzer $controllerAnalyzer;

    protected InlineValidationAnalyzer $inlineValidationAnalyzer;

    protected SchemaGenerator $schemaGenerator;

    protected ErrorResponseGenerator $errorResponseGenerator;

    protected AuthenticationAnalyzer $authenticationAnalyzer;

    protected SecuritySchemeGenerator $securitySchemeGenerator;

    public function __construct(
        FormRequestAnalyzer $requestAnalyzer,
        ResourceAnalyzer $resourceAnalyzer,
        ControllerAnalyzer $controllerAnalyzer,
        InlineValidationAnalyzer $inlineValidationAnalyzer,
        SchemaGenerator $schemaGenerator,
        ErrorResponseGenerator $errorResponseGenerator,
        AuthenticationAnalyzer $authenticationAnalyzer,
        SecuritySchemeGenerator $securitySchemeGenerator
    ) {
        $this->requestAnalyzer = $requestAnalyzer;
        $this->resourceAnalyzer = $resourceAnalyzer;
        $this->controllerAnalyzer = $controllerAnalyzer;
        $this->inlineValidationAnalyzer = $inlineValidationAnalyzer;
        $this->schemaGenerator = $schemaGenerator;
        $this->errorResponseGenerator = $errorResponseGenerator;
        $this->authenticationAnalyzer = $authenticationAnalyzer;
        $this->securitySchemeGenerator = $securitySchemeGenerator;
    }

    /**
     * OpenAPI仕様を生成
     */
    public function generate(array $routes): array
    {
        // カスタム認証スキームを読み込む
        $this->authenticationAnalyzer->loadCustomSchemes();

        // 認証情報を分析
        $authenticationInfo = $this->authenticationAnalyzer->analyze($routes);

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
                'securitySchemes' => $this->securitySchemeGenerator->generateSecuritySchemes(
                    $authenticationInfo['schemes']
                ),
            ],
        ];

        // グローバル認証設定
        $globalAuth = $this->authenticationAnalyzer->getGlobalAuthentication();
        if ($globalAuth && $globalAuth['required']) {
            $openapi['security'] = $this->securitySchemeGenerator->generateEndpointSecurity($globalAuth);
        }

        foreach ($routes as $index => $route) {
            $path = $this->convertToOpenApiPath($route['uri']);

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

        return $openapi;
    }

    /**
     * 単一のオペレーションを生成
     */
    protected function generateOperation(array $route, string $method, ?array $authentication = null): array
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
        if ($authentication) {
            $security = $this->securitySchemeGenerator->generateEndpointSecurity($authentication);
            if (! empty($security)) {
                $operation['security'] = $security;
            }
        }

        return $operation;
    }

    /**
     * リクエストボディを生成
     */
    protected function generateRequestBody(array $controllerInfo): ?array
    {
        $parameters = [];

        // FormRequestがある場合
        if (! empty($controllerInfo['formRequest'])) {
            $parameters = $this->requestAnalyzer->analyze($controllerInfo['formRequest']);
        }
        // インラインバリデーションがある場合
        elseif (! empty($controllerInfo['inlineValidation'])) {
            $parameters = $this->inlineValidationAnalyzer->generateParameters(
                $controllerInfo['inlineValidation']
            );
        }

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

        // バリデーションデータを取得
        $validationData = null;

        if (! empty($controllerInfo['formRequest'])) {
            $validationData = $this->requestAnalyzer->analyzeWithDetails($controllerInfo['formRequest']);
        } elseif (! empty($controllerInfo['inlineValidation'])) {
            // インラインバリデーションのデータをFormRequestの形式に変換
            $validationData = [
                'rules' => $controllerInfo['inlineValidation']['rules'] ?? [],
                'messages' => $controllerInfo['inlineValidation']['messages'] ?? [],
                'attributes' => $controllerInfo['inlineValidation']['attributes'] ?? [],
            ];
        }

        // エラーレスポンスを生成
        $allErrorResponses = $this->errorResponseGenerator->generateErrorResponses($validationData);

        // デフォルトのエラーレスポンスを取得
        $defaultErrorResponses = $this->errorResponseGenerator->getDefaultErrorResponses(
            $method,
            $requiresAuth,
            ! empty($validationData)
        );

        // バリデーションがない場合は、デフォルトのエラーレスポンスのみを使用
        if (empty($validationData)) {
            return $defaultErrorResponses;
        }

        // バリデーションがある場合は、422エラーも含める
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
