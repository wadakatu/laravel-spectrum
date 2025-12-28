<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\EnumParameterInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnumParameterInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new EnumParameterInfo(
            name: 'status',
            type: 'string',
            enum: ['active', 'inactive', 'pending'],
            required: true,
            description: 'User status',
            in: 'path',
            enumClass: 'App\\Enums\\UserStatus',
        );

        $this->assertEquals('status', $info->name);
        $this->assertEquals('string', $info->type);
        $this->assertEquals(['active', 'inactive', 'pending'], $info->enum);
        $this->assertTrue($info->required);
        $this->assertEquals('User status', $info->description);
        $this->assertEquals('path', $info->in);
        $this->assertEquals('App\\Enums\\UserStatus', $info->enumClass);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'name' => 'priority',
            'type' => 'integer',
            'enum' => [1, 2, 3],
            'required' => false,
            'description' => 'Priority level',
            'in' => 'query',
            'enumClass' => 'App\\Enums\\Priority',
        ];

        $info = EnumParameterInfo::fromArray($array);

        $this->assertEquals('priority', $info->name);
        $this->assertEquals('integer', $info->type);
        $this->assertEquals([1, 2, 3], $info->enum);
        $this->assertFalse($info->required);
        $this->assertEquals('Priority level', $info->description);
        $this->assertEquals('query', $info->in);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [
            'name' => 'status',
        ];

        $info = EnumParameterInfo::fromArray($array);

        $this->assertEquals('status', $info->name);
        $this->assertEquals('string', $info->type);
        $this->assertEquals([], $info->enum);
        $this->assertTrue($info->required);
        $this->assertEquals('', $info->description);
        $this->assertEquals('path', $info->in);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new EnumParameterInfo(
            name: 'status',
            type: 'string',
            enum: ['active', 'inactive'],
            required: true,
            description: 'Status',
            in: 'path',
            enumClass: 'Status',
        );

        $array = $info->toArray();

        $this->assertEquals('status', $array['name']);
        $this->assertEquals('string', $array['type']);
        $this->assertEquals(['active', 'inactive'], $array['enum']);
        $this->assertTrue($array['required']);
        $this->assertEquals('Status', $array['description']);
        $this->assertEquals('path', $array['in']);
        $this->assertEquals('Status', $array['enumClass']);
    }

    #[Test]
    public function it_checks_parameter_location(): void
    {
        $path = new EnumParameterInfo(name: 's', type: 'string', enum: [], in: 'path');
        $query = new EnumParameterInfo(name: 's', type: 'string', enum: [], in: 'query');

        $this->assertTrue($path->isPathParameter());
        $this->assertFalse($path->isQueryParameter());

        $this->assertFalse($query->isPathParameter());
        $this->assertTrue($query->isQueryParameter());
    }

    #[Test]
    public function it_checks_backing_type(): void
    {
        $string = new EnumParameterInfo(name: 's', type: 'string', enum: []);
        $int = new EnumParameterInfo(name: 's', type: 'integer', enum: []);

        $this->assertTrue($string->isStringBacked());
        $this->assertFalse($string->isIntegerBacked());

        $this->assertFalse($int->isStringBacked());
        $this->assertTrue($int->isIntegerBacked());
    }
}
