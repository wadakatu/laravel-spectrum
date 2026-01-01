<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\Support\FormatInferrer;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Analyzers\Support\RuleRequirementAnalyzer;
use LaravelSpectrum\Analyzers\Support\ValidationDescriptionGenerator;
use LaravelSpectrum\DTO\EnumBackingType;
use LaravelSpectrum\DTO\EnumInfo;
use LaravelSpectrum\DTO\ParameterDefinition;
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
        $this->assertInstanceOf(ParameterDefinition::class, $nameParam);
        $this->assertTrue($nameParam->required);
        $this->assertEquals('string', $nameParam->type);
        $this->assertEquals('body', $nameParam->in);

        $emailParam = $this->findParameter($parameters, 'email');
        $this->assertNotNull($emailParam);
        $this->assertInstanceOf(ParameterDefinition::class, $emailParam);
        $this->assertTrue($emailParam->required);
        $this->assertEquals('string', $emailParam->type);
        $this->assertEquals('email', $emailParam->format);
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
        $this->assertTrue($ageParam->required);
        $this->assertEquals('integer', $ageParam->type);
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
        $this->assertEquals('name', $parameters[0]->name);
    }

    #[Test]
    public function it_excludes_fields_with_exclude_rule(): void
    {
        // Only plain 'exclude' rule should exclude the field from schema.
        // Conditional excludes (exclude_if, exclude_unless, etc.) should remain
        // because the field may be included depending on conditions.
        $rules = [
            'internal_notes' => ['exclude'],  // Unconditionally excluded - should NOT appear
            'debug_info' => ['exclude_if:environment,production'],  // Conditionally excluded - should appear
            'legacy_field' => ['exclude_unless:include_legacy,true'],  // Conditionally excluded - should appear
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // internal_notes should be excluded, but conditional excludes should remain
        $this->assertCount(4, $parameters);

        $names = array_map(fn ($p) => $p->name, $parameters);
        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
        $this->assertContains('debug_info', $names);  // Conditional exclude - included
        $this->assertContains('legacy_field', $names);  // Conditional exclude - included
        $this->assertNotContains('internal_notes', $names);  // Plain exclude - removed
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
        $this->assertEquals('メールアドレス', $emailParam->description);
    }

    #[Test]
    public function it_generates_description_when_no_attribute(): void
    {
        $rules = [
            'user_name' => 'required|string|max:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $param = $this->findParameter($parameters, 'user_name');
        $this->assertStringContainsString('User Name', $param->description);
        $this->assertStringContainsString('(最大100文字)', $param->description);
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
        $this->assertEquals('date', $birthDate->format);

        $createdAt = $this->findParameter($parameters, 'created_at');
        $this->assertEquals('date-time', $createdAt->format);
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
        $this->assertFalse($nickname->required);

        $bio = $this->findParameter($parameters, 'bio');
        $this->assertFalse($bio->required);
    }

    #[Test]
    public function it_detects_conditional_required_rules(): void
    {
        $rules = [
            'phone' => 'required_if:contact_method,phone|string',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $phone = $this->findParameter($parameters, 'phone');
        $this->assertTrue($phone->conditionalRequired);
        $this->assertTrue($phone->hasConditionalRules());
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
        $this->assertNotNull($email->example);

        $age = $this->findParameter($parameters, 'age');
        $this->assertNotNull($age->example);
        $this->assertIsInt($age->example);
    }

    #[Test]
    public function it_generates_boolean_example_for_accepted_rule(): void
    {
        $rules = [
            'terms' => 'required|accepted',
            'terms_if' => 'accepted_if:other_field,value',
            'decline_test' => 'declined',
            'decline_if_test' => 'declined_if:other_field,value',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // accepted should generate true
        $terms = $this->findParameter($parameters, 'terms');
        $this->assertNotNull($terms);
        $this->assertEquals('boolean', $terms->type);
        $this->assertTrue($terms->example, 'accepted rule should generate example: true');

        // accepted_if should also generate true
        $termsIf = $this->findParameter($parameters, 'terms_if');
        $this->assertNotNull($termsIf);
        $this->assertEquals('boolean', $termsIf->type);
        $this->assertTrue($termsIf->example, 'accepted_if rule should generate example: true');

        // declined should generate false
        $decline = $this->findParameter($parameters, 'decline_test');
        $this->assertNotNull($decline);
        $this->assertEquals('boolean', $decline->type);
        $this->assertFalse($decline->example, 'declined rule should generate example: false');

        // declined_if should also generate false
        $declineIf = $this->findParameter($parameters, 'decline_if_test');
        $this->assertNotNull($declineIf);
        $this->assertEquals('boolean', $declineIf->type);
        $this->assertFalse($declineIf->example, 'declined_if rule should generate example: false');
    }

    #[Test]
    public function it_generates_numeric_example_for_decimal_rule_with_parameters(): void
    {
        $rules = [
            'price' => 'required|decimal:2',
            'weight' => 'nullable|decimal:0,3',
            'precise' => 'decimal:4',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // decimal:2 should generate example with exactly 2 decimal places
        $price = $this->findParameter($parameters, 'price');
        $this->assertNotNull($price);
        $this->assertEquals('number', $price->type);
        $this->assertEquals(19.99, $price->example, 'decimal:2 should generate 19.99');

        // decimal:0,3 should generate example with max (3) decimal places
        $weight = $this->findParameter($parameters, 'weight');
        $this->assertNotNull($weight);
        $this->assertEquals('number', $weight->type);
        $this->assertEquals(19.999, $weight->example, 'decimal:0,3 should generate 19.999');

        // decimal:4 should generate example with exactly 4 decimal places
        $precise = $this->findParameter($parameters, 'precise');
        $this->assertNotNull($precise);
        $this->assertEquals('number', $precise->type);
        $this->assertEquals(19.9999, $precise->example, 'decimal:4 should generate 19.9999');
    }

    #[Test]
    public function it_generates_date_format_for_date_rules(): void
    {
        $rules = [
            'publish_at' => ['nullable', 'date', 'after:now'],
            'timestamp' => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // date rule should have format: date
        $publishAt = $this->findParameter($parameters, 'publish_at');
        $this->assertNotNull($publishAt);
        $this->assertEquals('string', $publishAt->type);
        $this->assertEquals('date', $publishAt->format, 'date rule should set format to "date"');

        // date_format with time should have format: date-time
        $timestamp = $this->findParameter($parameters, 'timestamp');
        $this->assertNotNull($timestamp);
        $this->assertEquals('string', $timestamp->type);
        $this->assertEquals('date-time', $timestamp->format, 'date_format with time should set format to "date-time"');

        // date_format without time should have format: date
        $birthDate = $this->findParameter($parameters, 'birth_date');
        $this->assertNotNull($birthDate);
        $this->assertEquals('string', $birthDate->type);
        $this->assertEquals('date', $birthDate->format, 'date_format:Y-m-d should set format to "date"');
    }

    #[Test]
    public function it_generates_format_for_ip_rules(): void
    {
        $rules = [
            'ip_address' => 'required|ip',
            'server_ipv4' => 'required|ipv4',
            'server_ipv6' => 'required|ipv6',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // ip rule should have format: ipv4
        $ipAddress = $this->findParameter($parameters, 'ip_address');
        $this->assertNotNull($ipAddress);
        $this->assertEquals('string', $ipAddress->type);
        $this->assertEquals('ipv4', $ipAddress->format, 'ip rule should set format to "ipv4"');

        // ipv4 rule should have format: ipv4
        $serverIpv4 = $this->findParameter($parameters, 'server_ipv4');
        $this->assertNotNull($serverIpv4);
        $this->assertEquals('string', $serverIpv4->type);
        $this->assertEquals('ipv4', $serverIpv4->format, 'ipv4 rule should set format to "ipv4"');

        // ipv6 rule should have format: ipv6
        $serverIpv6 = $this->findParameter($parameters, 'server_ipv6');
        $this->assertNotNull($serverIpv6);
        $this->assertEquals('string', $serverIpv6->type);
        $this->assertEquals('ipv6', $serverIpv6->format, 'ipv6 rule should set format to "ipv6"');
    }

    #[Test]
    public function it_generates_format_for_ulid_and_uuid_rules(): void
    {
        $rules = [
            'external_id' => 'required|uuid',
            'tracking_id' => 'required|ulid',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // uuid rule should have format: uuid
        $externalId = $this->findParameter($parameters, 'external_id');
        $this->assertNotNull($externalId);
        $this->assertEquals('string', $externalId->type);
        $this->assertEquals('uuid', $externalId->format, 'uuid rule should set format to "uuid"');

        // ulid rule should have format: ulid
        $trackingId = $this->findParameter($parameters, 'tracking_id');
        $this->assertNotNull($trackingId);
        $this->assertEquals('string', $trackingId->type);
        $this->assertEquals('ulid', $trackingId->format, 'ulid rule should set format to "ulid"');
    }

    #[Test]
    public function it_generates_format_for_mac_address_rule(): void
    {
        $rules = [
            'device_mac' => 'required|mac_address',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // mac_address rule should have format
        $deviceMac = $this->findParameter($parameters, 'device_mac');
        $this->assertNotNull($deviceMac);
        $this->assertEquals('string', $deviceMac->type);
        $this->assertEquals('mac', $deviceMac->format, 'mac_address rule should set format to "mac"');
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
        $this->assertEquals('file', $avatar->type);
        $this->assertEquals('binary', $avatar->format);
        $this->assertNotNull($avatar->fileInfo);
        $this->assertTrue($avatar->isFileUpload());
    }

    #[Test]
    public function it_builds_image_upload_parameters(): void
    {
        $rules = [
            'photo' => 'required|image|dimensions:min_width=100,min_height=100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $photo = $this->findParameter($parameters, 'photo');
        $this->assertEquals('file', $photo->type);
        $this->assertNotNull($photo->fileInfo);
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
        $this->assertTrue($cardNumber->hasConditionalRules());

        $accountNumber = $this->findParameter($parameters, 'account_number');
        $this->assertNotNull($accountNumber);
    }

    #[Test]
    public function it_generates_format_for_conditional_parameters_with_date_rules(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['mode' => 'schedule'],
                    'rules' => [
                        'publish_at' => 'required|date|after:now',
                        'email' => 'required|email',
                    ],
                ],
                [
                    'conditions' => ['mode' => 'immediate'],
                    'rules' => [
                        'publish_at' => 'nullable|date',
                    ],
                ],
            ],
            'merged_rules' => [
                'publish_at' => ['required', 'nullable', 'date', 'after:now'],
                'email' => ['required', 'email'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        // Check publish_at has format: date
        $publishAt = $this->findParameter($parameters, 'publish_at');
        $this->assertNotNull($publishAt);
        $this->assertEquals('date', $publishAt->format, 'date rule should set format to "date"');

        // Check email has format: email
        $email = $this->findParameter($parameters, 'email');
        $this->assertNotNull($email);
        $this->assertEquals('email', $email->format, 'email rule should set format to "email"');

        // Verify format is included in toArray output
        $publishAtArray = $publishAt->toArray();
        $this->assertArrayHasKey('format', $publishAtArray);
        $this->assertEquals('date', $publishAtArray['format']);
    }

    #[Test]
    public function it_excludes_fields_with_exclude_rule_in_conditional_rules(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['mode' => 'debug'],
                    'rules' => [
                        'debug_info' => 'required|string',
                        'internal_notes' => 'exclude',  // Should be excluded
                        'email' => 'required|email',
                    ],
                ],
                [
                    'conditions' => ['mode' => 'production'],
                    'rules' => [
                        'email' => 'required|email',
                    ],
                ],
            ],
            'merged_rules' => [
                'debug_info' => ['required', 'string'],
                'internal_notes' => ['exclude'],  // Should be excluded
                'email' => ['required', 'email'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $names = array_map(fn ($p) => $p->name, $parameters);
        $this->assertContains('debug_info', $names);
        $this->assertContains('email', $names);
        $this->assertNotContains('internal_notes', $names, 'Fields with exclude rule should not appear');
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
        $this->assertCount(2, $email->conditionalRules);
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
        $this->assertEquals('支払い方法', $payment->description);
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
                    return new EnumInfo(
                        class: 'App\\Enums\\UserStatus',
                        values: ['active', 'inactive'],
                        backingType: EnumBackingType::STRING,
                    );
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
        $this->assertTrue($status->hasEnum());
        $this->assertInstanceOf(EnumInfo::class, $status->enum);
        $this->assertEquals('App\\Enums\\UserStatus', $status->enum->class);
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
        $this->assertEquals(['required', 'string', 'max:255'], $name->validation);
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

        $this->assertEquals('integer', $this->findParameter($parameters, 'count')->type);
        $this->assertEquals('number', $this->findParameter($parameters, 'price')->type);
        $this->assertEquals('boolean', $this->findParameter($parameters, 'is_active')->type);
        $this->assertEquals('array', $this->findParameter($parameters, 'tags')->type);
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
        $this->assertTrue($field->hasConditionalRules());
        $this->assertEquals([], $field->conditionalRules[0]['conditions']);
    }

    // ========== Suggested improvements tests ==========

    #[Test]
    public function it_detects_enum_in_conditional_rules(): void
    {
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->andReturnUsing(function ($rule) {
                if (is_string($rule) && str_contains($rule, 'PaymentType')) {
                    return new EnumInfo(
                        class: 'App\\Enums\\PaymentType',
                        values: ['credit', 'debit'],
                        backingType: EnumBackingType::STRING,
                    );
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
        $this->assertTrue($payment->hasEnum());
        $this->assertInstanceOf(EnumInfo::class, $payment->enum);
        $this->assertEquals('App\\Enums\\PaymentType', $payment->enum->class);
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
        $this->assertStringContainsString('PDFドキュメント', $document->description);
    }

    #[Test]
    public function it_passes_namespace_and_use_statements_to_enum_analyzer(): void
    {
        $enumInfo = new EnumInfo(
            class: 'App\\Enums\\Status',
            values: ['active'],
            backingType: EnumBackingType::STRING,
        );
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        // Called by both ParameterBuilder.findEnumInfo() and ValidationDescriptionGenerator.generateDescription()
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->with('required', 'App\\Http\\Requests', ['Status' => 'App\\Enums\\Status'])
            ->andReturn(null);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->with('Status', 'App\\Http\\Requests', ['Status' => 'App\\Enums\\Status'])
            ->andReturn($enumInfo);

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
        $this->assertTrue($status->hasEnum());
        $this->assertInstanceOf(EnumInfo::class, $status->enum);
        $this->assertEquals('App\\Enums\\Status', $status->enum->class);
    }

    #[Test]
    public function it_extracts_regex_pattern_without_pcre_delimiters(): void
    {
        $rules = [
            'phone' => 'required|regex:/^\\+?[1-9]\\d{1,14}$/',  // E.164 phone format
            'zip_code' => 'regex:/^\\d{5}(-\\d{4})?$/',  // US zip code
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $phone = $this->findParameter($parameters, 'phone');
        $this->assertNotNull($phone);
        // PCRE delimiters should be stripped
        $this->assertEquals('^\\+?[1-9]\\d{1,14}$', $phone->pattern);

        $zipCode = $this->findParameter($parameters, 'zip_code');
        $this->assertNotNull($zipCode);
        $this->assertEquals('^\\d{5}(-\\d{4})?$', $zipCode->pattern);
    }

    #[Test]
    public function it_returns_null_pattern_when_no_regex_rule(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $name = $this->findParameter($parameters, 'name');
        $this->assertNotNull($name);
        $this->assertNull($name->pattern);
    }

    // ========== String Length Constraints (Issue #318) ==========

    #[Test]
    public function it_extracts_min_length_for_string_type(): void
    {
        $rules = [
            'title' => 'required|string|min:5',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $title = $this->findParameter($parameters, 'title');
        $this->assertNotNull($title);
        $this->assertEquals('string', $title->type);
        $this->assertEquals(5, $title->minLength);
    }

    #[Test]
    public function it_extracts_max_length_for_string_type(): void
    {
        $rules = [
            'title' => 'required|string|max:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $title = $this->findParameter($parameters, 'title');
        $this->assertNotNull($title);
        $this->assertEquals('string', $title->type);
        $this->assertEquals(100, $title->maxLength);
    }

    #[Test]
    public function it_extracts_both_min_and_max_length_for_string_type(): void
    {
        $rules = [
            'title' => 'required|string|min:5|max:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $title = $this->findParameter($parameters, 'title');
        $this->assertNotNull($title);
        $this->assertEquals('string', $title->type);
        $this->assertEquals(5, $title->minLength);
        $this->assertEquals(100, $title->maxLength);
    }

    #[Test]
    public function it_extracts_size_as_both_min_and_max_length_for_string_type(): void
    {
        $rules = [
            'code' => 'required|string|size:6',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $code = $this->findParameter($parameters, 'code');
        $this->assertNotNull($code);
        $this->assertEquals('string', $code->type);
        $this->assertEquals(6, $code->minLength);
        $this->assertEquals(6, $code->maxLength);
    }

    #[Test]
    public function it_does_not_extract_min_max_as_length_for_numeric_types(): void
    {
        $rules = [
            'age' => 'required|integer|min:0|max:120',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $age = $this->findParameter($parameters, 'age');
        $this->assertNotNull($age);
        $this->assertEquals('integer', $age->type);
        // For numeric types, min/max should NOT become minLength/maxLength
        $this->assertNull($age->minLength);
        $this->assertNull($age->maxLength);
    }

    #[Test]
    public function it_does_not_extract_min_max_as_length_for_array_types(): void
    {
        $rules = [
            'tags' => 'required|array|min:1|max:10',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $tags = $this->findParameter($parameters, 'tags');
        $this->assertNotNull($tags);
        $this->assertEquals('array', $tags->type);
        // For array types, min/max should NOT become minLength/maxLength
        $this->assertNull($tags->minLength);
        $this->assertNull($tags->maxLength);
    }

    #[Test]
    public function it_extracts_min_max_length_for_conditional_string_parameters(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['type' => 'credit'],
                    'rules' => [
                        'card_number' => ['required', 'string', 'size:16'],
                    ],
                ],
            ],
            'merged_rules' => [
                'card_number' => ['required', 'string', 'size:16'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $cardNumber = $this->findParameter($parameters, 'card_number');
        $this->assertNotNull($cardNumber);
        $this->assertEquals('string', $cardNumber->type);
        $this->assertEquals(16, $cardNumber->minLength);
        $this->assertEquals(16, $cardNumber->maxLength);
    }

    // ========== Numeric Constraints (Issue #314) ==========

    #[Test]
    public function it_extracts_minimum_for_integer_type(): void
    {
        $rules = [
            'age' => 'required|integer|min:18',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $age = $this->findParameter($parameters, 'age');
        $this->assertNotNull($age);
        $this->assertEquals('integer', $age->type);
        $this->assertEquals(18, $age->minimum);
    }

    #[Test]
    public function it_extracts_maximum_for_integer_type(): void
    {
        $rules = [
            'age' => 'required|integer|max:120',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $age = $this->findParameter($parameters, 'age');
        $this->assertNotNull($age);
        $this->assertEquals('integer', $age->type);
        $this->assertEquals(120, $age->maximum);
    }

    #[Test]
    public function it_extracts_both_minimum_and_maximum_for_numeric_type(): void
    {
        $rules = [
            'salary' => 'required|numeric|min:0|max:10000000',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $salary = $this->findParameter($parameters, 'salary');
        $this->assertNotNull($salary);
        $this->assertEquals('number', $salary->type);
        $this->assertEquals(0, $salary->minimum);
        $this->assertEquals(10000000, $salary->maximum);
    }

    #[Test]
    public function it_extracts_between_as_minimum_and_maximum_for_integer_type(): void
    {
        $rules = [
            'quantity' => 'required|integer|between:1,100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $quantity = $this->findParameter($parameters, 'quantity');
        $this->assertNotNull($quantity);
        $this->assertEquals('integer', $quantity->type);
        $this->assertEquals(1, $quantity->minimum);
        $this->assertEquals(100, $quantity->maximum);
    }

    #[Test]
    public function it_extracts_gte_as_minimum_for_numeric_type(): void
    {
        $rules = [
            'price' => 'required|numeric|gte:0',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $price = $this->findParameter($parameters, 'price');
        $this->assertNotNull($price);
        $this->assertEquals('number', $price->type);
        $this->assertEquals(0, $price->minimum);
    }

    #[Test]
    public function it_extracts_lte_as_maximum_for_numeric_type(): void
    {
        $rules = [
            'discount' => 'required|numeric|lte:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $discount = $this->findParameter($parameters, 'discount');
        $this->assertNotNull($discount);
        $this->assertEquals('number', $discount->type);
        $this->assertEquals(100, $discount->maximum);
    }

    #[Test]
    public function it_extracts_gt_as_exclusive_minimum_for_integer_type(): void
    {
        $rules = [
            'count' => 'required|integer|gt:0',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $count = $this->findParameter($parameters, 'count');
        $this->assertNotNull($count);
        $this->assertEquals('integer', $count->type);
        $this->assertEquals(0, $count->exclusiveMinimum);
    }

    #[Test]
    public function it_extracts_lt_as_exclusive_maximum_for_integer_type(): void
    {
        $rules = [
            'index' => 'required|integer|lt:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $index = $this->findParameter($parameters, 'index');
        $this->assertNotNull($index);
        $this->assertEquals('integer', $index->type);
        $this->assertEquals(100, $index->exclusiveMaximum);
    }

    #[Test]
    public function it_does_not_extract_min_max_as_numeric_constraints_for_string_types(): void
    {
        $rules = [
            'name' => 'required|string|min:5|max:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $name = $this->findParameter($parameters, 'name');
        $this->assertNotNull($name);
        $this->assertEquals('string', $name->type);
        // For string types, min/max should become minLength/maxLength, NOT minimum/maximum
        $this->assertNull($name->minimum);
        $this->assertNull($name->maximum);
        $this->assertEquals(5, $name->minLength);
        $this->assertEquals(100, $name->maxLength);
    }

    #[Test]
    public function it_extracts_numeric_constraints_for_conditional_parameters(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['type' => 'premium'],
                    'rules' => [
                        'price' => ['required', 'numeric', 'min:100', 'max:10000'],
                    ],
                ],
            ],
            'merged_rules' => [
                'price' => ['required', 'numeric', 'min:100', 'max:10000'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $price = $this->findParameter($parameters, 'price');
        $this->assertNotNull($price);
        $this->assertEquals('number', $price->type);
        $this->assertEquals(100, $price->minimum);
        $this->assertEquals(10000, $price->maximum);
    }

    #[Test]
    public function it_extracts_float_minimum_and_maximum_for_number_type(): void
    {
        $rules = [
            'price' => 'required|numeric|min:0.01|max:999.99',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $price = $this->findParameter($parameters, 'price');
        $this->assertNotNull($price);
        $this->assertEquals('number', $price->type);
        $this->assertSame(0.01, $price->minimum);
        $this->assertSame(999.99, $price->maximum);
    }

    #[Test]
    public function it_extracts_float_between_as_minimum_and_maximum(): void
    {
        $rules = [
            'rate' => 'required|numeric|between:0.5,100.5',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $rate = $this->findParameter($parameters, 'rate');
        $this->assertNotNull($rate);
        $this->assertEquals('number', $rate->type);
        $this->assertSame(0.5, $rate->minimum);
        $this->assertSame(100.5, $rate->maximum);
    }

    // ========== Array Items Constraints (Issue #315) ==========

    #[Test]
    public function it_extracts_min_items_for_array_type(): void
    {
        $rules = [
            'items' => 'required|array|min:1',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $items = $this->findParameter($parameters, 'items');
        $this->assertNotNull($items);
        $this->assertEquals('array', $items->type);
        $this->assertEquals(1, $items->minItems);
    }

    #[Test]
    public function it_extracts_max_items_for_array_type(): void
    {
        $rules = [
            'tags' => 'nullable|array|max:10',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $tags = $this->findParameter($parameters, 'tags');
        $this->assertNotNull($tags);
        $this->assertEquals('array', $tags->type);
        $this->assertEquals(10, $tags->maxItems);
    }

    #[Test]
    public function it_extracts_both_min_and_max_items_for_array_type(): void
    {
        $rules = [
            'addresses' => 'required|array|min:1|max:5',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $addresses = $this->findParameter($parameters, 'addresses');
        $this->assertNotNull($addresses);
        $this->assertEquals('array', $addresses->type);
        $this->assertEquals(1, $addresses->minItems);
        $this->assertEquals(5, $addresses->maxItems);
    }

    #[Test]
    public function it_extracts_size_as_both_min_and_max_items_for_array_type(): void
    {
        $rules = [
            'coordinates' => 'required|array|size:2',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $coordinates = $this->findParameter($parameters, 'coordinates');
        $this->assertNotNull($coordinates);
        $this->assertEquals('array', $coordinates->type);
        $this->assertEquals(2, $coordinates->minItems);
        $this->assertEquals(2, $coordinates->maxItems);
    }

    #[Test]
    public function it_does_not_extract_min_max_as_items_for_string_types(): void
    {
        $rules = [
            'name' => 'required|string|min:5|max:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $name = $this->findParameter($parameters, 'name');
        $this->assertNotNull($name);
        $this->assertEquals('string', $name->type);
        // For string types, min/max should become minLength/maxLength, NOT minItems/maxItems
        $this->assertNull($name->minItems);
        $this->assertNull($name->maxItems);
        $this->assertEquals(5, $name->minLength);
        $this->assertEquals(100, $name->maxLength);
    }

    #[Test]
    public function it_extracts_array_items_constraints_for_conditional_parameters(): void
    {
        $conditionalRules = [
            'rules_sets' => [
                [
                    'conditions' => ['type' => 'bulk'],
                    'rules' => [
                        'items' => ['required', 'array', 'min:1', 'max:100'],
                    ],
                ],
            ],
            'merged_rules' => [
                'items' => ['required', 'array', 'min:1', 'max:100'],
            ],
        ];

        $parameters = $this->builder->buildFromConditionalRules($conditionalRules);

        $items = $this->findParameter($parameters, 'items');
        $this->assertNotNull($items);
        $this->assertEquals('array', $items->type);
        $this->assertEquals(1, $items->minItems);
        $this->assertEquals(100, $items->maxItems);
    }

    #[Test]
    public function it_extracts_between_as_min_and_max_items_for_array_type(): void
    {
        $rules = [
            'tags' => 'required|array|between:1,10',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $tags = $this->findParameter($parameters, 'tags');
        $this->assertNotNull($tags);
        $this->assertEquals('array', $tags->type);
        $this->assertEquals(1, $tags->minItems);
        $this->assertEquals(10, $tags->maxItems);
    }

    #[Test]
    public function it_does_not_extract_min_max_as_items_for_integer_types(): void
    {
        $rules = [
            'quantity' => 'required|integer|min:1|max:100',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $quantity = $this->findParameter($parameters, 'quantity');
        $this->assertNotNull($quantity);
        $this->assertEquals('integer', $quantity->type);
        // For integer types, min/max should become minimum/maximum, NOT minItems/maxItems
        $this->assertNull($quantity->minItems);
        $this->assertNull($quantity->maxItems);
        $this->assertEquals(1, $quantity->minimum);
        $this->assertEquals(100, $quantity->maximum);
    }

    // ========== Confirmed Rule (Issue #325) ==========

    #[Test]
    public function it_generates_confirmation_field_for_confirmed_rule(): void
    {
        $rules = [
            'password' => 'required|string|min:8|confirmed',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        // Should have both password and password_confirmation
        $password = $this->findParameter($parameters, 'password');
        $this->assertNotNull($password);
        $this->assertEquals('string', $password->type);
        $this->assertTrue($password->required);

        $passwordConfirmation = $this->findParameter($parameters, 'password_confirmation');
        $this->assertNotNull($passwordConfirmation, 'Confirmation field should be generated');
        $this->assertEquals('string', $passwordConfirmation->type);
        $this->assertTrue($passwordConfirmation->required);
    }

    #[Test]
    public function it_generates_confirmation_field_with_same_constraints(): void
    {
        $rules = [
            'secret' => 'required|string|min:6|max:50|confirmed',
        ];

        $parameters = $this->builder->buildFromRules($rules);

        $secret = $this->findParameter($parameters, 'secret');
        $secretConfirmation = $this->findParameter($parameters, 'secret_confirmation');

        $this->assertNotNull($secretConfirmation);
        // Confirmation field should have same type and length constraints
        $this->assertEquals($secret->type, $secretConfirmation->type);
        $this->assertEquals($secret->minLength, $secretConfirmation->minLength);
        $this->assertEquals($secret->maxLength, $secretConfirmation->maxLength);
    }

    // ========== Helper methods ==========

    /**
     * @param  array<ParameterDefinition>  $parameters
     */
    private function findParameter(array $parameters, string $name): ?ParameterDefinition
    {
        foreach ($parameters as $parameter) {
            if ($parameter->name === $name) {
                return $parameter;
            }
        }

        return null;
    }
}
