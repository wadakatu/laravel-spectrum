<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use LaravelSpectrum\Attributes\OpenApiCallback;

/**
 * Controller with callback attributes for testing callback detection.
 */
class CallbackTestController
{
    #[OpenApiCallback(
        name: 'onOrderStatusChange',
        expression: '{$request.body#/callbackUrl}',
        method: 'post',
        requestBody: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
        responses: ['200' => ['description' => 'Callback received']],
        description: 'Notifies when order status changes',
    )]
    public function store(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    #[OpenApiCallback(
        name: 'onPaymentComplete',
        expression: '{$request.body#/callbackUrl}',
        method: 'post',
        requestBody: ['type' => 'object', 'properties' => ['paymentId' => ['type' => 'string']]],
    )]
    #[OpenApiCallback(
        name: 'onShipmentUpdate',
        expression: '{$request.body#/shipmentCallbackUrl}',
        method: 'put',
        requestBody: ['type' => 'object', 'properties' => ['trackingNumber' => ['type' => 'string']]],
    )]
    public function update(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    #[OpenApiCallback(
        name: 'onRefund',
        expression: '{$request.body#/webhookUrl}',
        ref: 'RefundCallback',
    )]
    public function refund(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
