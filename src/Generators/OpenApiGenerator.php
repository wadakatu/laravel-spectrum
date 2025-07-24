<?php

namespace LaravelSpectrum\Generators;

use Illuminate\Support\Str;
use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Support\PaginationDetector;
use LaravelSpectrum\Support\QueryParameterTypeInference;

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

    protected PaginationSchemaGenerator $paginationSchemaGenerator;

    protected PaginationDetector $paginationDetector;

    protected ExampleGenerator $exampleGenerator;

    protected ResponseSchemaGenerator $responseSchemaGenerator;

    public function __construct(
        FormRequestAnalyzer $requestAnalyzer,
        ResourceAnalyzer $resourceAnalyzer,
        ControllerAnalyzer $controllerAnalyzer,
        InlineValidationAnalyzer $inlineValidationAnalyzer,
        SchemaGenerator $schemaGenerator,
        ErrorResponseGenerator $errorResponseGenerator,
        AuthenticationAnalyzer $authenticationAnalyzer,
        SecuritySchemeGenerator $securitySchemeGenerator,
        PaginationSchemaGenerator $paginationSchemaGenerator,
        PaginationDetector $paginationDetector,
        ExampleGenerator $exampleGenerator,
        ResponseSchemaGenerator $responseSchemaGenerator
    ) {
        $this->requestAnalyzer = $requestAnalyzer;
        $this->resourceAnalyzer = $resourceAnalyzer;
        $this->controllerAnalyzer = $controllerAnalyzer;
        $this->inlineValidationAnalyzer = $inlineValidationAnalyzer;
        $this->schemaGenerator = $schemaGenerator;
        $this->errorResponseGenerator = $errorResponseGenerator;
        $this->authenticationAnalyzer = $authenticationAnalyzer;
        $this->securitySchemeGenerator = $securitySchemeGenerator;
        $this->paginationSchemaGenerator = $paginationSchemaGenerator;
        $this->paginationDetector = $paginationDetector;
        $this->exampleGenerator = $exampleGenerator;
        $this->responseSchemaGenerator = $responseSchemaGenerator;
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
            $requestBody = $this->generateRequestBody($controllerInfo, $route);
            if ($requestBody) {
                $operation['requestBody'] = $requestBody;

                // Add consumes for file uploads
                if (isset($requestBody['content']['multipart/form-data'])) {
                    $operation['consumes'] = ['multipart/form-data'];
                }
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
    protected function generateRequestBody(array $controllerInfo, array $route): ?array
    {
        $parameters = [];
        $conditionalRules = null;

        // FormRequestがある場合
        if (! empty($controllerInfo['formRequest'])) {
            // Try to get conditional rules
            $analysisResult = $this->requestAnalyzer->analyzeWithConditionalRules($controllerInfo['formRequest']);

            if (! empty($analysisResult['conditional_rules']['rules_sets'])) {
                $conditionalRules = $analysisResult['conditional_rules'];
                $parameters = $analysisResult['parameters'] ?? [];
            } else {
                // Fallback to regular analysis
                $parameters = $this->requestAnalyzer->analyze($controllerInfo['formRequest']);
            }
        }
        // インラインバリデーションがある場合
        elseif (! empty($controllerInfo['inlineValidation'])) {
            $parameters = $this->inlineValidationAnalyzer->generateParameters(
                $controllerInfo['inlineValidation']
            );
        }

        if (empty($parameters) && empty($conditionalRules)) {
            return null;
        }

        // Check if any parameter is a file upload
        $hasFileUpload = $this->hasFileUploadParameters($parameters);

        if ($hasFileUpload) {
            // Generate enhanced multipart schema
            $schema = $this->schemaGenerator->generateFromParameters($parameters);

            // Ensure content is set correctly
            if (isset($schema['content'])) {
                $requestBody = [
                    'required' => true,
                    'content' => $schema['content'],
                ];

                // Add description for file upload endpoint
                $description = $this->generateFileUploadDescription($parameters);

                if ($description) {
                    $requestBody['description'] = $description;
                }

                return $requestBody;
            }
        }

        // Generate schema
        if ($conditionalRules && ! empty($conditionalRules['rules_sets'])) {
            $schema = $this->schemaGenerator->generateConditionalSchema($conditionalRules, $parameters);
        } else {
            $schema = $this->schemaGenerator->generateFromParameters($parameters);
        }

        // Check for file uploads
        if (isset($schema['content'])) {
            return [
                'required' => true,
                'content' => $schema['content'],
            ];
        }

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
            // Add examples to default error responses
            foreach ($defaultErrorResponses as $statusCode => &$errorResponse) {
                $errorExample = $this->exampleGenerator->generateErrorExample((int) $statusCode);
                if (isset($errorResponse['content']['application/json'])) {
                    $errorResponse['content']['application/json']['example'] = $errorExample;
                }
            }

            return $defaultErrorResponses;
        }

        // バリデーションがある場合は、422エラーも含める
        $responses = $defaultErrorResponses;
        if (isset($allErrorResponses['422'])) {
            $responses['422'] = $allErrorResponses['422'];
            // Add validation error example
            $validationExample = $this->exampleGenerator->generateErrorExample(422, $validationData['rules'] ?? []);
            if (isset($responses['422']['content']['application/json'])) {
                $responses['422']['content']['application/json']['example'] = $validationExample;
            }
        }

        // Add examples to other error responses
        foreach ($responses as $statusCode => &$errorResponse) {
            if ($statusCode !== '422' && isset($errorResponse['content']['application/json'])) {
                $errorExample = $this->exampleGenerator->generateErrorExample((int) $statusCode);
                $errorResponse['content']['application/json']['example'] = $errorExample;
            }
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

        // ResponseAnalyzerによる解析結果がある場合
        if (! empty($controllerInfo['response']) && config('spectrum.response_detection.enabled', true)) {
            $responseSchema = $this->responseSchemaGenerator->generate($controllerInfo['response'], (int) $statusCode);
            if (! empty($responseSchema[$statusCode])) {
                return [
                    'code' => $statusCode,
                    'response' => $responseSchema[$statusCode],
                ];
            }
        }

        // Resourceクラスがある場合
        if (! empty($controllerInfo['resource'])) {
            $resourceStructure = $this->resourceAnalyzer->analyze($controllerInfo['resource']);

            if (! empty($resourceStructure)) {
                $schema = $this->schemaGenerator->generateFromResource($resourceStructure);

                // Generate example from resource
                $example = $this->exampleGenerator->generateFromResource(
                    $resourceStructure,
                    $controllerInfo['resource']
                );

                // Paginationが検出された場合
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
        // Paginationのみ検出された場合（Resourceなし）
        elseif (! empty($controllerInfo['pagination'])) {
            // モデルから基本的なスキーマを生成
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
        if (! empty($route['name'])) {
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
        // 設定ファイルからカスタムマッピングを取得
        $customMappings = config('spectrum.tags', []);

        // 完全一致のマッピングをチェック
        if (isset($customMappings[$route['uri']])) {
            return (array) $customMappings[$route['uri']];
        }

        // ワイルドカードマッピングをチェック
        foreach ($customMappings as $pattern => $tag) {
            if (Str::is($pattern, $route['uri'])) {
                return (array) $tag;
            }
        }

        // URIからセグメントを取得
        $segments = explode('/', trim($route['uri'], '/'));
        $tags = [];

        // 一般的なプレフィックスを除外
        $ignorePrefixes = ['api', 'v1', 'v2', 'v3'];
        $segments = array_values(array_filter($segments, function ($segment) use ($ignorePrefixes) {
            return ! in_array($segment, $ignorePrefixes);
        }));

        // セグメントからタグを生成
        foreach ($segments as $segment) {
            // パラメータ（{param}形式）を除外
            if (preg_match('/^\{[^}]+\}$/', $segment)) {
                continue;
            }

            // パラメータを含むセグメントから名前部分を抽出
            $cleanSegment = preg_replace('/\{[^}]+\}/', '', $segment);
            if (! empty($cleanSegment)) {
                $tags[] = Str::studly(Str::singular($cleanSegment));
            }
        }

        // タグが空の場合、コントローラー名をフォールバックとして使用
        if (empty($tags) && isset($route['controller'])) {
            $controllerName = class_basename($route['controller']);
            $controllerName = str_replace('Controller', '', $controllerName);
            if (! empty($controllerName)) {
                $tags[] = Str::studly(Str::singular($controllerName));
            }
        }

        // 重複を除去して返す
        return array_values(array_unique($tags));
    }

    /**
     * パラメータを生成
     */
    protected function generateParameters(array $route, array $controllerInfo): array
    {
        $parameters = $route['parameters'];

        // Enum型パラメータを追加
        if (! empty($controllerInfo['enumParameters'])) {
            foreach ($controllerInfo['enumParameters'] as $enumParam) {
                // ルートパラメータに含まれているか確認
                $isRouteParam = false;
                foreach ($parameters as &$routeParam) {
                    if ($routeParam['name'] === $enumParam['name']) {
                        $isRouteParam = true;
                        // 既存のルートパラメータにEnum情報を追加
                        $routeParam['schema'] = [
                            'type' => $enumParam['type'],
                            'enum' => $enumParam['enum'],
                        ];
                        if ($enumParam['description']) {
                            $routeParam['description'] = $enumParam['description'];
                        }
                        break;
                    }
                }

                // ルートパラメータでない場合は、クエリパラメータとして追加
                if (! $isRouteParam) {
                    $parameters[] = [
                        'name' => $enumParam['name'],
                        'in' => 'query',
                        'required' => $enumParam['required'],
                        'schema' => [
                            'type' => $enumParam['type'],
                            'enum' => $enumParam['enum'],
                        ],
                        'description' => $enumParam['description'],
                    ];
                }
            }
        }

        // Query Parametersを追加
        if (! empty($controllerInfo['queryParameters'])) {
            foreach ($controllerInfo['queryParameters'] as $queryParam) {
                $parameter = [
                    'name' => $queryParam['name'],
                    'in' => 'query',
                    'required' => $queryParam['required'] ?? false,
                    'schema' => [
                        'type' => $queryParam['type'],
                    ],
                ];

                // デフォルト値を追加
                if (isset($queryParam['default'])) {
                    $parameter['schema']['default'] = $queryParam['default'];
                }

                // Enum値を追加
                if (isset($queryParam['enum'])) {
                    $parameter['schema']['enum'] = $queryParam['enum'];
                }

                // 説明を追加
                if (isset($queryParam['description'])) {
                    $parameter['description'] = $queryParam['description'];
                }

                // バリデーション制約を追加
                if (isset($queryParam['validation_rules'])) {
                    $typeInference = app(QueryParameterTypeInference::class);
                    $constraints = $typeInference->getConstraintsFromRules($queryParam['validation_rules']);
                    foreach ($constraints as $key => $value) {
                        $parameter['schema'][$key] = $value;
                    }
                }

                $parameters[] = $parameter;
            }
        }

        return $parameters;
    }

    /**
     * バリデーションルールから制約を抽出
     */
    protected function extractConstraintsFromRules(array $rules): array
    {
        $constraints = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $parts = explode(':', $rule);
                $ruleName = $parts[0];
                $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

                switch ($ruleName) {
                    case 'min':
                        if (isset($parameters[0])) {
                            $constraints['minimum'] = (int) $parameters[0];
                        }
                        break;
                    case 'max':
                        if (isset($parameters[0])) {
                            $constraints['maximum'] = (int) $parameters[0];
                        }
                        break;
                    case 'between':
                        if (isset($parameters[0]) && isset($parameters[1])) {
                            $constraints['minimum'] = (int) $parameters[0];
                            $constraints['maximum'] = (int) $parameters[1];
                        }
                        break;
                }
            }
        }

        return $constraints;
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
     * モデルから基本的なスキーマを生成
     */
    protected function generateBasicModelSchema(string $modelClass): array
    {
        // シンプルな実装 - モデルクラス名から基本的なスキーマを生成
        $modelName = class_basename($modelClass);

        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                // 他のプロパティは実際のモデルから推測することもできるが、
                // 今回は基本的な実装にとどめる
            ],
            'description' => $modelName.' object',
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

    /**
     * Check if parameters contain file uploads
     */
    private function hasFileUploadParameters(array $parameters): bool
    {
        foreach ($parameters as $param) {
            if (isset($param['type']) && $param['type'] === 'file') {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate comprehensive description for file upload endpoint
     */
    private function generateFileUploadDescription(array $parameters): string
    {
        $fileParams = array_filter($parameters, fn ($p) => isset($p['type']) && $p['type'] === 'file');

        if (empty($fileParams)) {
            return '';
        }

        $parts = ['This endpoint accepts file uploads.'];

        foreach ($fileParams as $param) {
            if (isset($param['file_info']['multiple']) && $param['file_info']['multiple']) {
                $parts[] = "- {$param['name']}: Multiple files allowed";
            }
        }

        return implode("\n", $parts);
    }
}
