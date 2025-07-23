<?php

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\Node;
use PhpParser\ParserFactory;

class LumenEnumIntegrationTest extends TestCase
{
    private EnumAnalyzer $enumAnalyzer;

    private InlineValidationAnalyzer $inlineValidationAnalyzer;

    private SchemaGenerator $schemaGenerator;

    private $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enumAnalyzer = new EnumAnalyzer;
        $typeInference = new TypeInference;

        $this->inlineValidationAnalyzer = new InlineValidationAnalyzer($typeInference, $this->enumAnalyzer);
        $this->schemaGenerator = new SchemaGenerator;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function test_lumen_validate_method_with_enum(): void
    {
        // Lumenコントローラーのコードをシミュレート
        $code = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\UserTypeEnum;

class TestController extends Controller
{
    public function store(Request $request)
    {
        $this->validate($request, [
            'status' => ['required', Rule::enum(StatusEnum::class)],
            'type' => 'required|enum:' . UserTypeEnum::class,
            'name' => 'required|string|max:255'
        ]);
        
        // Store logic here
    }
}
PHP;

        // Parse the code
        $ast = $this->parser->parse($code);

        // Find the store method
        $method = $this->findMethodInAst($ast, 'store');
        $this->assertNotNull($method);

        // Extract use statements
        $useStatements = $this->extractUseStatements($ast);

        // Analyze inline validation
        $validation = $this->inlineValidationAnalyzer->analyze($method);

        // Generate parameters with namespace context
        $parameters = $this->inlineValidationAnalyzer->generateParameters($validation, 'App\\Http\\Controllers', $useStatements);

        // Generate schema
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        // Verify status field has enum
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['status']['type']);
        $this->assertArrayHasKey('enum', $schema['properties']['status']);
        $this->assertEquals(['active', 'inactive', 'pending'], $schema['properties']['status']['enum']);

        // Verify type field has enum
        $this->assertArrayHasKey('type', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['type']['type']);
        $this->assertArrayHasKey('enum', $schema['properties']['type']);
        $this->assertEquals(['admin', 'user', 'guest'], $schema['properties']['type']['enum']);

        // Verify regular field
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayNotHasKey('enum', $schema['properties']['name']);
    }

    public function test_lumen_validate_with_array_syntax(): void
    {
        // Lumenコントローラーで配列構文を使用
        $code = <<<'PHP'
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;

class TestController extends Controller
{
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'status' => [
                'sometimes',
                'required',
                new Enum(StatusEnum::class)
            ]
        ]);
        
        // Update logic here
    }
}
PHP;

        // Parse the code
        $ast = $this->parser->parse($code);

        // Find the update method
        $method = $this->findMethodInAst($ast, 'update');
        $this->assertNotNull($method);

        // Extract use statements
        $useStatements = $this->extractUseStatements($ast);

        // Analyze inline validation
        $validation = $this->inlineValidationAnalyzer->analyze($method);

        // Generate parameters with namespace context
        $parameters = $this->inlineValidationAnalyzer->generateParameters($validation, 'App\\Http\\Controllers', $useStatements);

        // Find status parameter
        $statusParam = null;
        foreach ($parameters as $param) {
            if ($param['name'] === 'status') {
                $statusParam = $param;
                break;
            }
        }

        $this->assertNotNull($statusParam);
        $this->assertArrayHasKey('enum', $statusParam);
        $this->assertEquals(['active', 'inactive', 'pending'], $statusParam['enum']['values']);
        $this->assertEquals('string', $statusParam['enum']['type']);
    }

    private function findMethodInAst(array $ast, string $methodName): ?Node\Stmt\ClassMethod
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_) {
                        foreach ($stmt->stmts as $classStmt) {
                            if ($classStmt instanceof Node\Stmt\ClassMethod &&
                                $classStmt->name->toString() === $methodName) {
                                return $classStmt;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private function extractUseStatements(array $ast): array
    {
        $useStatements = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                            $useStatements[$alias] = $use->name->toString();
                        }
                    }
                }
            }
        }

        return $useStatements;
    }
}
