<?php

namespace LaravelSpectrum\MockServer;

use Workerman\Protocols\Http\Request;

/**
 * @phpstan-type AuthResult array{
 *     authenticated: bool,
 *     method?: string,
 *     message?: string,
 *     credentials?: string|array{username: string, password: string},
 *     scopes?: array<int, string>
 * }
 * @phpstan-type SecurityScheme array<string, array<int, string>>
 */
class AuthenticationSimulator
{
    /**
     * Valid tokens for testing
     *
     * @var array<int, string>
     */
    private array $validTokens = [
        'test-token-123',
        'Bearer test-jwt-token',
        'oauth2-token-123',
    ];

    /**
     * Valid API keys for testing
     *
     * @var array<int, string>
     */
    private array $validApiKeys = [
        'test-api-key-123',
        'sk_test_12345',
    ];

    /**
     * Valid basic auth credentials
     *
     * @var array<int, string>
     */
    private array $validBasicAuth = [
        'user:password',
        'admin:secret',
    ];

    /**
     * Authenticate a request based on security requirements.
     *
     * @param  array<int, SecurityScheme>  $security
     * @return AuthResult
     */
    public function authenticate(Request $request, array $security): array
    {
        // No security required
        if (empty($security)) {
            return [
                'authenticated' => true,
                'method' => 'none',
            ];
        }

        // Try each security scheme
        $lastError = null;
        foreach ($security as $scheme) {
            $result = $this->tryAuthenticateWithScheme($request, $scheme);
            if ($result['authenticated']) {
                return $result;
            }
            // Keep track of the last error message
            if (! empty($result['message'])) {
                $lastError = $result;
            }
        }

        // Return the last specific error, or a generic message
        return $lastError ?? [
            'authenticated' => false,
            'message' => 'No valid authentication provided',
        ];
    }

    /**
     * Try to authenticate with a specific security scheme.
     *
     * @param  SecurityScheme  $scheme
     * @return AuthResult
     */
    private function tryAuthenticateWithScheme(Request $request, array $scheme): array
    {
        $schemeName = array_key_first($scheme);
        $schemeConfig = $scheme[$schemeName];

        switch ($schemeName) {
            case 'bearerAuth':
                return $this->authenticateBearerToken($request);

            case 'apiKey':
                return $this->authenticateApiKey($request);

            case 'basicAuth':
                return $this->authenticateBasicAuth($request);

            case 'oauth2':
                return $this->authenticateOAuth2($request, $schemeConfig);

            case 'sanctumAuth':
                // Sanctum uses Bearer tokens similar to regular bearer auth
                return $this->authenticateBearerToken($request);

            default:
                return [
                    'authenticated' => false,
                    'message' => "Unsupported authentication scheme: {$schemeName}",
                ];
        }
    }

    /**
     * Authenticate Bearer token.
     *
     * @return AuthResult
     */
    private function authenticateBearerToken(Request $request): array
    {
        $authHeader = $request->header('authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return [
                'authenticated' => false,
                'message' => 'Bearer token is missing',
            ];
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        if (in_array($token, $this->validTokens)) {
            return [
                'authenticated' => true,
                'method' => 'bearer',
                'credentials' => $token,
            ];
        }

        return [
            'authenticated' => false,
            'message' => 'Invalid bearer token',
        ];
    }

    /**
     * Authenticate API key.
     *
     * @return AuthResult
     */
    private function authenticateApiKey(Request $request): array
    {
        // Check common API key headers
        $apiKey = $request->header('x-api-key')
            ?? $request->header('api-key')
            ?? $request->header('authorization');

        if (! $apiKey) {
            return [
                'authenticated' => false,
                'message' => 'API key is missing',
            ];
        }

        if (in_array($apiKey, $this->validApiKeys)) {
            return [
                'authenticated' => true,
                'method' => 'apiKey',
                'credentials' => $apiKey,
            ];
        }

        return [
            'authenticated' => false,
            'message' => 'Invalid API key',
        ];
    }

    /**
     * Authenticate Basic auth.
     *
     * @return AuthResult
     */
    private function authenticateBasicAuth(Request $request): array
    {
        $authHeader = $request->header('authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Basic ')) {
            return [
                'authenticated' => false,
                'message' => 'Basic auth credentials are missing',
            ];
        }

        $encoded = substr($authHeader, 6); // Remove "Basic " prefix
        $decoded = @base64_decode($encoded, true);

        if ($decoded === false || ! str_contains($decoded, ':')) {
            return [
                'authenticated' => false,
                'message' => 'Invalid basic auth credentials',
            ];
        }

        [$username, $password] = explode(':', $decoded, 2);

        if (in_array($decoded, $this->validBasicAuth)) {
            return [
                'authenticated' => true,
                'method' => 'basic',
                'credentials' => [
                    'username' => $username,
                    'password' => $password,
                ],
            ];
        }

        return [
            'authenticated' => false,
            'message' => 'Invalid username or password',
        ];
    }

    /**
     * Authenticate OAuth2.
     *
     * @param  array<int, string>  $scopes
     * @return AuthResult
     */
    private function authenticateOAuth2(Request $request, array $scopes): array
    {
        $authHeader = $request->header('authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return [
                'authenticated' => false,
                'message' => 'OAuth2 token is missing',
            ];
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        if (in_array($token, $this->validTokens)) {
            return [
                'authenticated' => true,
                'method' => 'oauth2',
                'credentials' => $token,
                'scopes' => $scopes,
            ];
        }

        return [
            'authenticated' => false,
            'message' => 'Invalid OAuth2 token',
        ];
    }

    /**
     * Set valid tokens for testing.
     *
     * @param  array<int, string>  $tokens
     */
    public function setValidTokens(array $tokens): void
    {
        $this->validTokens = $tokens;
    }

    /**
     * Set valid API keys for testing.
     *
     * @param  array<int, string>  $keys
     */
    public function setValidApiKeys(array $keys): void
    {
        $this->validApiKeys = $keys;
    }

    /**
     * Set valid basic auth credentials for testing.
     *
     * @param  array<int, string>  $credentials
     */
    public function setValidBasicAuth(array $credentials): void
    {
        $this->validBasicAuth = $credentials;
    }
}
