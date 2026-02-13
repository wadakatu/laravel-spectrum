<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\CallbackAnalyzer;
use LaravelSpectrum\DTO\CallbackInfo;
use LaravelSpectrum\Tests\Fixtures\Controllers\CallbackTestController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CallbackAnalyzerTest extends TestCase
{
    private CallbackAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new CallbackAnalyzer;
    }

    #[Test]
    public function it_detects_single_callback_attribute(): void
    {
        $method = new ReflectionMethod(CallbackTestController::class, 'store');

        $callbacks = $this->analyzer->analyze($method);

        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(CallbackInfo::class, $callbacks[0]);
        $this->assertEquals('onOrderStatusChange', $callbacks[0]->name);
        $this->assertEquals('{$request.body#/callbackUrl}', $callbacks[0]->expression);
        $this->assertEquals('post', $callbacks[0]->method);
        $this->assertIsArray($callbacks[0]->requestBody);
        $this->assertIsArray($callbacks[0]->responses);
        $this->assertEquals('Notifies when order status changes', $callbacks[0]->description);
    }

    #[Test]
    public function it_detects_multiple_callback_attributes(): void
    {
        $method = new ReflectionMethod(CallbackTestController::class, 'update');

        $callbacks = $this->analyzer->analyze($method);

        $this->assertCount(2, $callbacks);
        $this->assertEquals('onPaymentComplete', $callbacks[0]->name);
        $this->assertEquals('post', $callbacks[0]->method);
        $this->assertEquals('onShipmentUpdate', $callbacks[1]->name);
        $this->assertEquals('put', $callbacks[1]->method);
    }

    #[Test]
    public function it_returns_empty_array_for_method_without_callbacks(): void
    {
        $method = new ReflectionMethod(CallbackTestController::class, 'index');

        $callbacks = $this->analyzer->analyze($method);

        $this->assertEmpty($callbacks);
    }

    #[Test]
    public function it_detects_ref_in_callback_attribute(): void
    {
        $method = new ReflectionMethod(CallbackTestController::class, 'refund');

        $callbacks = $this->analyzer->analyze($method);

        $this->assertCount(1, $callbacks);
        $this->assertEquals('onRefund', $callbacks[0]->name);
        $this->assertEquals('RefundCallback', $callbacks[0]->ref);
        $this->assertTrue($callbacks[0]->hasRef());
    }

    #[Test]
    public function it_merges_config_callbacks(): void
    {
        $configCallbacks = [
            CallbackTestController::class.'@index' => [
                [
                    'name' => 'onConfigCallback',
                    'expression' => '{$request.body#/url}',
                    'method' => 'post',
                    'description' => 'Configured via config',
                ],
            ],
        ];

        $analyzer = new CallbackAnalyzer($configCallbacks);
        $method = new ReflectionMethod(CallbackTestController::class, 'index');

        $callbacks = $analyzer->analyze($method);

        $this->assertCount(1, $callbacks);
        $this->assertEquals('onConfigCallback', $callbacks[0]->name);
        $this->assertEquals('Configured via config', $callbacks[0]->description);
    }

    #[Test]
    public function it_combines_attribute_and_config_callbacks(): void
    {
        $configCallbacks = [
            CallbackTestController::class.'@store' => [
                [
                    'name' => 'onConfigAdditional',
                    'expression' => '{$request.body#/additionalUrl}',
                ],
            ],
        ];

        $analyzer = new CallbackAnalyzer($configCallbacks);
        $method = new ReflectionMethod(CallbackTestController::class, 'store');

        $callbacks = $analyzer->analyze($method);

        // 1 from attribute + 1 from config
        $this->assertCount(2, $callbacks);
        $this->assertEquals('onOrderStatusChange', $callbacks[0]->name);
        $this->assertEquals('onConfigAdditional', $callbacks[1]->name);
    }

    #[Test]
    public function it_has_error_collector(): void
    {
        $collector = $this->analyzer->getErrorCollector();

        $this->assertNotNull($collector);
    }

    #[Test]
    public function it_collects_error_for_invalid_config_callback(): void
    {
        $configCallbacks = [
            CallbackTestController::class.'@index' => [
                ['invalid' => 'data'],
            ],
        ];

        $analyzer = new CallbackAnalyzer($configCallbacks);
        $method = new ReflectionMethod(CallbackTestController::class, 'index');

        $callbacks = $analyzer->analyze($method);

        $this->assertEmpty($callbacks);
        $this->assertTrue($analyzer->getErrorCollector()->hasErrors());
    }

    #[Test]
    public function it_continues_processing_after_config_callback_failure(): void
    {
        $configCallbacks = [
            CallbackTestController::class.'@index' => [
                ['invalid' => 'data'],
                [
                    'name' => 'validCallback',
                    'expression' => '{$request.body#/url}',
                    'method' => 'post',
                ],
            ],
        ];

        $analyzer = new CallbackAnalyzer($configCallbacks);
        $method = new ReflectionMethod(CallbackTestController::class, 'index');

        $callbacks = $analyzer->analyze($method);

        $this->assertCount(1, $callbacks);
        $this->assertEquals('validCallback', $callbacks[0]->name);
        $this->assertTrue($analyzer->getErrorCollector()->hasErrors());
    }
}
