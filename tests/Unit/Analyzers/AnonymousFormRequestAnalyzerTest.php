<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;

class AnonymousFormRequestAnalyzerTest extends TestCase
{
    private InlineValidationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new InlineValidationAnalyzer(
            new TypeInference(new NodeTraverser, new Standard),
            new EnumAnalyzer,
            new FileUploadAnalyzer
        );
    }

    public function test_it_detects_anonymous_form_request_validation()
    {
        $code = '<?php
            class TestController {
                public function store(Request $request) {
                    $validated = $request->validate(
                        (new class extends FormRequest {
                            public function rules() {
                                return [
                                    "name" => "required|string|max:255",
                                    "email" => "required|email|unique:users",
                                    "age" => "required|integer|min:18",
                                ];
                            }
                        })->rules()
                    );
                    
                    return response()->json($validated);
                }
            }
        ';

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $ast[0]->stmts[0];
        $result = $this->analyzer->analyze($method);

        $expected = [
            'rules' => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'age' => 'required|integer|min:18',
            ],
            'messages' => [],
            'attributes' => [],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_it_detects_anonymous_form_request_with_messages()
    {
        $code = '<?php
            class TestController {
                public function store(Request $request) {
                    $validated = $request->validate(
                        (new class extends FormRequest {
                            public function rules() {
                                return [
                                    "name" => "required|string",
                                    "email" => "required|email",
                                ];
                            }
                            
                            public function messages() {
                                return [
                                    "name.required" => "Name is required",
                                    "email.required" => "Email is required",
                                ];
                            }
                        })->rules()
                    );
                    
                    return response()->json($validated);
                }
            }
        ';

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $ast[0]->stmts[0];
        $result = $this->analyzer->analyze($method);

        $expected = [
            'rules' => [
                'name' => 'required|string',
                'email' => 'required|email',
            ],
            'messages' => [
                'name.required' => 'Name is required',
                'email.required' => 'Email is required',
            ],
            'attributes' => [],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_it_detects_anonymous_form_request_with_attributes()
    {
        $code = '<?php
            class TestController {
                public function store(Request $request) {
                    $validated = $request->validate(
                        (new class extends FormRequest {
                            public function rules() {
                                return [
                                    "user_name" => "required|string",
                                    "user_email" => "required|email",
                                ];
                            }
                            
                            public function attributes() {
                                return [
                                    "user_name" => "Full Name",
                                    "user_email" => "Email Address",
                                ];
                            }
                        })->rules()
                    );
                    
                    return response()->json($validated);
                }
            }
        ';

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $ast[0]->stmts[0];
        $result = $this->analyzer->analyze($method);

        $expected = [
            'rules' => [
                'user_name' => 'required|string',
                'user_email' => 'required|email',
            ],
            'messages' => [],
            'attributes' => [
                'user_name' => 'Full Name',
                'user_email' => 'Email Address',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_it_detects_anonymous_form_request_with_array_rules()
    {
        $code = '<?php
            class TestController {
                public function store(Request $request) {
                    $validated = $request->validate(
                        (new class extends FormRequest {
                            public function rules() {
                                return [
                                    "name" => ["required", "string", "max:255"],
                                    "email" => ["required", "email", "unique:users"],
                                ];
                            }
                        })->rules()
                    );
                    
                    return response()->json($validated);
                }
            }
        ';

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $ast[0]->stmts[0];
        $result = $this->analyzer->analyze($method);

        $expected = [
            'rules' => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users'],
            ],
            'messages' => [],
            'attributes' => [],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_it_ignores_anonymous_class_not_extending_form_request()
    {
        $code = '<?php
            class TestController {
                public function store(Request $request) {
                    $validated = $request->validate(
                        (new class {
                            public function rules() {
                                return [
                                    "name" => "required|string",
                                ];
                            }
                        })->rules()
                    );
                    
                    return response()->json($validated);
                }
            }
        ';

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $ast[0]->stmts[0];
        $result = $this->analyzer->analyze($method);

        // Should return empty array since the anonymous class doesn't extend FormRequest
        $this->assertEquals([], $result);
    }

    public function test_it_combines_anonymous_form_request_with_other_validations()
    {
        $code = '<?php
            class TestController {
                public function store(Request $request) {
                    // First validation - inline
                    $request->validate([
                        "id" => "required|integer",
                    ]);
                    
                    // Second validation - anonymous FormRequest
                    $validated = $request->validate(
                        (new class extends FormRequest {
                            public function rules() {
                                return [
                                    "name" => "required|string",
                                    "email" => "required|email",
                                ];
                            }
                        })->rules()
                    );
                    
                    return response()->json($validated);
                }
            }
        ';

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $ast[0]->stmts[0];
        $result = $this->analyzer->analyze($method);

        // Since mergeValidations is called, both validation rules should be merged
        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('id', $result['rules']);
        $this->assertArrayHasKey('name', $result['rules']);
        $this->assertArrayHasKey('email', $result['rules']);
        $this->assertEquals('required|integer', $result['rules']['id']);
        $this->assertEquals('required|string', $result['rules']['name']);
        $this->assertEquals('required|email', $result['rules']['email']);
    }
}
