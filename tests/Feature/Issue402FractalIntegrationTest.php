<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Tests\Fixtures\Controllers\Issue402FractalController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class Issue402FractalIntegrationTest extends TestCase
{
    #[Test]
    public function it_generates_fractal_schema_and_prefers_success_response_over_error_guard_clause(): void
    {
        $this->isolateRoutes(function () {
            Route::get('api/issue402/me', [Issue402FractalController::class, 'me']);
        });

        $openapi = $this->generateOpenApi();
        $responseSchema = $openapi['paths']['/api/issue402/me']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('object', $responseSchema['type']);
        $this->assertArrayHasKey('data', $responseSchema['properties']);
        $this->assertArrayHasKey('properties', $responseSchema['properties']['data']);

        $dataProperties = $responseSchema['properties']['data']['properties'];
        $this->assertArrayHasKey('is_beta_user', $dataProperties);
        $this->assertSame('boolean', $dataProperties['is_beta_user']['type']);
        $this->assertArrayHasKey('created_at', $dataProperties);
        $this->assertSame('string', $dataProperties['created_at']['type']);
        $this->assertSame('date-time', $dataProperties['created_at']['format']);

        $this->assertArrayNotHasKey('code', $responseSchema['properties']);
        $this->assertArrayHasKey('GetterBasedUserTransformer', $openapi['components']['schemas']);
    }
}
