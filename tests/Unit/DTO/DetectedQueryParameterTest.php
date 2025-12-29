<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\DetectedQueryParameter;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DetectedQueryParameterTest extends TestCase
{
    #[Test]
    public function it_creates_detected_query_parameter(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'user_id',
            method: 'get',
            default: null,
            context: []
        );

        $this->assertEquals('user_id', $param->name);
        $this->assertEquals('get', $param->method);
        $this->assertNull($param->default);
        $this->assertEquals([], $param->context);
    }

    #[Test]
    public function it_creates_parameter_with_default_value(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'page',
            method: 'input',
            default: 1,
            context: []
        );

        $this->assertEquals('page', $param->name);
        $this->assertEquals('input', $param->method);
        $this->assertEquals(1, $param->default);
    }

    #[Test]
    public function it_creates_parameter_with_string_default(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'status',
            method: 'get',
            default: 'active',
            context: []
        );

        $this->assertEquals('active', $param->default);
    }

    #[Test]
    public function it_creates_parameter_with_context_flags(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'email',
            method: 'has',
            default: null,
            context: ['has_check' => true]
        );

        $this->assertEquals(['has_check' => true], $param->context);
    }

    #[Test]
    public function it_creates_parameter_with_multiple_context_flags(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'terms',
            method: 'filled',
            default: null,
            context: ['has_check' => true, 'filled_check' => true]
        );

        $this->assertArrayHasKey('has_check', $param->context);
        $this->assertArrayHasKey('filled_check', $param->context);
    }

    #[Test]
    public function it_detects_magic_access_method(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'query',
            method: 'magic',
            default: null,
            context: []
        );

        $this->assertTrue($param->isMagicAccess());
        $this->assertFalse($param->isTypedMethod());
    }

    #[Test]
    public function it_detects_typed_methods(): void
    {
        $typedMethods = ['integer', 'float', 'boolean', 'string', 'date'];

        foreach ($typedMethods as $method) {
            $param = DetectedQueryParameter::create(
                name: 'value',
                method: $method,
                default: null,
                context: []
            );

            $this->assertTrue($param->isTypedMethod(), "Method '{$method}' should be typed");
            $this->assertFalse($param->isMagicAccess());
        }
    }

    #[Test]
    public function it_detects_non_typed_methods(): void
    {
        $nonTypedMethods = ['get', 'input', 'has', 'filled', 'magic'];

        foreach ($nonTypedMethods as $method) {
            $param = DetectedQueryParameter::create(
                name: 'value',
                method: $method,
                default: null,
                context: []
            );

            $this->assertFalse($param->isTypedMethod(), "Method '{$method}' should not be typed");
        }
    }

    #[Test]
    public function it_checks_if_has_default_value(): void
    {
        $withDefault = DetectedQueryParameter::create(
            name: 'page',
            method: 'get',
            default: 1,
            context: []
        );

        $withoutDefault = DetectedQueryParameter::create(
            name: 'page',
            method: 'get',
            default: null,
            context: []
        );

        $this->assertTrue($withDefault->hasDefault());
        $this->assertFalse($withoutDefault->hasDefault());
    }

    #[Test]
    public function it_checks_if_has_context_flag(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'email',
            method: 'has',
            default: null,
            context: ['has_check' => true, 'validated' => false]
        );

        $this->assertTrue($param->hasContextFlag('has_check'));
        $this->assertTrue($param->hasContextFlag('validated'));
        $this->assertFalse($param->hasContextFlag('nonexistent'));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $param = DetectedQueryParameter::create(
            name: 'user_id',
            method: 'integer',
            default: 0,
            context: ['has_check' => true]
        );

        $array = $param->toArray();

        $this->assertEquals('user_id', $array['name']);
        $this->assertEquals('integer', $array['method']);
        $this->assertEquals(0, $array['default']);
        $this->assertEquals(['has_check' => true], $array['context']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'name' => 'status',
            'method' => 'get',
            'default' => 'pending',
            'context' => ['filled_check' => true],
        ];

        $param = DetectedQueryParameter::fromArray($data);

        $this->assertEquals('status', $param->name);
        $this->assertEquals('get', $param->method);
        $this->assertEquals('pending', $param->default);
        $this->assertEquals(['filled_check' => true], $param->context);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [
            'name' => 'query',
            'method' => 'input',
        ];

        $param = DetectedQueryParameter::fromArray($data);

        $this->assertEquals('query', $param->name);
        $this->assertEquals('input', $param->method);
        $this->assertNull($param->default);
        $this->assertEquals([], $param->context);
    }

    #[Test]
    public function it_performs_round_trip_serialization(): void
    {
        $original = DetectedQueryParameter::create(
            name: 'filters',
            method: 'input',
            default: [],
            context: ['has_check' => true, 'array_param' => true]
        );

        $array = $original->toArray();
        $restored = DetectedQueryParameter::fromArray($array);

        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->method, $restored->method);
        $this->assertEquals($original->default, $restored->default);
        $this->assertEquals($original->context, $restored->context);
    }
}
