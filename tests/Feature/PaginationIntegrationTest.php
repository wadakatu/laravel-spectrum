<?php

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\SpectrumServiceProvider;
use LaravelSpectrum\Tests\TestCase;

class PaginationIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SpectrumServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用のルートを登録
        $this->setupTestRoutes();
    }

    public function test_detects_basic_pagination_and_generates_correct_schema(): void
    {
        $this->artisan('spectrum:generate')
            ->assertExitCode(0);

        $openapi = $this->getGeneratedOpenApi();

        // /api/users エンドポイントを確認
        $this->assertArrayHasKey('/api/users', $openapi['paths']);
        $userEndpoint = $openapi['paths']['/api/users']['get'];

        // レスポンススキーマを確認
        $responseSchema = $userEndpoint['responses']['200']['content']['application/json']['schema'];

        // Paginationスキーマの確認
        $this->assertEquals('object', $responseSchema['type']);
        $this->assertArrayHasKey('data', $responseSchema['properties']);
        $this->assertArrayHasKey('current_page', $responseSchema['properties']);
        $this->assertArrayHasKey('total', $responseSchema['properties']);
        $this->assertArrayHasKey('per_page', $responseSchema['properties']);
        $this->assertArrayHasKey('last_page', $responseSchema['properties']);

        // dataが配列であることを確認
        $this->assertEquals('array', $responseSchema['properties']['data']['type']);
    }

    public function test_detects_pagination_with_resource(): void
    {
        $this->artisan('spectrum:generate')
            ->assertExitCode(0);

        $openapi = $this->getGeneratedOpenApi();

        // /api/posts エンドポイントを確認
        $this->assertArrayHasKey('/api/posts', $openapi['paths']);
        $postEndpoint = $openapi['paths']['/api/posts']['get'];

        // レスポンススキーマを確認
        $responseSchema = $postEndpoint['responses']['200']['content']['application/json']['schema'];

        // Paginationスキーマの確認
        $this->assertEquals('object', $responseSchema['type']);
        $this->assertArrayHasKey('data', $responseSchema['properties']);

        // dataにリソースの$ref参照が適用されていることを確認
        $dataItems = $responseSchema['properties']['data']['items'];
        $this->assertArrayHasKey('$ref', $dataItems);
        $this->assertStringContainsString('#/components/schemas/', $dataItems['$ref']);

        // components.schemasにスキーマが登録されていることを確認
        $schemaName = str_replace('#/components/schemas/', '', $dataItems['$ref']);
        $this->assertArrayHasKey($schemaName, $openapi['components']['schemas']);
        $schema = $openapi['components']['schemas'][$schemaName];
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('title', $schema['properties']);
    }

    public function test_detects_simple_pagination(): void
    {
        $this->artisan('spectrum:generate')
            ->assertExitCode(0);

        $openapi = $this->getGeneratedOpenApi();

        // /api/simple-posts エンドポイントを確認
        $this->assertArrayHasKey('/api/simple-posts', $openapi['paths']);
        $endpoint = $openapi['paths']['/api/simple-posts']['get'];

        // レスポンススキーマを確認
        $responseSchema = $endpoint['responses']['200']['content']['application/json']['schema'];

        // Simple Paginationスキーマの確認
        $this->assertArrayHasKey('data', $responseSchema['properties']);
        $this->assertArrayHasKey('first_page_url', $responseSchema['properties']);
        $this->assertArrayHasKey('next_page_url', $responseSchema['properties']);

        // Simple Paginationには含まれないフィールドを確認
        $this->assertArrayNotHasKey('current_page', $responseSchema['properties']);
        $this->assertArrayNotHasKey('total', $responseSchema['properties']);
        $this->assertArrayNotHasKey('last_page', $responseSchema['properties']);
    }

    public function test_detects_cursor_pagination(): void
    {
        $this->artisan('spectrum:generate')
            ->assertExitCode(0);

        $openapi = $this->getGeneratedOpenApi();

        // /api/comments エンドポイントを確認
        $this->assertArrayHasKey('/api/comments', $openapi['paths']);
        $endpoint = $openapi['paths']['/api/comments']['get'];

        // レスポンススキーマを確認
        $responseSchema = $endpoint['responses']['200']['content']['application/json']['schema'];

        // Cursor Paginationスキーマの確認
        $this->assertArrayHasKey('data', $responseSchema['properties']);
        $this->assertArrayHasKey('next_cursor', $responseSchema['properties']);
        $this->assertArrayHasKey('prev_cursor', $responseSchema['properties']);

        // Cursor Paginationには含まれないフィールドを確認
        $this->assertArrayNotHasKey('current_page', $responseSchema['properties']);
        $this->assertArrayNotHasKey('total', $responseSchema['properties']);
    }

    protected function setupTestRoutes(): void
    {
        $router = $this->app['router'];

        // Basic pagination
        $router->get('/api/users', TestControllers\PaginatedUsersController::class.'@index');

        // Pagination with Resource
        $router->get('/api/posts', TestControllers\PaginatedPostsController::class.'@index');

        // Simple pagination
        $router->get('/api/simple-posts', TestControllers\SimplePaginatedController::class.'@index');

        // Cursor pagination
        $router->get('/api/comments', TestControllers\CursorPaginatedController::class.'@index');
    }

    protected function getGeneratedOpenApi(): array
    {
        $path = storage_path('app/spectrum/openapi.json');
        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true);
    }
}

// Test Controllers

namespace LaravelSpectrum\Tests\Feature\TestControllers;

use LaravelSpectrum\Tests\Fixtures\Models\Comment;
use LaravelSpectrum\Tests\Fixtures\Models\Post;
use LaravelSpectrum\Tests\Fixtures\Models\User;
use LaravelSpectrum\Tests\Fixtures\Resources\PostResource;

class PaginatedUsersController
{
    public function index()
    {
        return User::paginate(15);
    }
}

class PaginatedPostsController
{
    public function index()
    {
        return PostResource::collection(Post::paginate());
    }
}

class SimplePaginatedController
{
    public function index()
    {
        return Post::simplePaginate(10);
    }
}

class CursorPaginatedController
{
    public function index()
    {
        return Comment::cursorPaginate(20);
    }
}
