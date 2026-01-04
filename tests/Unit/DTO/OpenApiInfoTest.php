<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\OpenApiInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_required_fields(): void
    {
        $info = new OpenApiInfo(
            title: 'My API',
            version: '1.0.0',
        );

        $this->assertEquals('My API', $info->title);
        $this->assertEquals('1.0.0', $info->version);
        $this->assertEquals('', $info->description);
        $this->assertNull($info->termsOfService);
        $this->assertNull($info->contact);
        $this->assertNull($info->license);
    }

    #[Test]
    public function it_can_be_constructed_with_all_fields(): void
    {
        $info = new OpenApiInfo(
            title: 'Complete API',
            version: '2.0.0',
            description: 'A complete API specification',
            termsOfService: 'https://example.com/terms',
            contact: ['name' => 'Support', 'email' => 'support@example.com', 'url' => 'https://example.com'],
            license: ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'],
        );

        $this->assertEquals('Complete API', $info->title);
        $this->assertEquals('2.0.0', $info->version);
        $this->assertEquals('A complete API specification', $info->description);
        $this->assertEquals('https://example.com/terms', $info->termsOfService);
        $this->assertEquals(['name' => 'Support', 'email' => 'support@example.com', 'url' => 'https://example.com'], $info->contact);
        $this->assertEquals(['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'], $info->license);
    }

    #[Test]
    public function it_converts_to_array_with_required_fields_only(): void
    {
        $info = new OpenApiInfo(
            title: 'Simple API',
            version: '1.0.0',
        );

        $array = $info->toArray();

        $this->assertEquals('Simple API', $array['title']);
        $this->assertEquals('1.0.0', $array['version']);
        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('termsOfService', $array);
        $this->assertArrayNotHasKey('contact', $array);
        $this->assertArrayNotHasKey('license', $array);
    }

    #[Test]
    public function it_converts_to_array_with_all_fields(): void
    {
        $info = new OpenApiInfo(
            title: 'Full API',
            version: '3.0.0',
            description: 'Full description',
            termsOfService: 'https://example.com/terms',
            contact: ['name' => 'Admin'],
            license: ['name' => 'Apache 2.0'],
        );

        $array = $info->toArray();

        $this->assertEquals('Full API', $array['title']);
        $this->assertEquals('3.0.0', $array['version']);
        $this->assertEquals('Full description', $array['description']);
        $this->assertEquals('https://example.com/terms', $array['termsOfService']);
        $this->assertEquals(['name' => 'Admin'], $array['contact']);
        $this->assertEquals(['name' => 'Apache 2.0'], $array['license']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'title' => 'Array API',
            'version' => '1.5.0',
            'description' => 'Created from array',
            'termsOfService' => 'https://example.com/tos',
            'contact' => ['email' => 'api@example.com'],
            'license' => ['name' => 'GPL'],
        ];

        $info = OpenApiInfo::fromArray($data);

        $this->assertEquals('Array API', $info->title);
        $this->assertEquals('1.5.0', $info->version);
        $this->assertEquals('Created from array', $info->description);
        $this->assertEquals('https://example.com/tos', $info->termsOfService);
        $this->assertEquals(['email' => 'api@example.com'], $info->contact);
        $this->assertEquals(['name' => 'GPL'], $info->license);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [];

        $info = OpenApiInfo::fromArray($data);

        $this->assertEquals('', $info->title);
        $this->assertEquals('', $info->version);
        $this->assertEquals('', $info->description);
        $this->assertNull($info->termsOfService);
        $this->assertNull($info->contact);
        $this->assertNull($info->license);
    }

    #[Test]
    public function it_checks_if_has_contact(): void
    {
        $with = new OpenApiInfo(
            title: 'API',
            version: '1.0.0',
            contact: ['name' => 'Support'],
        );
        $without = new OpenApiInfo(
            title: 'API',
            version: '1.0.0',
        );

        $this->assertTrue($with->hasContact());
        $this->assertFalse($without->hasContact());
    }

    #[Test]
    public function it_checks_if_has_license(): void
    {
        $with = new OpenApiInfo(
            title: 'API',
            version: '1.0.0',
            license: ['name' => 'MIT'],
        );
        $without = new OpenApiInfo(
            title: 'API',
            version: '1.0.0',
        );

        $this->assertTrue($with->hasLicense());
        $this->assertFalse($without->hasLicense());
    }

    #[Test]
    public function it_checks_if_has_terms_of_service(): void
    {
        $with = new OpenApiInfo(
            title: 'API',
            version: '1.0.0',
            termsOfService: 'https://example.com/terms',
        );
        $without = new OpenApiInfo(
            title: 'API',
            version: '1.0.0',
        );

        $this->assertTrue($with->hasTermsOfService());
        $this->assertFalse($without->hasTermsOfService());
    }

    #[Test]
    public function it_survives_round_trip_serialization(): void
    {
        $original = new OpenApiInfo(
            title: 'Round Trip API',
            version: '2.0.0',
            description: 'Test round trip',
            termsOfService: 'https://example.com/terms',
            contact: ['name' => 'Test', 'email' => 'test@example.com'],
            license: ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'],
        );

        $restored = OpenApiInfo::fromArray($original->toArray());

        $this->assertEquals($original->title, $restored->title);
        $this->assertEquals($original->version, $restored->version);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->termsOfService, $restored->termsOfService);
        $this->assertEquals($original->contact, $restored->contact);
        $this->assertEquals($original->license, $restored->license);
    }
}
