<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use LaravelSpectrum\Contracts\ExampleGenerationStrategy;
use LaravelSpectrum\Support\Example\FieldPatternRegistry;
use LaravelSpectrum\Support\Example\ValueProviders\FakerValueProvider;
use LaravelSpectrum\Support\Example\ValueProviders\StaticValueProvider;

/**
 * Factory for creating example values for API documentation.
 *
 * Uses strategy pattern to switch between Faker-based and static generation.
 */
class ExampleValueFactory
{
    private ExampleGenerationStrategy $strategy;

    private FieldPatternRegistry $registry;

    private ?Faker $faker = null;

    public function __construct(
        ?FieldPatternRegistry $registry = null,
        ?string $locale = null
    ) {
        $this->registry = $registry ?? new FieldPatternRegistry;
        $useFaker = config('spectrum.example_generation.use_faker', true);

        if ($useFaker) {
            $this->faker = $this->createFaker($locale);
            $this->strategy = new FakerValueProvider($this->faker, $this->registry);
        } else {
            $this->strategy = new StaticValueProvider($this->registry);
        }
    }

    /**
     * Create example value based on field name and schema.
     */
    public function create(string $fieldName, array $fieldSchema, ?callable $customGenerator = null): mixed
    {
        // Use custom generator if provided
        if ($customGenerator !== null && $this->faker !== null) {
            return $customGenerator($this->faker);
        }

        // OpenAPI priorities: const > examples > enum > default > generate
        if (isset($fieldSchema['const'])) {
            return $fieldSchema['const'];
        }

        if (isset($fieldSchema['examples'][0])) {
            return $fieldSchema['examples'][0];
        }

        if (isset($fieldSchema['enum']) && ! empty($fieldSchema['enum'])) {
            return $this->selectEnumValue($fieldSchema['enum']);
        }

        if (isset($fieldSchema['default'])) {
            return $fieldSchema['default'];
        }

        // Special field handling
        $specialValue = $this->handleSpecialFields($fieldName, $fieldSchema);
        if ($specialValue !== null) {
            return $specialValue;
        }

        // Delegate to strategy
        return $this->strategy->generate($fieldName, $fieldSchema);
    }

    /**
     * Generate by type (backward compatibility).
     */
    public function generateByType(string $type, ?string $format = null): mixed
    {
        if ($format !== null) {
            return $this->strategy->generateByFormat($format);
        }

        return $this->strategy->generateByType($type, []);
    }

    /**
     * Get the underlying Faker instance (for custom generators).
     */
    public function getFaker(): ?Faker
    {
        return $this->faker;
    }

    /**
     * Create Faker instance with locale.
     */
    private function createFaker(?string $locale): Faker
    {
        $locale = $locale ?? config('spectrum.example_generation.faker_locale', config('app.faker_locale', 'en_US'));
        $faker = FakerFactory::create($locale);

        // Set seed for consistent examples if configured
        if ($seed = config('spectrum.example_generation.faker_seed')) {
            $faker->seed($seed);
        }

        return $faker;
    }

    /**
     * Select enum value (first for static, random for Faker).
     */
    private function selectEnumValue(array $enum): mixed
    {
        if ($this->faker !== null) {
            return $this->faker->randomElement($enum);
        }

        return $enum[0];
    }

    /**
     * Handle fields requiring special processing.
     */
    private function handleSpecialFields(string $fieldName, array $fieldSchema): mixed
    {
        $lower = strtolower($fieldName);

        // Timestamp fields with context-aware generation
        if ($this->faker !== null && $this->isTimestampField($lower)) {
            return $this->generateTimestamp($lower);
        }

        // Name field with context detection
        if ($this->faker !== null && $lower === 'name') {
            return $this->generateContextualName($fieldName, $fieldSchema);
        }

        // Phone field with locale handling
        if ($this->faker !== null && $this->isPhoneField($lower)) {
            return $this->generatePhone();
        }

        // Image fields with dimension handling
        if ($this->faker !== null && $this->isImageField($lower)) {
            return $this->generateImageUrl($fieldName);
        }

        return null;
    }

    /**
     * Check if field is a timestamp field.
     */
    private function isTimestampField(string $fieldName): bool
    {
        return str_ends_with($fieldName, '_at') || in_array($fieldName, ['created_at', 'updated_at', 'deleted_at']);
    }

    /**
     * Check if field is a phone field.
     */
    private function isPhoneField(string $fieldName): bool
    {
        return in_array($fieldName, ['phone', 'phonenumber', 'mobile', 'fax']);
    }

    /**
     * Check if field is an image field.
     */
    private function isImageField(string $fieldName): bool
    {
        return str_contains($fieldName, 'image') ||
               str_contains($fieldName, 'photo') ||
               str_contains($fieldName, 'picture') ||
               str_contains($fieldName, 'avatar') ||
               str_contains($fieldName, 'thumbnail') ||
               str_contains($fieldName, 'banner') ||
               str_contains($fieldName, 'cover');
    }

    /**
     * Generate timestamp with context-aware logic.
     */
    private function generateTimestamp(string $fieldName): ?string
    {
        if (str_contains($fieldName, 'deleted')) {
            /** @var \DateTime|null $dateTime */
            $dateTime = $this->faker->optional(0.2)->dateTime();

            return $dateTime instanceof \DateTime ? $dateTime->format('Y-m-d\TH:i:s\Z') : null;
        }

        if (str_contains($fieldName, 'created')) {
            return $this->faker->dateTimeBetween('-1 year', '-1 week')->format('Y-m-d\TH:i:s\Z');
        }

        if (str_contains($fieldName, 'updated') || str_contains($fieldName, 'modified')) {
            return $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d\TH:i:s\Z');
        }

        if (str_contains($fieldName, 'expired') || str_contains($fieldName, 'expires')) {
            return $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d\TH:i:s\Z');
        }

        return $this->faker->dateTime()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Generate name based on context.
     */
    private function generateContextualName(string $fieldName, array $fieldSchema): string
    {
        // Check related fields for context hints
        $contextHints = ['user', 'person', 'customer', 'client', 'employee', 'product', 'item', 'service', 'company', 'organization'];

        foreach ($contextHints as $hint) {
            if (str_contains(strtolower($fieldName), $hint)) {
                return match ($hint) {
                    'product', 'item', 'service' => $this->faker->words(3, true),
                    'company', 'organization' => $this->faker->company(),
                    default => $this->faker->name(),
                };
            }
        }

        return $this->faker->name();
    }

    /**
     * Generate phone number with locale handling.
     */
    private function generatePhone(): string
    {
        $locale = config('spectrum.example_generation.faker_locale', 'en_US');

        // Japanese phone format
        if (str_starts_with($locale, 'ja_')) {
            return $this->faker->regexify('0[789]0-[0-9]{4}-[0-9]{4}');
        }

        return $this->faker->phoneNumber();
    }

    /**
     * Generate image URL with appropriate dimensions.
     */
    private function generateImageUrl(string $fieldName): string
    {
        $lower = strtolower($fieldName);

        if (str_contains($lower, 'avatar') || str_contains($lower, 'profile')) {
            return $this->faker->imageUrl(200, 200);
        }

        if (str_contains($lower, 'thumbnail')) {
            return $this->faker->imageUrl(150, 150);
        }

        if (str_contains($lower, 'banner')) {
            return $this->faker->imageUrl(1200, 400);
        }

        if (str_contains($lower, 'cover')) {
            return $this->faker->imageUrl(1200, 600);
        }

        return $this->faker->imageUrl(640, 480);
    }
}
