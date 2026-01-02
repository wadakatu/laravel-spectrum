<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO\Collections;

use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use LaravelSpectrum\DTO\Collections\ValidationRuleCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidationRuleCollectionTest extends TestCase
{
    #[Test]
    public function it_creates_empty_collection(): void
    {
        $collection = ValidationRuleCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
    }

    #[Test]
    public function it_creates_from_pipe_separated_string(): void
    {
        $collection = ValidationRuleCollection::fromString('required|email|max:255');

        $this->assertCount(3, $collection);
        $this->assertEquals(['required', 'email', 'max:255'], $collection->all());
    }

    #[Test]
    public function it_creates_empty_from_empty_string(): void
    {
        $collection = ValidationRuleCollection::fromString('');

        $this->assertTrue($collection->isEmpty());
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $rules = ['required', 'string', 'min:3'];
        $collection = ValidationRuleCollection::fromArray($rules);

        $this->assertCount(3, $collection);
        $this->assertEquals($rules, $collection->all());
    }

    #[Test]
    public function it_creates_from_array_with_file_object(): void
    {
        $fileRule = File::types(['jpg', 'png']);
        $rules = ['required', $fileRule, 'max:1024'];
        $collection = ValidationRuleCollection::fromArray($rules);

        $this->assertCount(3, $collection);
        $this->assertSame($fileRule, $collection->all()[1]);
    }

    #[Test]
    public function it_creates_from_string_using_from(): void
    {
        $collection = ValidationRuleCollection::from('required|email');

        $this->assertCount(2, $collection);
        $this->assertEquals(['required', 'email'], $collection->all());
    }

    #[Test]
    public function it_creates_from_array_using_from(): void
    {
        $rules = ['required', 'string'];
        $collection = ValidationRuleCollection::from($rules);

        $this->assertCount(2, $collection);
        $this->assertEquals($rules, $collection->all());
    }

    #[Test]
    public function it_creates_empty_from_null_using_from(): void
    {
        $collection = ValidationRuleCollection::from(null);

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
    }

    #[Test]
    public function it_detects_file_rule_in_string_rules(): void
    {
        $collection = ValidationRuleCollection::fromString('required|file|max:1024');

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_detects_image_rule(): void
    {
        $collection = ValidationRuleCollection::fromString('required|image|dimensions:min_width=100');

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_detects_mimes_rule(): void
    {
        $collection = ValidationRuleCollection::fromString('required|mimes:jpg,png,gif');

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_detects_mimetypes_rule(): void
    {
        $collection = ValidationRuleCollection::fromString('required|mimetypes:image/jpeg,image/png');

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_detects_file_object_rule(): void
    {
        $collection = ValidationRuleCollection::fromArray([
            'required',
            File::types(['jpg', 'png'])->max(1024),
        ]);

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_returns_false_when_no_file_rules(): void
    {
        $collection = ValidationRuleCollection::fromString('required|email|max:255');

        $this->assertFalse($collection->hasFileRule());
    }

    #[Test]
    public function it_gets_file_rules_only(): void
    {
        $fileRule = File::types(['pdf']);
        $collection = ValidationRuleCollection::fromArray([
            'required',
            'file',
            $fileRule,
            'max:1024',
        ]);

        $fileRules = $collection->getFileRules();

        $this->assertCount(2, $fileRules);
        $this->assertEquals('file', $fileRules->first());
        $this->assertSame($fileRule, $fileRules->last());
    }

    #[Test]
    public function it_gets_non_file_rules_only(): void
    {
        $collection = ValidationRuleCollection::fromArray([
            'required',
            'file',
            'max:1024',
            'mimes:pdf',
        ]);

        $nonFileRules = $collection->getNonFileRules();

        $this->assertCount(2, $nonFileRules);
        $this->assertEquals(['required', 'max:1024'], $nonFileRules->all());
    }

    #[Test]
    public function it_is_iterable(): void
    {
        $rules = ['required', 'file', 'max:1024'];
        $collection = ValidationRuleCollection::fromArray($rules);

        $result = [];
        foreach ($collection as $rule) {
            $result[] = $rule;
        }

        $this->assertEquals($rules, $result);
    }

    #[Test]
    public function it_inherits_filter_from_base_class(): void
    {
        $collection = ValidationRuleCollection::fromString('required|email|max:255|min:3');

        $filtered = $collection->filter(fn (string $rule): bool => str_contains($rule, ':'));

        $this->assertCount(2, $filtered);
        $this->assertEquals(['max:255', 'min:3'], $filtered->all());
        $this->assertInstanceOf(ValidationRuleCollection::class, $filtered);
    }

    #[Test]
    public function it_inherits_map_from_base_class(): void
    {
        $collection = ValidationRuleCollection::fromString('required|email');

        $result = $collection->map(fn (string $rule): string => strtoupper($rule));

        $this->assertEquals(['REQUIRED', 'EMAIL'], $result);
    }

    #[Test]
    public function it_inherits_any_from_base_class(): void
    {
        $collection = ValidationRuleCollection::fromString('required|email|max:255');

        $this->assertTrue($collection->any(fn (string $rule): bool => $rule === 'required'));
        $this->assertFalse($collection->any(fn (string $rule): bool => $rule === 'nullable'));
    }

    #[Test]
    public function it_inherits_every_from_base_class(): void
    {
        $collection = ValidationRuleCollection::fromString('required|string|max:255');

        $this->assertTrue($collection->every(fn (string $rule): bool => is_string($rule)));
        $this->assertFalse($collection->every(fn (string $rule): bool => str_contains($rule, ':')));
    }

    #[Test]
    public function it_reindexes_array_keys_on_from_array(): void
    {
        $rules = [
            5 => 'required',
            10 => 'email',
            15 => 'max:255',
        ];
        $collection = ValidationRuleCollection::fromArray($rules);

        $this->assertEquals(['required', 'email', 'max:255'], $collection->all());
        $this->assertEquals('required', $collection->first());
    }

    #[Test]
    public function it_handles_enum_rule_objects(): void
    {
        $enumRule = new Enum(\BackedEnum::class);
        $collection = ValidationRuleCollection::fromArray([
            'required',
            $enumRule,
            'string',
        ]);

        $this->assertCount(3, $collection);
        $this->assertFalse($collection->hasFileRule());
    }

    #[Test]
    public function it_handles_mixed_rule_types(): void
    {
        $fileRule = File::types(['jpg', 'png']);
        $enumRule = new Enum(\BackedEnum::class);
        $collection = ValidationRuleCollection::fromArray([
            'required',
            $fileRule,
            $enumRule,
            ['nested', 'rules'],
        ]);

        $this->assertCount(4, $collection);
        $this->assertTrue($collection->hasFileRule());

        $fileRules = $collection->getFileRules();
        $this->assertCount(1, $fileRules);
        $this->assertSame($fileRule, $fileRules->first());

        $nonFileRules = $collection->getNonFileRules();
        $this->assertCount(3, $nonFileRules);
    }

    #[Test]
    public function it_returns_false_for_array_rules_as_file(): void
    {
        $collection = ValidationRuleCollection::fromArray([
            ['required', 'file'],
        ]);

        $this->assertFalse($collection->hasFileRule());
    }

    #[Test]
    public function it_maintains_sequential_indexes_after_get_non_file_rules(): void
    {
        $collection = ValidationRuleCollection::fromString('file|required|image|email');

        $nonFileRules = $collection->getNonFileRules();

        // Verify first() and last() work correctly (would fail if indexes not resequenced)
        $this->assertEquals('required', $nonFileRules->first());
        $this->assertEquals('email', $nonFileRules->last());
        $this->assertEquals(['required', 'email'], $nonFileRules->all());
    }

    #[Test]
    public function it_does_not_misidentify_rules_with_colon_as_file_rules(): void
    {
        // Specifically test rules that have colons but are NOT file rules
        $collection = ValidationRuleCollection::fromString('max:1024|min:1|regex:/^[a-z]+$/|in:foo,bar');

        $this->assertFalse($collection->hasFileRule());
        $this->assertCount(0, $collection->getFileRules());
        $this->assertCount(4, $collection->getNonFileRules());
    }

    /**
     * Test for Issue #317: File Rule object (File::types(), File::image()) not detected as file upload
     * When AST extracts validation rules, File:: static calls are converted to string representations.
     */
    #[Test]
    public function it_detects_file_types_static_call_string_as_file_rule(): void
    {
        // This is what AST extractor produces for File::types(['pdf', 'docx'])->min(1)->max(10 * 1024)
        $collection = ValidationRuleCollection::fromArray([
            'required',
            'File::types(["pdf", "docx"])->min(1)->max(10 * 1024)',
        ]);

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_detects_file_image_static_call_string_as_file_rule(): void
    {
        // This is what AST extractor produces for File::image()->min(10)->max(5 * 1024)
        $collection = ValidationRuleCollection::fromArray([
            'nullable',
            'File::image()->min(10)->max(5 * 1024)',
        ]);

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_detects_fully_qualified_file_class_as_file_rule(): void
    {
        // Test with fully qualified class name that AST might produce
        $collection = ValidationRuleCollection::fromArray([
            'required',
            '\\Illuminate\\Validation\\Rules\\File::types(["pdf"])',
        ]);

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_detects_fully_qualified_file_class_without_leading_backslash(): void
    {
        // Test FQN without leading backslash
        $collection = ValidationRuleCollection::fromArray([
            'required',
            'Illuminate\\Validation\\Rules\\File::image()',
        ]);

        $this->assertTrue($collection->hasFileRule());
    }

    #[Test]
    public function it_correctly_separates_file_static_call_strings_in_get_file_rules(): void
    {
        // Test that getFileRules() and getNonFileRules() work correctly with File:: strings
        $collection = ValidationRuleCollection::fromArray([
            'required',
            'File::types(["pdf", "docx"])->max(1024)',
            'max:255',
            '\\Illuminate\\Validation\\Rules\\File::image()',
        ]);

        $fileRules = $collection->getFileRules();
        $nonFileRules = $collection->getNonFileRules();

        $this->assertCount(2, $fileRules);
        $this->assertCount(2, $nonFileRules);
        $this->assertEquals(['required', 'max:255'], $nonFileRules->all());
    }

    #[Test]
    public function it_does_not_misidentify_strings_containing_file_in_middle_as_file_rules(): void
    {
        // Test that strings containing "File::" in unexpected positions are NOT detected as file rules
        $collection = ValidationRuleCollection::fromArray([
            'required',
            'regex:/^File::.*$/',  // A regex pattern containing File::
            'in:File::types,Other::method',  // An "in" rule containing File::
        ]);

        $this->assertFalse($collection->hasFileRule());
        $this->assertCount(0, $collection->getFileRules());
    }
}
