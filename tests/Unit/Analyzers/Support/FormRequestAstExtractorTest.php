<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\FormRequestAstExtractor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PHPUnit\Framework\Attributes\Test;

class FormRequestAstExtractorTest extends TestCase
{
    private FormRequestAstExtractor $extractor;

    private \PhpParser\Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new FormRequestAstExtractor(new PrettyPrinter\Standard);
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
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
