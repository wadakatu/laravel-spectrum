<?php

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\QueryParameterAnalysisResult;
use LaravelSpectrum\DTO\QueryParameterInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryParameterAnalysisResultTest extends TestCase
{
    #[Test]
    public function it_creates_empty_result(): void
    {
        $result = QueryParameterAnalysisResult::empty();

        $this->assertFalse($result->hasParameters());
        $this->assertEquals(0, $result->count());
        $this->assertEquals([], $result->parameters);
    }

    #[Test]
    public function it_creates_from_parameters(): void
    {
        $params = [
            new QueryParameterInfo(name: 'search', type: 'string'),
            new QueryParameterInfo(name: 'page', type: 'integer'),
        ];

        $result = QueryParameterAnalysisResult::fromParameters($params);

        $this->assertTrue($result->hasParameters());
        $this->assertEquals(2, $result->count());
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'parameters' => [
                ['name' => 'search', 'type' => 'string'],
                ['name' => 'page', 'type' => 'integer', 'default' => 1],
            ],
        ];

        $result = QueryParameterAnalysisResult::fromArray($data);

        $this->assertEquals(2, $result->count());
        $this->assertEquals('search', $result->parameters[0]->name);
        $this->assertEquals('string', $result->parameters[0]->type);
        $this->assertEquals('page', $result->parameters[1]->name);
        $this->assertEquals(1, $result->parameters[1]->default);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $params = [
            new QueryParameterInfo(name: 'search', type: 'string', description: 'Search query'),
            new QueryParameterInfo(name: 'page', type: 'integer', default: 1),
        ];

        $result = QueryParameterAnalysisResult::fromParameters($params);
        $array = $result->toArray();

        $this->assertArrayHasKey('parameters', $array);
        $this->assertCount(2, $array['parameters']);
        $this->assertEquals('search', $array['parameters'][0]['name']);
        $this->assertEquals('Search query', $array['parameters'][0]['description']);
        $this->assertEquals('page', $array['parameters'][1]['name']);
        $this->assertEquals(1, $array['parameters'][1]['default']);
    }

    #[Test]
    public function it_gets_parameter_by_name(): void
    {
        $params = [
            new QueryParameterInfo(name: 'search', type: 'string'),
            new QueryParameterInfo(name: 'page', type: 'integer'),
        ];

        $result = QueryParameterAnalysisResult::fromParameters($params);

        $search = $result->getByName('search');
        $this->assertNotNull($search);
        $this->assertEquals('search', $search->name);

        $missing = $result->getByName('nonexistent');
        $this->assertNull($missing);
    }

    #[Test]
    public function it_gets_all_parameter_names(): void
    {
        $params = [
            new QueryParameterInfo(name: 'search', type: 'string'),
            new QueryParameterInfo(name: 'page', type: 'integer'),
            new QueryParameterInfo(name: 'per_page', type: 'integer'),
        ];

        $result = QueryParameterAnalysisResult::fromParameters($params);
        $names = $result->getNames();

        $this->assertEquals(['search', 'page', 'per_page'], $names);
    }

    #[Test]
    public function it_merges_with_validation_rules(): void
    {
        $params = [
            new QueryParameterInfo(name: 'search', type: 'string'),
            new QueryParameterInfo(name: 'page', type: 'string'), // Will be updated to integer
        ];

        $result = QueryParameterAnalysisResult::fromParameters($params);

        $validationRules = [
            'search' => ['string', 'max:255'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['integer', 'between:10,100'],
        ];

        $typeInferenceFn = fn (array $rules): ?string => match (true) {
            in_array('integer', $rules) => 'integer',
            in_array('string', $rules) => 'string',
            default => null,
        };

        $merged = $result->mergeWithValidation($validationRules, $typeInferenceFn);

        // Should have 3 parameters now
        $this->assertEquals(3, $merged->count());

        // Search should have validation rules
        $search = $merged->getByName('search');
        $this->assertNotNull($search);
        $this->assertEquals(['string', 'max:255'], $search->validationRules);

        // Page should be updated to integer and required
        $page = $merged->getByName('page');
        $this->assertNotNull($page);
        $this->assertEquals('integer', $page->type);
        $this->assertTrue($page->required);

        // Per_page should be added from validation
        $perPage = $merged->getByName('per_page');
        $this->assertNotNull($perPage);
        $this->assertEquals('integer', $perPage->type);
        $this->assertEquals('validation', $perPage->source);
    }

    #[Test]
    public function it_skips_nested_fields_when_merging(): void
    {
        $result = QueryParameterAnalysisResult::empty();

        $validationRules = [
            'user.name' => ['required', 'string'],
            'items.*.id' => ['required', 'integer'],
            'search' => ['string'],
        ];

        $merged = $result->mergeWithValidation($validationRules, fn ($rules) => 'string');

        // Should only have 'search', not nested fields
        $this->assertEquals(1, $merged->count());
        $this->assertNotNull($merged->getByName('search'));
        $this->assertNull($merged->getByName('user.name'));
        $this->assertNull($merged->getByName('items.*.id'));
    }

    #[Test]
    public function it_generates_description_for_new_params_from_validation(): void
    {
        $result = QueryParameterAnalysisResult::empty();

        $validationRules = [
            'user_id' => ['required', 'integer'],
            'api_key' => ['required', 'string'],
        ];

        $merged = $result->mergeWithValidation($validationRules, fn ($rules) => 'string');

        $userId = $merged->getByName('user_id');
        $this->assertEquals('User ID', $userId->description);

        $apiKey = $merged->getByName('api_key');
        $this->assertEquals('API Key', $apiKey->description);
    }
}
