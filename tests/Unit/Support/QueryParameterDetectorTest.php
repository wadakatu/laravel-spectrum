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

    public function test_detects_switch_enum_values(): void
    {
        $code = '<?php
            $status = $request->input("status");
            switch ($status) {
                case "active":
                    return "active";
                case "pending":
                    return "pending";
                case "inactive":
                    return "inactive";
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('status', $result[0]['name']);
        $this->assertContains('active', $result[0]['context']['enum_values']);
        $this->assertContains('pending', $result[0]['context']['enum_values']);
        $this->assertContains('inactive', $result[0]['context']['enum_values']);
    }

    public function test_detects_match_enum_values(): void
    {
        $code = '<?php
            $role = $request->input("role");
            $result = match ($role) {
                "admin" => "Administrator",
                "editor" => "Editor",
                "viewer" => "Viewer",
                default => "Guest",
            };
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('role', $result[0]['name']);
        $this->assertContains('admin', $result[0]['context']['enum_values']);
        $this->assertContains('editor', $result[0]['context']['enum_values']);
        $this->assertContains('viewer', $result[0]['context']['enum_values']);
    }

    public function test_tracks_variable_from_magic_property(): void
    {
        $code = '<?php
            $sortField = $request->sort_field;
            switch ($sortField) {
                case "name":
                    return "name";
                case "date":
                    return "date";
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('sort_field', $result[0]['name']);
        $this->assertEquals('magic', $result[0]['method']);
        $this->assertContains('name', $result[0]['context']['enum_values']);
        $this->assertContains('date', $result[0]['context']['enum_values']);
    }

    public function test_handles_method_call_without_identifier(): void
    {
        $code = '<?php
            $method = "input";
            $value = $request->$method("search");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Dynamic method names should be ignored
        $this->assertEmpty($result);
    }

    public function test_handles_method_call_without_args(): void
    {
        $code = '<?php
            $value = $request->all();
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // all() without arguments should be ignored
        $this->assertEmpty($result);
    }

    public function test_handles_static_call_without_identifier(): void
    {
        $code = '<?php
            $method = "input";
            $value = Request::$method("search");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Dynamic method names should be ignored
        $this->assertEmpty($result);
    }

    public function test_handles_static_call_without_args(): void
    {
        $code = '<?php
            $value = Request::all();
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // all() without arguments should be ignored
        $this->assertEmpty($result);
    }

    public function test_handles_magic_access_without_identifier(): void
    {
        $code = '<?php
            $prop = "search";
            $value = $request->$prop;
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Dynamic property names should be ignored
        $this->assertEmpty($result);
    }

    public function test_handles_in_array_with_less_than_two_args(): void
    {
        $code = '<?php
            $search = $request->input("search");
            $result = in_array($search);
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should detect search but without enum values
        $this->assertCount(1, $result);
        $this->assertEquals('search', $result[0]['name']);
        $this->assertEmpty($result[0]['context']['enum_values'] ?? []);
    }

    public function test_handles_in_array_with_non_request_variable(): void
    {
        $code = '<?php
            $search = "test";
            $result = in_array($search, ["a", "b", "c"]);
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should not detect any parameters
        $this->assertEmpty($result);
    }

    public function test_coalesce_updates_existing_magic_parameter(): void
    {
        $code = '<?php
            $value = $request->sort;
            $result = $request->sort ?? "default";
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should consolidate to one parameter with default
        $this->assertCount(1, $result);
        $this->assertEquals('sort', $result[0]['name']);
        $this->assertEquals('default', $result[0]['default']);
    }

    public function test_coalesce_with_non_property_fetch(): void
    {
        $code = '<?php
            $value = $someVar ?? "default";
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should not detect any parameters
        $this->assertEmpty($result);
    }

    public function test_handles_method_call_with_non_string_first_arg(): void
    {
        $code = '<?php
            $field = "search";
            $value = $request->input($field);
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Variable as first argument, cannot extract string value
        $this->assertEmpty($result);
    }

    public function test_detects_missing_method(): void
    {
        $code = '<?php
            if ($request->missing("optional_field")) {
                return null;
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('optional_field', $result[0]['name']);
        $this->assertEquals('missing', $result[0]['method']);
    }

    public function test_detects_only_method(): void
    {
        $code = '<?php
            $data = $request->only("name", "email");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('name', $result[0]['name']);
        $this->assertEquals('only', $result[0]['method']);
    }

    public function test_detects_except_method(): void
    {
        $code = '<?php
            $data = $request->except("password");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('password', $result[0]['name']);
        $this->assertEquals('except', $result[0]['method']);
    }

    public function test_detects_post_method(): void
    {
        $code = '<?php
            $data = $request->post("content");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('content', $result[0]['name']);
        $this->assertEquals('post', $result[0]['method']);
    }

    public function test_detects_date_method(): void
    {
        $code = '<?php
            $date = $request->date("published_at");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('published_at', $result[0]['name']);
        $this->assertEquals('date', $result[0]['method']);
    }

    public function test_conditional_with_non_method_call(): void
    {
        $code = '<?php
            if ($someCondition) {
                $value = $request->input("field");
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('field', $result[0]['name']);
    }

    public function test_switch_with_non_request_variable(): void
    {
        $code = '<?php
            $type = "test";
            switch ($type) {
                case "a":
                    break;
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertEmpty($result);
    }

    public function test_match_with_non_request_variable(): void
    {
        $code = '<?php
            $type = "test";
            $result = match ($type) {
                "a" => 1,
                default => 0,
            };
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertEmpty($result);
    }

    public function test_variable_assignment_from_non_variable(): void
    {
        $code = '<?php
            $obj->property = $request->input("field");
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // Should still detect the field but not track the assignment
        $this->assertCount(1, $result);
        $this->assertEquals('field', $result[0]['name']);
    }

    public function test_parse_method_with_reflection(): void
    {
        // Create a test class with a method
        $testClass = new class
        {
            public function test_method(): void
            {
                // This is a test method
            }
        };

        $reflection = new \ReflectionMethod($testClass, 'test_method');
        $result = $this->detector->parseMethod($reflection);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function test_switch_with_existing_enum_values(): void
    {
        $code = '<?php
            $status = $request->input("status");
            if (!in_array($status, ["existing"])) {
                $status = "default";
            }
            switch ($status) {
                case "new":
                    break;
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertContains('existing', $result[0]['context']['enum_values']);
        $this->assertContains('new', $result[0]['context']['enum_values']);
    }

    public function test_match_with_existing_enum_values(): void
    {
        $code = '<?php
            $type = $request->input("type");
            if (!in_array($type, ["existing"])) {
                $type = "default";
            }
            $result = match ($type) {
                "new" => 1,
                default => 0,
            };
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertContains('existing', $result[0]['context']['enum_values']);
        $this->assertContains('new', $result[0]['context']['enum_values']);
    }

    public function test_in_array_with_direct_method_call(): void
    {
        // When in_array has a direct method call like $request->input("type"),
        // the method call is detected but enum values extraction requires
        // the parameter to already exist in detectedParams for context update.
        // Since processMethodCall and processInArrayCall are processed in
        // traversal order, the context update may not find the parameter.
        $code = '<?php
            if (in_array($request->input("type"), ["a", "b", "c"])) {
                return true;
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        // The input() call is detected
        $this->assertCount(1, $result);
        $this->assertEquals('type', $result[0]['name']);
        $this->assertEquals('input', $result[0]['method']);
    }

    public function test_switch_with_null_case(): void
    {
        $code = '<?php
            $status = $request->input("status");
            switch ($status) {
                case "active":
                    break;
                default:
                    break;
            }
        ';

        $ast = $this->parser->parse($code);
        $result = $this->detector->detectRequestCalls($ast);

        $this->assertCount(1, $result);
        $this->assertEquals('status', $result[0]['name']);
        // Only non-default cases are extracted
        $this->assertContains('active', $result[0]['context']['enum_values']);
    }
}
