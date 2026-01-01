<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use LaravelSpectrum\Support\ValidationRules;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ValidationRulesTest extends TestCase
{
    #[Test]
    public function it_extracts_rule_name_from_simple_string(): void
    {
        $this->assertEquals('required', ValidationRules::extractRuleName('required'));
        $this->assertEquals('email', ValidationRules::extractRuleName('email'));
        $this->assertEquals('string', ValidationRules::extractRuleName('string'));
    }

    #[Test]
    public function it_extracts_rule_name_from_string_with_parameters(): void
    {
        $this->assertEquals('min', ValidationRules::extractRuleName('min:5'));
        $this->assertEquals('max', ValidationRules::extractRuleName('max:100'));
        $this->assertEquals('between', ValidationRules::extractRuleName('between:1,10'));
        $this->assertEquals('in', ValidationRules::extractRuleName('in:a,b,c'));
    }

    #[Test]
    public function it_extracts_rule_name_from_array(): void
    {
        $this->assertEquals('required', ValidationRules::extractRuleName(['required', 'string']));
        $this->assertEquals('email', ValidationRules::extractRuleName(['email']));
    }

    #[Test]
    public function it_returns_unknown_for_empty_array(): void
    {
        $this->assertEquals('unknown', ValidationRules::extractRuleName([]));
    }

    #[Test]
    public function it_returns_unknown_for_non_string_array_element(): void
    {
        $this->assertEquals('unknown', ValidationRules::extractRuleName([123, 'string']));
    }

    #[Test]
    public function it_extracts_rule_name_from_enum_object(): void
    {
        $rule = Rule::enum(StatusEnum::class);
        // Enum rule doesn't have __toString, so returns 'unknown'
        $this->assertEquals('unknown', ValidationRules::extractRuleName($rule));
    }

    #[Test]
    public function it_extracts_rule_name_from_new_enum_instance(): void
    {
        $rule = new Enum(StatusEnum::class);
        // Enum rule doesn't have __toString, so returns 'unknown'
        $this->assertEquals('unknown', ValidationRules::extractRuleName($rule));
    }

    #[Test]
    public function it_returns_unknown_for_object_without_tostring(): void
    {
        $rule = new \stdClass;
        $this->assertEquals('unknown', ValidationRules::extractRuleName($rule));
    }

    #[Test]
    public function it_returns_enum_for_object_with_tostring(): void
    {
        // Create an anonymous class with __toString
        $rule = new class
        {
            public function __toString(): string
            {
                return 'enum:SomeEnum';
            }
        };
        $this->assertEquals('enum', ValidationRules::extractRuleName($rule));
    }

    #[Test]
    public function it_extracts_empty_parameters_from_rule_without_params(): void
    {
        $this->assertEquals([], ValidationRules::extractRuleParameters('required'));
        $this->assertEquals([], ValidationRules::extractRuleParameters('email'));
    }

    #[Test]
    public function it_extracts_single_parameter(): void
    {
        $this->assertEquals(['5'], ValidationRules::extractRuleParameters('min:5'));
        $this->assertEquals(['100'], ValidationRules::extractRuleParameters('max:100'));
    }

    #[Test]
    public function it_extracts_multiple_parameters(): void
    {
        $this->assertEquals(['1', '10'], ValidationRules::extractRuleParameters('between:1,10'));
        $this->assertEquals(['a', 'b', 'c'], ValidationRules::extractRuleParameters('in:a,b,c'));
    }

    #[Test]
    public function it_extracts_parameters_with_special_characters(): void
    {
        $params = ValidationRules::extractRuleParameters('regex:/^[a-z]+$/');
        $this->assertEquals(['/^[a-z]+$/'], $params);
    }

    #[Test]
    public function it_gets_message_template_for_simple_rule(): void
    {
        $this->assertEquals('The :attribute field is required.', ValidationRules::getMessageTemplate('required'));
        $this->assertEquals('The :attribute must be a valid email address.', ValidationRules::getMessageTemplate('email'));
        $this->assertEquals('The :attribute must be a string.', ValidationRules::getMessageTemplate('string'));
    }

    #[Test]
    public function it_gets_message_template_for_rule_with_type_variants(): void
    {
        // Numeric type
        $this->assertEquals('The :attribute must be at least :min.', ValidationRules::getMessageTemplate('min', 'numeric'));
        $this->assertEquals('The :attribute may not be greater than :max.', ValidationRules::getMessageTemplate('max', 'numeric'));

        // String type
        $this->assertEquals('The :attribute must be at least :min characters.', ValidationRules::getMessageTemplate('min', 'string'));
        $this->assertEquals('The :attribute may not be greater than :max characters.', ValidationRules::getMessageTemplate('max', 'string'));

        // Array type
        $this->assertEquals('The :attribute must have at least :min items.', ValidationRules::getMessageTemplate('min', 'array'));
        $this->assertEquals('The :attribute may not have more than :max items.', ValidationRules::getMessageTemplate('max', 'array'));
    }

    #[Test]
    public function it_returns_null_for_unknown_rule(): void
    {
        $this->assertNull(ValidationRules::getMessageTemplate('unknown_rule'));
        $this->assertNull(ValidationRules::getMessageTemplate(''));
    }

    #[Test]
    public function it_falls_back_to_string_type_for_unknown_type(): void
    {
        // When an unknown type is passed, should fallback to 'string'
        $message = ValidationRules::getMessageTemplate('min', 'unknown_type');
        $this->assertEquals('The :attribute must be at least :min characters.', $message);
    }

    #[Test]
    public function it_infers_numeric_type_from_integer_rule(): void
    {
        $this->assertEquals('numeric', ValidationRules::inferFieldType(['integer']));
        $this->assertEquals('numeric', ValidationRules::inferFieldType(['required', 'integer']));
    }

    #[Test]
    public function it_infers_numeric_type_from_numeric_rule(): void
    {
        $this->assertEquals('numeric', ValidationRules::inferFieldType(['numeric']));
        $this->assertEquals('numeric', ValidationRules::inferFieldType(['required', 'numeric']));
    }

    #[Test]
    public function it_infers_array_type_from_array_rule(): void
    {
        $this->assertEquals('array', ValidationRules::inferFieldType(['array']));
        $this->assertEquals('array', ValidationRules::inferFieldType(['required', 'array']));
    }

    #[Test]
    public function it_infers_string_type_from_enum_rule(): void
    {
        $rule = Rule::enum(StatusEnum::class);
        $this->assertEquals('string', ValidationRules::inferFieldType([$rule]));
    }

    #[Test]
    public function it_infers_string_type_by_default(): void
    {
        $this->assertEquals('string', ValidationRules::inferFieldType(['required']));
        $this->assertEquals('string', ValidationRules::inferFieldType(['email']));
        $this->assertEquals('string', ValidationRules::inferFieldType([]));
    }

    #[Test]
    public function it_returns_first_type_rule_found(): void
    {
        // integer comes first, should return numeric
        $this->assertEquals('numeric', ValidationRules::inferFieldType(['integer', 'array']));

        // array comes first, should return array (iteration order matters)
        $this->assertEquals('array', ValidationRules::inferFieldType(['array', 'integer']));
    }

    #[Test]
    public function it_gets_between_message_template_for_all_types(): void
    {
        $this->assertEquals(
            'The :attribute must be between :min and :max.',
            ValidationRules::getMessageTemplate('between', 'numeric')
        );
        $this->assertEquals(
            'The :attribute must be between :min and :max characters.',
            ValidationRules::getMessageTemplate('between', 'string')
        );
        $this->assertEquals(
            'The :attribute must have between :min and :max items.',
            ValidationRules::getMessageTemplate('between', 'array')
        );
    }

    #[Test]
    public function it_gets_size_message_template_for_all_types(): void
    {
        $this->assertEquals(
            'The :attribute must be :size.',
            ValidationRules::getMessageTemplate('size', 'numeric')
        );
        $this->assertEquals(
            'The :attribute must be :size characters.',
            ValidationRules::getMessageTemplate('size', 'string')
        );
        $this->assertEquals(
            'The :attribute must contain :size items.',
            ValidationRules::getMessageTemplate('size', 'array')
        );
    }

    #[Test]
    public function it_gets_message_templates_for_conditional_rules(): void
    {
        $this->assertEquals(
            'The :attribute field is required when :other is :value.',
            ValidationRules::getMessageTemplate('required_if')
        );
        $this->assertEquals(
            'The :attribute field is required unless :other is in :values.',
            ValidationRules::getMessageTemplate('required_unless')
        );
        $this->assertEquals(
            'The :attribute field is required when :values is present.',
            ValidationRules::getMessageTemplate('required_with')
        );
        $this->assertEquals(
            'The :attribute field is required when :values is not present.',
            ValidationRules::getMessageTemplate('required_without')
        );
    }

    #[Test]
    public function it_gets_message_templates_for_comparison_rules(): void
    {
        $this->assertEquals(
            'The :attribute confirmation does not match.',
            ValidationRules::getMessageTemplate('confirmed')
        );
        $this->assertEquals(
            'The :attribute and :other must match.',
            ValidationRules::getMessageTemplate('same')
        );
        $this->assertEquals(
            'The :attribute and :other must be different.',
            ValidationRules::getMessageTemplate('different')
        );
    }

    #[Test]
    public function it_gets_message_templates_for_format_rules(): void
    {
        $this->assertEquals(
            'The :attribute format is invalid.',
            ValidationRules::getMessageTemplate('url')
        );
        $this->assertEquals(
            'The :attribute format is invalid.',
            ValidationRules::getMessageTemplate('regex')
        );
        $this->assertEquals(
            'The :attribute must be a valid IP address.',
            ValidationRules::getMessageTemplate('ip')
        );
        $this->assertEquals(
            'The :attribute must be a valid JSON string.',
            ValidationRules::getMessageTemplate('json')
        );
    }

    #[Test]
    public function it_gets_message_templates_for_alpha_rules(): void
    {
        $this->assertEquals(
            'The :attribute may only contain letters.',
            ValidationRules::getMessageTemplate('alpha')
        );
        $this->assertEquals(
            'The :attribute may only contain letters and numbers.',
            ValidationRules::getMessageTemplate('alpha_num')
        );
        $this->assertEquals(
            'The :attribute may only contain letters, numbers, dashes and underscores.',
            ValidationRules::getMessageTemplate('alpha_dash')
        );
    }

    #[Test]
    public function it_strips_pcre_delimiters_with_forward_slash(): void
    {
        // Standard forward slash delimiter
        $this->assertEquals(
            '^\\+?[1-9]\\d{1,14}$',
            ValidationRules::stripPcreDelimiters('/^\\+?[1-9]\\d{1,14}$/')
        );

        // Simple pattern
        $this->assertEquals(
            '^[a-z]+$',
            ValidationRules::stripPcreDelimiters('/^[a-z]+$/')
        );

        // US zip code pattern
        $this->assertEquals(
            '^\\d{5}(-\\d{4})?$',
            ValidationRules::stripPcreDelimiters('/^\\d{5}(-\\d{4})?$/')
        );
    }

    #[Test]
    public function it_strips_pcre_delimiters_with_other_delimiters(): void
    {
        // Hash delimiter
        $this->assertEquals(
            '^[a-z]+$',
            ValidationRules::stripPcreDelimiters('#^[a-z]+$#')
        );

        // Tilde delimiter
        $this->assertEquals(
            '^[a-z]+$',
            ValidationRules::stripPcreDelimiters('~^[a-z]+$~')
        );
    }

    #[Test]
    public function it_returns_pattern_unchanged_if_no_valid_delimiter(): void
    {
        // No delimiter (already clean pattern)
        $this->assertEquals(
            '^[a-z]+$',
            ValidationRules::stripPcreDelimiters('^[a-z]+$')
        );

        // Invalid first character as delimiter
        $this->assertEquals(
            '[a-z]+',
            ValidationRules::stripPcreDelimiters('[a-z]+')
        );
    }

    #[Test]
    public function it_handles_edge_cases_in_pcre_delimiter_stripping(): void
    {
        // Very short string
        $this->assertEquals('a', ValidationRules::stripPcreDelimiters('a'));

        // Empty pattern with delimiters
        $this->assertEquals('', ValidationRules::stripPcreDelimiters('//'));

        // Single character
        $this->assertEquals('/', ValidationRules::stripPcreDelimiters('/'));
    }
}
