<?php

namespace LaravelSpectrum\MockServer;

use LaravelSpectrum\Generators\DynamicExampleGenerator;

class ResponseGenerator
{
    private DynamicExampleGenerator $exampleGenerator;

    public function __construct(DynamicExampleGenerator $exampleGenerator)
    {
        $this->exampleGenerator = $exampleGenerator;
    }

    /**
     * Generate response for a given operation.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<string, string>  $pathParams
     * @return array<string, mixed>
     */
    public function generate(
        array $operation,
        int $statusCode,
        string $scenario = 'success',
        array $pathParams = []
    ): array {
        // レスポンス定義を取得
        $responseSpec = $operation['responses'][$statusCode] ?? null;

        if (! $responseSpec) {
            // 定義がない場合はデフォルトレスポンス
            return $this->generateDefaultResponse($statusCode);
        }

        // コンテンツタイプごとの処理
        if (isset($responseSpec['content']['application/json'])) {
            $body = $this->generateJsonResponse(
                $responseSpec['content']['application/json'],
                $scenario,
                $pathParams
            );
        } else {
            $body = null;
        }

        // ヘッダーの生成
        $headers = $this->generateHeaders($responseSpec, $operation);

        return [
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Generate JSON response from content specification.
     *
     * @param  array<string, mixed>  $contentSpec
     * @param  array<string, string>  $pathParams
     * @return array<string, mixed>
     */
    private function generateJsonResponse(array $contentSpec, string $scenario, array $pathParams): array
    {
        // 既存の例がある場合
        if (isset($contentSpec['example'])) {
            return $this->processExample($contentSpec['example'], $pathParams);
        }

        if (isset($contentSpec['examples']) && isset($contentSpec['examples'][$scenario])) {
            return $this->processExample($contentSpec['examples'][$scenario]['value'], $pathParams);
        }

        // スキーマから生成
        if (isset($contentSpec['schema'])) {
            return $this->generateFromSchema($contentSpec['schema'], $pathParams);
        }

        return [];
    }

    /**
     * Generate response from schema.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string>  $pathParams
     * @return array<string, mixed>
     */
    private function generateFromSchema(array $schema, array $pathParams): array
    {
        // ページネーションの検出
        if ($this->isPaginatedResponse($schema)) {
            return $this->generatePaginatedResponse($schema, $pathParams);
        }

        // 通常のスキーマ処理
        return $this->exampleGenerator->generateFromSchema($schema, [
            'path_params' => $pathParams,
            'use_realistic_data' => true,
        ]);
    }

    /**
     * Check if the schema represents a paginated response.
     *
     * @param  array<string, mixed>  $schema
     */
    private function isPaginatedResponse(array $schema): bool
    {
        if ($schema['type'] !== 'object') {
            return false;
        }

        $properties = $schema['properties'] ?? [];
        $paginationKeys = ['data', 'links', 'meta', 'current_page', 'per_page', 'total'];

        $matchCount = count(array_intersect(array_keys($properties), $paginationKeys));

        return $matchCount >= 3;
    }

    /**
     * Generate paginated response.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string>  $pathParams
     * @return array<string, mixed>
     */
    private function generatePaginatedResponse(array $schema, array $pathParams): array
    {
        $properties = $schema['properties'] ?? [];
        $itemSchema = $properties['data']['items'] ?? ['type' => 'object'];

        // アイテムを生成
        $items = [];
        $perPage = 10;
        for ($i = 0; $i < $perPage; $i++) {
            $items[] = $this->exampleGenerator->generateFromSchema($itemSchema);
        }

        // ページネーションメタデータ
        $response = [
            'data' => $items,
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 5,
                'per_page' => $perPage,
                'to' => $perPage,
                'total' => 50,
            ],
            'links' => [
                'first' => 'http://localhost:8081'.($pathParams['_path'] ?? '').'?page=1',
                'last' => 'http://localhost:8081'.($pathParams['_path'] ?? '').'?page=5',
                'prev' => null,
                'next' => 'http://localhost:8081'.($pathParams['_path'] ?? '').'?page=2',
            ],
        ];

        return $response;
    }

    /**
     * Process example with path parameter substitution.
     *
     * @param  array<string, mixed>|object  $example
     * @param  array<string, string>  $pathParams
     * @return array<string, mixed>
     */
    private function processExample(array|object $example, array $pathParams): array
    {
        // パスパラメータの置換
        $json = json_encode($example);
        foreach ($pathParams as $key => $value) {
            $json = str_replace('{'.$key.'}', $value, $json);
        }

        return json_decode($json, true);
    }

    /**
     * Generate response headers.
     *
     * @param  array<string, mixed>  $responseSpec
     * @param  array<string, mixed>  $operation
     * @return array<string, string>
     */
    private function generateHeaders(array $responseSpec, array $operation): array
    {
        $headers = [];

        // レスポンス仕様からヘッダーを取得
        if (isset($responseSpec['headers'])) {
            foreach ($responseSpec['headers'] as $name => $spec) {
                $headers[$name] = $spec['example'] ?? $this->generateHeaderValue($name);
            }
        }

        // レート制限ヘッダー
        if ($this->hasRateLimiting($operation)) {
            $headers['X-RateLimit-Limit'] = '60';
            $headers['X-RateLimit-Remaining'] = (string) rand(0, 60);
            $headers['X-RateLimit-Reset'] = (string) (time() + 3600);
        }

        return $headers;
    }

    private function generateHeaderValue(string $name): string
    {
        $commonHeaders = [
            'X-Request-Id' => fn () => \Illuminate\Support\Str::uuid()->toString(),
            'X-Response-Time' => fn () => rand(10, 200).'ms',
            'X-Powered-By' => 'Laravel Spectrum Mock Server',
        ];

        return isset($commonHeaders[$name]) ? $commonHeaders[$name]() : 'mock-value';
    }

    /**
     * Check if operation has rate limiting.
     *
     * @param  array<string, mixed>  $operation
     */
    private function hasRateLimiting(array $operation): bool
    {
        // x-rate-limit拡張があるかチェック
        return isset($operation['x-rate-limit']) ||
               in_array('throttle', $operation['x-middleware'] ?? []);
    }

    /**
     * Generate default response for status code.
     *
     * @return array<string, mixed>
     */
    private function generateDefaultResponse(int $statusCode): array
    {
        $defaults = [
            200 => ['message' => 'Success'],
            201 => ['message' => 'Created', 'id' => rand(1, 1000)],
            204 => null,
            400 => ['error' => 'Bad Request', 'message' => 'The request was invalid.'],
            401 => ['error' => 'Unauthorized', 'message' => 'Authentication required.'],
            403 => ['error' => 'Forbidden', 'message' => 'You do not have permission to access this resource.'],
            404 => ['error' => 'Not Found', 'message' => 'The requested resource was not found.'],
            422 => ['message' => 'The given data was invalid.', 'errors' => []],
            500 => ['error' => 'Internal Server Error', 'message' => 'An unexpected error occurred.'],
        ];

        return [
            'status' => $statusCode,
            'body' => $defaults[$statusCode] ?? ['message' => 'Mock response'],
        ];
    }
}
