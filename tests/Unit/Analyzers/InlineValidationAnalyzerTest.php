<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_handles_closure_validation_rules()
    {
        $code = '<?php
        use Illuminate\Support\Facades\Validator;
        
        class UserController {
            public function store($request) {
                $validator = Validator::make($request->all(), [
                    "username" => [
                        "required",
                        "string",
                        "min:3",
                        function ($attribute, $value, $fail) {
                            if (strtolower($value) === "admin") {
                                $fail("The username cannot be admin.");
                            }
                        }
                    ],
                    "age" => [
                        "required",
                        "integer",
                        function ($attribute, $value, $fail) {
                            if ($value < 18) {
                                $fail("You must be at least 18 years old.");
                            }
                        }
                    ],
                    "email" => ["required", "email"]
                ]);
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'store');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('rules', $result);

        // Check username rules
        $this->assertIsArray($result['rules']['username']);
        $this->assertContains('required', $result['rules']['username']);
        $this->assertContains('string', $result['rules']['username']);
        $this->assertContains('min:3', $result['rules']['username']);
        // Closure should be detected but represented as a custom rule
        $hasCustomRule = false;
        foreach ($result['rules']['username'] as $rule) {
            if (is_string($rule) && strpos($rule, 'custom:') === 0) {
                $hasCustomRule = true;
                break;
            }
        }
        $this->assertTrue($hasCustomRule, 'Closure rule should be detected as custom rule');

        // Check age rules
        $this->assertIsArray($result['rules']['age']);
        $this->assertContains('required', $result['rules']['age']);
        $this->assertContains('integer', $result['rules']['age']);

        // Check email rules
        $this->assertIsArray($result['rules']['email']);
        $this->assertContains('required', $result['rules']['email']);
        $this->assertContains('email', $result['rules']['email']);
    }

    #[Test]
    public function it_handles_mixed_string_and_closure_rules()
    {
        $code = '<?php
        class UserController {
            public function update($request) {
                $this->validate($request, [
                    "password" => [
                        "sometimes",
                        "string",
                        "min:8",
                        function ($attribute, $value, $fail) use ($request) {
                            if ($value === $request->old_password) {
                                $fail("New password must be different from old password.");
                            }
                        },
                        "confirmed"
                    ]
                ]);
            }
        }';

        $ast = $this->parser->parse($code);
        $method = $this->findMethod($ast, 'update');

        $result = $this->analyzer->analyze($method);

        $this->assertArrayHasKey('rules', $result);
        $this->assertIsArray($result['rules']['password']);
        $this->assertContains('sometimes', $result['rules']['password']);
        $this->assertContains('string', $result['rules']['password']);
        $this->assertContains('min:8', $result['rules']['password']);
        $this->assertContains('confirmed', $result['rules']['password']);

        // Verify closure is detected
        $hasCustomRule = false;
        foreach ($result['rules']['password'] as $rule) {
            if (is_string($rule) && strpos($rule, 'custom:') === 0) {
                $hasCustomRule = true;
                break;
            }
        }
        $this->assertTrue($hasCustomRule, 'Closure rule should be detected');
    }

    #[Test]
    public function it_generates_parameters_with_closure_rules()
    {
        $validation = [
            'rules' => [
                'code' => [
                    'required',
                    'string',
                    'size:6',
                    'custom:closure_validation',
                ],
            ],
            'attributes' => [
                'code' => 'Verification Code',
            ],
        ];

        $parameters = $this->analyzer->generateParameters($validation);

        $this->assertCount(1, $parameters);

        $codeParam = $parameters[0];
        $this->assertEquals('code', $codeParam['name']);
        $this->assertEquals('string', $codeParam['type']);
        $this->assertTrue($codeParam['required']);
        // size:6 should set both minLength and maxLength
        $this->assertArrayHasKey('minLength', $codeParam);
        $this->assertArrayHasKey('maxLength', $codeParam);
        $this->assertEquals(6, $codeParam['minLength']);
        $this->assertEquals(6, $codeParam['maxLength']);
        $this->assertStringContainsString('Verification Code', $codeParam['description']);
        $this->assertStringContainsString('Custom validation applied', $codeParam['description']);
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
