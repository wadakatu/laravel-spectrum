<?php

namespace LaravelPrism\Tests\Unit\Analyzers;

use LaravelPrism\Analyzers\InlineValidationAnalyzer;
use LaravelPrism\Support\TypeInference;
use LaravelPrism\Tests\TestCase;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class InlineValidationAnalyzerTest extends TestCase
{
    private Parser $parser;

    private InlineValidationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->analyzer = new InlineValidationAnalyzer(new TypeInference);
    }

    /** @test */
    public function it_analyzes_basic_inline_validation()
    {
        $code = '<?php
        class UserController {
            public function store($request) {
                $this->validate($request, [
                    "name" => "required|string|max:255",
                    "email" => "required|email|unique:users"
                ]);
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'store');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('rules', $result);
        $this->assertEquals('required|string|max:255', $result['rules']['name']);
        $this->assertEquals('required|email|unique:users', $result['rules']['email']);
    }

    /** @test */
    public function it_handles_array_format_rules()
    {
        $code = '<?php
        class UserController {
            public function store($request) {
                $this->validate($request, [
                    "name" => ["required", "string", "max:255"],
                    "email" => ["required", "email", "unique:users,email"]
                ]);
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'store');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('rules', $result);
        $this->assertEquals(['required', 'string', 'max:255'], $result['rules']['name']);
        $this->assertEquals(['required', 'email', 'unique:users,email'], $result['rules']['email']);
    }

    /** @test */
    public function it_handles_custom_messages()
    {
        $code = '<?php
        class UserController {
            public function store($request) {
                $this->validate($request, [
                    "name" => "required|string",
                    "email" => "required|email"
                ], [
                    "name.required" => "お名前は必須です",
                    "email.required" => "メールアドレスは必須です"
                ]);
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'store');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('messages', $result);
        $this->assertEquals('お名前は必須です', $result['messages']['name.required']);
        $this->assertEquals('メールアドレスは必須です', $result['messages']['email.required']);
    }

    /** @test */
    public function it_handles_custom_attributes()
    {
        $code = '<?php
        class UserController {
            public function store($request) {
                $this->validate($request, [
                    "name" => "required|string"
                ], [], [
                    "name" => "Full Name"
                ]);
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'store');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('attributes', $result);
        $this->assertEquals('Full Name', $result['attributes']['name']);
    }

    /** @test */
    public function it_analyzes_validator_make()
    {
        $code = '<?php
        use Illuminate\Support\Facades\Validator;
        
        class UserController {
            public function store($request) {
                $validator = Validator::make($request->all(), [
                    "name" => "required|string",
                    "email" => "required|email"
                ]);
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'store');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('rules', $result);
        $this->assertEquals('required|string', $result['rules']['name']);
        $this->assertEquals('required|email', $result['rules']['email']);
    }

    /** @test */
    public function it_merges_multiple_validations()
    {
        $code = '<?php
        class UserController {
            public function store($request) {
                $this->validate($request, [
                    "name" => "required|string"
                ]);
                
                if ($request->has("email")) {
                    $this->validate($request, [
                        "email" => "required|email"
                    ]);
                }
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'store');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('rules', $result);
        $this->assertEquals('required|string', $result['rules']['name']);
        $this->assertEquals('required|email', $result['rules']['email']);
    }

    /** @test */
    public function it_generates_parameters_from_validation()
    {
        $validation = [
            'rules' => [
                'name' => 'required|string|max:255',
                'age' => 'required|integer|min:18|max:100',
                'status' => 'required|in:active,inactive',
            ],
            'attributes' => [
                'name' => 'Full Name',
            ],
        ];

        $parameters = $this->analyzer->generateParameters($validation);

        $this->assertCount(3, $parameters);

        // Check name parameter
        $nameParam = $parameters[0];
        $this->assertEquals('name', $nameParam['name']);
        $this->assertEquals('string', $nameParam['type']);
        $this->assertTrue($nameParam['required']);
        $this->assertEquals(255, $nameParam['maxLength']);
        $this->assertStringContainsString('Full Name', $nameParam['description']);

        // Check age parameter
        $ageParam = $parameters[1];
        $this->assertEquals('age', $ageParam['name']);
        $this->assertEquals('integer', $ageParam['type']);
        $this->assertTrue($ageParam['required']);
        $this->assertEquals(18, $ageParam['minimum']);
        $this->assertEquals(100, $ageParam['maximum']);

        // Check status parameter
        $statusParam = $parameters[2];
        $this->assertEquals('status', $statusParam['name']);
        $this->assertEquals('string', $statusParam['type']);
        $this->assertTrue($statusParam['required']);
        $this->assertEquals(['active', 'inactive'], $statusParam['enum']);
    }

    /** @test */
    public function it_handles_conditional_required_rules()
    {
        $validation = [
            'rules' => [
                'email' => 'required_if:contact_method,email|email',
                'phone' => 'required_unless:contact_method,email|string',
            ],
        ];

        $parameters = $this->analyzer->generateParameters($validation);

        $this->assertTrue($parameters[0]['required']); // required_if is considered required
        $this->assertTrue($parameters[1]['required']); // required_unless is considered required
    }

    /** @test */
    public function it_returns_empty_array_for_methods_without_validation()
    {
        $code = '<?php
        class UserController {
            public function index() {
                return User::all();
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'index');

        $result = $this->analyzer->analyze($method);

        $this->assertEmpty($result);
    }

    private function findMethod(array $ast, string $methodName): ?ClassMethod
    {
        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Class_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof ClassMethod && $stmt->name->name === $methodName) {
                        return $stmt;
                    }
                }
            }
        }

        return null;
    }
}
