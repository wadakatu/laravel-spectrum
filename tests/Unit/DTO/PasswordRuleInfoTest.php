<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\PasswordRuleInfo;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PasswordRuleInfoTest extends TestCase
{
    // ========== Construction tests ==========

    #[Test]
    public function it_creates_with_all_parameters(): void
    {
        $info = new PasswordRuleInfo(
            minLength: 8,
            maxLength: 64,
            requiresMixedCase: true,
            requiresNumbers: true,
            requiresSymbols: true,
            requiresLetters: true,
            requiresUncompromised: true,
        );

        $this->assertEquals(8, $info->minLength);
        $this->assertEquals(64, $info->maxLength);
        $this->assertTrue($info->requiresMixedCase);
        $this->assertTrue($info->requiresNumbers);
        $this->assertTrue($info->requiresSymbols);
        $this->assertTrue($info->requiresLetters);
        $this->assertTrue($info->requiresUncompromised);
    }

    #[Test]
    public function it_creates_with_default_values(): void
    {
        $info = new PasswordRuleInfo;

        $this->assertNull($info->minLength);
        $this->assertNull($info->maxLength);
        $this->assertFalse($info->requiresMixedCase);
        $this->assertFalse($info->requiresNumbers);
        $this->assertFalse($info->requiresSymbols);
        $this->assertFalse($info->requiresLetters);
        $this->assertFalse($info->requiresUncompromised);
    }

    #[Test]
    public function it_creates_with_only_min_length(): void
    {
        $info = new PasswordRuleInfo(minLength: 12);

        $this->assertEquals(12, $info->minLength);
        $this->assertNull($info->maxLength);
        $this->assertFalse($info->requiresMixedCase);
    }

    // ========== hasRequirements tests ==========

    #[Test]
    public function it_returns_true_when_mixed_case_required(): void
    {
        $info = new PasswordRuleInfo(requiresMixedCase: true);
        $this->assertTrue($info->hasRequirements());
    }

    #[Test]
    public function it_returns_true_when_numbers_required(): void
    {
        $info = new PasswordRuleInfo(requiresNumbers: true);
        $this->assertTrue($info->hasRequirements());
    }

    #[Test]
    public function it_returns_true_when_symbols_required(): void
    {
        $info = new PasswordRuleInfo(requiresSymbols: true);
        $this->assertTrue($info->hasRequirements());
    }

    #[Test]
    public function it_returns_true_when_letters_required(): void
    {
        $info = new PasswordRuleInfo(requiresLetters: true);
        $this->assertTrue($info->hasRequirements());
    }

    #[Test]
    public function it_returns_true_when_uncompromised_required(): void
    {
        $info = new PasswordRuleInfo(requiresUncompromised: true);
        $this->assertTrue($info->hasRequirements());
    }

    #[Test]
    public function it_returns_false_when_no_requirements(): void
    {
        $info = new PasswordRuleInfo(minLength: 8, maxLength: 64);
        $this->assertFalse($info->hasRequirements());
    }

    #[Test]
    public function it_returns_true_when_multiple_requirements(): void
    {
        $info = new PasswordRuleInfo(
            requiresMixedCase: true,
            requiresNumbers: true,
            requiresSymbols: true,
        );
        $this->assertTrue($info->hasRequirements());
    }

    // ========== getRequirementDescriptions tests ==========

    #[Test]
    public function it_returns_empty_array_when_no_requirements(): void
    {
        $info = new PasswordRuleInfo;
        $this->assertEquals([], $info->getRequirementDescriptions());
    }

    #[Test]
    public function it_returns_mixed_case_description(): void
    {
        $info = new PasswordRuleInfo(requiresMixedCase: true);
        $this->assertEquals(['mixed case'], $info->getRequirementDescriptions());
    }

    #[Test]
    public function it_returns_numbers_description(): void
    {
        $info = new PasswordRuleInfo(requiresNumbers: true);
        $this->assertEquals(['numbers'], $info->getRequirementDescriptions());
    }

    #[Test]
    public function it_returns_symbols_description(): void
    {
        $info = new PasswordRuleInfo(requiresSymbols: true);
        $this->assertEquals(['symbols'], $info->getRequirementDescriptions());
    }

    #[Test]
    public function it_returns_letters_description(): void
    {
        $info = new PasswordRuleInfo(requiresLetters: true);
        $this->assertEquals(['letters'], $info->getRequirementDescriptions());
    }

    #[Test]
    public function it_returns_uncompromised_description(): void
    {
        $info = new PasswordRuleInfo(requiresUncompromised: true);
        $this->assertEquals(['not compromised'], $info->getRequirementDescriptions());
    }

    #[Test]
    public function it_returns_all_descriptions_in_order(): void
    {
        $info = new PasswordRuleInfo(
            requiresMixedCase: true,
            requiresNumbers: true,
            requiresSymbols: true,
            requiresLetters: true,
            requiresUncompromised: true,
        );

        $expected = ['mixed case', 'numbers', 'symbols', 'letters', 'not compromised'];
        $this->assertEquals($expected, $info->getRequirementDescriptions());
    }

    // ========== toArray tests ==========

    #[Test]
    public function it_converts_to_array_with_all_fields(): void
    {
        $info = new PasswordRuleInfo(
            minLength: 8,
            maxLength: 64,
            requiresMixedCase: true,
            requiresNumbers: true,
            requiresSymbols: false,
            requiresLetters: true,
            requiresUncompromised: false,
        );

        $expected = [
            'min_length' => 8,
            'max_length' => 64,
            'requires_mixed_case' => true,
            'requires_numbers' => true,
            'requires_symbols' => false,
            'requires_letters' => true,
            'requires_uncompromised' => false,
        ];

        $this->assertEquals($expected, $info->toArray());
    }

    #[Test]
    public function it_converts_to_array_with_default_values(): void
    {
        $info = new PasswordRuleInfo;

        $expected = [
            'min_length' => null,
            'max_length' => null,
            'requires_mixed_case' => false,
            'requires_numbers' => false,
            'requires_symbols' => false,
            'requires_letters' => false,
            'requires_uncompromised' => false,
        ];

        $this->assertEquals($expected, $info->toArray());
    }

    // ========== Validation tests ==========

    #[Test]
    public function it_throws_exception_for_negative_min_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('minLength must be non-negative');

        new PasswordRuleInfo(minLength: -1);
    }

    #[Test]
    public function it_throws_exception_for_negative_max_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxLength must be non-negative');

        new PasswordRuleInfo(maxLength: -5);
    }

    #[Test]
    public function it_throws_exception_when_min_exceeds_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('minLength cannot exceed maxLength');

        new PasswordRuleInfo(minLength: 100, maxLength: 10);
    }

    #[Test]
    public function it_allows_equal_min_and_max(): void
    {
        $info = new PasswordRuleInfo(minLength: 10, maxLength: 10);

        $this->assertEquals(10, $info->minLength);
        $this->assertEquals(10, $info->maxLength);
    }

    #[Test]
    public function it_allows_zero_min_length(): void
    {
        $info = new PasswordRuleInfo(minLength: 0);
        $this->assertEquals(0, $info->minLength);
    }
}
