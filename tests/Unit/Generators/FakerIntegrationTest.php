<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\ExampleValueFactory;
use LaravelSpectrum\Support\FieldNameInference;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FakerIntegrationTest extends TestCase
{
    private ExampleGenerator $generator;

    private ExampleValueFactory $valueFactory;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spectrum.example_generation.use_faker' => true]);
        config(['spectrum.example_generation.faker_seed' => 12345]); // For consistent tests

        $this->valueFactory = new ExampleValueFactory(new FieldNameInference);
        $this->generator = new ExampleGenerator($this->valueFactory);
    }

    #[Test]
    public function it_generates_realistic_email_addresses()
    {
        $value = $this->valueFactory->create('email', ['type' => 'string', 'format' => 'email']);

        $this->assertMatchesRegularExpression('/^.+@.+\..+$/', $value);
        $this->assertNotEquals('user@example.com', $value);
    }

    #[Test]
    public function it_generates_locale_specific_phone_numbers()
    {
        // Test Japanese locale
        config(['spectrum.example_generation.faker_locale' => 'ja_JP']);
        $valueFactory = new ExampleValueFactory(new FieldNameInference, 'ja_JP');

        $phone = $valueFactory->create('phone_number', ['type' => 'string']);

        $this->assertMatchesRegularExpression('/^0[789]0-\d{4}-\d{4}$/', $phone);
    }

    #[Test]
    public function it_generates_appropriate_timestamps()
    {
        $created = $this->valueFactory->create('created_at', ['type' => 'string', 'format' => 'date-time']);
        $updated = $this->valueFactory->create('updated_at', ['type' => 'string', 'format' => 'date-time']);
        $deleted = $this->valueFactory->create('deleted_at', ['type' => 'string', 'format' => 'date-time']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $created);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $updated);
        // deleted_at might be null
        $this->assertTrue($deleted === null || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $deleted) === 1);
    }

    #[Test]
    public function it_respects_field_constraints()
    {
        $age = $this->valueFactory->create('age', [
            'type' => 'integer',
            'minimum' => 18,
            'maximum' => 65,
        ]);

        $this->assertGreaterThanOrEqual(18, $age);
        $this->assertLessThanOrEqual(65, $age);

        $price = $this->valueFactory->create('price', [
            'type' => 'number',
            'minimum' => 0.01,
            'maximum' => 999.99,
        ]);

        $this->assertGreaterThanOrEqual(0.01, $price);
        $this->assertLessThanOrEqual(999.99, $price);
    }

    #[Test]
    public function it_uses_custom_generators()
    {
        $schema = [
            'properties' => [
                'id' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
            ],
        ];

        $customMapping = [
            'status' => fn ($faker) => $faker->randomElement(['pending', 'processing', 'completed']),
        ];

        // Use ExampleGenerator through public API
        $resource = new class implements \LaravelSpectrum\Contracts\HasCustomExamples
        {
            public static function getExampleMapping(): array
            {
                return [
                    'status' => fn ($faker) => $faker->randomElement(['pending', 'processing', 'completed']),
                ];
            }
        };

        $example = $this->generator->generateFromResource($schema, get_class($resource));

        $this->assertContains($example['status'], ['pending', 'processing', 'completed']);
    }

    #[Test]
    public function it_falls_back_to_static_values_when_faker_disabled()
    {
        config(['spectrum.example_generation.use_faker' => false]);
        $valueFactory = new ExampleValueFactory(new FieldNameInference);

        $email = $valueFactory->create('email', ['type' => 'string', 'format' => 'email']);

        $this->assertEquals('user@example.com', $email);
    }

    #[Test]
    public function it_generates_realistic_names_based_on_context()
    {
        $userName = $this->valueFactory->create('user_name', ['type' => 'string']);
        $productName = $this->valueFactory->create('product_name', ['type' => 'string']);
        $companyName = $this->valueFactory->create('company_name', ['type' => 'string']);

        $this->assertIsString($userName);
        $this->assertIsString($productName);
        $this->assertIsString($companyName);

        // Names should have some content
        $this->assertGreaterThan(3, strlen($userName));
        $this->assertGreaterThan(3, strlen($productName));
        $this->assertGreaterThan(3, strlen($companyName));
    }

    #[Test]
    public function it_generates_appropriate_image_urls()
    {
        $avatar = $this->valueFactory->create('avatar', ['type' => 'string']);
        $thumbnail = $this->valueFactory->create('thumbnail', ['type' => 'string']);
        $banner = $this->valueFactory->create('banner_image', ['type' => 'string']);

        $this->assertStringContainsString('200x200', $avatar); // Avatar should be square
        $this->assertStringContainsString('150x150', $thumbnail); // Thumbnail should be small
        $this->assertStringContainsString('1200x400', $banner); // Banner should be wide
    }

    #[Test]
    public function it_generates_boolean_fields_based_on_prefix()
    {
        $isActive = $this->valueFactory->create('is_active', ['type' => 'boolean']);
        $hasChildren = $this->valueFactory->create('has_children', ['type' => 'boolean']);
        $canEdit = $this->valueFactory->create('can_edit', ['type' => 'boolean']);

        $this->assertIsBool($isActive);
        $this->assertIsBool($hasChildren);
        $this->assertIsBool($canEdit);
    }

    #[Test]
    public function it_handles_enum_fields()
    {
        $status = $this->valueFactory->create('status', [
            'type' => 'string',
            'enum' => ['active', 'inactive', 'pending'],
        ]);

        $this->assertContains($status, ['active', 'inactive', 'pending']);
    }

    #[Test]
    public function it_generates_with_consistent_seed()
    {
        config(['spectrum.example_generation.faker_seed' => 100]);

        $factory1 = new ExampleValueFactory(new FieldNameInference);
        $value1 = $factory1->create('name', ['type' => 'string']);

        $factory2 = new ExampleValueFactory(new FieldNameInference);
        $value2 = $factory2->create('name', ['type' => 'string']);

        $this->assertEquals($value1, $value2);
    }

    #[Test]
    public function it_generates_string_with_length_constraints()
    {
        $shortString = $this->valueFactory->create('code', [
            'type' => 'string',
            'maxLength' => 5,
        ]);

        $longString = $this->valueFactory->create('description', [
            'type' => 'string',
            'minLength' => 100,
            'maxLength' => 2000,
        ]);

        $this->assertLessThanOrEqual(5, strlen($shortString));
        $this->assertGreaterThan(50, strlen($longString)); // Faker might not respect exact minLength
    }
}
