<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\CallbackInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CallbackInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_all_properties(): void
    {
        $callback = new CallbackInfo(
            name: 'onOrderStatusChange',
            expression: '{$request.body#/callbackUrl}',
            method: 'post',
            requestBody: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
            responses: ['200' => ['description' => 'Callback received']],
            description: 'Order status change notification',
            summary: 'Order status callback',
            ref: 'OrderStatusCallback',
        );

        $this->assertEquals('onOrderStatusChange', $callback->name);
        $this->assertEquals('{$request.body#/callbackUrl}', $callback->expression);
        $this->assertEquals('post', $callback->method);
        $this->assertIsArray($callback->requestBody);
        $this->assertIsArray($callback->responses);
        $this->assertEquals('Order status change notification', $callback->description);
        $this->assertEquals('Order status callback', $callback->summary);
        $this->assertEquals('OrderStatusCallback', $callback->ref);
    }

    #[Test]
    public function it_can_be_constructed_with_defaults(): void
    {
        $callback = new CallbackInfo(
            name: 'onEvent',
            expression: '{$request.body#/callbackUrl}',
        );

        $this->assertEquals('onEvent', $callback->name);
        $this->assertEquals('{$request.body#/callbackUrl}', $callback->expression);
        $this->assertEquals('post', $callback->method);
        $this->assertNull($callback->requestBody);
        $this->assertNull($callback->responses);
        $this->assertNull($callback->description);
        $this->assertNull($callback->summary);
        $this->assertNull($callback->ref);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $callback = new CallbackInfo(
            name: 'onOrderStatusChange',
            expression: '{$request.body#/callbackUrl}',
            method: 'post',
            requestBody: ['type' => 'object'],
            responses: ['200' => ['description' => 'OK']],
            description: 'Status change notification',
            summary: 'Status callback',
            ref: 'StatusCallback',
        );

        $array = $callback->toArray();

        $this->assertEquals('onOrderStatusChange', $array['name']);
        $this->assertEquals('{$request.body#/callbackUrl}', $array['expression']);
        $this->assertEquals('post', $array['method']);
        $this->assertEquals(['type' => 'object'], $array['requestBody']);
        $this->assertEquals(['200' => ['description' => 'OK']], $array['responses']);
        $this->assertEquals('Status change notification', $array['description']);
        $this->assertEquals('Status callback', $array['summary']);
        $this->assertEquals('StatusCallback', $array['ref']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'name' => 'onPaymentComplete',
            'expression' => '{$request.body#/webhookUrl}',
            'method' => 'put',
            'requestBody' => ['type' => 'object'],
            'responses' => ['200' => ['description' => 'OK']],
            'description' => 'Payment complete notification',
            'summary' => 'Payment callback',
            'ref' => 'PaymentCallback',
        ];

        $callback = CallbackInfo::fromArray($data);

        $this->assertEquals('onPaymentComplete', $callback->name);
        $this->assertEquals('{$request.body#/webhookUrl}', $callback->expression);
        $this->assertEquals('put', $callback->method);
        $this->assertEquals(['type' => 'object'], $callback->requestBody);
        $this->assertEquals('Payment complete notification', $callback->description);
        $this->assertEquals('Payment callback', $callback->summary);
        $this->assertEquals('PaymentCallback', $callback->ref);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $data = [
            'name' => 'onEvent',
            'expression' => '{$request.body#/url}',
        ];

        $callback = CallbackInfo::fromArray($data);

        $this->assertEquals('onEvent', $callback->name);
        $this->assertEquals('{$request.body#/url}', $callback->expression);
        $this->assertEquals('post', $callback->method);
        $this->assertNull($callback->requestBody);
        $this->assertNull($callback->responses);
        $this->assertNull($callback->description);
        $this->assertNull($callback->summary);
        $this->assertNull($callback->ref);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new CallbackInfo(
            name: 'onOrderStatusChange',
            expression: '{$request.body#/callbackUrl}',
            method: 'post',
            requestBody: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
            responses: ['200' => ['description' => 'OK']],
            description: 'Order status change',
            summary: 'Order callback',
            ref: 'OrderCallback',
        );

        $restored = CallbackInfo::fromArray($original->toArray());

        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->expression, $restored->expression);
        $this->assertEquals($original->method, $restored->method);
        $this->assertEquals($original->requestBody, $restored->requestBody);
        $this->assertEquals($original->responses, $restored->responses);
        $this->assertEquals($original->description, $restored->description);
        $this->assertEquals($original->summary, $restored->summary);
        $this->assertEquals($original->ref, $restored->ref);
    }

    #[Test]
    public function it_checks_if_has_ref(): void
    {
        $withRef = new CallbackInfo(
            name: 'cb',
            expression: '{$request.body#/url}',
            ref: 'MyCallback',
        );
        $withoutRef = new CallbackInfo(
            name: 'cb',
            expression: '{$request.body#/url}',
        );

        $this->assertTrue($withRef->hasRef());
        $this->assertFalse($withoutRef->hasRef());
    }

    #[Test]
    public function it_checks_if_has_request_body(): void
    {
        $with = new CallbackInfo(
            name: 'cb',
            expression: '{$request.body#/url}',
            requestBody: ['type' => 'object'],
        );
        $without = new CallbackInfo(
            name: 'cb',
            expression: '{$request.body#/url}',
        );

        $this->assertTrue($with->hasRequestBody());
        $this->assertFalse($without->hasRequestBody());
    }

    #[Test]
    public function it_checks_if_has_responses(): void
    {
        $with = new CallbackInfo(
            name: 'cb',
            expression: '{$request.body#/url}',
            responses: ['200' => ['description' => 'OK']],
        );
        $without = new CallbackInfo(
            name: 'cb',
            expression: '{$request.body#/url}',
        );

        $this->assertTrue($with->hasResponses());
        $this->assertFalse($without->hasResponses());
    }
}
