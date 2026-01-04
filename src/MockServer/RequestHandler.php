<?php

namespace LaravelSpectrum\MockServer;

use Workerman\Protocols\Http\Request;

/**
 * @phpstan-import-type MockResponse from ResponseGenerator
 */
class RequestHandler
{
    private ValidationSimulator $validator;

    private AuthenticationSimulator $authenticator;

    private ResponseGenerator $responseGenerator;

    public function __construct(
        ValidationSimulator $validator,
        AuthenticationSimulator $authenticator,
        ResponseGenerator $responseGenerator
    ) {
        $this->validator = $validator;
        $this->authenticator = $authenticator;
        $this->responseGenerator = $responseGenerator;
    }

    /**
     * Handle an incoming request.
     *
     * @param  array<string, mixed>  $route
     * @return MockResponse
     */
    public function handle(Request $request, array $route): array
    {
        // 認証チェック
        if ($authError = $this->checkAuthentication($request, $route)) {
            return $authError;
        }

        // バリデーション
        if ($validationError = $this->validateRequest($request, $route)) {
            return $validationError;
        }

        // 成功レスポンスの生成
        return $this->generateSuccessResponse($request, $route);
    }

    /**
     * Check authentication for the request.
     *
     * @param  array<string, mixed>  $route
     * @return MockResponse|null
     */
    private function checkAuthentication(Request $request, array $route): ?array
    {
        // No security defined means no authentication required
        $security = $route['operation']['security'] ?? [];

        // Always call authenticate even if security is empty
        $authResult = $this->authenticator->authenticate($request, $security);

        if (! $authResult['authenticated']) {
            return [
                'status' => 401,
                'body' => [
                    'error' => 'Unauthorized',
                    'message' => $authResult['message'] ?? 'Authentication required',
                ],
            ];
        }

        return null;
    }

    /**
     * Validate the request.
     *
     * @param  array<string, mixed>  $route
     * @return MockResponse|null
     */
    private function validateRequest(Request $request, array $route): ?array
    {
        $requestBody = null;
        $method = strtolower($request->method());

        // リクエストボディの取得
        if (in_array($method, ['post', 'put', 'patch'])) {
            $contentType = $request->header('content-type', 'application/json');

            if (str_contains($contentType, 'application/json')) {
                $requestBody = json_decode($request->rawBody(), true);
            } elseif (str_contains($contentType, 'multipart/form-data')) {
                $requestBody = $request->post();
            }
        }

        // クエリパラメータの取得
        $queryParams = $request->get() ?? [];

        // パスパラメータの取得
        $pathParams = $route['params'] ?? [];

        // バリデーション実行
        $validationResult = $this->validator->validate(
            $route['operation'],
            $requestBody,
            $queryParams,
            $pathParams
        );

        if (! $validationResult['valid']) {
            return [
                'status' => 422,
                'body' => [
                    'message' => 'The given data was invalid.',
                    'errors' => $validationResult['errors'],
                ],
            ];
        }

        return null;
    }

    /**
     * Generate a success response.
     *
     * @param  array<string, mixed>  $route
     * @return MockResponse
     */
    private function generateSuccessResponse(Request $request, array $route): array
    {
        // シナリオベースのレスポンス選択
        $queryParams = $request->get() ?? [];
        $scenario = $queryParams['_scenario'] ?? 'success';

        // レスポンスコードの決定
        $statusCode = $this->determineStatusCode($route, $scenario);

        // レスポンス生成
        return $this->responseGenerator->generate(
            $route['operation'],
            $statusCode,
            $scenario,
            $route['params'] ?? []
        );
    }

    /**
     * Determine the status code for the response.
     *
     * @param  array<string, mixed>  $route
     */
    private function determineStatusCode(array $route, string $scenario): int
    {
        $method = strtolower($route['method']);

        // シナリオ別のステータスコード
        if ($scenario === 'not_found') {
            return 404;
        }

        if ($scenario === 'forbidden') {
            return 403;
        }

        if ($scenario === 'error') {
            return 500;
        }

        // デフォルトのステータスコード
        $defaults = [
            'post' => 201,
            'put' => 200,
            'patch' => 200,
            'delete' => 204,
            'get' => 200,
        ];

        return $defaults[$method] ?? 200;
    }
}
