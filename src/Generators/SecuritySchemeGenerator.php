<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\DTO\AuthenticationScheme;

class SecuritySchemeGenerator
{
    /**
     * OpenAPIのsecuritySchemesセクションを生成
     *
     * @param  array<string, array<string, mixed>>  $authSchemes
     * @return array<string, array<string, mixed>>
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
     *
     * @param  array<string, mixed>|AuthenticationScheme  $scheme
     * @return array<string, mixed>
     */
    protected function generateScheme(array|AuthenticationScheme $scheme): array
    {
        // Convert array to DTO if needed
        $dto = $scheme instanceof AuthenticationScheme
            ? $scheme
            : AuthenticationScheme::fromArray($scheme);

        return $dto->toOpenApiSecurityScheme();
    }

    /**
     * エンドポイントのsecurityセクションを生成
     *
     * @param  array<string, mixed>  $authentication
     * @return array<int, array<string, array<int, string>>>
     */
    public function generateEndpointSecurity(array $authentication): array
    {
        if (! $authentication || ! isset($authentication['required']) || ! $authentication['required']) {
            return [];
        }

        // Convert scheme to DTO if it's an array
        $schemeData = $authentication['scheme'] ?? [];
        $dto = $schemeData instanceof AuthenticationScheme
            ? $schemeData
            : AuthenticationScheme::fromArray($schemeData);

        $schemeName = $dto->name;

        // OAuth2の場合はスコープを含める
        if ($dto->isOAuth2()) {
            $scopes = $authentication['scopes'] ?? [];

            return [[$schemeName => $scopes]];
        }

        // その他の認証方式
        return [[$schemeName => []]];
    }

    /**
     * 複数の認証方式をサポートする場合
     *
     * @param  array<int, array<string, mixed>>  $authentications
     * @return array<int, array<string, array<int, string>>>
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
     *
     * @param  array<string, mixed>|null  $global
     * @param  array<string, mixed>|null  $local
     * @return array<string, mixed>|null
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
