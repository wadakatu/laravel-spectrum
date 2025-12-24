<?php

namespace Tests\Unit\Analyzers\AST;

use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConditionalRulesTest extends TestCase
{
    private FormRequestAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache that always calls the callback
        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberFormRequest')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        // Register mock cache in container and get analyzer via DI
        $this->app->instance(DocumentationCache::class, $cache);
        $this->analyzer = $this->app->make(FormRequestAnalyzer::class);
    }

    #[Test]
    public function it_analyzes_http_method_conditions()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\ConditionalFormRequest'
        );

        // Check that conditional rules were detected
        $this->assertArrayHasKey('conditional_rules', $result);
        $this->assertArrayHasKey('rules_sets', $result['conditional_rules']);
        $this->assertArrayHasKey('merged_rules', $result['conditional_rules']);

        // Check rule sets
        $ruleSets = $result['conditional_rules']['rules_sets'];
        $this->assertCount(3, $ruleSets); // POST, PUT, and default

        // POST rules
        $postRules = null;
        $putRules = null;
        $defaultRules = null;

        foreach ($ruleSets as $ruleSet) {
            if (! empty($ruleSet['conditions']) && $ruleSet['conditions'][0]['type'] === 'http_method') {
                if ($ruleSet['conditions'][0]['method'] === 'POST') {
                    $postRules = $ruleSet;
                } elseif ($ruleSet['conditions'][0]['method'] === 'PUT') {
                    $putRules = $ruleSet;
                }
            } elseif (empty($ruleSet['conditions'])) {
                $defaultRules = $ruleSet;
            }
        }

        // Test POST rules
        $this->assertNotNull($postRules);
        $this->assertArrayHasKey('email', $postRules['rules']);
        $this->assertArrayHasKey('password', $postRules['rules']);
        $this->assertStringContainsString('required', $postRules['rules']['email']);
        $this->assertStringContainsString('unique:users', $postRules['rules']['email']);

        // Test PUT rules
        $this->assertNotNull($putRules);
        $this->assertArrayHasKey('email', $putRules['rules']);
        $this->assertStringContainsString('sometimes', $putRules['rules']['email']);

        // Test default rules
        $this->assertNotNull($defaultRules);
        $this->assertArrayHasKey('name', $defaultRules['rules']);
        $this->assertStringContainsString('string', $defaultRules['rules']['name']);

        // Check merged rules
        $mergedRules = $result['conditional_rules']['merged_rules'];
        $this->assertArrayHasKey('email', $mergedRules);
        $this->assertArrayHasKey('name', $mergedRules);
        $this->assertArrayHasKey('password', $mergedRules);
    }

    #[Test]
    public function it_analyzes_nested_conditions()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\NestedConditionalRequest'
        );

        $ruleSets = $result['conditional_rules']['rules_sets'];
        $this->assertCount(3, $ruleSets);

        // Find the admin rule set (has published_at field)
        $adminRuleSet = null;
        foreach ($ruleSets as $ruleSet) {
            if (isset($ruleSet['rules']['published_at'])) {
                $adminRuleSet = $ruleSet;
                break;
            }
        }

        $this->assertNotNull($adminRuleSet);
        $this->assertCount(2, $adminRuleSet['conditions']);
        $this->assertEquals('http_method', $adminRuleSet['conditions'][0]['type']);
        $this->assertEquals('custom', $adminRuleSet['conditions'][1]['type']);
        $this->assertStringContainsString('isAdmin', $adminRuleSet['conditions'][1]['expression']);
    }

    #[Test]
    public function it_handles_early_returns()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\EarlyReturnRequest'
        );

        $ruleSets = $result['conditional_rules']['rules_sets'];
        $this->assertCount(3, $ruleSets);

        // Check DELETE returns empty rules
        $deleteRules = null;
        foreach ($ruleSets as $ruleSet) {
            if (! empty($ruleSet['conditions']) &&
                $ruleSet['conditions'][0]['type'] === 'http_method' &&
                $ruleSet['conditions'][0]['method'] === 'DELETE') {
                $deleteRules = $ruleSet;
                break;
            }
        }

        $this->assertNotNull($deleteRules);
        $this->assertEmpty($deleteRules['rules']);
    }

    #[Test]
    public function it_handles_elseif_conditions()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\ElseIfConditionRequest'
        );

        $ruleSets = $result['conditional_rules']['rules_sets'];
        $this->assertCount(3, $ruleSets);

        // Find PUT rules
        $putRules = null;
        foreach ($ruleSets as $ruleSet) {
            if (! empty($ruleSet['conditions']) &&
                $ruleSet['conditions'][0]['type'] === 'http_method' &&
                $ruleSet['conditions'][0]['method'] === 'PUT') {
                $putRules = $ruleSet;
                break;
            }
        }

        $this->assertNotNull($putRules);
        $this->assertStringContainsString('archived', $putRules['rules']['status']);
    }

    #[Test]
    public function it_handles_request_helper_conditions()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\RequestHelperConditionRequest'
        );

        $ruleSets = $result['conditional_rules']['rules_sets'];
        $this->assertCount(2, $ruleSets);

        // Check that request()->isMethod() is recognized
        $postRules = $ruleSets[0];
        $this->assertEquals('http_method', $postRules['conditions'][0]['type']);
        $this->assertEquals('POST', $postRules['conditions'][0]['method']);
    }

    #[Test]
    public function it_handles_array_validation_rules()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\ArrayValidationRequest'
        );

        $ruleSets = $result['conditional_rules']['rules_sets'];
        $postRules = $ruleSets[0];

        $this->assertIsArray($postRules['rules']['tags']);
        $this->assertContains('required', $postRules['rules']['tags']);
        $this->assertContains('array', $postRules['rules']['tags']);

        $this->assertIsArray($postRules['rules']['tags.*']);
        $this->assertContains('max:50', $postRules['rules']['tags.*']);
    }

    #[Test]
    public function it_generates_parameters_from_conditional_rules()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\ParameterGenerationRequest'
        );
        $parameters = $result['parameters'];

        // Find email parameter
        $emailParam = null;
        foreach ($parameters as $param) {
            if ($param['name'] === 'email') {
                $emailParam = $param;
                break;
            }
        }

        $this->assertNotNull($emailParam);
        $this->assertArrayHasKey('conditional_rules', $emailParam);
        $this->assertCount(2, $emailParam['conditional_rules']);

        // Check that required is true (since it's required in at least one condition)
        $this->assertTrue($emailParam['required']);
    }

    #[Test]
    public function it_falls_back_to_regular_extraction_when_no_conditions()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\NoConditionsRequest'
        );

        // Should still work and return parameters
        $this->assertArrayHasKey('parameters', $result);
        $this->assertCount(2, $result['parameters']);

        // Should have one rule set with empty conditions (no if statements)
        $this->assertArrayHasKey('conditional_rules', $result);
        $this->assertCount(1, $result['conditional_rules']['rules_sets']);
        $this->assertEmpty($result['conditional_rules']['rules_sets'][0]['conditions']);
    }

    #[Test]
    public function it_handles_complex_conditions()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\ComplexConditionalRequest'
        );

        $ruleSets = $result['conditional_rules']['rules_sets'];
        $this->assertCount(2, $ruleSets);

        // Check custom condition type
        $premiumRules = $ruleSets[0];
        $this->assertEquals('custom', $premiumRules['conditions'][0]['type']);
        $this->assertStringContainsString('isPremium', $premiumRules['conditions'][0]['expression']);
    }

    #[Test]
    public function it_calculates_probability_correctly()
    {
        $result = $this->analyzer->analyzeWithConditionalRules(
            'LaravelSpectrum\\Tests\\Fixtures\\Requests\\ProbabilityCalculationRequest'
        );

        $ruleSets = $result['conditional_rules']['rules_sets'];

        // Find nested condition rule set
        $nestedRules = null;
        foreach ($ruleSets as $ruleSet) {
            if (count($ruleSet['conditions']) === 2) {
                $nestedRules = $ruleSet;
                break;
            }
        }

        $this->assertNotNull($nestedRules);
        // Probability should be 1/4 for 2 nested conditions
        $this->assertEquals(0.25, $nestedRules['probability']);
    }
}
