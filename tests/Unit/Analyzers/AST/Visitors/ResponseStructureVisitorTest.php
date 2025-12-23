<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\AST\Visitors;

use LaravelSpectrum\Analyzers\AST\Visitors\ResponseStructureVisitor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;

class ResponseStructureVisitorTest extends TestCase
{
    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    #[Test]
    public function it_tracks_variable_assignments(): void
    {
        $code = <<<'PHP'
        <?php
        $name = 'John';
        $age = 25;
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $variables = $visitor->getVariables();

        $this->assertArrayHasKey('name', $variables);
        $this->assertArrayHasKey('age', $variables);
    }

    #[Test]
    public function it_detects_response_json_pattern(): void
    {
        $code = <<<'PHP'
        <?php
        return response()->json(['message' => 'Success']);
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('response_json', $structure['type']);
        $this->assertArrayHasKey('data', $structure);
    }

    #[Test]
    public function it_tracks_collection_operations(): void
    {
        $code = <<<'PHP'
        <?php
        $users->map(function($user) {
            return $user->name;
        });
        $users->filter('active');
        $users->pluck('id');
        $users->only(['name', 'email']);
        $users->except(['password']);
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('collection_operations', $structure);
        $this->assertCount(5, $structure['collection_operations']);

        $methods = array_column($structure['collection_operations'], 'method');
        $this->assertContains('map', $methods);
        $this->assertContains('filter', $methods);
        $this->assertContains('pluck', $methods);
        $this->assertContains('only', $methods);
        $this->assertContains('except', $methods);
    }

    #[Test]
    public function it_analyzes_array_structures(): void
    {
        $code = <<<'PHP'
        <?php
        $data = [
            'name' => 'John',
            'age' => 25,
            'active' => true,
        ];
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('array_structures', $structure);
        $this->assertNotEmpty($structure['array_structures']);

        $arrayStructure = $structure['array_structures'][0];
        $this->assertEquals('John', $arrayStructure['name']);
        $this->assertEquals(25, $arrayStructure['age']);
        $this->assertEquals('true', $arrayStructure['active']);
    }

    #[Test]
    public function it_extracts_string_values(): void
    {
        $code = <<<'PHP'
        <?php
        $message = 'Hello World';
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $variables = $visitor->getVariables();

        $this->assertArrayHasKey('message', $variables);
    }

    #[Test]
    public function it_extracts_integer_values(): void
    {
        $code = <<<'PHP'
        <?php
        $count = 42;
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $variables = $visitor->getVariables();

        $this->assertArrayHasKey('count', $variables);
    }

    #[Test]
    public function it_extracts_float_values(): void
    {
        $code = <<<'PHP'
        <?php
        $price = 19.99;
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $variables = $visitor->getVariables();

        $this->assertArrayHasKey('price', $variables);
    }

    #[Test]
    public function it_returns_empty_structure_for_empty_code(): void
    {
        $code = <<<'PHP'
        <?php
        // Empty file
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();
        $variables = $visitor->getVariables();

        $this->assertEmpty($structure);
        $this->assertEmpty($variables);
    }

    #[Test]
    public function it_handles_nested_array_in_response_json(): void
    {
        $code = <<<'PHP'
        <?php
        return response()->json([
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
            'status' => 'success',
        ]);
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('response_json', $structure['type']);
        $this->assertArrayHasKey('data', $structure);
    }

    #[Test]
    public function it_handles_response_json_without_data(): void
    {
        $code = <<<'PHP'
        <?php
        return response()->json();
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('response_json', $structure['type']);
        $this->assertArrayNotHasKey('data', $structure);
    }

    #[Test]
    public function it_ignores_non_response_method_calls(): void
    {
        $code = <<<'PHP'
        <?php
        $service->json(['data' => 'value']);
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayNotHasKey('type', $structure);
    }

    #[Test]
    public function it_handles_method_call_without_identifier_name(): void
    {
        $code = <<<'PHP'
        <?php
        $obj->$dynamicMethod();
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        // Should not crash and return empty structure
        $this->assertEmpty($structure);
    }

    #[Test]
    public function it_resolves_variable_references_in_arrays(): void
    {
        $code = <<<'PHP'
        <?php
        $name = 'John';
        $data = [
            'name' => $name,
        ];
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('array_structures', $structure);
        // The variable reference should be resolved to its value
        $this->assertEquals('John', $structure['array_structures'][0]['name']);
    }

    #[Test]
    public function it_handles_collection_operation_with_string_argument(): void
    {
        $code = <<<'PHP'
        <?php
        $users->pluck('email');
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('collection_operations', $structure);
        $this->assertEquals('pluck', $structure['collection_operations'][0]['method']);
        $this->assertEquals(['email'], $structure['collection_operations'][0]['args']);
    }

    #[Test]
    public function it_handles_arrays_without_keys(): void
    {
        $code = <<<'PHP'
        <?php
        $items = ['apple', 'banana', 'cherry'];
        PHP;

        $visitor = new ResponseStructureVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('array_structures', $structure);
        // Items without keys should not be added to the structure
        $this->assertEmpty($structure['array_structures'][0]);
    }
}
