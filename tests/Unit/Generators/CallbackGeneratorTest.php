<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\DTO\CallbackInfo;
use LaravelSpectrum\Generators\CallbackGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CallbackGeneratorTest extends TestCase
{
    private CallbackGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new CallbackGenerator;
    }

    #[Test]
    public function it_returns_null_for_empty_callbacks(): void
    {
        $result = $this->generator->generate([]);

        $this->assertNull($result);
    }

    #[Test]
    public function it_generates_callback_with_request_body_and_responses(): void
    {
        $callbacks = [
            new CallbackInfo(
                name: 'onOrderStatusChange',
                expression: '{$request.body#/callbackUrl}',
                method: 'post',
                requestBody: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
                responses: ['200' => ['description' => 'Callback received']],
                description: 'Order status change notification',
                summary: 'Order callback',
            ),
        ];

        $result = $this->generator->generate($callbacks);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('onOrderStatusChange', $result);

        $callbackDef = $result['onOrderStatusChange'];
        $this->assertArrayHasKey('{$request.body#/callbackUrl}', $callbackDef);

        $pathItem = $callbackDef['{$request.body#/callbackUrl}'];
        $this->assertArrayHasKey('post', $pathItem);

        $operation = $pathItem['post'];
        $this->assertEquals('Order callback', $operation['summary']);
        $this->assertEquals('Order status change notification', $operation['description']);
        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertEquals(
            ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
            $operation['requestBody']['content']['application/json']['schema']
        );
        $this->assertArrayHasKey('responses', $operation);
        $this->assertArrayHasKey('200', $operation['responses']);
    }

    #[Test]
    public function it_generates_callback_with_default_response(): void
    {
        $callbacks = [
            new CallbackInfo(
                name: 'onEvent',
                expression: '{$request.body#/callbackUrl}',
                method: 'post',
            ),
        ];

        $result = $this->generator->generate($callbacks);

        $this->assertIsArray($result);
        $operation = $result['onEvent']['{$request.body#/callbackUrl}']['post'];

        // Should have a default 200 response
        $this->assertArrayHasKey('responses', $operation);
        $this->assertArrayHasKey('200', $operation['responses']);
        $this->assertEquals('Callback received successfully', $operation['responses']['200']['description']);
    }

    #[Test]
    public function it_generates_multiple_callbacks(): void
    {
        $callbacks = [
            new CallbackInfo(
                name: 'onPaymentComplete',
                expression: '{$request.body#/callbackUrl}',
                method: 'post',
            ),
            new CallbackInfo(
                name: 'onShipmentUpdate',
                expression: '{$request.body#/shipmentCallbackUrl}',
                method: 'put',
            ),
        ];

        $result = $this->generator->generate($callbacks);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('onPaymentComplete', $result);
        $this->assertArrayHasKey('onShipmentUpdate', $result);

        // Verify different methods
        $this->assertArrayHasKey('post', $result['onPaymentComplete']['{$request.body#/callbackUrl}']);
        $this->assertArrayHasKey('put', $result['onShipmentUpdate']['{$request.body#/shipmentCallbackUrl}']);
    }

    #[Test]
    public function it_generates_callback_without_request_body(): void
    {
        $callbacks = [
            new CallbackInfo(
                name: 'onDelete',
                expression: '{$request.body#/callbackUrl}',
                method: 'post',
            ),
        ];

        $result = $this->generator->generate($callbacks);

        $operation = $result['onDelete']['{$request.body#/callbackUrl}']['post'];
        $this->assertArrayNotHasKey('requestBody', $operation);
    }

    #[Test]
    public function it_generates_ref_callback(): void
    {
        $callbacks = [
            new CallbackInfo(
                name: 'onRefund',
                expression: '{$request.body#/webhookUrl}',
                ref: 'RefundCallback',
            ),
        ];

        $result = $this->generator->generate($callbacks);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('onRefund', $result);
        $this->assertEquals(
            ['$ref' => '#/components/callbacks/RefundCallback'],
            $result['onRefund']
        );
    }

    #[Test]
    public function it_generates_component_callbacks(): void
    {
        $callbacks = [
            new CallbackInfo(
                name: 'RefundCallback',
                expression: '{$request.body#/webhookUrl}',
                method: 'post',
                requestBody: ['type' => 'object', 'properties' => ['refundId' => ['type' => 'string']]],
                responses: ['200' => ['description' => 'Refund callback received']],
                description: 'Refund notification',
            ),
        ];

        $result = $this->generator->generateComponentCallbacks($callbacks);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('RefundCallback', $result);
        $this->assertArrayHasKey('{$request.body#/webhookUrl}', $result['RefundCallback']);
    }
}
