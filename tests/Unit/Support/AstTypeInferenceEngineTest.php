<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\AstTypeInferenceEngine;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\Node;

class AstTypeInferenceEngineTest extends TestCase
{
    private AstTypeInferenceEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AstTypeInferenceEngine;
    }

    public function test_infer_string_scalar(): void
    {
        $node = new Node\Scalar\String_('hello');

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'string'], $result);
    }

    public function test_infer_integer_scalar(): void
    {
        $node = new Node\Scalar\Int_(42);

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_infer_float_scalar(): void
    {
        $node = new Node\Scalar\Float_(3.14);

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'number'], $result);
    }

    public function test_infer_boolean_true(): void
    {
        $node = new Node\Expr\ConstFetch(new Node\Name('true'));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'boolean'], $result);
    }

    public function test_infer_boolean_false(): void
    {
        $node = new Node\Expr\ConstFetch(new Node\Name('false'));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'boolean'], $result);
    }

    public function test_infer_null(): void
    {
        $node = new Node\Expr\ConstFetch(new Node\Name('null'));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'null'], $result);
    }

    public function test_infer_integer_cast(): void
    {
        $node = new Node\Expr\Cast\Int_(new Node\Scalar\String_('42'));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_infer_string_cast(): void
    {
        $node = new Node\Expr\Cast\String_(new Node\Scalar\Int_(42));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'string'], $result);
    }

    public function test_infer_boolean_cast(): void
    {
        $node = new Node\Expr\Cast\Bool_(new Node\Scalar\Int_(1));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'boolean'], $result);
    }

    public function test_infer_array_cast(): void
    {
        $node = new Node\Expr\Cast\Array_(new Node\Scalar\String_('test'));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'array'], $result);
    }

    public function test_infer_double_cast(): void
    {
        $node = new Node\Expr\Cast\Double(new Node\Scalar\String_('3.14'));

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'number'], $result);
    }

    public function test_infer_sequential_array(): void
    {
        $node = new Node\Expr\Array_([
            new Node\Expr\ArrayItem(new Node\Scalar\String_('a')),
            new Node\Expr\ArrayItem(new Node\Scalar\String_('b')),
        ]);

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'array'], $result);
    }

    public function test_infer_associative_array(): void
    {
        $node = new Node\Expr\Array_([
            new Node\Expr\ArrayItem(
                new Node\Scalar\String_('value1'),
                new Node\Scalar\String_('key1')
            ),
            new Node\Expr\ArrayItem(
                new Node\Scalar\Int_(42),
                new Node\Scalar\String_('key2')
            ),
        ]);

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('object', $result['type']);
        $this->assertArrayHasKey('properties', $result);
        $this->assertSame(['type' => 'string'], $result['properties']['key1']);
        $this->assertSame(['type' => 'integer'], $result['properties']['key2']);
    }

    public function test_infer_property_fetch_id(): void
    {
        $node = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('model'),
            new Node\Identifier('id')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('integer', $result['type']);
    }

    public function test_infer_property_fetch_email(): void
    {
        $node = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('user'),
            new Node\Identifier('email')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('string', $result['type']);
        $this->assertSame('email', $result['format']);
    }

    public function test_infer_property_fetch_created_at(): void
    {
        $node = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('model'),
            new Node\Identifier('created_at')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('string', $result['type']);
        $this->assertSame('date-time', $result['format']);
    }

    public function test_infer_property_fetch_is_active(): void
    {
        $node = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('user'),
            new Node\Identifier('is_active')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('boolean', $result['type']);
    }

    public function test_infer_method_call_to_iso8601_string(): void
    {
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('date'),
            new Node\Identifier('toIso8601String')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('string', $result['type']);
        $this->assertSame('date-time', $result['format']);
    }

    public function test_infer_method_call_to_array(): void
    {
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('collection'),
            new Node\Identifier('toArray')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'array'], $result);
    }

    public function test_infer_method_call_count(): void
    {
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('collection'),
            new Node\Identifier('count')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_infer_method_call_is_prefix(): void
    {
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('user'),
            new Node\Identifier('isAdmin')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'boolean'], $result);
    }

    public function test_infer_method_call_has_prefix(): void
    {
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('user'),
            new Node\Identifier('hasPermission')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'boolean'], $result);
    }

    public function test_infer_func_call_json_decode(): void
    {
        $node = new Node\Expr\FuncCall(
            new Node\Name('json_decode'),
            [new Node\Arg(new Node\Scalar\String_('{}'))]
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'array'], $result);
    }

    public function test_infer_func_call_count(): void
    {
        $node = new Node\Expr\FuncCall(
            new Node\Name('count'),
            [new Node\Arg(new Node\Expr\Variable('array'))]
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_infer_ternary_with_if_part(): void
    {
        $node = new Node\Expr\Ternary(
            new Node\Expr\Variable('condition'),
            new Node\Scalar\Int_(1),
            new Node\Scalar\String_('fallback')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_infer_ternary_elvis_operator(): void
    {
        $node = new Node\Expr\Ternary(
            new Node\Scalar\Int_(42),
            null,
            new Node\Scalar\String_('fallback')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_infer_null_coalesce(): void
    {
        $node = new Node\Expr\BinaryOp\Coalesce(
            new Node\Scalar\Int_(42),
            new Node\Scalar\String_('fallback')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_infer_type_string_returns_string(): void
    {
        $node = new Node\Scalar\Int_(42);

        $result = $this->engine->inferTypeString($node);

        $this->assertSame('integer', $result);
    }

    public function test_infer_only_method_with_fields(): void
    {
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('user'),
            new Node\Identifier('only'),
            [
                new Node\Arg(
                    new Node\Expr\Array_([
                        new Node\Expr\ArrayItem(new Node\Scalar\String_('id')),
                        new Node\Expr\ArrayItem(new Node\Scalar\String_('email')),
                        new Node\Expr\ArrayItem(new Node\Scalar\String_('name')),
                    ])
                ),
            ]
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('object', $result['type']);
        $this->assertArrayHasKey('properties', $result);
        $this->assertSame('integer', $result['properties']['id']['type']);
        $this->assertSame('string', $result['properties']['email']['type']);
        $this->assertSame('email', $result['properties']['email']['format']);
    }

    public function test_infer_nested_array(): void
    {
        $node = new Node\Expr\Array_([
            new Node\Expr\ArrayItem(
                new Node\Expr\Array_([
                    new Node\Expr\ArrayItem(
                        new Node\Scalar\String_('nested'),
                        new Node\Scalar\String_('key')
                    ),
                ]),
                new Node\Scalar\String_('data')
            ),
        ]);

        $result = $this->engine->inferFromNode($node);

        $this->assertSame('object', $result['type']);
        $this->assertArrayHasKey('properties', $result);
        $this->assertSame('object', $result['properties']['data']['type']);
        $this->assertArrayHasKey('properties', $result['properties']['data']);
    }

    public function test_infer_unknown_node_defaults_to_string(): void
    {
        // Use a node that's not explicitly handled
        $node = new Node\Expr\Variable('unknown');

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'string'], $result);
    }

    public function test_infer_method_call_can_prefix(): void
    {
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('user'),
            new Node\Identifier('canEdit')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'boolean'], $result);
    }

    public function test_infer_dynamic_property_fetch_defaults_to_string(): void
    {
        // Dynamic property access like $obj->$propertyName
        $node = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('obj'),
            new Node\Expr\Variable('propertyName')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'string'], $result);
    }

    public function test_infer_dynamic_method_call_defaults_to_string(): void
    {
        // Dynamic method call like $obj->$methodName()
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('obj'),
            new Node\Expr\Variable('methodName')
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'string'], $result);
    }

    public function test_infer_only_method_with_non_array_argument(): void
    {
        // only() called with a variable instead of array literal
        $node = new Node\Expr\MethodCall(
            new Node\Expr\Variable('model'),
            new Node\Identifier('only'),
            [new Node\Arg(new Node\Expr\Variable('fields'))]
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'object', 'properties' => []], $result);
    }

    public function test_infer_func_call_json_encode(): void
    {
        $node = new Node\Expr\FuncCall(
            new Node\Name('json_encode'),
            [new Node\Arg(new Node\Expr\Variable('data'))]
        );

        $result = $this->engine->inferFromNode($node);

        $this->assertSame(['type' => 'string'], $result);
    }
}
