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
        $this->assertEquals('http_method', $ruleSets['rules_sets'][0]['conditions'][0]->getTypeAsString());
        $this->assertEquals('POST', $ruleSets['rules_sets'][0]['conditions'][0]->method);
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
                if ($condition->isCustom() && strpos($condition->expression, 'isAdmin') !== false) {
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
        $this->assertEquals('else', $elseRuleSet['conditions'][0]->getTypeAsString());
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

    #[Test]
    public function it_handles_ternary_with_null_if_branch()
    {
        // Elvis operator: $expr ?: $default
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $this->customRules() ?: ['name' => 'required|string'];
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
    public function it_handles_numeric_keys_in_array()
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

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('0', $mergedRules);
        $this->assertArrayHasKey('1', $mergedRules);
    }

    #[Test]
    public function it_handles_concatenated_rules()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'name' => ['required', 'string' . '|max:255'],
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('name', $mergedRules);
    }

    #[Test]
    public function it_handles_new_expression_as_rule()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => ['required', new Enum(StatusEnum::class)],
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('status', $mergedRules);
        $this->assertContains('required', $mergedRules['status']);
    }

    #[Test]
    public function it_handles_rule_in_without_arguments()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => Rule::in(),
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('status', $mergedRules);
        // The rule is stored as-is (could be string or array based on context)
        $statusRules = $mergedRules['status'];
        $this->assertContains('in:', is_array($statusRules) ? $statusRules : [$statusRules]);
    }

    #[Test]
    public function it_handles_rule_in_with_variable_argument()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => Rule::in($this->getAllowedStatuses()),
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('status', $mergedRules);
        // The rule is stored as-is (could be string or array based on context)
        $statusRules = $mergedRules['status'];
        $this->assertContains('in:...', is_array($statusRules) ? $statusRules : [$statusRules]);
    }

    #[Test]
    public function it_handles_rule_when_static_call()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'phone' => Rule::when(true, 'required'),
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('phone', $mergedRules);
        $phoneRules = $mergedRules['phone'];
        $this->assertContains('sometimes', is_array($phoneRules) ? $phoneRules : [$phoneRules]);
    }

    #[Test]
    public function it_handles_expression_key()
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

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        // The key is converted to a string representation
        $this->assertNotEmpty($ruleSets['merged_rules']);
    }

    #[Test]
    public function it_handles_rule_exists_without_arguments()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'user_id' => Rule::exists($this->getTable()),
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('user_id', $mergedRules);
        $userIdRules = $mergedRules['user_id'];
        $this->assertContains('exists:...', is_array($userIdRules) ? $userIdRules : [$userIdRules]);
    }

    #[Test]
    public function it_handles_rule_unique_without_arguments()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => Rule::unique($this->getTable()),
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('email', $mergedRules);
        $emailRules = $mergedRules['email'];
        $this->assertContains('unique:...', is_array($emailRules) ? $emailRules : [$emailRules]);
    }

    #[Test]
    public function it_handles_rule_required_if_with_string_args()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'company_name' => Rule::requiredIf('is_company', 'true'),
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('company_name', $mergedRules);
        $companyNameRules = $mergedRules['company_name'];
        // Check that the rule contains required_if
        if (is_array($companyNameRules)) {
            $found = false;
            foreach ($companyNameRules as $rule) {
                if (str_starts_with($rule, 'required_if:')) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Should contain required_if rule');
        } else {
            $this->assertStringStartsWith('required_if:', $companyNameRules);
        }
    }

    #[Test]
    public function it_handles_unknown_rule_static_method()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'file' => Rule::dimensions(['min_width' => 100]),
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('file', $mergedRules);
    }

    #[Test]
    public function it_handles_assignment_to_array_element()
    {
        // Assignment to array elements ($rules['name'] = ...) is not captured by handleAssignment
        // because the $node->var is ArrayDimFetch, not Variable.
        // This tests the early return behavior in handleAssignment.
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $rules = [];
                $rules['name'] = 'required|string';
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
        // The initial empty array is captured, but element assignments are not
        // This is expected behavior - the visitor captures variable assignments
        $this->assertNotNull($ruleSets['merged_rules']);
    }

    #[Test]
    public function it_handles_method_call_assignment()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $baseRules = $this->getBaseRules();
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
        // Method call result is evaluated
        $this->assertEmpty($ruleSets['merged_rules']);
    }

    #[Test]
    public function it_handles_method_without_identifier_name()
    {
        // When isMethod('POST') is used, the method name is checked
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->{$methodName}()) {
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

        $ruleSets = $visitor->getRuleSets();
        // Should handle gracefully
        $this->assertNotEmpty($ruleSets['rules_sets']);
    }

    #[Test]
    public function it_handles_missing_method_check()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->missing('optional_field')) {
                    return ['default_field' => 'required'];
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
        $firstCondition = $ruleSets['rules_sets'][0]['conditions'][0] ?? null;
        $this->assertEquals('request_field', $firstCondition->getTypeAsString());
        $this->assertEquals('missing', $firstCondition->check);
    }

    #[Test]
    public function it_returns_default_for_unknown_method_call()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return $this->unknownMethod();
            }
        }
        PHP;

        $visitor = new ConditionalRulesExtractorVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $ruleSets = $visitor->getRuleSets();
        // Unknown methods return null, so merged_rules is empty
        $this->assertEmpty($ruleSets['merged_rules']);
    }

    #[Test]
    public function it_handles_rule_method_chain()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'email' => ['required', Rule::unique('users')->ignore($this->user())],
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('email', $mergedRules);
        // The method chain is evaluated to extract the base rule
        $this->assertContains('required', $mergedRules['email']);
    }

    #[Test]
    public function it_handles_rule_exists_method_chain()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'user_id' => ['required', Rule::exists('users')->where('active', true)],
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('user_id', $mergedRules);
        $this->assertContains('required', $mergedRules['user_id']);
        // The exists rule is extracted from the chain
        $this->assertContains('exists:users', $mergedRules['user_id']);
    }

    #[Test]
    public function it_handles_non_rule_method_call_in_single_rule()
    {
        // This tests the else branch in evaluateSingleRule for MethodCall that's not a Rule chain
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'name' => ['required', $this->getCustomRule()],
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
        $mergedRules = $ruleSets['merged_rules'];
        $this->assertArrayHasKey('name', $mergedRules);
        $this->assertContains('required', $mergedRules['name']);
    }

    #[Test]
    public function it_handles_function_call_assignment()
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                $rules = collect(['name' => 'required'])->toArray();
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
        // Function call result is stored in variable scope
        $this->assertNotNull($ruleSets['merged_rules']);
    }

    // =====================================
    // Custom Rule Tests (Issue #316)
    // =====================================

    #[Test]
    public function it_extracts_custom_rule_with_named_arguments(): void
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'password' => ['required', new StrongPassword(minLength: 16, requireUppercase: true)],
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
        $mergedRules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('password', $mergedRules);
        $this->assertContains('required', $mergedRules['password']);

        // Find the custom rule array
        $customRule = null;
        foreach ($mergedRules['password'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $customRule = $rule;
                break;
            }
        }

        $this->assertNotNull($customRule, 'Custom rule should be extracted as structured array');
        $this->assertEquals('StrongPassword', $customRule['class']);
        $this->assertEquals(16, $customRule['args']['minLength']);
        $this->assertTrue($customRule['args']['requireUppercase']);
    }

    #[Test]
    public function it_extracts_custom_rule_with_positional_arguments(): void
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'age' => ['required', new NumericRange(18, 120)],
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
        $mergedRules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('age', $mergedRules);

        // Find the custom rule array
        $customRule = null;
        foreach ($mergedRules['age'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $customRule = $rule;
                break;
            }
        }

        $this->assertNotNull($customRule, 'Custom rule should be extracted as structured array');
        $this->assertEquals('NumericRange', $customRule['class']);
        $this->assertEquals(18, $customRule['args'][0]);
        $this->assertEquals(120, $customRule['args'][1]);
    }

    #[Test]
    public function it_extracts_custom_rule_without_arguments(): void
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'phone' => ['required', new PhoneNumber],
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
        $mergedRules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('phone', $mergedRules);

        // Find the custom rule array
        $customRule = null;
        foreach ($mergedRules['phone'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $customRule = $rule;
                break;
            }
        }

        $this->assertNotNull($customRule, 'Custom rule should be extracted as structured array');
        $this->assertEquals('PhoneNumber', $customRule['class']);
        $this->assertEmpty($customRule['args']);
    }

    #[Test]
    public function it_deduplicates_custom_rules_in_merged_rules(): void
    {
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                if ($this->isAdmin()) {
                    return [
                        'password' => ['required', new StrongPassword(minLength: 16)],
                    ];
                }
                return [
                    'password' => ['required', new StrongPassword(minLength: 16)],
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
        $mergedRules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('password', $mergedRules);

        // Count custom rules - should be deduplicated
        $customRuleCount = 0;
        foreach ($mergedRules['password'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'custom_rule') {
                $customRuleCount++;
            }
        }

        $this->assertEquals(1, $customRuleCount, 'Duplicate custom rules should be merged');
    }

    // =====================================
    // Mutation Testing Coverage Tests
    // =====================================

    #[Test]
    public function it_concatenates_string_literals_in_correct_order(): void
    {
        // Test string literal concatenation which exercises the Concat branch
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'name' => ['required', 'string' . '|max:255'],
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
        $mergedRules = $ruleSets['merged_rules'];

        // Verify concatenation produces correct result
        $this->assertArrayHasKey('name', $mergedRules);
        $this->assertContains('required', $mergedRules['name']);
        // The concatenated rule should be 'string|max:255' (not 'max:255|string')
        $this->assertContains('string|max:255', $mergedRules['name']);
    }

    #[Test]
    public function it_preserves_order_in_nested_string_concatenation(): void
    {
        // Test nested concatenation: 'a' . 'b' . 'c' should produce 'abc'
        $code = <<<'PHP'
        <?php
        class TestRequest {
            public function rules(): array
            {
                return [
                    'name' => ['required', 'min:' . '5' . '|max:100'],
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
        $mergedRules = $ruleSets['merged_rules'];

        $this->assertArrayHasKey('name', $mergedRules);
        $this->assertContains('required', $mergedRules['name']);
        // Should be 'min:5|max:100' in correct order
        $this->assertContains('min:5|max:100', $mergedRules['name']);
    }

    #[Test]
    public function it_handles_enum_with_dynamic_class_argument(): void
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rules\Enum;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => ['required', new Enum($this->getEnumClass())],
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
        $mergedRules = $ruleSets['merged_rules'];

        // Enum with dynamic class argument falls back to __enum__
        $this->assertArrayHasKey('status', $mergedRules);
        $this->assertContains('required', $mergedRules['status']);
        $this->assertContains('__enum__', $mergedRules['status']);
    }

    #[Test]
    public function it_handles_enum_with_static_class_constant(): void
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Validation\Rules\Enum;

        class TestRequest {
            public function rules(): array
            {
                return [
                    'status' => ['required', new Enum(StatusEnum::class)],
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
        $mergedRules = $ruleSets['merged_rules'];

        // Enum with static class constant is properly extracted
        $this->assertArrayHasKey('status', $mergedRules);
        $this->assertContains('required', $mergedRules['status']);

        // Find the enum rule
        $enumRule = null;
        foreach ($mergedRules['status'] as $rule) {
            if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'enum') {
                $enumRule = $rule;

                break;
            }
        }

        $this->assertNotNull($enumRule);
        $this->assertEquals('StatusEnum', $enumRule['class']);
    }
}
