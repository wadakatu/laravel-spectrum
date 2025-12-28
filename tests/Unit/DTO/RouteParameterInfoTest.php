<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\RouteParameterInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RouteParameterInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new RouteParameterInfo(
            name: 'id',
            required: true,
            in: 'path',
            schema: ['type' => 'integer'],
        );

        $this->assertEquals('id', $info->name);
        $this->assertTrue($info->required);
        $this->assertEquals('path', $info->in);
        $this->assertEquals(['type' => 'integer'], $info->schema);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'name' => 'userId',
            'required' => false,
            'in' => 'path',
            'schema' => ['type' => 'string'],
        ];

        $info = RouteParameterInfo::fromArray($array);

        $this->assertEquals('userId', $info->name);
        $this->assertFalse($info->required);
        $this->assertEquals('path', $info->in);
        $this->assertEquals(['type' => 'string'], $info->schema);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [
            'name' => 'id',
        ];

        $info = RouteParameterInfo::fromArray($array);

        $this->assertEquals('id', $info->name);
        $this->assertTrue($info->required);
        $this->assertEquals('path', $info->in);
        $this->assertEquals(['type' => 'string'], $info->schema);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new RouteParameterInfo(
            name: 'id',
            required: true,
            in: 'path',
            schema: ['type' => 'integer'],
        );

        $array = $info->toArray();

        $this->assertEquals('id', $array['name']);
        $this->assertTrue($array['required']);
        $this->assertEquals('path', $array['in']);
        $this->assertEquals(['type' => 'integer'], $array['schema']);
    }

    #[Test]
    public function it_checks_if_optional(): void
    {
        $required = new RouteParameterInfo(name: 'id', required: true);
        $optional = new RouteParameterInfo(name: 'id', required: false);

        $this->assertFalse($required->isOptional());
        $this->assertTrue($optional->isOptional());
    }

    #[Test]
    public function it_checks_schema_type(): void
    {
        $stringParam = new RouteParameterInfo(name: 'id', schema: ['type' => 'string']);
        $intParam = new RouteParameterInfo(name: 'id', schema: ['type' => 'integer']);
        $noTypeParam = new RouteParameterInfo(name: 'id', schema: []);

        $this->assertEquals('string', $stringParam->getSchemaType());
        $this->assertEquals('integer', $intParam->getSchemaType());
        $this->assertNull($noTypeParam->getSchemaType());
    }
}
