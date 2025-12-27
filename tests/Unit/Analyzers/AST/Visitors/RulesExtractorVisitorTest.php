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

        // Non-Enum new expressions are stringified
        $this->assertStringContainsString('new CustomRule()', $rules['custom']);
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
}
