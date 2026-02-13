<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Attributes;

use LaravelSpectrum\Attributes\OpenApiCallback;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OpenApiCallbackTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated_with_required_fields(): void
    {
        $attr = new OpenApiCallback(
            name: 'onEvent',
            expression: '{$request.body#/callbackUrl}',
        );

        $this->assertEquals('onEvent', $attr->name);
        $this->assertEquals('{$request.body#/callbackUrl}', $attr->expression);
        $this->assertEquals('post', $attr->method);
        $this->assertNull($attr->requestBody);
        $this->assertNull($attr->responses);
        $this->assertNull($attr->description);
        $this->assertNull($attr->summary);
        $this->assertNull($attr->ref);
    }

    #[Test]
    public function it_can_be_instantiated_with_all_fields(): void
    {
        $attr = new OpenApiCallback(
            name: 'onOrderStatusChange',
            expression: '{$request.body#/callbackUrl}',
            method: 'put',
            requestBody: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
            responses: ['200' => ['description' => 'OK']],
            description: 'Order status change notification',
            summary: 'Order status callback',
            ref: 'OrderStatusCallback',
        );

        $this->assertEquals('onOrderStatusChange', $attr->name);
        $this->assertEquals('{$request.body#/callbackUrl}', $attr->expression);
        $this->assertEquals('put', $attr->method);
        $this->assertIsArray($attr->requestBody);
        $this->assertIsArray($attr->responses);
        $this->assertEquals('Order status change notification', $attr->description);
        $this->assertEquals('Order status callback', $attr->summary);
        $this->assertEquals('OrderStatusCallback', $attr->ref);
    }

    #[Test]
    public function it_targets_methods(): void
    {
        $reflection = new ReflectionClass(OpenApiCallback::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertTrue(($attribute->flags & \Attribute::TARGET_METHOD) !== 0);
    }

    #[Test]
    public function it_is_repeatable(): void
    {
        $reflection = new ReflectionClass(OpenApiCallback::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertTrue(($attribute->flags & \Attribute::IS_REPEATABLE) !== 0);
    }

    #[Test]
    public function it_can_be_read_from_method_reflection(): void
    {
        $controller = new class
        {
            #[OpenApiCallback(
                name: 'onOrderCreated',
                expression: '{$request.body#/callbackUrl}',
                method: 'post',
                requestBody: ['type' => 'object', 'properties' => ['orderId' => ['type' => 'integer']]],
                description: 'Callback when order is created',
            )]
            public function store(): void {}
        };

        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('store');
        $attributes = $method->getAttributes(OpenApiCallback::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertEquals('onOrderCreated', $instance->name);
        $this->assertEquals('{$request.body#/callbackUrl}', $instance->expression);
        $this->assertEquals('post', $instance->method);
        $this->assertIsArray($instance->requestBody);
        $this->assertEquals('Callback when order is created', $instance->description);
    }

    #[Test]
    public function it_supports_multiple_attributes_on_same_method(): void
    {
        $controller = new class
        {
            #[OpenApiCallback(
                name: 'onOrderCreated',
                expression: '{$request.body#/callbackUrl}',
            )]
            #[OpenApiCallback(
                name: 'onPaymentProcessed',
                expression: '{$request.body#/paymentCallbackUrl}',
                method: 'put',
            )]
            public function store(): void {}
        };

        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('store');
        $attributes = $method->getAttributes(OpenApiCallback::class);

        $this->assertCount(2, $attributes);

        $first = $attributes[0]->newInstance();
        $second = $attributes[1]->newInstance();

        $this->assertEquals('onOrderCreated', $first->name);
        $this->assertEquals('onPaymentProcessed', $second->name);
        $this->assertEquals('post', $first->method);
        $this->assertEquals('put', $second->method);
    }
}
