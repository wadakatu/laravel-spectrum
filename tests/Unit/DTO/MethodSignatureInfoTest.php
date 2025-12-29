<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\EnumBackingType;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\DTO\MethodSignatureInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MethodSignatureInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_empty_parameters_and_null_return(): void
    {
        $info = new MethodSignatureInfo(
            parameters: [],
            return: null,
        );

        $this->assertEquals([], $info->parameters);
        $this->assertNull($info->return);
    }

    #[Test]
    public function it_can_be_constructed_with_parameters(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive'],
            backingType: EnumBackingType::STRING,
        );

        $info = new MethodSignatureInfo(
            parameters: ['status' => $enumInfo],
            return: null,
        );

        $this->assertCount(1, $info->parameters);
        $this->assertArrayHasKey('status', $info->parameters);
        $this->assertSame($enumInfo, $info->parameters['status']);
        $this->assertNull($info->return);
    }

    #[Test]
    public function it_can_be_constructed_with_return_type(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: ['low', 'medium', 'high'],
            backingType: EnumBackingType::STRING,
        );

        $info = new MethodSignatureInfo(
            parameters: [],
            return: $enumInfo,
        );

        $this->assertEquals([], $info->parameters);
        $this->assertSame($enumInfo, $info->return);
    }

    #[Test]
    public function it_can_be_constructed_with_both_parameters_and_return(): void
    {
        $paramEnum = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive'],
            backingType: EnumBackingType::STRING,
        );

        $returnEnum = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );

        $info = new MethodSignatureInfo(
            parameters: ['status' => $paramEnum],
            return: $returnEnum,
        );

        $this->assertCount(1, $info->parameters);
        $this->assertSame($paramEnum, $info->parameters['status']);
        $this->assertSame($returnEnum, $info->return);
    }

    #[Test]
    public function it_can_be_constructed_with_multiple_parameters(): void
    {
        $statusEnum = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive'],
            backingType: EnumBackingType::STRING,
        );

        $priorityEnum = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );

        $info = new MethodSignatureInfo(
            parameters: [
                'status' => $statusEnum,
                'priority' => $priorityEnum,
            ],
            return: null,
        );

        $this->assertCount(2, $info->parameters);
        $this->assertArrayHasKey('status', $info->parameters);
        $this->assertArrayHasKey('priority', $info->parameters);
    }

    #[Test]
    public function it_checks_if_has_parameters(): void
    {
        $withParams = new MethodSignatureInfo(
            parameters: ['status' => new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING)],
            return: null,
        );

        $withoutParams = new MethodSignatureInfo(
            parameters: [],
            return: null,
        );

        $this->assertTrue($withParams->hasParameters());
        $this->assertFalse($withoutParams->hasParameters());
    }

    #[Test]
    public function it_checks_if_has_return_type(): void
    {
        $withReturn = new MethodSignatureInfo(
            parameters: [],
            return: new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING),
        );

        $withoutReturn = new MethodSignatureInfo(
            parameters: [],
            return: null,
        );

        $this->assertTrue($withReturn->hasReturnType());
        $this->assertFalse($withoutReturn->hasReturnType());
    }

    #[Test]
    public function it_checks_if_has_any_enums(): void
    {
        $withParams = new MethodSignatureInfo(
            parameters: ['status' => new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING)],
            return: null,
        );

        $withReturn = new MethodSignatureInfo(
            parameters: [],
            return: new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING),
        );

        $empty = new MethodSignatureInfo(
            parameters: [],
            return: null,
        );

        $this->assertTrue($withParams->hasAnyEnums());
        $this->assertTrue($withReturn->hasAnyEnums());
        $this->assertFalse($empty->hasAnyEnums());
    }

    #[Test]
    public function it_counts_parameters(): void
    {
        $info = new MethodSignatureInfo(
            parameters: [
                'status' => new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING),
                'priority' => new EnumInfo('App\\Enums\\Priority', [1], EnumBackingType::INTEGER),
            ],
            return: null,
        );

        $this->assertEquals(2, $info->parameterCount());
    }

    #[Test]
    public function it_gets_parameter_names(): void
    {
        $info = new MethodSignatureInfo(
            parameters: [
                'status' => new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING),
                'priority' => new EnumInfo('App\\Enums\\Priority', [1], EnumBackingType::INTEGER),
            ],
            return: null,
        );

        $this->assertEquals(['status', 'priority'], $info->getParameterNames());
    }

    #[Test]
    public function it_gets_parameter_by_name(): void
    {
        $statusEnum = new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING);

        $info = new MethodSignatureInfo(
            parameters: ['status' => $statusEnum],
            return: null,
        );

        $this->assertSame($statusEnum, $info->getParameter('status'));
        $this->assertNull($info->getParameter('nonexistent'));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $statusEnum = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active', 'inactive'],
            backingType: EnumBackingType::STRING,
        );

        $returnEnum = new EnumInfo(
            class: 'App\\Enums\\Priority',
            values: [1, 2, 3],
            backingType: EnumBackingType::INTEGER,
        );

        $info = new MethodSignatureInfo(
            parameters: ['status' => $statusEnum],
            return: $returnEnum,
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('parameters', $array);
        $this->assertArrayHasKey('return', $array);
        $this->assertArrayHasKey('status', $array['parameters']);
        $this->assertEquals('App\\Enums\\Status', $array['parameters']['status']['class']);
        $this->assertEquals('App\\Enums\\Priority', $array['return']['class']);
    }

    #[Test]
    public function it_converts_to_array_with_null_return(): void
    {
        $info = new MethodSignatureInfo(
            parameters: [],
            return: null,
        );

        $array = $info->toArray();

        $this->assertEquals([], $array['parameters']);
        $this->assertNull($array['return']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'parameters' => [
                'status' => [
                    'class' => 'App\\Enums\\Status',
                    'values' => ['active', 'inactive'],
                    'type' => 'string',
                ],
            ],
            'return' => [
                'class' => 'App\\Enums\\Priority',
                'values' => [1, 2, 3],
                'type' => 'integer',
            ],
        ];

        $info = MethodSignatureInfo::fromArray($data);

        $this->assertCount(1, $info->parameters);
        $this->assertArrayHasKey('status', $info->parameters);
        $this->assertInstanceOf(EnumInfo::class, $info->parameters['status']);
        $this->assertEquals('App\\Enums\\Status', $info->parameters['status']->class);
        $this->assertInstanceOf(EnumInfo::class, $info->return);
        $this->assertEquals('App\\Enums\\Priority', $info->return->class);
    }

    #[Test]
    public function it_creates_from_array_with_null_return(): void
    {
        $data = [
            'parameters' => [],
            'return' => null,
        ];

        $info = MethodSignatureInfo::fromArray($data);

        $this->assertEquals([], $info->parameters);
        $this->assertNull($info->return);
    }

    #[Test]
    public function it_creates_from_array_with_enum_info_instances(): void
    {
        $statusEnum = new EnumInfo('App\\Enums\\Status', ['a'], EnumBackingType::STRING);
        $returnEnum = new EnumInfo('App\\Enums\\Priority', [1], EnumBackingType::INTEGER);

        $data = [
            'parameters' => ['status' => $statusEnum],
            'return' => $returnEnum,
        ];

        $info = MethodSignatureInfo::fromArray($data);

        $this->assertSame($statusEnum, $info->parameters['status']);
        $this->assertSame($returnEnum, $info->return);
    }

    #[Test]
    public function it_uses_defaults_when_keys_missing(): void
    {
        $info = MethodSignatureInfo::fromArray([]);

        $this->assertEquals([], $info->parameters);
        $this->assertNull($info->return);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new MethodSignatureInfo(
            parameters: [
                'status' => new EnumInfo('App\\Enums\\Status', ['active'], EnumBackingType::STRING),
            ],
            return: new EnumInfo('App\\Enums\\Priority', [1, 2], EnumBackingType::INTEGER),
        );

        $restored = MethodSignatureInfo::fromArray($original->toArray());

        $this->assertCount(1, $restored->parameters);
        $this->assertEquals('App\\Enums\\Status', $restored->parameters['status']->class);
        $this->assertEquals('App\\Enums\\Priority', $restored->return->class);
    }

    #[Test]
    public function it_creates_empty_instance(): void
    {
        $info = MethodSignatureInfo::empty();

        $this->assertEquals([], $info->parameters);
        $this->assertNull($info->return);
        $this->assertFalse($info->hasAnyEnums());
    }
}
