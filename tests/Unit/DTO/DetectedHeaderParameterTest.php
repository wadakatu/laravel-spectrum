<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\DetectedHeaderParameter;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DetectedHeaderParameterTest extends TestCase
{
    #[Test]
    public function it_can_create_with_header_method(): void
    {
        $detected = DetectedHeaderParameter::create(
            name: 'X-Request-Id',
            method: 'header',
        );

        $this->assertEquals('X-Request-Id', $detected->name);
        $this->assertEquals('header', $detected->method);
        $this->assertNull($detected->default);
        $this->assertEmpty($detected->context);
    }

    #[Test]
    public function it_can_create_with_default_value(): void
    {
        $detected = DetectedHeaderParameter::create(
            name: 'Accept-Language',
            method: 'header',
            default: 'en',
        );

        $this->assertEquals('Accept-Language', $detected->name);
        $this->assertEquals('en', $detected->default);
        $this->assertTrue($detected->hasDefault());
    }

    #[Test]
    public function it_detects_bearer_token_method(): void
    {
        $detected = DetectedHeaderParameter::create(
            name: 'Authorization',
            method: 'bearerToken',
        );

        $this->assertTrue($detected->isBearerToken());
        $this->assertEquals('bearerToken', $detected->method);
    }

    #[Test]
    public function it_detects_has_header_method(): void
    {
        $detected = DetectedHeaderParameter::create(
            name: 'X-Custom-Auth',
            method: 'hasHeader',
            context: ['hasHeader_check' => true],
        );

        $this->assertTrue($detected->isHasHeaderCheck());
        $this->assertTrue($detected->hasContextFlag('hasHeader_check'));
    }

    #[Test]
    public function it_can_convert_to_array(): void
    {
        $detected = DetectedHeaderParameter::create(
            name: 'X-Tenant-Id',
            method: 'header',
            default: null,
            context: ['line' => 42],
        );

        $array = $detected->toArray();

        $this->assertEquals('X-Tenant-Id', $array['name']);
        $this->assertEquals('header', $array['method']);
        $this->assertNull($array['default']);
        $this->assertEquals(['line' => 42], $array['context']);
    }

    #[Test]
    public function it_can_create_from_array(): void
    {
        $data = [
            'name' => 'X-Api-Key',
            'method' => 'header',
            'default' => 'default-key',
            'context' => [],
        ];

        $detected = DetectedHeaderParameter::fromArray($data);

        $this->assertEquals('X-Api-Key', $detected->name);
        $this->assertEquals('header', $detected->method);
        $this->assertEquals('default-key', $detected->default);
    }
}
