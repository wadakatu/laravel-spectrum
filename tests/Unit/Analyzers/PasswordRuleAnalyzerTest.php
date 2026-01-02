<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\PasswordRuleAnalyzer;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PasswordRuleAnalyzerTest extends TestCase
{
    private PasswordRuleAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new PasswordRuleAnalyzer;
    }

    // ========== isPasswordRule tests ==========

    #[Test]
    public function it_detects_password_static_call_string(): void
    {
        $this->assertTrue($this->analyzer->isPasswordRule('Password::min(8)'));
        $this->assertTrue($this->analyzer->isPasswordRule('Password::min(8)->mixedCase()'));
    }

    #[Test]
    public function it_detects_fully_qualified_password_class_with_leading_backslash(): void
    {
        $this->assertTrue($this->analyzer->isPasswordRule('\\Illuminate\\Validation\\Rules\\Password::min(8)'));
    }

    #[Test]
    public function it_detects_fully_qualified_password_class_without_leading_backslash(): void
    {
        $this->assertTrue($this->analyzer->isPasswordRule('Illuminate\\Validation\\Rules\\Password::min(12)'));
    }

    #[Test]
    public function it_returns_false_for_non_password_rules(): void
    {
        $this->assertFalse($this->analyzer->isPasswordRule('required'));
        $this->assertFalse($this->analyzer->isPasswordRule('string'));
        $this->assertFalse($this->analyzer->isPasswordRule('min:8'));
        $this->assertFalse($this->analyzer->isPasswordRule('max:255'));
    }

    #[Test]
    public function it_returns_false_for_non_string_rules(): void
    {
        $this->assertFalse($this->analyzer->isPasswordRule(null));
        $this->assertFalse($this->analyzer->isPasswordRule(123));
        $this->assertFalse($this->analyzer->isPasswordRule(['array']));
        $this->assertFalse($this->analyzer->isPasswordRule(new \stdClass));
    }

    #[Test]
    public function it_returns_false_for_password_in_wrong_position(): void
    {
        // Password:: should be at the start, not in the middle
        $this->assertFalse($this->analyzer->isPasswordRule('regex:/Password::/'));
        $this->assertFalse($this->analyzer->isPasswordRule('in:Password::min,other'));
    }

    // ========== findPasswordRule tests ==========

    #[Test]
    public function it_finds_password_rule_in_array(): void
    {
        $rules = ['required', 'confirmed', 'Password::min(8)->mixedCase()'];
        $result = $this->analyzer->findPasswordRule($rules);

        $this->assertEquals('Password::min(8)->mixedCase()', $result);
    }

    #[Test]
    public function it_returns_null_when_no_password_rule_in_array(): void
    {
        $rules = ['required', 'string', 'min:8'];
        $result = $this->analyzer->findPasswordRule($rules);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_empty_array(): void
    {
        $this->assertNull($this->analyzer->findPasswordRule([]));
    }

    #[Test]
    public function it_finds_password_rule_with_mixed_rule_types(): void
    {
        $rules = ['required', null, 123, 'Password::min(8)', new \stdClass];
        $result = $this->analyzer->findPasswordRule($rules);

        $this->assertEquals('Password::min(8)', $result);
    }

    // ========== analyze tests ==========

    #[Test]
    public function it_extracts_min_length_from_password_rule(): void
    {
        $rules = ['required', 'Password::min(8)'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertEquals(8, $info->minLength);
    }

    #[Test]
    public function it_extracts_max_length_from_password_rule(): void
    {
        $rules = ['required', 'Password::min(8)->max(32)'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertEquals(8, $info->minLength);
        $this->assertEquals(32, $info->maxLength);
    }

    #[Test]
    public function it_extracts_mixed_case_requirement(): void
    {
        $rules = ['Password::min(8)->mixedCase()'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertTrue($info->requiresMixedCase);
        $this->assertFalse($info->requiresNumbers);
    }

    #[Test]
    public function it_extracts_numbers_requirement(): void
    {
        $rules = ['Password::min(8)->numbers()'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertTrue($info->requiresNumbers);
        $this->assertFalse($info->requiresSymbols);
    }

    #[Test]
    public function it_extracts_symbols_requirement(): void
    {
        $rules = ['Password::min(8)->symbols()'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertTrue($info->requiresSymbols);
    }

    #[Test]
    public function it_extracts_letters_requirement(): void
    {
        $rules = ['Password::min(8)->letters()'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertTrue($info->requiresLetters);
    }

    #[Test]
    public function it_extracts_uncompromised_requirement(): void
    {
        $rules = ['Password::min(8)->uncompromised()'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertTrue($info->requiresUncompromised);
    }

    #[Test]
    public function it_extracts_all_requirements_from_complex_rule(): void
    {
        $rules = ['Password::min(8)->max(64)->mixedCase()->numbers()->symbols()->letters()->uncompromised()'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertEquals(8, $info->minLength);
        $this->assertEquals(64, $info->maxLength);
        $this->assertTrue($info->requiresMixedCase);
        $this->assertTrue($info->requiresNumbers);
        $this->assertTrue($info->requiresSymbols);
        $this->assertTrue($info->requiresLetters);
        $this->assertTrue($info->requiresUncompromised);
    }

    #[Test]
    public function it_returns_null_when_no_password_rule(): void
    {
        $rules = ['required', 'string', 'min:8'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNull($info);
    }

    #[Test]
    public function it_handles_password_default(): void
    {
        // Password::default() is detected as a Password rule
        $rules = ['Password::default()'];
        $this->assertTrue($this->analyzer->isPasswordRule('Password::default()'));

        $info = $this->analyzer->analyze($rules);
        $this->assertNotNull($info);
        // default() doesn't have explicit min, so minLength is null
        $this->assertNull($info->minLength);
    }

    #[Test]
    public function it_extracts_large_min_length(): void
    {
        $rules = ['Password::min(128)'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertEquals(128, $info->minLength);
    }

    #[Test]
    public function it_handles_zero_min_length(): void
    {
        $rules = ['Password::min(0)'];
        $info = $this->analyzer->analyze($rules);

        $this->assertNotNull($info);
        $this->assertEquals(0, $info->minLength);
    }
}
