<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\Support\FormatInferrer;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Analyzers\Support\RuleRequirementAnalyzer;
use LaravelSpectrum\Analyzers\Support\ValidationDescriptionGenerator;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ParameterBuilderTest extends TestCase
{
    private ParameterBuilder $builder;

    private TypeInference $typeInference;

    private RuleRequirementAnalyzer $ruleRequirementAnalyzer;

    private FormatInferrer $formatInferrer;

    private ValidationDescriptionGenerator $descriptionGenerator;

    private EnumAnalyzer $enumAnalyzer;

    private FileUploadAnalyzer $fileUploadAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeInference = new TypeInference;
        $this->ruleRequirementAnalyzer = new RuleRequirementAnalyzer;
        $this->formatInferrer = new FormatInferrer;
        $this->enumAnalyzer = new EnumAnalyzer;
        $this->descriptionGenerator = new ValidationDescriptionGenerator($this->enumAnalyzer);
        $this->fileUploadAnalyzer = new FileUploadAnalyzer;

        $this->builder = new ParameterBuilder(
            $this->typeInference,
            $this->ruleRequirementAnalyzer,
            $this->formatInferrer,
            $this->descriptionGenerator,
            $this->enumAnalyzer,
            $this->fileUploadAnalyzer
        );
    }

    // ========== buildFromRules tests ==========

    #[Test]
    public function it_builds_parameters_from_simple_rules(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $this->assertCount(2, $parameters);

        $nameParam = $this->findParameter($parameters, 'name');
        $this->assertNotNull($nameParam);
        $this->assertTrue($nameParam['required']);
        $this->assertEquals('string', $nameParam['type']);
        $this->assertEquals('body', $nameParam['in']);

        $emailParam = $this->findParameter($parameters, 'email');
        $this->assertNotNull($emailParam);
        $this->assertTrue($emailParam['required']);
        $this->assertEquals('string', $emailParam['type']);
        $this->assertEquals('email', $emailParam['format']);
    }

    #[Test]
    public function it_builds_parameters_with_array_rules(): void
    {
        $rules = [
            'age' => ['required', 'integer', 'min:0', 'max:150'],
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $ageParam = $this->findParameter($parameters, 'age');
        $this->assertNotNull($ageParam);
        $this->assertTrue($ageParam['required']);
        $this->assertEquals('integer', $ageParam['type']);
    }

    #[Test]
    public function it_skips_special_fields_starting_with_underscore(): void
    {
        $rules = [
            '_token' => 'required',
            '_notice' => 'string',
            'name' => 'required|string',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $this->assertCount(1, $parameters);
        $this->assertEquals('name', $parameters[0]['name']);
    }

    #[Test]
    public function it_uses_custom_attributes_for_descriptions(): void
    {
        $rules = [
            'email' => 'required|email',
        ];
        $attributes = [
            'email' => 'メールアドレス',
        ];

        $parameters = $this->builder->buildFromRules($rules, $attributes);

        $emailParam = $this->findParameter($parameters, 'email');
        $this->assertEquals('メールアドレス', $emailParam['description']);
    }

    #[Test]
    public function it_generates_description_when_no_attribute(): void
    {
        $rules = [
            'user_name' => 'required|string|max:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $param = $this->findParameter($parameters, 'user_name');
        $this->assertStringContainsString('User Name', $param['description']);
        $this->assertStringContainsString('(最大100文字)', $param['description']);
    }

    #[Test]
    public function it_infers_format_for_date_rules(): void
    {
        $rules = [
            'birth_date' => 'required|date',
            'created_at' => 'date_format:Y-m-d H:i:s',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $birthDate = $this->findParameter($parameters, 'birth_date');
        $this->assertEquals('date', $birthDate['format']);

        $createdAt = $this->findParameter($parameters, 'created_at');
        $this->assertEquals('date-time', $createdAt['format']);
    }

    #[Test]
    public function it_handles_optional_fields(): void
    {
        $rules = [
            'nickname' => 'nullable|string',
            'bio' => 'sometimes|string',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $nickname = $this->findParameter($parameters, 'nickname');
        $this->assertFalse($nickname['required']);

        $bio = $this->findParameter($parameters, 'bio');
        $this->assertFalse($bio['required']);
    }

    #[Test]
    public function it_detects_conditional_required_rules(): void
    {
        $rules = [
            'phone' => 'required_if:contact_method,phone|string',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $phone = $this->findParameter($parameters, 'phone');
        $this->assertTrue($phone['conditional_required']);
        $this->assertArrayHasKey('conditional_rules', $phone);
    }

    #[Test]
    public function it_generates_examples_for_fields(): void
    {
        $rules = [
            'email' => 'required|email',
            'age' => 'required|integer',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $email = $this->findParameter($parameters, 'email');
        $this->assertArrayHasKey('example', $email);

        $age = $this->findParameter($parameters, 'age');
        $this->assertArrayHasKey('example', $age);
        $this->assertIsInt($age['example']);
    }

    // ========== File upload tests ==========

    #[Test]
    public function it_builds_file_upload_parameters(): void
    {
        $rules = [
            'avatar' => 'required|file|mimes:jpg,png|max:2048',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $avatar = $this->findParameter($parameters, 'avatar');
        $this->assertNotNull($avatar);
        $this->assertEquals('file', $avatar['type']);
        $this->assertEquals('binary', $avatar['format']);
        $this->assertArrayHasKey('file_info', $avatar);
    }

    #[Test]
    public function it_builds_image_upload_parameters(): void
    {
        $rules = [
            'photo' => 'required|image|dimensions:min_width=100,min_height=100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $photo = $this->findParameter($parameters, 'photo');
        $this->assertEquals('file', $photo['type']);
        $this->assertArrayHasKey('file_info', $photo);
    }

    // ========== buildFromConditionalRules tests ==========

    #[Test]
    public function it_builds_parameters_from_conditional_rules(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['type' => 'credit'],
                    'rules' => [
                        'card_number' => 'required|string|size:16',
                    ],
                ],
                [
                    'conditions' => ['type' => 'bank'],
                    'rules' => [
                        'account_number' => 'required|string',
                    ],
                ],
            ],
            'merged_rules' => [
                'card_number' => ['required', 'string', 'size:16'],
                'account_number' => ['required', 'string'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertCount(2, $parameters);

        $cardNumber = $this->findParameter($parameters, 'card_number');
        $this->assertNotNull($cardNumber);
        $this->assertArrayHasKey('conditional_rules', $cardNumber);

        $accountNumber = $this->findParameter($parameters, 'account_number');
        $this->assertNotNull($accountNumber);
    }

    #[Test]
    public function it_merges_rules_from_multiple_conditions(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['status' => 'active'],
                    'rules' => [
                        'email' => 'required|email',
                    ],
                ],
                [
                    'conditions' => ['status' => 'inactive'],
                    'rules' => [
                        'email' => 'nullable|email',
                    ],
                ],
            ],
            'merged_rules' => [
                'email' => ['required', 'nullable', 'email'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $email = $this->findParameter($parameters, 'email');
        $this->assertCount(2, $email['conditional_rules']);
    }

    #[Test]
    public function it_uses_attributes_for_conditional_parameters(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => [],
                    'rules' => [
                        'payment_method' => 'required|string',
                    ],
                ],
            ],
            'merged_rules' => [
                'payment_method' => ['required', 'string'],
            ],
        ];
        $attributes = [
            'payment_method' => '支払い方法',
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules, $attributes);

        $payment = $this->findParameter($parameters, 'payment_method');
        $this->assertEquals('支払い方法', $payment['description']);
    }

    // ========== Enum tests ==========

    #[Test]
    public function it_detects_enum_rules(): void
    {
        // Create a mock enum analyzer that returns enum info
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->andReturnUsing(function ($rule) {
                if (is_string($rule) && str_contains($rule, 'UserStatus')) {
                    return [
                        'class' => 'App\\Enums\\UserStatus',
                        'values' => ['active', 'inactive'],
                        'type' => 'string',
                    ];
                }

                return null;
            });

        $builder = new ParameterBuilder(
            $this->typeInference,
            $this->ruleRequirementAnalyzer,
            $this->formatInferrer,
            new ValidationDescriptionGenerator($enumAnalyzer),
            $enumAnalyzer,
            $this->fileUploadAnalyzer
        );

        $rules = [
            'status' => 'required|App\\Enums\\UserStatus',
        ];

        $parameters = $builder->buildFromRules($rules);

        $status = $this->findParameter($parameters, 'status');
        $this->assertArrayHasKey('enum', $status);
        $this->assertEquals('App\\Enums\\UserStatus', $status['enum']['class']);
    }

    // ========== Edge cases ==========

    #[Test]
    public function it_handles_empty_rules(): void
    {
        $parameters = $this->builder->buildFromRules([]);

        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }

    #[Test]
    public function it_handles_empty_conditional_rules(): void
    {
        $conditionalRules = [
            'rules_sets' => [],
            'merged_rules' => [],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }

    #[Test]
    public function it_preserves_validation_rules_in_output(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $name = $this->findParameter($parameters, 'name');
        $this->assertEquals(['required', 'string', 'max:255'], $name['validation']);
    }

    #[Test]
    public function it_handles_nested_field_names(): void
    {
        $rules = [
            'user.name' => 'required|string',
            'items.*.price' => 'required|numeric',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $this->assertCount(2, $parameters);
        $this->assertNotNull($this->findParameter($parameters, 'user.name'));
        $this->assertNotNull($this->findParameter($parameters, 'items.*.price'));
    }

    #[Test]
    public function it_infers_types_correctly(): void
    {
        $rules = [
            'count' => 'integer',
            'price' => 'numeric',
            'is_active' => 'boolean',
            'tags' => 'array',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $this->assertEquals('integer', $this->findParameter($parameters, 'count')['type']);
        $this->assertEquals('number', $this->findParameter($parameters, 'price')['type']);
        $this->assertEquals('boolean', $this->findParameter($parameters, 'is_active')['type']);
        $this->assertEquals('array', $this->findParameter($parameters, 'tags')['type']);
    }

    // ========== Malformed input edge cases ==========

    #[Test]
    public function it_handles_conditional_rules_without_rules_sets_key(): void
    {
        $conditionalRules = [
            'merged_rules' => ['field' => ['required']],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }

    #[Test]
    public function it_handles_conditional_rules_without_merged_rules_key(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                ['conditions' => [], 'rules' => ['field' => 'required']],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }

    #[Test]
    public function it_handles_conditional_rules_with_non_array_rules_sets(): void
    {
        $conditionalRules = [
            'rules_sets' => 'invalid',
            'merged_rules' => [],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }

    #[Test]
    public function it_handles_conditional_rules_with_non_array_merged_rules(): void
    {
        $conditionalRules = [
            'rules_sets' => [],
            'merged_rules' => 'invalid',
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }

    #[Test]
    public function it_skips_malformed_rule_sets_without_rules_key(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                ['conditions' => ['type' => 'valid'], 'rules' => ['email' => 'required|email']],
                ['conditions' => ['type' => 'invalid']],
                ['conditions' => ['type' => 'also_invalid'], 'rules' => 'not_an_array'],
            ],
            'merged_rules' => [
                'email' => ['required', 'email'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertCount(1, $parameters);
        $email = $this->findParameter($parameters, 'email');
        $this->assertNotNull($email);
    }

    #[Test]
    public function it_skips_fields_in_merged_rules_not_in_rules_sets(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                ['conditions' => [], 'rules' => ['existing_field' => 'required|string']],
            ],
            'merged_rules' => [
                'existing_field' => ['required', 'string'],
                'orphaned_field' => ['required', 'email'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertCount(1, $parameters);
        $this->assertNotNull($this->findParameter($parameters, 'existing_field'));
        $this->assertNull($this->findParameter($parameters, 'orphaned_field'));
    }

    #[Test]
    public function it_handles_rule_sets_without_conditions_key(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                ['rules' => ['field' => 'required|string']],
            ],
            'merged_rules' => [
                'field' => ['required', 'string'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $this->assertCount(1, $parameters);
        $field = $this->findParameter($parameters, 'field');
        $this->assertNotNull($field);
        $this->assertArrayHasKey('conditional_rules', $field);
        $this->assertEquals([], $field['conditional_rules'][0]['conditions']);
    }

    // ========== Suggested improvements tests ==========

    #[Test]
    public function it_detects_enum_in_conditional_rules(): void
    {
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->andReturnUsing(function ($rule) {
                if (is_string($rule) && str_contains($rule, 'PaymentType')) {
                    return [
                        'class' => 'App\\Enums\\PaymentType',
                        'values' => ['credit', 'debit'],
                        'type' => 'string',
                    ];
                }

                return null;
            });

        $builder = new ParameterBuilder(
            $this->typeInference,
            $this->ruleRequirementAnalyzer,
            $this->formatInferrer,
            new ValidationDescriptionGenerator($enumAnalyzer),
            $enumAnalyzer,
            $this->fileUploadAnalyzer
        );

        $conditionalRules = [
            'rules_sets' => [
                ['conditions' => [], 'rules' => ['payment' => 'required|App\\Enums\\PaymentType']],
            ],
            'merged_rules' => [
                'payment' => ['required', 'App\\Enums\\PaymentType'],
            ],
        ];

        $parameters = $builder->buildFromConditionalRules($conditionalRules);

        $payment = $this->findParameter($parameters, 'payment');
        $this->assertArrayHasKey('enum', $payment);
        $this->assertEquals('App\\Enums\\PaymentType', $payment['enum']['class']);
    }

    #[Test]
    public function it_uses_custom_attributes_for_file_upload_descriptions(): void
    {
        $rules = [
            'document' => 'required|file|mimes:pdf',
        ];
        $attributes = [
            'document' => 'PDFドキュメント',
        ];

        $parameters = $this->builder->buildFromRules($rules, $attributes);

        $document = $this->findParameter($parameters, 'document');
        $this->assertStringContainsString('PDFドキュメント', $document['description']);
    }

    #[Test]
    public function it_passes_namespace_and_use_statements_to_enum_analyzer(): void
    {
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        // Called by both ParameterBuilder.findEnumInfo() and ValidationDescriptionGenerator.generateDescription()
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->with('required', 'App\\Http\\Requests', ['Status' => 'App\\Enums\\Status'])
            ->andReturn(null);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->with('Status', 'App\\Http\\Requests', ['Status' => 'App\\Enums\\Status'])
            ->andReturn(['class' => 'App\\Enums\\Status', 'values' => ['active'], 'type' => 'string']);

        $builder = new ParameterBuilder(
            $this->typeInference,
            $this->ruleRequirementAnalyzer,
            $this->formatInferrer,
            new ValidationDescriptionGenerator($enumAnalyzer),
            $enumAnalyzer,
            $this->fileUploadAnalyzer
        );

        $rules = ['status' => 'required|Status'];

        $parameters = $builder->buildFromRules($rules, [], 'App\\Http\\Requests', ['Status' => 'App\\Enums\\Status']);

        $status = $this->findParameter($parameters, 'status');
        $this->assertArrayHasKey('enum', $status);
        $this->assertEquals('App\\Enums\\Status', $status['enum']['class']);
    }

    // ========== Helper methods ==========

    private function findParameter(array $parameters, string $name): ?array
    {
        foreach ($parameters as $parameter) {
            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }

        return null;
    }
}
