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
}
