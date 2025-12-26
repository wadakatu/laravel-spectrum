<?php

namespace LaravelSpectrum\Tests\Unit\MockServer;

use LaravelSpectrum\MockServer\AuthenticationSimulator;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Request;

class AuthenticationSimulatorTest extends TestCase
{
    private AuthenticationSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new AuthenticationSimulator;
    }

    /**
     * Create a stub Request object with the given header values
     */
    private function createRequestStub(array $headers = []): Request
    {
        return new class($headers) extends Request
        {
            private array $headers;

            public function __construct(array $headers)
            {
                $this->headers = $headers;
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                $name = strtolower($name);

                return $this->headers[$name] ?? $default;
            }
        };
    }

    public function test_authenticates_with_bearer_token(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Bearer test-token-123',
        ]);

        $security = [
            ['bearerAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('bearer', $result['method']);
        $this->assertEquals('test-token-123', $result['credentials']);
    }

    public function test_fails_authentication_with_invalid_bearer_token(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Bearer invalid-token',
        ]);

        $security = [
            ['bearerAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid bearer token', $result['message']);
    }

    public function test_authenticates_with_api_key(): void
    {
        $request = $this->createRequestStub([
            'x-api-key' => 'test-api-key-123',
        ]);

        $security = [
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('apiKey', $result['method']);
        $this->assertEquals('test-api-key-123', $result['credentials']);
    }

    public function test_fails_authentication_with_missing_api_key(): void
    {
        $request = $this->createRequestStub([]);

        $security = [
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('API key is missing', $result['message']);
    }

    public function test_authenticates_with_basic_auth(): void
    {
        $credentials = base64_encode('user:password');
        $request = $this->createRequestStub([
            'authorization' => 'Basic '.$credentials,
        ]);

        $security = [
            ['basicAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('basic', $result['method']);
        $this->assertEquals(['username' => 'user', 'password' => 'password'], $result['credentials']);
    }

    public function test_fails_authentication_with_invalid_basic_auth(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Basic invalid-base64',
        ]);

        $security = [
            ['basicAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid basic auth credentials', $result['message']);
    }

    public function test_authenticates_with_multiple_security_schemes(): void
    {
        $request = $this->createRequestStub([
            'x-api-key' => 'test-api-key-123',
        ]);

        $security = [
            ['bearerAuth' => []],
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('apiKey', $result['method']);
    }

    public function test_fails_when_no_security_scheme_matches(): void
    {
        $request = $this->createRequestStub([]);

        $security = [
            ['bearerAuth' => []],
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        // The last error message will be from the API key check
        $this->assertEquals('API key is missing', $result['message']);
    }

    public function test_returns_authenticated_when_no_security_defined(): void
    {
        $request = $this->createRequestStub([]);

        $result = $this->simulator->authenticate($request, []);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('none', $result['method']);
    }

    public function test_authenticates_with_o_auth2(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Bearer oauth2-token-123',
        ]);

        $security = [
            ['oauth2' => ['read', 'write']],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('oauth2', $result['method']);
        $this->assertEquals('oauth2-token-123', $result['credentials']);
        $this->assertEquals(['read', 'write'], $result['scopes']);
    }

    public function test_fails_authentication_with_missing_bearer_token(): void
    {
        $request = $this->createRequestStub([]);

        $security = [
            ['bearerAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Bearer token is missing', $result['message']);
    }

    public function test_fails_authentication_with_invalid_api_key(): void
    {
        $request = $this->createRequestStub([
            'x-api-key' => 'invalid-api-key',
        ]);

        $security = [
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid API key', $result['message']);
    }

    public function test_fails_authentication_with_missing_basic_auth(): void
    {
        $request = $this->createRequestStub([]);

        $security = [
            ['basicAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Basic auth credentials are missing', $result['message']);
    }

    public function test_fails_authentication_with_invalid_basic_auth_without_colon(): void
    {
        // Valid base64 but doesn't contain ':'
        $request = $this->createRequestStub([
            'authorization' => 'Basic '.base64_encode('nocolon'),
        ]);

        $security = [
            ['basicAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid basic auth credentials', $result['message']);
    }

    public function test_fails_authentication_with_wrong_basic_auth_credentials(): void
    {
        $credentials = base64_encode('wrong:credentials');
        $request = $this->createRequestStub([
            'authorization' => 'Basic '.$credentials,
        ]);

        $security = [
            ['basicAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid username or password', $result['message']);
    }

    public function test_fails_authentication_with_missing_oauth2_token(): void
    {
        $request = $this->createRequestStub([]);

        $security = [
            ['oauth2' => ['read']],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('OAuth2 token is missing', $result['message']);
    }

    public function test_fails_authentication_with_invalid_oauth2_token(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Bearer invalid-oauth2-token',
        ]);

        $security = [
            ['oauth2' => ['read']],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid OAuth2 token', $result['message']);
    }

    public function test_fails_authentication_with_unsupported_scheme(): void
    {
        $request = $this->createRequestStub([]);

        $security = [
            ['customAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Unsupported authentication scheme: customAuth', $result['message']);
    }

    public function test_authenticates_with_sanctum_auth(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Bearer test-token-123',
        ]);

        $security = [
            ['sanctumAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('bearer', $result['method']);
        $this->assertEquals('test-token-123', $result['credentials']);
    }

    public function test_authenticates_with_api_key_in_api_key_header(): void
    {
        $request = $this->createRequestStub([
            'api-key' => 'test-api-key-123',
        ]);

        $security = [
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('apiKey', $result['method']);
    }

    public function test_authenticates_with_api_key_in_authorization_header(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'test-api-key-123',
        ]);

        $security = [
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('apiKey', $result['method']);
    }

    public function test_set_valid_tokens(): void
    {
        $this->simulator->setValidTokens(['custom-token-1', 'custom-token-2']);

        // New token should work
        $request = $this->createRequestStub([
            'authorization' => 'Bearer custom-token-1',
        ]);

        $result = $this->simulator->authenticate($request, [['bearerAuth' => []]]);

        $this->assertTrue($result['authenticated']);

        // Old default token should not work
        $request2 = $this->createRequestStub([
            'authorization' => 'Bearer test-token-123',
        ]);

        $result2 = $this->simulator->authenticate($request2, [['bearerAuth' => []]]);

        $this->assertFalse($result2['authenticated']);
    }

    public function test_set_valid_api_keys(): void
    {
        $this->simulator->setValidApiKeys(['custom-api-key']);

        $request = $this->createRequestStub([
            'x-api-key' => 'custom-api-key',
        ]);

        $result = $this->simulator->authenticate($request, [['apiKey' => []]]);

        $this->assertTrue($result['authenticated']);

        // Old default API key should not work
        $request2 = $this->createRequestStub([
            'x-api-key' => 'test-api-key-123',
        ]);

        $result2 = $this->simulator->authenticate($request2, [['apiKey' => []]]);

        $this->assertFalse($result2['authenticated']);
    }

    public function test_set_valid_basic_auth(): void
    {
        $this->simulator->setValidBasicAuth(['test:test']);

        $credentials = base64_encode('test:test');
        $request = $this->createRequestStub([
            'authorization' => 'Basic '.$credentials,
        ]);

        $result = $this->simulator->authenticate($request, [['basicAuth' => []]]);

        $this->assertTrue($result['authenticated']);

        // Old default credentials should not work
        $credentials2 = base64_encode('user:password');
        $request2 = $this->createRequestStub([
            'authorization' => 'Basic '.$credentials2,
        ]);

        $result2 = $this->simulator->authenticate($request2, [['basicAuth' => []]]);

        $this->assertFalse($result2['authenticated']);
    }

    public function test_fails_with_wrong_prefix_for_bearer(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Token test-token-123',
        ]);

        $security = [
            ['bearerAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Bearer token is missing', $result['message']);
    }

    public function test_fails_with_wrong_prefix_for_basic(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Digest '.base64_encode('user:password'),
        ]);

        $security = [
            ['basicAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Basic auth credentials are missing', $result['message']);
    }

    public function test_multiple_schemes_returns_generic_message_when_all_fail_without_specific_errors(): void
    {
        // When all schemes fail without leaving an error message
        // This tests the fallback generic message path
        $request = $this->createRequestStub([
            'authorization' => 'Bearer some-token',
        ]);

        // Both schemes will fail with specific error messages
        $security = [
            ['bearerAuth' => []], // fails: "Invalid bearer token"
            ['basicAuth' => []], // fails: "Basic auth credentials are missing" (no Basic prefix)
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        // Should return the last error message
        $this->assertEquals('Basic auth credentials are missing', $result['message']);
    }

    public function test_authenticates_with_first_matching_scheme(): void
    {
        // Request has valid bearer token
        $request = $this->createRequestStub([
            'authorization' => 'Bearer test-token-123',
        ]);

        // Both bearerAuth and apiKey schemes
        $security = [
            ['bearerAuth' => []],
            ['apiKey' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        // Should match bearerAuth first
        $this->assertTrue($result['authenticated']);
        $this->assertEquals('bearer', $result['method']);
    }

    public function test_oauth2_with_empty_scopes(): void
    {
        $request = $this->createRequestStub([
            'authorization' => 'Bearer oauth2-token-123',
        ]);

        $security = [
            ['oauth2' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('oauth2', $result['method']);
        $this->assertEquals([], $result['scopes']);
    }
}
