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

        $this->assertCount(3, $cases);
        $this->assertContains(AuthenticationType::HTTP, $cases);
        $this->assertContains(AuthenticationType::API_KEY, $cases);
        $this->assertContains(AuthenticationType::OAUTH2, $cases);
    }

    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertEquals('http', AuthenticationType::HTTP->value);
        $this->assertEquals('apiKey', AuthenticationType::API_KEY->value);
        $this->assertEquals('oauth2', AuthenticationType::OAUTH2->value);
    }

    #[Test]
    public function it_creates_from_string(): void
    {
        $this->assertEquals(AuthenticationType::HTTP, AuthenticationType::from('http'));
        $this->assertEquals(AuthenticationType::API_KEY, AuthenticationType::from('apiKey'));
        $this->assertEquals(AuthenticationType::OAUTH2, AuthenticationType::from('oauth2'));
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
    }

    #[Test]
    public function it_checks_if_api_key(): void
    {
        $this->assertTrue(AuthenticationType::API_KEY->isApiKey());
        $this->assertFalse(AuthenticationType::HTTP->isApiKey());
        $this->assertFalse(AuthenticationType::OAUTH2->isApiKey());
    }

    #[Test]
    public function it_checks_if_oauth2(): void
    {
        $this->assertTrue(AuthenticationType::OAUTH2->isOAuth2());
        $this->assertFalse(AuthenticationType::HTTP->isOAuth2());
        $this->assertFalse(AuthenticationType::API_KEY->isOAuth2());
    }
}
