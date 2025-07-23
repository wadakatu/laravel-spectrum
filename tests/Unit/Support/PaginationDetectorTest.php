<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\PaginationDetector;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class PaginationDetectorTest extends TestCase
{
    private PaginationDetector $detector;

    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new PaginationDetector;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function test_detects_basic_paginate_call(): void
    {
        $code = '<?php User::paginate(15);';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('paginate', $result[0]['type']);
        $this->assertEquals('User', $result[0]['model']);
    }

    public function test_detects_simple_paginate_call(): void
    {
        $code = '<?php Post::simplePaginate(10);';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('simplePaginate', $result[0]['type']);
        $this->assertEquals('Post', $result[0]['model']);
    }

    public function test_detects_cursor_paginate_call(): void
    {
        $code = '<?php Comment::cursorPaginate(20);';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('cursorPaginate', $result[0]['type']);
        $this->assertEquals('Comment', $result[0]['model']);
    }

    public function test_detects_method_chain_pagination(): void
    {
        $code = '<?php User::where("active", true)->orderBy("name")->paginate();';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('paginate', $result[0]['type']);
        $this->assertEquals('User', $result[0]['model']);
    }

    public function test_detects_resource_collection_pagination(): void
    {
        $code = '<?php UserResource::collection(User::paginate());';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('paginate', $result[0]['type']);
        $this->assertEquals('User', $result[0]['model']);
        $this->assertEquals('UserResource', $result[0]['resource']);
    }

    public function test_detects_relation_pagination(): void
    {
        $code = '<?php $user->posts()->paginate();';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('paginate', $result[0]['type']);
        $this->assertEquals('posts', $result[0]['model']);
        // source情報はオプショナルなので、存在する場合のみチェック
        if (isset($result[0]['source'])) {
            $this->assertEquals('relation', $result[0]['source']);
        }
    }

    public function test_detects_multiple_pagination_calls(): void
    {
        $code = '<?php 
            $users = User::paginate(10);
            $posts = Post::simplePaginate(20);
        ';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(2, $result);
        $this->assertEquals('paginate', $result[0]['type']);
        $this->assertEquals('User', $result[0]['model']);
        $this->assertEquals('simplePaginate', $result[1]['type']);
        $this->assertEquals('Post', $result[1]['model']);
    }

    public function test_detects_query_builder_pagination(): void
    {
        $code = '<?php DB::table("users")->paginate();';
        $ast = $this->parser->parse($code);

        $result = $this->detector->detectPaginationCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('paginate', $result[0]['type']);
        $this->assertEquals('users', $result[0]['model']);
        // source情報はオプショナルなので、存在する場合のみチェック
        if (isset($result[0]['source'])) {
            $this->assertEquals('query_builder', $result[0]['source']);
        }
    }

    public function test_gets_pagination_type(): void
    {
        $this->assertEquals('length_aware', $this->detector->getPaginationType('paginate'));
        $this->assertEquals('simple', $this->detector->getPaginationType('simplePaginate'));
        $this->assertEquals('cursor', $this->detector->getPaginationType('cursorPaginate'));
        $this->assertEquals('unknown', $this->detector->getPaginationType('unknown'));
    }

    public function test_extracts_model_from_static_call(): void
    {
        $code = '<?php User::paginate();';
        $ast = $this->parser->parse($code);
        $node = $ast[0]->expr;

        $model = $this->detector->extractModelFromMethodCall($node);

        $this->assertEquals('User', $model);
    }

    public function test_extracts_model_from_method_chain(): void
    {
        $code = '<?php User::where("active", true)->paginate();';
        $ast = $this->parser->parse($code);
        $node = $ast[0]->expr;

        $model = $this->detector->extractModelFromMethodCall($node);

        $this->assertEquals('User', $model);
    }

    public function test_extracts_table_from_query_builder(): void
    {
        $code = '<?php DB::table("users")->paginate();';
        $ast = $this->parser->parse($code);
        $node = $ast[0]->expr;

        $model = $this->detector->extractModelFromMethodCall($node);

        $this->assertEquals('users', $model);
    }
}
