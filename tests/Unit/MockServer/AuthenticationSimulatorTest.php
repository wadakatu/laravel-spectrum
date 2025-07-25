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

    public function test_authenticates_with_bearer_token(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('header')
            ->with('authorization')
            ->willReturn('Bearer test-token-123');

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
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('header')
            ->with('authorization')
            ->willReturn('Bearer invalid-token');

        $security = [
            ['bearerAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid bearer token', $result['message']);
    }

    public function test_authenticates_with_api_key(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('header')
            ->with('x-api-key')
            ->willReturn('test-api-key-123');

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
        $request = $this->createMock(Request::class);
        $request->expects($this->atLeastOnce())
            ->method('header')
            ->willReturn(null);

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
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('header')
            ->with('authorization')
            ->willReturn('Basic '.$credentials);

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
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('header')
            ->with('authorization')
            ->willReturn('Basic invalid-base64');

        $security = [
            ['basicAuth' => []],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid basic auth credentials', $result['message']);
    }

    public function test_authenticates_with_multiple_security_schemes(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())
            ->method('header')
            ->willReturnMap([
                ['authorization', null, null],
                ['x-api-key', null, 'test-api-key-123'],
                ['api-key', null, null],
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
        $request = $this->createMock(Request::class);
        $request->expects($this->any())
            ->method('header')
            ->willReturn(null);

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
        $request = $this->createMock(Request::class);

        $result = $this->simulator->authenticate($request, []);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('none', $result['method']);
    }

    public function test_authenticates_with_o_auth2(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('header')
            ->with('authorization')
            ->willReturn('Bearer oauth2-token-123');

        $security = [
            ['oauth2' => ['read', 'write']],
        ];

        $result = $this->simulator->authenticate($request, $security);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('oauth2', $result['method']);
        $this->assertEquals('oauth2-token-123', $result['credentials']);
        $this->assertEquals(['read', 'write'], $result['scopes']);
    }
}
