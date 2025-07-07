<?php

namespace LaravelSpectrum\Generators;

class SecuritySchemeGenerator
{
    /**
     * OpenAPIのsecuritySchemesセクションを生成
     */
    public function generateSecuritySchemes(array $authSchemes): array
    {
        $securitySchemes = [];

        foreach ($authSchemes as $name => $scheme) {
            $securitySchemes[$name] = $this->generateScheme($scheme);
        }

        return $securitySchemes;
    }

    /**
     * 単一のセキュリティスキームを生成
     */
    protected function generateScheme(array $scheme): array
    {
        $openApiScheme = [
            'type' => $scheme['type'],
        ];

        // HTTPタイプの場合
        if ($scheme['type'] === 'http') {
            $openApiScheme['scheme'] = $scheme['scheme'];

            if (isset($scheme['bearerFormat'])) {
                $openApiScheme['bearerFormat'] = $scheme['bearerFormat'];
            }
        }

        // API Keyタイプの場合
        elseif ($scheme['type'] === 'apiKey') {
            $openApiScheme['in'] = $scheme['in'];
            $openApiScheme['name'] = $scheme['headerName'] ?? $scheme['name'];
        }

        // OAuth2タイプの場合
        elseif ($scheme['type'] === 'oauth2') {
            $openApiScheme['flows'] = $scheme['flows'];
        }

        // 説明を追加
        if (isset($scheme['description'])) {
            $openApiScheme['description'] = $scheme['description'];
        }

        return $openApiScheme;
    }

    /**
     * エンドポイントのsecurityセクションを生成
     */
    public function generateEndpointSecurity(array $authentication): array
    {
        if (! $authentication || ! $authentication['required']) {
            return [];
        }

        $schemeName = $authentication['scheme']['name'];

        // OAuth2の場合はスコープを含める
        if ($authentication['scheme']['type'] === 'oauth2') {
            $scopes = $authentication['scopes'] ?? [];

            return [[$schemeName => $scopes]];
        }

        // その他の認証方式
        return [[$schemeName => []]];
    }

    /**
     * 複数の認証方式をサポートする場合
     */
    public function generateMultipleAuthSecurity(array $authentications): array
    {
        $security = [];

        foreach ($authentications as $auth) {
            if ($auth['required']) {
                $security[] = $this->generateEndpointSecurity($auth)[0];
            }
        }

        return $security;
    }

    /**
     * グローバル認証とローカル認証をマージ
     */
    public function mergeAuthentications(?array $global, ?array $local): ?array
    {
        // ローカル設定が優先
        if ($local) {
            return $local;
        }

        // グローバル設定を使用
        if ($global) {
            return $global;
        }

        return null;
    }
}
