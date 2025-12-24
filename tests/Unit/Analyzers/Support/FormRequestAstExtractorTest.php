<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\FormRequestAstExtractor;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PHPUnit\Framework\Attributes\Test;

class FormRequestAstExtractorTest extends TestCase
{
    private FormRequestAstExtractor $extractor;

    private \PhpParser\Parser $parser;

    private ErrorCollector $errorCollector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorCollector = new ErrorCollector;
        $this->extractor = new FormRequestAstExtractor(
            new PrettyPrinter\Standard,
            null,
            $this->errorCollector
        );
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    // ========== parseFile tests ==========

    #[Test]
    public function it_parses_file_successfully(): void
    {
        // Use a known fixture file
        $filePath = __DIR__.'/../../../Fixtures/FormRequests/EnumTestRequest.php';

        $ast = $this->extractor->parseFile($filePath);

        $this->assertNotNull($ast);
        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    #[Test]
    public function it_returns_null_when_file_does_not_exist(): void
    {
        $ast = $this->extractor->parseFile('/non/existent/path/file.php');

        $this->assertNull($ast);
    }

    #[Test]
    public function it_parses_file_with_class_content(): void
    {
        $filePath = __DIR__.'/../../../Fixtures/FormRequests/EnumTestRequest.php';

        $ast = $this->extractor->parseFile($filePath);
        $classNode = $this->extractor->findClassNode($ast, 'EnumTestRequest');

        $this->assertNotNull($classNode);
        $this->assertEquals('EnumTestRequest', $classNode->name->toString());
    }

    // ========== parseCode tests ==========

    #[Test]
    public function it_parses_code_string_successfully(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
PHP;

        $ast = $this->extractor->parseCode($code);

        $this->assertNotNull($ast);
        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    #[Test]
    public function it_parses_code_and_finds_class(): void
    {
        $code = <<<'PHP'
<?php

class MyFormRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email'];
    }
}
PHP;

        $ast = $this->extractor->parseCode($code);
        $classNode = $this->extractor->findClassNode($ast, 'MyFormRequest');

        $this->assertNotNull($classNode);
        $this->assertEquals('MyFormRequest', $classNode->name->toString());
    }

    // ========== Parse error handling tests ==========

    #[Test]
    public function it_returns_null_and_logs_error_for_invalid_php_syntax_in_code(): void
    {
        $invalidCode = '<?php class { broken syntax';

        $ast = $this->extractor->parseCode($invalidCode);

        $this->assertNull($ast);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('parse_error', $errors[0]['metadata']['error_type']);
    }

    #[Test]
    public function it_returns_null_and_logs_error_for_invalid_php_syntax_in_file(): void
    {
        // Create a temporary file with invalid PHP
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.php';
        file_put_contents($tempFile, '<?php class Test { invalid syntax');

        try {
            $ast = $this->extractor->parseFile($tempFile);

            $this->assertNull($ast);

            $errors = $this->errorCollector->getErrors();
            $this->assertNotEmpty($errors);
            $this->assertEquals('parse_error', $errors[0]['metadata']['error_type']);
            $this->assertStringContainsString($tempFile, $errors[0]['metadata']['file_path']);
        } finally {
            unlink($tempFile);
        }
    }

    #[Test]
    public function it_logs_warning_when_file_does_not_exist(): void
    {
        $ast = $this->extractor->parseFile('/non/existent/path/file.php');

        $this->assertNull($ast);

        $warnings = $this->errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertEquals('file_not_found', $warnings[0]['metadata']['error_type']);
    }

    // ========== Empty file/code handling tests ==========

    #[Test]
    public function it_handles_empty_code_string(): void
    {
        $ast = $this->extractor->parseCode('');

        // Empty string returns empty array from parser
        $this->assertIsArray($ast);
        $this->assertEmpty($ast);
    }

    #[Test]
    public function it_handles_code_with_only_php_tag(): void
    {
        $ast = $this->extractor->parseCode('<?php');

        $this->assertIsArray($ast);
        $this->assertEmpty($ast);
    }

    #[Test]
    public function it_handles_empty_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_').'.php';
        file_put_contents($tempFile, '');

        try {
            $ast = $this->extractor->parseFile($tempFile);
            // Empty file returns empty array from parser
            $this->assertIsArray($ast);
            $this->assertEmpty($ast);
        } finally {
            unlink($tempFile);
        }
    }

    #[Test]
    public function it_handles_file_with_only_php_tag(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_').'.php';
        file_put_contents($tempFile, '<?php');

        try {
            $ast = $this->extractor->parseFile($tempFile);
            $this->assertIsArray($ast);
            $this->assertEmpty($ast);
        } finally {
            unlink($tempFile);
        }
    }

    // ========== findClassNode tests ==========

    #[Test]
    public function it_finds_class_node_by_name(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Http\Requests;

class StoreUserRequest
{
    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'StoreUserRequest');

        $this->assertNotNull($classNode);
        $this->assertEquals('StoreUserRequest', $classNode->name->toString());
    }

    #[Test]
    public function it_returns_null_when_class_not_found(): void
    {
        $code = <<<'PHP'
<?php

class SomeOtherClass {}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'NonExistentClass');

        $this->assertNull($classNode);
    }

    // ========== findMethodNode tests ==========

    #[Test]
    public function it_finds_method_node_by_name(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestClass');

        $rulesMethod = $this->extractor->findMethodNode($classNode, 'rules');
        $this->assertNotNull($rulesMethod);
        $this->assertEquals('rules', $rulesMethod->name->toString());

        $attributesMethod = $this->extractor->findMethodNode($classNode, 'attributes');
        $this->assertNotNull($attributesMethod);
        $this->assertEquals('attributes', $attributesMethod->name->toString());
    }

    #[Test]
    public function it_returns_null_when_method_not_found(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    public function someMethod(): void {}
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestClass');

        $method = $this->extractor->findMethodNode($classNode, 'nonExistentMethod');
        $this->assertNull($method);
    }

    // ========== findAnonymousClassNode tests ==========

    #[Test]
    public function it_finds_anonymous_class_node(): void
    {
        $code = <<<'PHP'
<?php

$request = new class extends FormRequest {
    public function rules(): array
    {
        return ['email' => 'required|email'];
    }
};
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findAnonymousClassNode($ast);

        $this->assertNotNull($classNode);
    }

    #[Test]
    public function it_returns_null_when_no_anonymous_class(): void
    {
        $code = <<<'PHP'
<?php

class RegularClass {}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findAnonymousClassNode($ast);

        $this->assertNull($classNode);
    }

    // ========== extractRules tests ==========

    #[Test]
    public function it_extracts_rules_from_class(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $rules = $this->extractor->extractRules($classNode);

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertEquals('required|string|max:255', $rules['name']);
        $this->assertEquals('required|email', $rules['email']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_rules_method(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $rules = $this->extractor->extractRules($classNode);

        $this->assertIsArray($rules);
        $this->assertEmpty($rules);
    }

    // ========== extractAttributes tests ==========

    #[Test]
    public function it_extracts_attributes_from_class(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function attributes(): array
    {
        return [
            'name' => 'ユーザー名',
            'email' => 'メールアドレス',
        ];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $attributes = $this->extractor->extractAttributes($classNode);

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('email', $attributes);
        $this->assertEquals('ユーザー名', $attributes['name']);
        $this->assertEquals('メールアドレス', $attributes['email']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_attributes_method(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function rules(): array
    {
        return [];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $attributes = $this->extractor->extractAttributes($classNode);

        $this->assertIsArray($attributes);
        $this->assertEmpty($attributes);
    }

    // ========== extractMessages tests ==========

    #[Test]
    public function it_extracts_messages_from_class(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.email' => 'Invalid email format.',
        ];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $messages = $this->extractor->extractMessages($classNode);

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('email.email', $messages);
        $this->assertEquals('Name is required.', $messages['name.required']);
        $this->assertEquals('Invalid email format.', $messages['email.email']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_messages_method(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function rules(): array
    {
        return [];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $messages = $this->extractor->extractMessages($classNode);

        $this->assertIsArray($messages);
        $this->assertEmpty($messages);
    }

    // ========== extractUseStatements tests ==========

    #[Test]
    public function it_extracts_use_statements(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class TestRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $useStatements = $this->extractor->extractUseStatements($ast);

        $this->assertArrayHasKey('UserStatus', $useStatements);
        $this->assertArrayHasKey('User', $useStatements);
        $this->assertArrayHasKey('FormRequest', $useStatements);
        $this->assertEquals('App\Enums\UserStatus', $useStatements['UserStatus']);
        $this->assertEquals('App\Models\User', $useStatements['User']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_use_statements(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function rules(): array
    {
        return [];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $useStatements = $this->extractor->extractUseStatements($ast);

        $this->assertIsArray($useStatements);
        $this->assertEmpty($useStatements);
    }

    // ========== extractConditionalRules tests ==========

    #[Test]
    public function it_extracts_conditional_rules(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string',
        ];

        if ($this->type === 'premium') {
            $rules['subscription_id'] = 'required|string';
        }

        return $rules;
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $conditionalRules = $this->extractor->extractConditionalRules($classNode);

        $this->assertArrayHasKey('rules_sets', $conditionalRules);
        $this->assertArrayHasKey('merged_rules', $conditionalRules);
    }

    #[Test]
    public function it_returns_empty_structure_when_no_rules_method_for_conditional(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $conditionalRules = $this->extractor->extractConditionalRules($classNode);

        $this->assertEquals(['rules_sets' => [], 'merged_rules' => []], $conditionalRules);
    }

    // ========== Edge cases ==========

    #[Test]
    public function it_handles_rules_with_array_syntax(): void
    {
        $code = <<<'PHP'
<?php

class TestRequest
{
    public function rules(): array
    {
        return [
            'age' => ['required', 'integer', 'min:0', 'max:150'],
        ];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'TestRequest');
        $rules = $this->extractor->extractRules($classNode);

        $this->assertArrayHasKey('age', $rules);
        $this->assertIsArray($rules['age']);
        $this->assertContains('required', $rules['age']);
        $this->assertContains('integer', $rules['age']);
    }

    #[Test]
    public function it_handles_nested_namespaces(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Status;

class StoreUserRequest
{
    public function rules(): array
    {
        return ['status' => 'required'];
    }
}
PHP;

        $ast = $this->parser->parse($code);
        $classNode = $this->extractor->findClassNode($ast, 'StoreUserRequest');

        $this->assertNotNull($classNode);

        $useStatements = $this->extractor->extractUseStatements($ast);
        $this->assertArrayHasKey('Status', $useStatements);
    }
}
