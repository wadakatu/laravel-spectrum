<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\AST\Visitors;

use LaravelSpectrum\Analyzers\AST\Visitors\RulesExtractorVisitor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\Test;

class RulesExtractorVisitorTest extends TestCase
{
    private $parser;

    private $printer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->printer = new Standard;
    }

    #[Test]
    public function it_extracts_simple_array_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users',
                    'age' => 'required|integer|min:18',
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('required|string|max:255', $rules['name']);
        $this->assertEquals('required|email|unique:users', $rules['email']);
        $this->assertEquals('required|integer|min:18', $rules['age']);
    }

    #[Test]
    public function it_extracts_array_syntax_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'username' => ['required', 'string', 'min:3', 'max:20'],
                    'password' => ['required', 'string', 'min:8', 'confirmed'],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['username']);
        $this->assertEquals(['required', 'string', 'min:3', 'max:20'], $rules['username']);
        $this->assertIsArray($rules['password']);
        $this->assertEquals(['required', 'string', 'min:8', 'confirmed'], $rules['password']);
    }

    #[Test]
    public function it_extracts_rules_from_variables()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $rules = [
                    'title' => 'required|string|max:100',
                    'content' => 'required|string',
                ];
                
                return $rules;
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('required|string|max:100', $rules['title']);
        $this->assertEquals('required|string', $rules['content']);
    }

    #[Test]
    public function it_extracts_array_merge_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $baseRules = [
                    'name' => 'required|string',
                ];
                
                return array_merge($baseRules, [
                    'email' => 'required|email',
                    'password' => 'required|min:8',
                ]);
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertEquals('required|string', $rules['name']);
        $this->assertEquals('required|email', $rules['email']);
        $this->assertEquals('required|min:8', $rules['password']);
    }

    #[Test]
    public function it_extracts_rule_in_static_calls()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;
        
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => ['required', Rule::in(['active', 'inactive', 'pending'])],
                    'role' => ['required', Rule::in(['admin', 'user'])],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['status']);
        $this->assertContains('required', $rules['status']);
        $this->assertContains('in:active,inactive,pending', $rules['status']);

        $this->assertIsArray($rules['role']);
        $this->assertContains('in:admin,user', $rules['role']);
    }

    #[Test]
    public function it_extracts_rule_unique_static_calls()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;
        
        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => ['required', 'email', Rule::unique('users')],
                    'username' => Rule::unique('users', 'username'),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['email']);
        $this->assertContains('unique:users', $rules['email']);
        $this->assertEquals('unique:users:username', $rules['username']);
    }

    #[Test]
    public function it_extracts_rule_exists_static_calls()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;
        
        class TestRequest {
            public function rules(): array
            {
                return [
                    'category_id' => ['required', Rule::exists('categories', 'id')],
                    'user_id' => Rule::exists('users'),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['category_id']);
        $this->assertContains('exists:categories:id', $rules['category_id']);
        $this->assertEquals('exists:users', $rules['user_id']);
    }

    #[Test]
    public function it_extracts_rule_required_if_static_calls()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;
        
        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => Rule::requiredIf('type', 'email'),
                    'phone' => ['nullable', Rule::requiredIf('type', 'phone')],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('required_if:type:email', $rules['email']);
        $this->assertIsArray($rules['phone']);
        $this->assertContains('nullable', $rules['phone']);
        $this->assertContains('required_if:type:phone', $rules['phone']);
    }

    #[Test]
    public function it_extracts_enum_rules()
    {
        $code = <<<'PHP'
        <?php
        use App\Enums\StatusEnum;
        use App\Enums\RoleEnum;
        use Illuminate\Validation\Rule;
        use Illuminate\Validation\Rules\Enum;
        
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => ['required', Rule::enum(StatusEnum::class)],
                    'role' => ['required', new Enum(RoleEnum::class)],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['status']);
        $this->assertContains('required', $rules['status']);
        $this->assertIsArray($rules['status'][1]);
        $this->assertEquals('enum', $rules['status'][1]['type']);
        $this->assertEquals('StatusEnum', $rules['status'][1]['class']);

        $this->assertIsArray($rules['role']);
        $this->assertContains('required', $rules['role']);
        $this->assertIsArray($rules['role'][1]);
        $this->assertEquals('enum', $rules['role'][1]['type']);
        $this->assertEquals('RoleEnum', $rules['role'][1]['class']);
    }

    #[Test]
    public function it_handles_method_chains_on_rules()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;
        
        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => Rule::unique('users')->ignore($this->user()->id),
                    'slug' => Rule::unique('posts')->where('status', 'published'),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // メソッドチェーンは基本ルールに簡略化される
        $this->assertEquals('unique:users', $rules['email']);
        $this->assertEquals('unique:posts', $rules['slug']);
    }

    #[Test]
    public function it_handles_dynamic_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $this->isMethod('POST') ? $this->postRules() : $this->putRules();
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // 動的ルールは空の配列として処理される（三項演算子は未対応）
        $this->assertEmpty($rules);
    }

    #[Test]
    public function it_handles_concatenated_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $maxLength = 255;
                return [
                    'name' => 'required|string|max:' . $maxLength,
                    'email' => 'required|' . 'email|' . 'unique:users',
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // 連結された文字列は評価される
        $this->assertEquals('required|email|unique:users', $rules['email']);
        // 変数を含む連結は式として保存される
        $this->assertStringContainsString('required|string|max:', $rules['name']);
    }

    #[Test]
    public function it_handles_match_expressions()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return match($this->input('type')) {
                    'email' => [
                        'contact' => 'required|email',
                    ],
                    'phone' => [
                        'contact' => 'required|regex:/^[0-9]{10}$/',
                    ],
                    default => [
                        'contact' => 'required|string',
                    ],
                };
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // match式の最初のアームのルールが抽出される
        $this->assertArrayHasKey('contact', $rules);
        $this->assertEquals('required|email', $rules['contact']);
    }

    #[Test]
    public function it_handles_multiple_array_merges()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $baseRules = ['name' => 'required|string'];
                $additionalRules = ['email' => 'required|email'];

                return array_merge($baseRules, $additionalRules, [
                    'password' => 'required|min:8',
                ]);
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertCount(3, $rules);
        $this->assertEquals('required|string', $rules['name']);
        $this->assertEquals('required|email', $rules['email']);
        $this->assertEquals('required|min:8', $rules['password']);
    }

    #[Test]
    public function it_handles_integer_keys_in_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    0 => 'required|string',
                    1 => 'required|integer',
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('required|string', $rules['0']);
        $this->assertEquals('required|integer', $rules['1']);
    }

    #[Test]
    public function it_handles_empty_rule_in()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => Rule::in([]),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('in:', $rules['status']);
    }

    #[Test]
    public function it_handles_rule_in_with_integers()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'priority' => Rule::in([1, 2, 3]),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('in:1,2,3', $rules['priority']);
    }

    #[Test]
    public function it_handles_unknown_rule_static_methods()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'dimensions' => Rule::dimensions(),
                    'password' => Rule::password(),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Unknown Rule methods return method name
        $this->assertEquals('dimensions', $rules['dimensions']);
        $this->assertEquals('password', $rules['password']);
    }

    #[Test]
    public function it_handles_return_from_unknown_variable()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $unknownVar;
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertArrayHasKey('_notice', $rules);
        $this->assertStringContainsString('Dynamic rules detected', $rules['_notice']);
    }

    #[Test]
    public function it_handles_method_call_in_variable_assignment()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $additionalRules = $this->additionalRules();

                return array_merge(['name' => 'required'], $additionalRules);
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Method call result is marked as dynamic
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('_dynamic', $rules);
    }

    #[Test]
    public function it_handles_nested_method_chains()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => Rule::unique('users')->ignore(1)->where('active', true),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Nested chains still extract base rule
        $this->assertEquals('unique:users', $rules['email']);
    }

    #[Test]
    public function it_handles_complex_return_expressions()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $this->getRulesFromService();
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertArrayHasKey('_notice', $rules);
        $this->assertStringContainsString('Complex rules detected', $rules['_notice']);
    }

    #[Test]
    public function it_handles_match_expression_with_non_array_body()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return match($this->input('type')) {
                    'email' => $this->getEmailRules(),
                    default => $this->getDefaultRules(),
                };
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertArrayHasKey('_notice', $rules);
        $this->assertStringContainsString('Match expression detected', $rules['_notice']);
    }

    #[Test]
    public function it_handles_non_string_static_class_call()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => SomeClass::someMethod(),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Non-Rule static calls are stringified
        $this->assertStringContainsString('SomeClass::someMethod()', $rules['status']);
    }

    #[Test]
    public function it_handles_new_expression_for_non_enum_class()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'custom' => new CustomRule(),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Non-Enum new expressions return structured format for custom rule analysis
        $this->assertIsArray($rules['custom']);
        $this->assertSame('custom_rule', $rules['custom']['type']);
        $this->assertSame('CustomRule', $rules['custom']['class']);
        $this->assertSame([], $rules['custom']['args']);
    }

    #[Test]
    public function it_handles_custom_rule_with_constructor_arguments()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'password' => new StrongPassword(minLength: 16, requireUppercase: true),
                    'age' => new NumericRange(18, 120),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Named arguments
        $this->assertIsArray($rules['password']);
        $this->assertSame('custom_rule', $rules['password']['type']);
        $this->assertSame('StrongPassword', $rules['password']['class']);
        $this->assertSame(['minLength' => 16, 'requireUppercase' => true], $rules['password']['args']);

        // Positional arguments
        $this->assertIsArray($rules['age']);
        $this->assertSame('custom_rule', $rules['age']['type']);
        $this->assertSame('NumericRange', $rules['age']['class']);
        $this->assertSame([18, 120], $rules['age']['args']);
    }

    #[Test]
    public function it_handles_enum_rule_without_class_argument()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => Rule::enum($statusClass),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('__enum__', $rules['status']);
    }

    #[Test]
    public function it_handles_new_enum_without_class_argument()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rules\Enum;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => new Enum($statusClass),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('__enum__', $rules['status']);
    }

    #[Test]
    public function it_handles_required_if_with_integer_value()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'extra_field' => Rule::requiredIf('type', 1),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('required_if:type:1', $rules['extra_field']);
    }

    #[Test]
    public function it_handles_array_with_null_items()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'name' => ['required', 'string'],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['name']);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
    }

    #[Test]
    public function it_handles_empty_array_merge()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return array_merge($this->dynamicRules(), $this->otherDynamicRules());
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertArrayHasKey('_notice', $rules);
        $this->assertStringContainsString('Dynamic array_merge', $rules['_notice']);
    }

    #[Test]
    public function it_handles_expression_key_in_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    $this->getFieldName() => 'required|string',
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Expression keys are stringified
        $keys = array_keys($rules);
        $this->assertNotEmpty($keys);
        $this->assertStringContainsString('getFieldName', $keys[0]);
    }

    #[Test]
    public function it_handles_rule_in_without_arguments()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => Rule::in(),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertEquals('in:', $rules['status']);
    }

    #[Test]
    public function it_handles_concat_with_mixed_values()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'name' => 'required|string|' . ['array_value'],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Concat with non-strings falls back to expression
        $this->assertArrayHasKey('name', $rules);
    }

    #[Test]
    public function it_handles_method_chain_with_other_methods()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => Rule::unique('users')->customMethod(),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Custom methods are stringified
        $this->assertArrayHasKey('email', $rules);
    }

    // ========== Additional Custom Rule tests (Issue #316) ==========

    #[Test]
    public function it_extracts_custom_rule_with_all_scalar_types()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'field' => new CustomRule(
                        stringArg: 'hello',
                        intArg: 42,
                        floatArg: 3.14,
                        boolTrue: true,
                        boolFalse: false,
                        nullArg: null,
                    ),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['field']);
        $this->assertSame('custom_rule', $rules['field']['type']);
        $this->assertSame('CustomRule', $rules['field']['class']);
        $this->assertSame('hello', $rules['field']['args']['stringArg']);
        $this->assertSame(42, $rules['field']['args']['intArg']);
        $this->assertSame(3.14, $rules['field']['args']['floatArg']);
        $this->assertTrue($rules['field']['args']['boolTrue']);
        $this->assertFalse($rules['field']['args']['boolFalse']);
        $this->assertNull($rules['field']['args']['nullArg']);
    }

    #[Test]
    public function it_extracts_custom_rule_with_array_argument()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'field' => new CustomRule(
                        options: ['a', 'b', 'c'],
                        mappedOptions: ['key1' => 'value1', 'key2' => 'value2'],
                    ),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['field']);
        $this->assertSame('custom_rule', $rules['field']['type']);
        $this->assertSame(['a', 'b', 'c'], $rules['field']['args']['options']);
        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $rules['field']['args']['mappedOptions']);
    }

    #[Test]
    public function it_extracts_custom_rule_in_array_syntax()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'password' => ['required', 'string', new StrongPassword(minLength: 12)],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['password']);
        $this->assertContains('required', $rules['password']);
        $this->assertContains('string', $rules['password']);

        // Find the custom rule in the array
        $customRule = null;
        foreach ($rules['password'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $customRule = $rule;

                break;
            }
        }

        $this->assertNotNull($customRule);
        $this->assertSame('StrongPassword', $customRule['class']);
        $this->assertSame(['minLength' => 12], $customRule['args']);
    }

    #[Test]
    public function it_extracts_custom_rule_with_complex_expression_argument()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'field' => new CustomRule($this->getDynamicValue()),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['field']);
        $this->assertSame('custom_rule', $rules['field']['type']);
        $this->assertSame('CustomRule', $rules['field']['class']);
        // Complex expression is stringified
        $this->assertIsString($rules['field']['args'][0]);
        $this->assertStringContainsString('getDynamicValue', $rules['field']['args'][0]);
    }

    #[Test]
    public function it_extracts_multiple_custom_rules_in_same_request()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'password' => ['required', new StrongPassword(minLength: 16)],
                    'phone' => ['required', new PhoneNumber(format: 'E164')],
                    'age' => ['required', new NumericRange(min: 18, max: 120)],
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        // Check password rule
        $passwordRule = null;
        foreach ($rules['password'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $passwordRule = $rule;

                break;
            }
        }
        $this->assertNotNull($passwordRule);
        $this->assertSame('StrongPassword', $passwordRule['class']);
        $this->assertSame(16, $passwordRule['args']['minLength']);

        // Check phone rule
        $phoneRule = null;
        foreach ($rules['phone'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $phoneRule = $rule;

                break;
            }
        }
        $this->assertNotNull($phoneRule);
        $this->assertSame('PhoneNumber', $phoneRule['class']);
        $this->assertSame('E164', $phoneRule['args']['format']);

        // Check age rule
        $ageRule = null;
        foreach ($rules['age'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $ageRule = $rule;

                break;
            }
        }
        $this->assertNotNull($ageRule);
        $this->assertSame('NumericRange', $ageRule['class']);
        $this->assertSame(18, $ageRule['args']['min']);
        $this->assertSame(120, $ageRule['args']['max']);
    }

    #[Test]
    public function it_extracts_custom_rule_with_nested_array_argument()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'config' => new CustomRule(
                        settings: [
                            'level1' => [
                                'level2' => 'value',
                            ],
                        ],
                    ),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['config']);
        $this->assertSame('custom_rule', $rules['config']['type']);
        $expected = [
            'level1' => [
                'level2' => 'value',
            ],
        ];
        $this->assertSame($expected, $rules['config']['args']['settings']);
    }

    #[Test]
    public function it_extracts_custom_rule_with_fully_qualified_class_name()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'password' => new \App\Rules\StrongPassword(minLength: 20),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['password']);
        $this->assertSame('custom_rule', $rules['password']['type']);
        $this->assertSame('App\Rules\StrongPassword', $rules['password']['class']);
        $this->assertSame(['minLength' => 20], $rules['password']['args']);
    }

    #[Test]
    public function it_extracts_custom_rule_with_no_arguments()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'field' => new DefaultRule(),
                ];
            }
        }
        PHP;

        $visitor = new RulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $rules = $visitor->getRules();

        $this->assertIsArray($rules['field']);
        $this->assertSame('custom_rule', $rules['field']['type']);
        $this->assertSame('DefaultRule', $rules['field']['class']);
        $this->assertSame([], $rules['field']['args']);
    }
}
