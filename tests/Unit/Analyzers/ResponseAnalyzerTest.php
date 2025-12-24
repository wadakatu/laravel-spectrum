<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\ResponseAnalyzer;
use LaravelSpectrum\Support\CollectionAnalyzer;
use LaravelSpectrum\Support\ModelSchemaExtractor;
use LaravelSpectrum\Tests\Fixtures\Models\User;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\ParserFactory;

class ResponseAnalyzerTest extends TestCase
{
    private ResponseAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->analyzer = new ResponseAnalyzer(
            $parser,
            new ModelSchemaExtractor,
            new CollectionAnalyzer
        );
    }

    public function test_analyzes_response_json_pattern()
    {
        $controller = new class
        {
            public function show($id)
            {
                $user = User::find($id);

                return response()->json([
                    'data' => $user->only(['id', 'name', 'email']),
                    'meta' => [
                        'version' => '1.0',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'show');

        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('data', $result['properties']);
        $this->assertArrayHasKey('meta', $result['properties']);
        $this->assertArrayHasKey('id', $result['properties']['data']['properties']);
        $this->assertArrayHasKey('name', $result['properties']['data']['properties']);
        $this->assertArrayHasKey('email', $result['properties']['data']['properties']);
    }

    public function test_analyzes_direct_array_return()
    {
        $controller = new class
        {
            public function index()
            {
                return [
                    'status' => 'success',
                    'total' => 100,
                    'page' => 1,
                ];
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'index');

        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('status', $result['properties']);
        $this->assertEquals('string', $result['properties']['status']['type']);
        $this->assertEquals('integer', $result['properties']['total']['type']);
    }

    public function test_analyzes_eloquent_model_return()
    {
        $controller = new class
        {
            public function show($id)
            {
                return User::findOrFail($id);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'show');

        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('properties', $result);
        // Modelのhiddenフィールドが除外されていることを確認
        $this->assertArrayNotHasKey('password', $result['properties']);
    }

    public function test_analyzes_collection_with_map()
    {
        $controller = new class
        {
            public function index()
            {
                return User::all()->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'display_name' => $user->name,
                        'contact' => $user->email,
                    ];
                });
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'index');

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
        $this->assertArrayHasKey('id', $result['items']['properties']);
        $this->assertArrayHasKey('display_name', $result['items']['properties']);
        $this->assertArrayHasKey('contact', $result['items']['properties']);
    }

    public function test_analyzes_void_return()
    {
        $controller = new class
        {
            public function delete($id)
            {
                User::destroy($id);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'delete');

        $this->assertEquals('void', $result['type']);
    }

    public function test_handles_exception_gracefully()
    {
        $result = $this->analyzer->analyze('NonExistentClass', 'method');

        $this->assertEquals('unknown', $result['type']);
        $this->assertArrayHasKey('error', $result);
    }
}
