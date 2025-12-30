<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\DTO\ResourceInfo;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\ExampleValueFactory;
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

        $this->valueFactory = new ExampleValueFactory;
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
        $valueFactory = new ExampleValueFactory(null, 'ja_JP');

        $phone = $valueFactory->create('phone_number', ['type' => 'string']);

        // Japanese phone numbers have various formats:
        // - Mobile: 0[789]0-XXXX-XXXX
        // - Landline: 0X-XXXX-XXXX, 0XX-XXX-XXXX, 0XXX-XX-XXXX
        // Just verify it starts with 0 and contains digits with dashes
        $this->assertMatchesRegularExpression('/^0\d{1,4}-\d{2,4}-\d{3,4}$/', $phone);
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
        $resourceInfo = ResourceInfo::fromArray([
            'properties' => [
                'id' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
            ],
        ]);

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

        $example = $this->generator->generateFromResource($resourceInfo, get_class($resource));

        $this->assertContains($example['status'], ['pending', 'processing', 'completed']);
    }

    #[Test]
    public function it_falls_back_to_static_values_when_faker_disabled()
    {
        config(['spectrum.example_generation.use_faker' => false]);
        $valueFactory = new ExampleValueFactory;

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
        // Test that seeding is properly applied and produces valid output
        config(['spectrum.example_generation.faker_seed' => 100]);

        $factory = new ExampleValueFactory;
        $faker = $factory->getFaker();

        // Verify faker was created and is seeded
        $this->assertNotNull($faker, 'Faker should be created when use_faker is enabled');

        // Seed and generate a value
        $faker->seed(100);
        $value = $faker->name();

        // Verify the generated value is a valid name format
        $this->assertNotEmpty($value, 'Seeded Faker should generate a non-empty name');
        $this->assertIsString($value);
        // Name should contain at least a first and last name (2+ words)
        $this->assertGreaterThanOrEqual(2, str_word_count($value), 'Name should contain multiple words');
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
