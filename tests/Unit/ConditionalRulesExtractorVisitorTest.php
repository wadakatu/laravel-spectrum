<?php

namespace LaravelSpectrum\Tests\Unit;

use LaravelSpectrum\Analyzers\AST\Visitors\ConditionalRulesExtractorVisitor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\Test;

class ConditionalRulesExtractorVisitorTest extends TestCase
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
    public function it_extracts_simple_if_condition_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    return [
                        'name' => 'required|string',
                        'email' => 'required|email',
                    ];
                }
                
                return [
                    'name' => 'sometimes|string',
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();

        $this->assertCount(2, $ruleSets['rules_sets']);
        $this->assertEquals('http_method', $ruleSets['rules_sets'][0]['conditions'][0]['type']);
        $this->assertEquals('POST', $ruleSets['rules_sets'][0]['conditions'][0]['method']);
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
                
                if ($this->isMethod('POST')) {
                    return array_merge($baseRules, [
                        'email' => 'required|email',
                        'password' => 'required|min:8',
                    ]);
                }
                
                return $baseRules;
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();

        // Should find rule sets with merged rules
        $this->assertNotEmpty($ruleSets['rules_sets']);

        // Check merged rules contain all fields
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('name', $mergedRules);
        $this->assertArrayHasKey('email', $mergedRules);
        $this->assertArrayHasKey('password', $mergedRules);
    }

    #[Test]
    public function it_extracts_method_call_results()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $rules = $this->baseRules();
                
                if ($this->user() && $this->user()->isAdmin()) {
                    return array_merge($rules, [
                        'role' => 'required|in:admin,user',
                    ]);
                }
                
                return $rules;
            }
            
            private function baseRules(): array
            {
                return [
                    'name' => 'required|string',
                    'email' => 'required|email',
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();

        // Should detect custom condition (not user_check anymore)
        $this->assertNotEmpty($ruleSets['rules_sets']);

        // Check that we have at least one rule set with custom condition
        $hasCustomCondition = false;
        foreach ($ruleSets['rules_sets'] as $ruleSet) {
            foreach ($ruleSet['conditions'] as $condition) {
                if ($condition['type'] === 'custom' && strpos($condition['expression'], 'isAdmin') !== false) {
                    $hasCustomCondition = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($hasCustomCondition, 'Should detect custom condition with isAdmin');
    }

    #[Test]
    public function it_extracts_rule_class_methods()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                    'email' => Rule::unique('users')->ignore($this->user()),
                    'role' => Rule::requiredIf($this->user()->isAdmin()),
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();

        // Should extract Rule:: methods
        $this->assertNotEmpty($ruleSets['rules_sets']);
        $rules = $ruleSets['rules_sets'][0]['rules'];

        $this->assertArrayHasKey('status', $rules);
        $this->assertContains('in:active,inactive', $rules['status']);
    }

    #[Test]
    public function it_handles_elseif_and_else_conditions()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    return ['name' => 'required|string'];
                } elseif ($this->isMethod('PUT')) {
                    return ['name' => 'sometimes|string'];
                } else {
                    return ['name' => 'string'];
                }
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();

        // Should have 3 rule sets (if, elseif, else)
        $this->assertCount(3, $ruleSets['rules_sets']);

        // Check else condition
        $elseRuleSet = end($ruleSets['rules_sets']);
        $this->assertEquals('else', $elseRuleSet['conditions'][0]['type']);
    }

    #[Test]
    public function it_extracts_ternary_expression_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $this->isAdmin()
                    ? ['role' => 'required|in:admin,super']
                    : ['role' => 'required|in:user'];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_extracts_request_field_checks()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->has('include_address')) {
                    return [
                        'address' => 'required|string',
                        'city' => 'required|string',
                    ];
                }
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);

        // Check that we detect the request field condition
        $firstCondition = $ruleSets['rules_sets'][0]['conditions'][0] ?? null;
        $this->assertNotNull($firstCondition);
    }

    #[Test]
    public function it_extracts_request_filled_check()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->filled('email')) {
                    return ['email_verified' => 'required|boolean'];
                }
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_extracts_rule_exists()
    {
        // Rule::exists only extracts the first argument (table name)
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'user_id' => ['required', Rule::exists('users')],
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
        $rules = $ruleSets['rules_sets'][0]['rules'];
        $this->assertArrayHasKey('user_id', $rules);
        $this->assertContains('exists:users', $rules['user_id']);
    }

    #[Test]
    public function it_extracts_rule_unique()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => ['required', Rule::unique('users')],
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
        $rules = $ruleSets['rules_sets'][0]['rules'];
        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('unique:users', $rules['email']);
    }

    #[Test]
    public function it_extracts_rule_required_if()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'company_name' => Rule::requiredIf($this->input('is_company')),
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_detects_conditional_rules_via_rule_sets()
    {
        // hasConditionalRules() checks if currentPath is non-empty,
        // but currentPath is cleared after traversal. Instead, check rule_sets count.
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    return ['name' => 'required'];
                }
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        // Having multiple rule sets indicates conditional rules
        $ruleSets = $visitor->getRuleSets();
        $this->assertCount(2, $ruleSets['rules_sets']);
    }

    #[Test]
    public function it_returns_false_for_no_conditional_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return ['name' => 'required'];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $this->assertFalse($visitor->hasConditionalRules());
    }

    #[Test]
    public function it_extracts_user_authenticated_check()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->user()) {
                    return ['profile' => 'required|array'];
                }
                return ['guest_email' => 'required|email'];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertCount(2, $ruleSets['rules_sets']);
    }

    #[Test]
    public function it_handles_array_addition_operator()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $baseRules = ['name' => 'required'];
                return $baseRules + ['email' => 'required|email'];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('name', $mergedRules);
        $this->assertArrayHasKey('email', $mergedRules);
    }

    #[Test]
    public function it_extracts_base_rules_method()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $this->baseRules();
            }

            public function baseRules(): array
            {
                return ['name' => 'required|string'];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['merged_rules']);
    }

    #[Test]
    public function it_extracts_additional_rules_method()
    {
        // The visitor stops traversal at the first Return_ statement it encounters.
        // To test array_merge with method calls, only traverse the rules() method.
        // additionalRules() is a recognized method name that gets cached.
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return array_merge(['name' => 'required'], $this->additionalRules());
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $mergedRules = $ruleSets['merged_rules'];
        // The inline array is merged, additionalRules() returns a placeholder
        $this->assertArrayHasKey('name', $mergedRules);
    }

    #[Test]
    public function it_handles_rule_when_pattern()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'phone' => Rule::when($this->has('contact_by_phone'), ['required', 'string']),
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_handles_negated_conditions()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if (!$this->isMethod('GET')) {
                    return ['body' => 'required|string'];
                }
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_handles_boolean_and_conditions()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->isMethod('POST') && $this->has('premium')) {
                    return ['premium_code' => 'required|string'];
                }
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_extracts_pipe_separated_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => 'required|email|max:255',
                    'password' => 'required|string|min:8|confirmed',
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $rules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    #[Test]
    public function it_handles_empty_rules_array()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertEmpty($ruleSets['merged_rules']);
    }

    #[Test]
    public function it_handles_array_of_rule_objects()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => [
                        'required',
                        'string',
                        Rule::in(['active', 'inactive', 'pending']),
                    ],
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $rules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('status', $rules);
        $this->assertContains('required', $rules['status']);
        $this->assertContains('string', $rules['status']);
    }

    #[Test]
    public function it_extracts_rules_with_numeric_parameters()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'age' => 'required|integer|min:18|max:120',
                    'score' => 'required|numeric|between:0,100',
                ];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $rules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('age', $rules);
        $this->assertArrayHasKey('score', $rules);
    }

    #[Test]
    public function it_handles_common_rules_method()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $this->commonRules();
            }

            public function commonRules(): array
            {
                return ['id' => 'required|uuid'];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['merged_rules']);
    }

    #[Test]
    public function it_handles_variable_assignment_with_array()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $rules = ['name' => 'required'];
                $rules['email'] = 'required|email';
                return $rules;
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('name', $mergedRules);
    }

    #[Test]
    public function it_handles_auth_check_condition()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if (auth()->check()) {
                    return ['user_id' => 'required|exists:users,id'];
                }
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_handles_input_method_check()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->input('type') === 'premium') {
                    return ['license_key' => 'required|string'];
                }
                return [];
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }
}
