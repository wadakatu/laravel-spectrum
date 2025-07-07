<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\TestCase;

class ErrorResponseIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        app(DocumentationCache::class)->clear();
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        app(DocumentationCache::class)->clear();

        // テスト後のクリーンアップ
        if (\Illuminate\Support\Facades\File::exists(storage_path('app/spectrum'))) {
            \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/spectrum'));
        }

        parent::tearDown();
    }

    /** @test */
    public function it_includes_error_responses_in_generated_openapi_spec()
    {
        // テスト用のルートを作成
        Route::post('api/users', 'LaravelSpectrum\Tests\Fixtures\Controllers\UserController@store')->name('users.store');
        Route::get('api/users/{user}', 'LaravelSpectrum\Tests\Fixtures\Controllers\UserController@show')
            ->middleware('auth:sanctum')
            ->name('users.show');

        // OpenAPI仕様を生成
        $this->artisan('spectrum:generate')->assertExitCode(0);

        // 生成されたファイルを読み込む
        $openapi = json_decode(file_get_contents(storage_path('app/spectrum/openapi.json')), true);

        // POST /api/users のエラーレスポンスを確認
        $postResponses = $openapi['paths']['/api/users']['post']['responses'];

        // デバッグ出力
        if (! isset($postResponses['422'])) {
            $this->fail('422 response not found. Available responses: '.implode(', ', array_keys($postResponses)));
        }
        $this->assertArrayHasKey('422', $postResponses);
        $this->assertArrayHasKey('500', $postResponses);

        // 422エラーの詳細を確認
        $validationError = $postResponses['422'];
        $this->assertEquals('Validation Error', $validationError['description']);
        $this->assertArrayHasKey('content', $validationError);
        $this->assertArrayHasKey('application/json', $validationError['content']);
        $this->assertArrayHasKey('schema', $validationError['content']['application/json']);

        $errorSchema = $validationError['content']['application/json']['schema'];
        $this->assertArrayHasKey('properties', $errorSchema);
        $this->assertArrayHasKey('message', $errorSchema['properties']);
        $this->assertArrayHasKey('errors', $errorSchema['properties']);

        // GET /api/users/{user} のエラーレスポンスを確認（認証必須）
        $getResponses = $openapi['paths']['/api/users/{user}']['get']['responses'];
        $this->assertArrayHasKey('401', $getResponses);
        $this->assertArrayHasKey('403', $getResponses);
        $this->assertArrayHasKey('404', $getResponses);
        $this->assertArrayHasKey('500', $getResponses);
    }
}
