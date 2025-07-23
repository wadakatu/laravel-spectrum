<?php

namespace Tests\Unit\Analyzers\AST;

use Illuminate\Foundation\Http\FormRequest;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Support\TypeInference;
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

        $this->analyzer = new FormRequestAnalyzer(new TypeInference, $cache);
    }

    #[Test]
    public function it_analyzes_http_method_conditions()
    {
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    return [
                        'email' => 'required|email|unique:users',
                        'password' => 'required|min:8|confirmed',
                    ];
                }

                return [
                    'email' => 'sometimes|email',
                    'password' => 'sometimes|min:8',
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

        // Check that conditional rules were detected
        $this->assertArrayHasKey('conditional_rules', $result);
        $this->assertArrayHasKey('rules_sets', $result['conditional_rules']);
        $this->assertArrayHasKey('merged_rules', $result['conditional_rules']);

        // Check rule sets
        $ruleSets = $result['conditional_rules']['rules_sets'];
        $this->assertCount(2, $ruleSets);

        // POST rules
        $postRules = $ruleSets[0];
        $this->assertNotEmpty($postRules['conditions']);
        $this->assertEquals('http_method', $postRules['conditions'][0]['type']);
        $this->assertEquals('POST', $postRules['conditions'][0]['method']);
        $this->assertArrayHasKey('email', $postRules['rules']);
        $this->assertArrayHasKey('password', $postRules['rules']);
        $this->assertStringContainsString('required', $postRules['rules']['email']);
        $this->assertStringContainsString('unique:users', $postRules['rules']['email']);

        // Non-POST rules (else block has empty conditions as it's the default)
        $otherRules = $ruleSets[1];
        $this->assertEmpty($otherRules['conditions']);
        $this->assertArrayHasKey('email', $otherRules['rules']);
        $this->assertStringContainsString('sometimes', $otherRules['rules']['email']);

        // Check merged rules
        $mergedRules = $result['conditional_rules']['merged_rules'];
        $this->assertArrayHasKey('email', $mergedRules);
        $this->assertArrayHasKey('password', $mergedRules);
        $this->assertContains('required', $mergedRules['email']);
        $this->assertContains('sometimes', $mergedRules['email']);
        $this->assertContains('unique:users', $mergedRules['email']);
    }

    #[Test]
    public function it_analyzes_nested_conditions()
    {
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    if ($this->user()->isAdmin()) {
                        return [
                            'title' => 'required|string',
                            'published_at' => 'required|date',
                        ];
                    }

                    return [
                        'title' => 'required|string',
                    ];
                }

                return [
                    'title' => 'sometimes|string',
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
        $this->assertEquals('user_method', $adminRuleSet['conditions'][1]['type']);
    }

    #[Test]
    public function it_handles_early_returns()
    {
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->isMethod('DELETE')) {
                    return [];
                }

                if ($this->isMethod('POST')) {
                    return [
                        'name' => 'required|string',
                        'email' => 'required|email',
                    ];
                }

                return [
                    'name' => 'sometimes|string',
                    'email' => 'sometimes|email',
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    return ['status' => 'required|in:draft,published'];
                } elseif ($this->isMethod('PUT')) {
                    return ['status' => 'required|in:draft,published,archived'];
                } else {
                    return ['status' => 'sometimes|string'];
                }
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if (request()->isMethod('POST')) {
                    return [
                        'name' => 'required|string',
                    ];
                }

                return [
                    'name' => 'sometimes|string',
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    return [
                        'tags' => ['required', 'array'],
                        'tags.*' => ['string', 'max:50'],
                    ];
                }

                return [
                    'tags' => ['sometimes', 'array'],
                    'tags.*' => ['string'],
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    return [
                        'email' => 'required|email|unique:users',
                        'password' => 'required|min:8',
                    ];
                }

                return [
                    'email' => 'sometimes|email',
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));
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
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'name' => 'required|string',
                    'email' => 'required|email',
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->user() && $this->user()->isPremium()) {
                    return [
                        'advanced_feature' => 'required|string',
                    ];
                }

                return [
                    'basic_feature' => 'required|string',
                ];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
        $requestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                if ($this->isMethod('POST')) {
                    if ($this->user()->isAdmin()) {
                        return ['role' => 'required|in:admin,moderator'];
                    }

                    return ['role' => 'required|in:user'];
                }

                return [];
            }
        };

        $result = $this->analyzer->analyzeWithConditionalRules(get_class($requestClass));

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
