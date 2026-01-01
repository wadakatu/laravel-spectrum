<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConditionalValidationRulesTest extends TestCase
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
    public function it_handles_required_if_validation_rule()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'type' => 'required|in:personal,business',
                    'company_name' => 'required_if:type,business|string|max:255',
                    'tax_id' => 'required_if:type,business|string',
                    'personal_id' => 'required_if:type,personal|string',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $this->assertCount(4, $parameters);

        $typeParam = $this->findParameterByName($parameters, 'type');
        $this->assertTrue($typeParam['required']);
        $this->assertContains('in:personal,business', $typeParam['validation']);

        $companyParam = $this->findParameterByName($parameters, 'company_name');
        $this->assertFalse($companyParam['required']); // Conditionally required
        $this->assertContains('required_if:type,business', $companyParam['validation']);

        $taxIdParam = $this->findParameterByName($parameters, 'tax_id');
        $this->assertFalse($taxIdParam['required']);
        $this->assertContains('required_if:type,business', $taxIdParam['validation']);

        $personalIdParam = $this->findParameterByName($parameters, 'personal_id');
        $this->assertFalse($personalIdParam['required']);
        $this->assertContains('required_if:type,personal', $personalIdParam['validation']);
    }

    #[Test]
    public function it_handles_required_unless_validation_rule()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'subscription_type' => 'required|in:free,premium,enterprise',
                    'payment_method' => 'required_unless:subscription_type,free|string',
                    'billing_address' => 'required_unless:subscription_type,free|string',
                    'coupon_code' => 'nullable|string|required_unless:subscription_type,free,premium',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $paymentParam = $this->findParameterByName($parameters, 'payment_method');
        $this->assertFalse($paymentParam['required']);
        $this->assertContains('required_unless:subscription_type,free', $paymentParam['validation']);

        $billingParam = $this->findParameterByName($parameters, 'billing_address');
        $this->assertFalse($billingParam['required']);

        $couponParam = $this->findParameterByName($parameters, 'coupon_code');
        $this->assertFalse($couponParam['required']);
        $this->assertContains('nullable', $couponParam['validation']);
    }

    #[Test]
    public function it_handles_required_with_and_without_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'email' => 'sometimes|email',
                    'phone' => 'sometimes|string',
                    'preferred_contact' => 'required_with:email,phone|in:email,phone,both',
                    'emergency_contact' => 'required_without:email,phone|string',
                    'notification_preferences' => 'required_with_all:email,phone|array',
                    'alternative_contact' => 'required_without_all:email,phone|string',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $preferredParam = $this->findParameterByName($parameters, 'preferred_contact');
        $this->assertFalse($preferredParam['required']);
        $this->assertContains('required_with:email,phone', $preferredParam['validation']);

        $emergencyParam = $this->findParameterByName($parameters, 'emergency_contact');
        $this->assertFalse($emergencyParam['required']);
        $this->assertContains('required_without:email,phone', $emergencyParam['validation']);

        $notificationParam = $this->findParameterByName($parameters, 'notification_preferences');
        $this->assertFalse($notificationParam['required']);
        $this->assertContains('required_with_all:email,phone', $notificationParam['validation']);
    }

    #[Test]
    public function it_handles_prohibited_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'user_type' => 'required|in:admin,user,guest',
                    'admin_key' => 'prohibited_if:user_type,user,guest|string',
                    'guest_limitations' => 'prohibited_unless:user_type,guest|array',
                    'legacy_field' => 'prohibited|string',
                    'new_field' => 'sometimes|string',
                    'incompatible_field' => 'prohibited_with:new_field|string',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $adminKeyParam = $this->findParameterByName($parameters, 'admin_key');
        $this->assertContains('prohibited_if:user_type,user,guest', $adminKeyParam['validation']);

        $guestLimitParam = $this->findParameterByName($parameters, 'guest_limitations');
        $this->assertContains('prohibited_unless:user_type,guest', $guestLimitParam['validation']);

        $legacyParam = $this->findParameterByName($parameters, 'legacy_field');
        $this->assertContains('prohibited', $legacyParam['validation']);

        $incompatibleParam = $this->findParameterByName($parameters, 'incompatible_field');
        $this->assertContains('prohibited_with:new_field', $incompatibleParam['validation']);
    }

    #[Test]
    public function it_handles_exclude_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'include_metadata' => 'required|boolean',
                    'metadata' => 'exclude_if:include_metadata,false|array',
                    'format' => 'required|in:json,xml,csv',
                    'xml_options' => 'exclude_unless:format,xml|array',
                    'temporary_field' => 'exclude|string',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert - Conditional excludes should be included in the schema
        $metadataParam = $this->findParameterByName($parameters, 'metadata');
        $this->assertContains('exclude_if:include_metadata,false', $metadataParam['validation']);

        $xmlOptionsParam = $this->findParameterByName($parameters, 'xml_options');
        $this->assertContains('exclude_unless:format,xml', $xmlOptionsParam['validation']);

        // Plain exclude should NOT appear in the schema
        $tempParam = $this->findParameterByName($parameters, 'temporary_field');
        $this->assertNull($tempParam, 'Fields with plain exclude rule should not appear in schema');
    }

    #[Test]
    public function it_handles_multiple_conditional_rules_on_same_field()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'account_type' => 'required|in:individual,company',
                    'has_tax_exemption' => 'required|boolean',
                    'tax_details' => [
                        'required_if:account_type,company',
                        'required_if:has_tax_exemption,true',
                        'prohibited_if:account_type,individual',
                        'array',
                    ],
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $taxDetailsParam = $this->findParameterByName($parameters, 'tax_details');
        $this->assertFalse($taxDetailsParam['required']); // Conditionally required
        $this->assertContains('required_if:account_type,company', $taxDetailsParam['validation']);
        $this->assertContains('required_if:has_tax_exemption,true', $taxDetailsParam['validation']);
        $this->assertContains('prohibited_if:account_type,individual', $taxDetailsParam['validation']);
        $this->assertEquals('array', $taxDetailsParam['type']);
    }

    #[Test]
    public function it_handles_complex_nested_conditional_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'shipping_method' => 'required|in:standard,express,pickup',
                    'delivery_address' => 'required_unless:shipping_method,pickup|string',
                    'delivery_address.street' => 'required_unless:shipping_method,pickup|string',
                    'delivery_address.city' => 'required_unless:shipping_method,pickup|string',
                    'delivery_address.postal_code' => 'required_unless:shipping_method,pickup|string',
                    'pickup_location' => 'required_if:shipping_method,pickup|string',
                    'express_insurance' => 'required_if:shipping_method,express|boolean',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $deliveryAddressParam = $this->findParameterByName($parameters, 'delivery_address');
        $this->assertFalse($deliveryAddressParam['required']);

        $streetParam = $this->findParameterByName($parameters, 'delivery_address.street');
        $this->assertFalse($streetParam['required']);
        $this->assertContains('required_unless:shipping_method,pickup', $streetParam['validation']);

        $pickupParam = $this->findParameterByName($parameters, 'pickup_location');
        $this->assertFalse($pickupParam['required']);
        $this->assertContains('required_if:shipping_method,pickup', $pickupParam['validation']);
    }

    #[Test]
    public function it_generates_proper_openapi_schema_for_conditional_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'notification_channel' => 'required|in:email,sms,push',
                    'email_address' => 'required_if:notification_channel,email|email',
                    'phone_number' => 'required_if:notification_channel,sms|string',
                    'device_token' => 'required_if:notification_channel,push|string',
                ];
            }

            public function attributes(): array
            {
                return [
                    'notification_channel' => 'Notification Channel',
                    'email_address' => 'Email Address (required when channel is email)',
                    'phone_number' => 'Phone Number (required when channel is sms)',
                    'device_token' => 'Device Token (required when channel is push)',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $this->assertCount(4, $parameters);

        $emailParam = $this->findParameterByName($parameters, 'email_address');
        $this->assertArrayHasKey('conditional_required', $emailParam);
        $this->assertTrue($emailParam['conditional_required']);
        $this->assertArrayHasKey('conditional_rules', $emailParam);
        $this->assertCount(1, $emailParam['conditional_rules']);
        $this->assertEquals('required_if', $emailParam['conditional_rules'][0]['type']);

        // Check that the description from attributes is used
        $this->assertStringContainsString('required when channel is email', $emailParam['description']);
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
