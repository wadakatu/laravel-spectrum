<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\HeaderParameterInfo;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HeaderParameterInfoTest extends TestCase
{
    #[Test]
    public function it_can_create_header_parameter_with_required_fields(): void
    {
        $info = new HeaderParameterInfo(
            name: 'X-Request-Id',
            required: true,
        );

        $this->assertEquals('X-Request-Id', $info->name);
        $this->assertTrue($info->required);
        $this->assertEquals('string', $info->type);
        $this->assertNull($info->default);
        $this->assertNull($info->description);
    }

    #[Test]
    public function it_can_create_header_parameter_with_all_fields(): void
    {
        $info = new HeaderParameterInfo(
            name: 'Accept-Language',
            required: false,
            type: 'string',
            default: 'en',
            source: 'header',
            description: 'Preferred language for response',
        );

        $this->assertEquals('Accept-Language', $info->name);
        $this->assertFalse($info->required);
        $this->assertEquals('string', $info->type);
        $this->assertEquals('en', $info->default);
        $this->assertEquals('header', $info->source);
        $this->assertEquals('Preferred language for response', $info->description);
    }

    #[Test]
    public function it_can_convert_to_array(): void
    {
        $info = new HeaderParameterInfo(
            name: 'X-Tenant-Id',
            required: true,
            type: 'string',
            description: 'Tenant identifier',
        );

        $array = $info->toArray();

        $this->assertEquals('X-Tenant-Id', $array['name']);
        $this->assertEquals('header', $array['in']);
        $this->assertTrue($array['required']);
        $this->assertEquals('string', $array['type']);
        $this->assertEquals('Tenant identifier', $array['description']);
    }

    #[Test]
    public function it_can_create_from_array(): void
    {
        $data = [
            'name' => 'X-Api-Key',
            'required' => true,
            'type' => 'string',
            'default' => null,
            'source' => 'header',
            'description' => 'API key for authentication',
        ];

        $info = HeaderParameterInfo::fromArray($data);

        $this->assertEquals('X-Api-Key', $info->name);
        $this->assertTrue($info->required);
        $this->assertEquals('string', $info->type);
        $this->assertEquals('API key for authentication', $info->description);
    }

    #[Test]
    public function it_has_correct_in_parameter_for_openapi(): void
    {
        $info = new HeaderParameterInfo(
            name: 'Authorization',
            required: true,
        );

        $array = $info->toArray();

        // Headers should have 'in' set to 'header' for OpenAPI spec
        $this->assertEquals('header', $array['in']);
    }

    #[Test]
    public function it_can_be_marked_as_bearer_token(): void
    {
        $info = new HeaderParameterInfo(
            name: 'Authorization',
            required: true,
            isBearerToken: true,
        );

        $this->assertTrue($info->isBearerToken);
        $this->assertEquals('Authorization', $info->name);
    }

    #[Test]
    public function to_array_includes_schema_for_openapi(): void
    {
        $info = new HeaderParameterInfo(
            name: 'X-Correlation-Id',
            required: false,
            type: 'string',
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('schema', $array);
        $this->assertEquals('string', $array['schema']['type']);
    }

    #[Test]
    public function to_array_includes_bearer_format_for_bearer_tokens(): void
    {
        $info = new HeaderParameterInfo(
            name: 'Authorization',
            required: true,
            isBearerToken: true,
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('schema', $array);
        $this->assertEquals('bearer', $array['schema']['format']);
    }

    #[Test]
    public function to_array_includes_default_value_in_schema(): void
    {
        $info = new HeaderParameterInfo(
            name: 'Accept-Language',
            required: false,
            default: 'en',
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('schema', $array);
        $this->assertEquals('en', $array['schema']['default']);
    }

    #[Test]
    public function generate_description_returns_custom_description_when_set(): void
    {
        $info = new HeaderParameterInfo(
            name: 'X-Custom',
            description: 'Custom header for testing',
        );

        $this->assertEquals('Custom header for testing', $info->generateDescription());
    }

    #[Test]
    public function generate_description_returns_bearer_description_for_bearer_token(): void
    {
        $info = new HeaderParameterInfo(
            name: 'Authorization',
            isBearerToken: true,
        );

        $this->assertEquals('Bearer token for authentication', $info->generateDescription());
    }

    #[Test]
    public function generate_description_returns_generic_description_when_no_custom(): void
    {
        $info = new HeaderParameterInfo(
            name: 'X-Custom-Header',
        );

        $this->assertEquals('Request header: X-Custom-Header', $info->generateDescription());
    }

    #[Test]
    public function from_array_supports_snake_case_is_bearer_token(): void
    {
        $data = [
            'name' => 'Authorization',
            'required' => true,
            'is_bearer_token' => true,
        ];

        $info = HeaderParameterInfo::fromArray($data);

        $this->assertTrue($info->isBearerToken);
    }
}
