<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\QueryParameterDetector;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class QueryParameterDetectorTest extends TestCase
{
    private QueryParameterDetector $detector;

    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->detector = new QueryParameterDetector($this->parser);
    }

    public function test_detects_basic_input_method(): void
    {
        $code = '<?php
            $search = $request->input("search");
            $page = $request->input("page", 1);
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(2, $result);

        $this->assertEquals('search', $result[0]['name']);
        $this->assertEquals('input', $result[0]['method']);
        $this->assertNull($result[0]['default']);

        $this->assertEquals('page', $result[1]['name']);
        $this->assertEquals('input', $result[1]['method']);
        $this->assertEquals(1, $result[1]['default']);
    }

    public function test_detects_query_method(): void
    {
        $code = '<?php
            $status = $request->query("status", "active");
            $category = $request->query("category");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(2, $result);

        $this->assertEquals('status', $result[0]['name']);
        $this->assertEquals('query', $result[0]['method']);
        $this->assertEquals('active', $result[0]['default']);

        $this->assertEquals('category', $result[1]['name']);
        $this->assertEquals('query', $result[1]['method']);
        $this->assertNull($result[1]['default']);
    }

    public function test_detects_typed_methods(): void
    {
        $code = '<?php
            $active = $request->boolean("active");
            $limit = $request->integer("limit", 100);
            $price = $request->float("price", 0.0);
            $tags = $request->array("tags");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(4, $result);

        $this->assertEquals('active', $result[0]['name']);
        $this->assertEquals('boolean', $result[0]['method']);

        $this->assertEquals('limit', $result[1]['name']);
        $this->assertEquals('integer', $result[1]['method']);
        $this->assertEquals(100, $result[1]['default']);

        $this->assertEquals('price', $result[2]['name']);
        $this->assertEquals('float', $result[2]['method']);
        $this->assertEquals(0.0, $result[2]['default']);

        $this->assertEquals('tags', $result[3]['name']);
        $this->assertEquals('array', $result[3]['method']);
    }

    public function test_detects_has_and_filled_methods(): void
    {
        $code = '<?php
            if ($request->has("required_field")) {
                $value = $request->input("required_field");
            }
            
            if ($request->filled("optional_field")) {
                $value2 = $request->get("optional_field");
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should detect all 4 calls (has, input, filled, get)
        $params = array_unique(array_column($result, 'name'));
        $this->assertCount(2, $params);

        // Find the consolidated parameters
        $requiredField = null;
        $optionalField = null;

        foreach ($result as $param) {
            if ($param['name'] === 'required_field') {
                $requiredField = $param;
            } elseif ($param['name'] === 'optional_field') {
                $optionalField = $param;
            }
        }

        $this->assertNotNull($requiredField);
        $this->assertNotNull($optionalField);
        $this->assertTrue($requiredField['context']['has_check'] ?? false);
        $this->assertTrue($optionalField['context']['filled_check'] ?? false);
    }

    public function test_detects_magic_access(): void
    {
        $code = '<?php
            $userId = $request->user_id;
            $sortBy = $request->sort_by ?? "created_at";
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(2, $result);

        $this->assertEquals('user_id', $result[0]['name']);
        $this->assertEquals('magic', $result[0]['method']);

        $this->assertEquals('sort_by', $result[1]['name']);
        $this->assertEquals('magic', $result[1]['method']);
        $this->assertEquals('created_at', $result[1]['default']);
    }

    public function test_detects_static_calls(): void
    {
        $code = '<?php
            $search = \Illuminate\Http\Request::input("search");
            $category = Request::get("category", "all");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(2, $result);

        $this->assertEquals('search', $result[0]['name']);
        $this->assertEquals('input', $result[0]['method']);

        $this->assertEquals('category', $result[1]['name']);
        $this->assertEquals('get', $result[1]['method']);
        $this->assertEquals('all', $result[1]['default']);
    }

    public function test_detects_enum_from_in_array(): void
    {
        $code = '<?php
            $sort = $request->input("sort", "relevance");
            if (!in_array($sort, ["relevance", "date", "popularity"])) {
                $sort = "relevance";
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('sort', $result[0]['name']);
        $this->assertEquals('relevance', $result[0]['default']);
        $this->assertEquals(['relevance', 'date', 'popularity'], $result[0]['context']['enum_values'] ?? null);
    }

    public function test_consolidates_duplicate_parameters(): void
    {
        $code = '<?php
            $search = $request->input("search");
            if ($request->has("search")) {
                $search = $request->string("search");
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should consolidate to one parameter with merged context
        $this->assertCount(1, $result);
        $this->assertEquals('search', $result[0]['name']);
        $this->assertEquals('string', $result[0]['method']); // Should prefer typed method
        $this->assertTrue($result[0]['context']['has_check'] ?? false);
    }

    public function test_handles_complex_default_values(): void
    {
        $code = '<?php
            $perPage = $request->input("per_page", 15);
            $active = $request->boolean("active", true);
            $nullable = $request->input("nullable", null);
            $emptyArray = $request->array("tags", []);
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(4, $result);

        $this->assertEquals(15, $result[0]['default']);
        $this->assertEquals(true, $result[1]['default']);
        $this->assertNull($result[2]['default']);
        $this->assertEquals([], $result[3]['default']);
    }

    public function test_ignores_non_request_methods(): void
    {
        $code = '<?php
            $request->validate(["name" => "required"]);
            $request->user();
            $request->route("id");
            $value = $request->input("valid_param");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should only detect the valid_param
        $this->assertCount(1, $result);
        $this->assertEquals('valid_param', $result[0]['name']);
    }
}
