<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use Illuminate\Support\Str;

/**
 * @phpstan-type AuthScheme array{
 *     type: string,
 *     scheme?: string,
 *     bearerFormat?: string,
 *     flows?: array<string, array<string, mixed>>,
 *     in?: string,
 *     headerName?: string,
 *     description: string,
 *     name: string
 * }
 * @phpstan-type MiddlewareList list<string>
 */
class AuthenticationDetector
{
    /**
     * 認証ミドルウェアとOpenAPIセキュリティスキームのマッピング
     *
     * @var array<string, AuthScheme>
     */
    protected static array $authSchemeMap = [
        // Laravel Sanctum
        'auth:sanctum' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Laravel Sanctum token authentication',
            'name' => 'sanctumAuth',
        ],

        // Laravel Passport
        'passport' => [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => [
                    'authorizationUrl' => '/oauth/authorize',
                    'tokenUrl' => '/oauth/token',
                    'scopes' => [],
                ],
                'password' => [
                    'tokenUrl' => '/oauth/token',
                    'scopes' => [],
                ],
            ],
            'description' => 'Laravel Passport OAuth2 authentication',
            'name' => 'passportAuth',
        ],

        // API Token
        'auth:api' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'API Token',
            'description' => 'API token authentication',
            'name' => 'apiAuth',
        ],

        // Basic Auth
        'auth.basic' => [
            'type' => 'http',
            'scheme' => 'basic',
            'description' => 'Basic HTTP authentication',
            'name' => 'basicAuth',
        ],

        // 汎用的な auth ミドルウェア
        'auth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Bearer token authentication',
            'name' => 'bearerAuth',
        ],
    ];

    /**
     * API Key認証のパターン（ヘッダー名で判定）
     *
     * @var array<string, AuthScheme>
     */
    protected static array $apiKeyPatterns = [
        'api-key' => [
            'type' => 'apiKey',
            'in' => 'header',
            'headerName' => 'X-API-Key',
            'description' => 'API Key authentication',
            'name' => 'apiKeyAuth',
        ],
        'api_key' => [
            'type' => 'apiKey',
            'in' => 'header',
            'headerName' => 'X-API-Key',
            'description' => 'API Key authentication',
            'name' => 'apiKeyAuth',
        ],
        'authorization-token' => [
            'type' => 'apiKey',
            'in' => 'header',
            'headerName' => 'Authorization-Token',
            'description' => 'Custom authorization token',
            'name' => 'customTokenAuth',
        ],
    ];

    /**
     * ミドルウェアから認証スキームを検出
     *
     * @param  MiddlewareList  $middleware
     * @return AuthScheme|null
     */
    public static function detectFromMiddleware(array $middleware): ?array
    {
        foreach ($middleware as $mw) {
            // 完全一致でチェック
            if (isset(self::$authSchemeMap[$mw])) {
                return self::$authSchemeMap[$mw];
            }

            // パターンマッチング（auth:guard形式）
            if (Str::startsWith($mw, 'auth:')) {
                $guard = Str::after($mw, 'auth:');

                return self::detectFromGuard($guard);
            }

            // API Keyパターンのチェック
            foreach (self::$apiKeyPatterns as $pattern => $scheme) {
                if (Str::contains($mw, $pattern)) {
                    return $scheme;
                }
            }
        }

        return null;
    }

    /**
     * ガード名から認証スキームを推測
     *
     * @return AuthScheme
     */
    protected static function detectFromGuard(string $guard): array
    {
        // 既知のガード名をチェック
        $knownGuards = [
            'sanctum' => self::$authSchemeMap['auth:sanctum'],
            'api' => self::$authSchemeMap['auth:api'],
            'web' => self::$authSchemeMap['auth'],
        ];

        if (isset($knownGuards[$guard])) {
            return $knownGuards[$guard];
        }

        // デフォルトはBearer Token
        return [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => "Authentication using {$guard} guard",
            'name' => "{$guard}Auth",
        ];
    }

    /**
     * カスタム認証スキームを追加
     *
     * @param  AuthScheme  $scheme
     */
    public static function addCustomScheme(string $middleware, array $scheme): void
    {
        self::$authSchemeMap[$middleware] = $scheme;
    }

    /**
     * 複数のミドルウェアから全ての認証スキームを検出
     *
     * @param  MiddlewareList  $middleware
     * @return list<AuthScheme>
     */
    public static function detectMultipleSchemes(array $middleware): array
    {
        $schemes = [];
        $processedTypes = [];

        foreach ($middleware as $mw) {
            $scheme = self::detectFromMiddleware([$mw]);

            if ($scheme && ! in_array($scheme['name'], $processedTypes)) {
                $schemes[] = $scheme;
                $processedTypes[] = $scheme['name'];
            }
        }

        return $schemes;
    }
}
