<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\AuthenticationType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AuthenticationTypeTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $cases = AuthenticationType::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(AuthenticationType::HTTP, $cases);
        $this->assertContains(AuthenticationType::API_KEY, $cases);
        $this->assertContains(AuthenticationType::OAUTH2, $cases);
        $this->assertContains(AuthenticationType::OPENID_CONNECT, $cases);
    }

    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertEquals('http', AuthenticationType::HTTP->value);
        $this->assertEquals('apiKey', AuthenticationType::API_KEY->value);
        $this->assertEquals('oauth2', AuthenticationType::OAUTH2->value);
        $this->assertEquals('openIdConnect', AuthenticationType::OPENID_CONNECT->value);
    }

    #[Test]
    public function it_creates_from_string(): void
    {
        $this->assertEquals(AuthenticationType::HTTP, AuthenticationType::from('http'));
        $this->assertEquals(AuthenticationType::API_KEY, AuthenticationType::from('apiKey'));
        $this->assertEquals(AuthenticationType::OAUTH2, AuthenticationType::from('oauth2'));
        $this->assertEquals(AuthenticationType::OPENID_CONNECT, AuthenticationType::from('openIdConnect'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(AuthenticationType::tryFrom('invalid'));
        $this->assertNull(AuthenticationType::tryFrom(''));
        $this->assertNull(AuthenticationType::tryFrom('bearer')); // Not a type
    }

    #[Test]
    public function it_checks_if_http(): void
    {
        $this->assertTrue(AuthenticationType::HTTP->isHttp());
        $this->assertFalse(AuthenticationType::API_KEY->isHttp());
        $this->assertFalse(AuthenticationType::OAUTH2->isHttp());
        $this->assertFalse(AuthenticationType::OPENID_CONNECT->isHttp());
    }

    #[Test]
    public function it_checks_if_api_key(): void
    {
        $this->assertTrue(AuthenticationType::API_KEY->isApiKey());
        $this->assertFalse(AuthenticationType::HTTP->isApiKey());
        $this->assertFalse(AuthenticationType::OAUTH2->isApiKey());
        $this->assertFalse(AuthenticationType::OPENID_CONNECT->isApiKey());
    }

    #[Test]
    public function it_checks_if_oauth2(): void
    {
        $this->assertTrue(AuthenticationType::OAUTH2->isOAuth2());
        $this->assertFalse(AuthenticationType::HTTP->isOAuth2());
        $this->assertFalse(AuthenticationType::API_KEY->isOAuth2());
        $this->assertFalse(AuthenticationType::OPENID_CONNECT->isOAuth2());
    }

    #[Test]
    public function it_checks_if_openid_connect(): void
    {
        $this->assertTrue(AuthenticationType::OPENID_CONNECT->isOpenIdConnect());
        $this->assertFalse(AuthenticationType::HTTP->isOpenIdConnect());
        $this->assertFalse(AuthenticationType::API_KEY->isOpenIdConnect());
        $this->assertFalse(AuthenticationType::OAUTH2->isOpenIdConnect());
    }
}
