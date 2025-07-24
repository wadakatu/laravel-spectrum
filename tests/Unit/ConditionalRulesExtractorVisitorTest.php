<?php

namespace LaravelSpectrum\Tests\Unit;

use LaravelSpectrum\Analyzers\AST\Visitors\ConditionalRulesExtractorVisitor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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
}
