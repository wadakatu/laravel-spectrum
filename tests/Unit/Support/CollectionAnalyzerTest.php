<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\CollectionAnalyzer;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;

class CollectionAnalyzerTest extends TestCase
{
    private CollectionAnalyzer $analyzer;

    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new CollectionAnalyzer;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    #[Test]
    public function it_analyzes_simple_collection_methods()
    {
        $code = '$users->toArray()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
    }

    #[Test]
    public function it_analyzes_pluck_operation()
    {
        $code = '$users->pluck("name")';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('string', $result['items']['type']);
    }

    #[Test]
    public function it_analyzes_map_operation_with_closure()
    {
        $code = '$users->map(function($user) {
            return [
                "id" => $user->id,
                "full_name" => $user->name,
                "email" => $user->email
            ];
        })';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
        $this->assertArrayHasKey('properties', $result['items']);
        $this->assertArrayHasKey('id', $result['items']['properties']);
        $this->assertArrayHasKey('full_name', $result['items']['properties']);
        $this->assertArrayHasKey('email', $result['items']['properties']);
    }

    #[Test]
    public function it_analyzes_only_operation()
    {
        $code = 'User::all()->only(["id", "name", "email"])';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
        // Since we don't have actual model data, it will just have the filtered properties
    }

    #[Test]
    public function it_analyzes_except_operation()
    {
        $code = 'User::all()->except(["password", "remember_token"])';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
    }

    #[Test]
    public function it_analyzes_first_operation()
    {
        $code = '$users->first()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // first() returns a single object, not an array
        $this->assertEquals('object', $result['type']);
    }

    #[Test]
    public function it_analyzes_key_by_operation()
    {
        $code = '$users->keyBy("id")';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // keyBy returns an object with dynamic keys
        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('additionalProperties', $result);
        $this->assertEquals('object', $result['additionalProperties']['type']);
    }

    #[Test]
    public function it_analyzes_chained_operations()
    {
        $code = 'User::all()
            ->map(function($user) {
                return [
                    "id" => $user->id,
                    "name" => $user->name,
                    "posts_count" => $user->posts()->count()
                ];
            })
            ->keyBy("id")';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // After keyBy, it should be an object
        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('additionalProperties', $result);
        $this->assertEquals('object', $result['additionalProperties']['type']);
        $this->assertArrayHasKey('properties', $result['additionalProperties']);
    }

    #[Test]
    public function it_handles_values_operation()
    {
        $code = '$users->values()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // values() resets the keys but keeps it as an array
        $this->assertEquals('array', $result['type']);
    }

    #[Test]
    public function it_analyzes_pluck_with_multiple_operations()
    {
        $code = '$users->pluck("email")->toArray()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('string', $result['items']['type']);
    }

    #[Test]
    public function it_infers_types_from_scalar_values_in_map()
    {
        $code = '$data->map(function($item) {
            return [
                "string_field" => "text",
                "int_field" => 123,
                "float_field" => 12.34,
                "bool_field" => true,
                "null_field" => null,
                "array_field" => []
            ];
        })';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
        $properties = $result['items']['properties'];

        $this->assertEquals('string', $properties['string_field']['type']);
        $this->assertEquals('integer', $properties['int_field']['type']);
        $this->assertEquals('number', $properties['float_field']['type']);
        $this->assertEquals('boolean', $properties['bool_field']['type']);
        $this->assertEquals('null', $properties['null_field']['type']);
        $this->assertEquals('array', $properties['array_field']['type']);
    }

    #[Test]
    public function it_handles_unknown_methods_gracefully()
    {
        $code = '$users->someUnknownMethod()->toArray()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // Should still return a valid schema
        $this->assertEquals('array', $result['type']);
    }

    #[Test]
    public function it_analyzes_static_model_calls()
    {
        $code = 'User::all()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
    }

    #[Test]
    public function it_analyzes_get_static_call()
    {
        $code = 'Post::get()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
    }

    #[Test]
    public function it_analyzes_map_with_arrow_function()
    {
        // Note: Arrow functions (fn() =>) are not detected as closures in current implementation
        // so they don't extract properties - only regular Closure functions are fully analyzed
        $code = '$users->map(fn($user) => ["id" => $user->id, "name" => $user->name])';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // Arrow functions return schema as-is since they're not recognized as closures
        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
    }

    #[Test]
    public function it_analyzes_filter_operation()
    {
        // filter is not explicitly handled, so it returns schema as-is (array)
        $code = '$users->filter()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
    }

    #[Test]
    public function it_analyzes_first_or_fail_operation()
    {
        $code = '$users->firstOrFail()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // firstOrFail() returns a single object
        $this->assertEquals('object', $result['type']);
    }

    #[Test]
    public function it_handles_property_fetch_gracefully()
    {
        $code = '$users';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
    }

    #[Test]
    public function it_analyzes_map_returning_scalar()
    {
        $code = '$users->map(function($user) { return $user->name; })';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
    }

    #[Test]
    public function it_analyzes_pluck_with_key()
    {
        // Note: The second argument (key) is not currently handled in applyPluckOperation
        // so pluck always returns an array regardless of whether a key is provided
        $code = '$users->pluck("name", "id")';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // Currently returns array even with key argument
        $this->assertEquals('array', $result['type']);
        $this->assertEquals('string', $result['items']['type']);
    }

    #[Test]
    public function it_handles_deeply_nested_chain()
    {
        // Chain: all() -> filter() -> map(arrow fn) -> keyBy() -> values() -> toArray()
        // - all() returns array
        // - filter() returns schema as-is (array)
        // - map(arrow fn) returns schema as-is because arrow functions aren't detected (array)
        // - keyBy() converts array to object with additionalProperties
        // - values() returns schema as-is (object)
        // - toArray() returns schema as-is (object)
        $code = 'User::all()->filter()->map(fn($u) => ["id" => $u->id])->keyBy("id")->values()->toArray()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // Final result is object because keyBy converts to object and later operations don't change it
        $this->assertEquals('object', $result['type']);
        $this->assertArrayHasKey('additionalProperties', $result);
    }

    #[Test]
    public function it_handles_unsupported_operations_gracefully()
    {
        // Operations like count(), sum(), etc. are not explicitly handled
        // They return the current schema as-is
        $code = '$users->count()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // Unknown operations keep the array type
        $this->assertEquals('array', $result['type']);
    }

    #[Test]
    public function it_analyzes_map_with_nested_arrays()
    {
        $code = '$users->map(function($user) {
            return [
                "id" => $user->id,
                "meta" => [
                    "created" => $user->created_at,
                    "updated" => $user->updated_at
                ]
            ];
        })';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
        $this->assertArrayHasKey('id', $result['items']['properties']);
        $this->assertArrayHasKey('meta', $result['items']['properties']);
    }

    #[Test]
    public function it_handles_only_with_multiple_keys()
    {
        $code = '$data->only(["id", "name", "email", "phone"])';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
        $this->assertEquals('object', $result['items']['type']);
    }

    #[Test]
    public function it_handles_except_with_multiple_keys()
    {
        $code = '$data->except(["password", "remember_token", "api_key"])';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        $this->assertEquals('array', $result['type']);
    }

    #[Test]
    public function it_chains_values_after_key_by()
    {
        $code = '$users->keyBy("id")->values()';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // keyBy converts to object, values keeps it but resets keys
        $this->assertEquals('object', $result['type']);
    }

    #[Test]
    public function it_analyzes_map_with_closure_returning_variable()
    {
        $code = '$users->map(function($user) { $data = ["id" => $user->id]; return $data; })';
        $ast = $this->parser->parse("<?php $code;")[0]->expr;

        $result = $this->analyzer->analyzeCollectionChain($ast);

        // When map returns a variable, items may be empty or generic
        $this->assertEquals('array', $result['type']);
    }
}
