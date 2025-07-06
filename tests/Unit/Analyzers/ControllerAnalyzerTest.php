<?php

namespace LaravelPrism\Tests\Unit\Analyzers;

use LaravelPrism\Analyzers\ControllerAnalyzer;
use LaravelPrism\Tests\TestCase;

class ControllerAnalyzerTest extends TestCase
{
    private ControllerAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ControllerAnalyzer;
    }

    /** @test */
    public function it_detects_fractal_item_usage()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'show');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelPrism\Tests\Fixtures\Transformers\UserTransformer', $result['fractal']['transformer']);
        $this->assertFalse($result['fractal']['collection']);
        $this->assertEquals('item', $result['fractal']['type']);
    }

    /** @test */
    public function it_detects_fractal_collection_usage()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'index');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelPrism\Tests\Fixtures\Transformers\UserTransformer', $result['fractal']['transformer']);
        $this->assertTrue($result['fractal']['collection']);
        $this->assertEquals('collection', $result['fractal']['type']);
    }

    /** @test */
    public function it_detects_fractal_with_includes()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'withIncludes');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelPrism\Tests\Fixtures\Transformers\PostTransformer', $result['fractal']['transformer']);
        $this->assertTrue($result['fractal']['hasIncludes']);
    }

    /** @test */
    public function it_detects_both_resource_and_fractal()
    {
        $controller = TestMixedController::class;
        $result = $this->analyzer->analyze($controller, 'mixed');

        // 既存のResource検出
        $this->assertArrayHasKey('resource', $result);

        // Fractal検出も動作する
        $this->assertArrayHasKey('fractal', $result);
    }
}

// テスト用のコントローラークラス
class TestFractalController
{
    public function show($id)
    {
        $user = User::find($id);

        return fractal()->item($user, new \LaravelPrism\Tests\Fixtures\Transformers\UserTransformer);
    }

    public function index()
    {
        $users = User::all();

        return fractal()->collection($users, new \LaravelPrism\Tests\Fixtures\Transformers\UserTransformer);
    }

    public function withIncludes()
    {
        $posts = Post::all();

        return fractal()
            ->collection($posts, new \LaravelPrism\Tests\Fixtures\Transformers\PostTransformer)
            ->parseIncludes(request()->get('include', ''))
            ->respond();
    }
}

class TestMixedController
{
    public function mixed()
    {
        if (request()->wantsJson()) {
            return fractal()->item($user, new \LaravelPrism\Tests\Fixtures\Transformers\UserTransformer);
        }

        return new UserResource($user);
    }
}
