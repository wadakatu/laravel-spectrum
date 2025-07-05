<?php

namespace LaravelPrism\Tests\Unit;

use LaravelPrism\Analyzers\FormRequestAnalyzer;
use LaravelPrism\Support\TypeInference;
use LaravelPrism\Tests\TestCase;
use LaravelPrism\Tests\Fixtures\StoreUserRequest;
use Illuminate\Foundation\Http\FormRequest;

class FormRequestAnalyzerTest extends TestCase
{
    protected FormRequestAnalyzer $analyzer;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new FormRequestAnalyzer(new TypeInference());
    }
    
    /** @test */
    public function it_extracts_validation_rules_from_form_request()
    {
        // Act
        $parameters = $this->analyzer->analyze(StoreUserRequest::class);
        
        // Assert
        $parameterNames = array_column($parameters, 'name');
        $this->assertContains('name', $parameterNames);
        $this->assertContains('email', $parameterNames);
        
        $nameParam = $this->findParameterByName($parameters, 'name');
        $this->assertTrue($nameParam['required']);
        $this->assertEquals('string', $nameParam['type']);
    }
    
    /** @test */
    public function it_infers_types_from_validation_rules()
    {
        // Arrange - Create a test FormRequest
        $testRequestClass = new class extends FormRequest {
            public function rules(): array {
                return [
                    'age' => 'required|integer|min:0|max:150',
                    'price' => 'required|numeric|min:0',
                    'is_active' => 'required|boolean',
                    'tags' => 'required|array',
                    'email' => 'required|email',
                ];
            }
        };
        
        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));
        
        // Assert
        $this->assertEquals('integer', $this->findParameterByName($parameters, 'age')['type']);
        $this->assertEquals('number', $this->findParameterByName($parameters, 'price')['type']);
        $this->assertEquals('boolean', $this->findParameterByName($parameters, 'is_active')['type']);
        $this->assertEquals('array', $this->findParameterByName($parameters, 'tags')['type']);
        $this->assertEquals('string', $this->findParameterByName($parameters, 'email')['type']);
    }
    
    /** @test */
    public function it_extracts_descriptions_from_attributes_method()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest {
            public function rules(): array {
                return ['name' => 'required|string'];
            }
            
            public function attributes(): array {
                return ['name' => 'ユーザー名'];
            }
        };
        
        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));
        
        // Assert
        $nameParam = $this->findParameterByName($parameters, 'name');
        $this->assertEquals('ユーザー名', $nameParam['description']);
    }
    
    /** @test */
    public function it_handles_array_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest {
            public function rules(): array {
                return [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email'],
                ];
            }
        };
        
        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));
        
        // Assert
        $nameParam = $this->findParameterByName($parameters, 'name');
        $this->assertTrue($nameParam['required']);
        $this->assertEquals('string', $nameParam['type']);
        $this->assertContains('required', $nameParam['validation']);
        $this->assertContains('string', $nameParam['validation']);
        $this->assertContains('max:255', $nameParam['validation']);
    }
    
    /** @test */
    public function it_returns_empty_array_for_non_form_request()
    {
        // Act
        $parameters = $this->analyzer->analyze(\stdClass::class);
        
        // Assert
        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }
    
    /** @test */
    public function it_detects_optional_fields()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest {
            public function rules(): array {
                return [
                    'required_field' => 'required|string',
                    'optional_field' => 'sometimes|string',
                    'nullable_field' => 'nullable|string',
                ];
            }
        };
        
        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));
        
        // Assert
        $this->assertTrue($this->findParameterByName($parameters, 'required_field')['required']);
        $this->assertFalse($this->findParameterByName($parameters, 'optional_field')['required']);
        $this->assertFalse($this->findParameterByName($parameters, 'nullable_field')['required']);
    }
    
    private function findParameterByName(array $parameters, string $name): ?array
    {
        foreach ($parameters as $parameter) {
            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }
        return null;
    }
}