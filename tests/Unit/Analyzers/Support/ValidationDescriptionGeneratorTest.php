<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\Support\ValidationDescriptionGenerator;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ValidationDescriptionGeneratorTest extends TestCase
{
    private ValidationDescriptionGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ValidationDescriptionGenerator;
    }

    // ========== generateDescription tests ==========

    #[Test]
    public function it_generates_basic_field_description(): void
    {
        $description = $this->generator->generateDescription('user_name', ['required', 'string']);

        $this->assertEquals('User Name', $description);
    }

    #[Test]
    public function it_includes_max_constraint_in_description(): void
    {
        $description = $this->generator->generateDescription('email', ['required', 'max:255']);

        $this->assertStringContainsString('(最大255文字)', $description);
    }

    #[Test]
    public function it_includes_min_constraint_in_description(): void
    {
        $description = $this->generator->generateDescription('password', ['required', 'min:8']);

        $this->assertStringContainsString('(最小8文字)', $description);
    }

    #[Test]
    public function it_includes_required_if_info(): void
    {
        $description = $this->generator->generateDescription('phone', ['required_if:contact_method,phone']);

        $this->assertStringContainsString('Required when contact_method is phone', $description);
    }

    #[Test]
    public function it_includes_required_unless_info(): void
    {
        $description = $this->generator->generateDescription('phone', ['required_unless:role,admin']);

        $this->assertStringContainsString('Required unless role is admin', $description);
    }

    #[Test]
    public function it_includes_required_with_info(): void
    {
        $description = $this->generator->generateDescription('phone', ['required_with:email,name']);

        $this->assertStringContainsString('Required when any of these fields are present: email,name', $description);
    }

    #[Test]
    public function it_includes_required_without_info(): void
    {
        $description = $this->generator->generateDescription('phone', ['required_without:email']);

        $this->assertStringContainsString('Required when any of these fields are not present: email', $description);
    }

    #[Test]
    public function it_includes_prohibited_if_info(): void
    {
        $description = $this->generator->generateDescription('old_field', ['prohibited_if:status,active']);

        $this->assertStringContainsString('Prohibited when status is active', $description);
    }

    #[Test]
    public function it_includes_date_after_info(): void
    {
        $description = $this->generator->generateDescription('end_date', ['date', 'after:start_date']);

        $this->assertStringContainsString('Date must be after start_date', $description);
    }

    #[Test]
    public function it_includes_date_before_info(): void
    {
        $description = $this->generator->generateDescription('start_date', ['date', 'before:end_date']);

        $this->assertStringContainsString('Date must be before end_date', $description);
    }

    #[Test]
    public function it_includes_date_format_info(): void
    {
        $description = $this->generator->generateDescription('created_at', ['date_format:Y-m-d']);

        $this->assertStringContainsString('Format: Y-m-d', $description);
    }

    #[Test]
    public function it_includes_timezone_info(): void
    {
        $description = $this->generator->generateDescription('tz', ['timezone']);

        $this->assertStringContainsString('Must be a valid timezone', $description);
    }

    #[Test]
    public function it_includes_enum_class_name_in_description(): void
    {
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->andReturn(['class' => 'App\Enums\UserStatus']);

        $generator = new ValidationDescriptionGenerator($enumAnalyzer);
        $description = $generator->generateDescription('status', ['required']);

        $this->assertStringContainsString('(UserStatus)', $description);
    }

    #[Test]
    public function it_handles_multiple_conditional_rules(): void
    {
        $description = $this->generator->generateDescription('field', [
            'required_if:status,active',
            'after:start_date',
        ]);

        $this->assertStringContainsString('Required when status is active', $description);
        $this->assertStringContainsString('Date must be after start_date', $description);
    }

    // ========== generateFileDescription tests ==========

    #[Test]
    public function it_generates_basic_file_description(): void
    {
        $description = $this->generator->generateFileDescription('avatar', []);

        $this->assertEquals('Avatar', $description);
    }

    #[Test]
    public function it_includes_allowed_mime_types(): void
    {
        $description = $this->generator->generateFileDescription('document', [
            'mimes' => ['pdf', 'doc', 'docx'],
        ]);

        $this->assertStringContainsString('Allowed types: pdf, doc, docx', $description);
    }

    #[Test]
    public function it_includes_max_file_size(): void
    {
        $description = $this->generator->generateFileDescription('image', [
            'max_size' => 5242880, // 5MB
        ]);

        $this->assertStringContainsString('Max size: 5MB', $description);
    }

    #[Test]
    public function it_includes_min_file_size(): void
    {
        $description = $this->generator->generateFileDescription('image', [
            'min_size' => 1024, // 1KB
        ]);

        $this->assertStringContainsString('Min size: 1KB', $description);
    }

    #[Test]
    public function it_includes_image_dimensions(): void
    {
        $description = $this->generator->generateFileDescription('banner', [
            'dimensions' => [
                'min_width' => 100,
                'min_height' => 100,
                'max_width' => 1920,
                'max_height' => 1080,
            ],
        ]);

        $this->assertStringContainsString('Min dimensions: 100x100', $description);
        $this->assertStringContainsString('Max dimensions: 1920x1080', $description);
    }

    #[Test]
    public function it_includes_aspect_ratio(): void
    {
        $description = $this->generator->generateFileDescription('photo', [
            'dimensions' => ['ratio' => '16/9'],
        ]);

        $this->assertStringContainsString('Aspect ratio: 16/9', $description);
    }

    // ========== generateFileDescriptionWithAttribute tests ==========

    #[Test]
    public function it_uses_custom_attribute_for_file_description(): void
    {
        $description = $this->generator->generateFileDescriptionWithAttribute(
            'profile_image',
            ['mimes' => ['jpg', 'png']],
            'プロフィール画像'
        );

        $this->assertStringStartsWith('プロフィール画像', $description);
        $this->assertStringContainsString('Allowed types: jpg, png', $description);
    }

    #[Test]
    public function it_falls_back_to_field_name_when_no_attribute(): void
    {
        $description = $this->generator->generateFileDescriptionWithAttribute(
            'profile_image',
            ['mimes' => ['jpg']],
            null
        );

        $this->assertStringStartsWith('Profile Image', $description);
    }

    // ========== generateConditionalDescription tests ==========

    #[Test]
    public function it_generates_basic_conditional_description(): void
    {
        $description = $this->generator->generateConditionalDescription('payment_method', [
            'rules_by_condition' => [
                ['condition' => 'default', 'rules' => ['string']],
            ],
        ]);

        $this->assertEquals('Payment Method', $description);
    }

    #[Test]
    public function it_indicates_multiple_conditions(): void
    {
        $description = $this->generator->generateConditionalDescription('payment_method', [
            'rules_by_condition' => [
                ['condition' => 'type=credit', 'rules' => ['string', 'size:16']],
                ['condition' => 'type=bank', 'rules' => ['string', 'max:20']],
            ],
        ]);

        $this->assertStringContainsString('条件により異なるルールが適用されます', $description);
    }

    // ========== Edge cases ==========

    #[Test]
    public function it_handles_empty_rules(): void
    {
        $description = $this->generator->generateDescription('field', []);

        $this->assertEquals('Field', $description);
    }

    #[Test]
    public function it_handles_non_string_rules(): void
    {
        $description = $this->generator->generateDescription('field', [
            'required',
            fn () => true,
            'string',
        ]);

        $this->assertEquals('Field', $description);
    }

    #[Test]
    public function it_handles_empty_file_info(): void
    {
        $description = $this->generator->generateFileDescription('document', []);

        $this->assertEquals('Document', $description);
    }

    #[Test]
    public function it_handles_field_with_hyphens(): void
    {
        $description = $this->generator->generateDescription('first-name', ['required']);

        $this->assertEquals('First Name', $description);
    }

    #[Test]
    public function it_handles_date_equals_info(): void
    {
        $description = $this->generator->generateDescription('event_date', ['date_equals:2024-01-01']);

        $this->assertStringContainsString('Date must be equal to 2024-01-01', $description);
    }

    #[Test]
    public function it_handles_after_or_equal_info(): void
    {
        $description = $this->generator->generateDescription('start_date', ['after_or_equal:today']);

        $this->assertStringContainsString('Date must be after or equal to today', $description);
    }

    #[Test]
    public function it_handles_before_or_equal_info(): void
    {
        $description = $this->generator->generateDescription('end_date', ['before_or_equal:tomorrow']);

        $this->assertStringContainsString('Date must be before or equal to tomorrow', $description);
    }

    #[Test]
    public function it_handles_timezone_with_group_parameter(): void
    {
        $description = $this->generator->generateDescription('tz', ['timezone:africa']);

        $this->assertStringContainsString('Must be a valid timezone', $description);
    }

    #[Test]
    public function it_does_not_add_enum_info_when_no_enum_detected(): void
    {
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->andReturn(null);

        $generator = new ValidationDescriptionGenerator($enumAnalyzer);
        $description = $generator->generateDescription('status', ['required', 'string']);

        $this->assertEquals('Status', $description);
    }

    #[Test]
    public function it_handles_enum_result_with_empty_class(): void
    {
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->andReturn(['class' => '']);

        $generator = new ValidationDescriptionGenerator($enumAnalyzer);
        $description = $generator->generateDescription('status', ['required']);

        $this->assertEquals('Status', $description);
    }

    #[Test]
    public function it_handles_enum_result_without_class_key(): void
    {
        $enumAnalyzer = Mockery::mock(EnumAnalyzer::class);
        $enumAnalyzer->shouldReceive('analyzeValidationRule')
            ->andReturn(['values' => ['active', 'inactive']]);

        $generator = new ValidationDescriptionGenerator($enumAnalyzer);
        $description = $generator->generateDescription('status', ['required']);

        $this->assertEquals('Status', $description);
    }

    #[Test]
    public function it_handles_conditional_description_without_rules_by_condition_key(): void
    {
        $description = $this->generator->generateConditionalDescription('payment_method', []);

        $this->assertEquals('Payment Method', $description);
    }

    #[Test]
    public function it_handles_required_if_with_multiple_values(): void
    {
        $description = $this->generator->generateDescription(
            'phone',
            ['required_if:type,home,mobile,work']
        );

        $this->assertStringContainsString('Required when type is home,mobile,work', $description);
    }
}
