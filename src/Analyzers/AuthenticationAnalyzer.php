<?php

namespace LaravelPrism\Analyzers;

use LaravelPrism\Support\AuthenticationDetector;

class AuthenticationAnalyzer
{
    /**
     * ルートコレクションから認証情報を分析
     */
    public function analyze(array $routes): array
    {
        $authSchemes = [];
        $routeAuthentications = [];

        foreach ($routes as $index => $route) {
            $authentication = $this->analyzeRoute($route);

            if ($authentication) {
                // ルートごとの認証情報を保存
                $routeAuthentications[$index] = $authentication;

                // 全体のスキーム一覧に追加（重複を避ける）
                $schemeName = $authentication['scheme']['name'];
                if (! isset($authSchemes[$schemeName])) {
                    $authSchemes[$schemeName] = $authentication['scheme'];
                }
            }
        }

        return [
            'schemes' => $authSchemes,
            'routes' => $routeAuthentications,
        ];
    }

    /**
     * 単一ルートの認証情報を分析
     */
    public function analyzeRoute(array $route): ?array
    {
        $middleware = $route['middleware'] ?? [];

        if (empty($middleware)) {
            return null;
        }

        // 認証スキームを検出
        $scheme = AuthenticationDetector::detectFromMiddleware($middleware);

        if (! $scheme) {
            return null;
        }

        return [
            'scheme' => $scheme,
            'middleware' => $middleware,
            'required' => true, // 認証ミドルウェアがある = 必須
        ];
    }

    /**
     * 設定から追加の認証スキームを読み込む
     */
    public function loadCustomSchemes(): void
    {
        $customSchemes = config('prism.authentication.custom_schemes', []);

        foreach ($customSchemes as $middleware => $scheme) {
            AuthenticationDetector::addCustomScheme($middleware, $scheme);
        }
    }

    /**
     * グローバル認証設定を取得
     */
    public function getGlobalAuthentication(): ?array
    {
        $globalAuth = config('prism.authentication.global');

        if (! $globalAuth || ! $globalAuth['enabled']) {
            return null;
        }

        return [
            'scheme' => $globalAuth['scheme'],
            'required' => $globalAuth['required'] ?? false,
        ];
    }

    /**
     * ルートパターンに基づく認証設定を取得
     */
    public function getPatternBasedAuthentication(string $uri): ?array
    {
        $patterns = config('prism.authentication.patterns', []);

        foreach ($patterns as $pattern => $auth) {
            if (\Illuminate\Support\Str::is($pattern, $uri)) {
                return $auth;
            }
        }

        return null;
    }
}
