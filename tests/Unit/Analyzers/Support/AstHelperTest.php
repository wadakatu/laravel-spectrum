<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;

class AstHelperTest extends TestCase
{
    private AstHelper $helper;

    private ErrorCollector $errorCollector;

    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorCollector = new ErrorCollector;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->helper = new AstHelper($this->parser, $this->errorCollector);
    }

    // ========== parseFile tests ==========

    #[Test]
    public function it_parses_file_successfully(): void
    {
        $filePath = __DIR__.'/../../../Fixtures/FormRequests/EnumTestRequest.php';

        $ast = $this->helper->parseFile($filePath);

        $this->assertNotNull($ast);
        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    #[Test]
    public function it_returns_null_when_file_does_not_exist(): void
    {
        $ast = $this->helper->parseFile('/non/existent/path/file.php');

        $this->assertNull($ast);

        $warnings = $this->errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertEquals('file_not_found', $warnings[0]->metadata['error_type']);
    }

    #[Test]
    public function it_returns_null_and_logs_error_for_invalid_php_syntax_in_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.php';
        file_put_contents($tempFile, '<?php class Test { invalid syntax');

        try {
            $ast = $this->helper->parseFile($tempFile);

            $this->assertNull($ast);

            $errors = $this->errorCollector->getErrors();
            $this->assertNotEmpty($errors);
            $this->assertEquals('parse_error', $errors[0]->metadata['error_type']);
            $this->assertStringContainsString($tempFile, $errors[0]->metadata['file_path']);
        } finally {
            unlink($tempFile);
        }
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

        $ast = $this->helper->parseCode($code);

        $this->assertNotNull($ast);
        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    #[Test]
    public function it_returns_null_and_logs_error_for_invalid_php_syntax(): void
    {
        $invalidCode = '<?php class { broken syntax';

        $ast = $this->helper->parseCode($invalidCode);

        $this->assertNull($ast);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('parse_error', $errors[0]->metadata['error_type']);
    }

    #[Test]
    public function it_includes_source_context_in_error_metadata(): void
    {
        $invalidCode = '<?php class { broken syntax';

        $ast = $this->helper->parseCode($invalidCode, 'test/file.php');

        $this->assertNull($ast);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('test/file.php', $errors[0]->metadata['file_path']);
    }

    #[Test]
    public function it_handles_empty_code_string(): void
    {
        $ast = $this->helper->parseCode('');

        $this->assertIsArray($ast);
        $this->assertEmpty($ast);
    }

    #[Test]
    public function it_handles_code_with_only_php_tag(): void
    {
        $ast = $this->helper->parseCode('<?php');

        $this->assertIsArray($ast);
        $this->assertEmpty($ast);
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

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'StoreUserRequest');

        $this->assertNotNull($classNode);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $classNode);
        $this->assertEquals('StoreUserRequest', $classNode->name->toString());
    }

    #[Test]
    public function it_returns_null_when_class_not_found(): void
    {
        $code = <<<'PHP'
<?php

class SomeOtherClass {}
PHP;

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'NonExistentClass');

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

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'TestClass');

        $rulesMethod = $this->helper->findMethodNode($classNode, 'rules');
        $this->assertNotNull($rulesMethod);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $rulesMethod);
        $this->assertEquals('rules', $rulesMethod->name->toString());

        $attributesMethod = $this->helper->findMethodNode($classNode, 'attributes');
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

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'TestClass');

        $method = $this->helper->findMethodNode($classNode, 'nonExistentMethod');
        $this->assertNull($method);
    }

    // ========== findPropertyNode tests ==========

    #[Test]
    public function it_finds_property_node_by_name(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    protected array $availableIncludes = ['user', 'comments'];

    protected array $defaultIncludes = ['author'];

    public string $publicProp = 'test';
}
PHP;

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'TestClass');

        $availableIncludes = $this->helper->findPropertyNode($classNode, 'availableIncludes');
        $this->assertNotNull($availableIncludes);
        $this->assertInstanceOf(Node\Stmt\Property::class, $availableIncludes);

        $defaultIncludes = $this->helper->findPropertyNode($classNode, 'defaultIncludes');
        $this->assertNotNull($defaultIncludes);

        $publicProp = $this->helper->findPropertyNode($classNode, 'publicProp');
        $this->assertNotNull($publicProp);
    }

    #[Test]
    public function it_returns_null_when_property_not_found(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    protected string $existingProp = 'value';
}
PHP;

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'TestClass');

        $property = $this->helper->findPropertyNode($classNode, 'nonExistentProp');
        $this->assertNull($property);
    }

    #[Test]
    public function it_finds_property_in_multi_declaration_statement(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    protected $firstProp, $secondProp, $thirdProp;
}
PHP;

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'TestClass');

        $secondProp = $this->helper->findPropertyNode($classNode, 'secondProp');
        $this->assertNotNull($secondProp);
        $this->assertInstanceOf(Node\Stmt\Property::class, $secondProp);
    }

    #[Test]
    public function it_finds_property_without_default_value(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    protected string $uninitializedProp;
}
PHP;

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'TestClass');

        $prop = $this->helper->findPropertyNode($classNode, 'uninitializedProp');
        $this->assertNotNull($prop);
        $this->assertInstanceOf(Node\Stmt\Property::class, $prop);
    }

    #[Test]
    public function it_returns_null_for_empty_class(): void
    {
        $code = <<<'PHP'
<?php

class EmptyClass {}
PHP;

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'EmptyClass');

        $prop = $this->helper->findPropertyNode($classNode, 'anyProp');
        $this->assertNull($prop);
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

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findAnonymousClassNode($ast);

        $this->assertNotNull($classNode);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $classNode);
    }

    #[Test]
    public function it_returns_null_when_no_anonymous_class(): void
    {
        $code = <<<'PHP'
<?php

class RegularClass {}
PHP;

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findAnonymousClassNode($ast);

        $this->assertNull($classNode);
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

        $ast = $this->helper->parseCode($code);
        $useStatements = $this->helper->extractUseStatements($ast);

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

        $ast = $this->helper->parseCode($code);
        $useStatements = $this->helper->extractUseStatements($ast);

        $this->assertIsArray($useStatements);
        $this->assertEmpty($useStatements);
    }

    // ========== traverse tests ==========

    #[Test]
    public function it_traverses_ast_with_visitor(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    public function testMethod(): void {}
}
PHP;

        $ast = $this->helper->parseCode($code);

        $visitor = new \LaravelSpectrum\Analyzers\AST\Visitors\ClassFindingVisitor('TestClass');
        $this->helper->traverse($ast, $visitor);

        $this->assertNotNull($visitor->getClassNode());
        $this->assertEquals('TestClass', $visitor->getClassNode()->name->toString());
    }

    #[Test]
    public function it_traverses_method_nodes_with_visitor(): void
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

        $ast = $this->helper->parseCode($code);
        $classNode = $this->helper->findClassNode($ast, 'TestClass');
        $rulesMethod = $this->helper->findMethodNode($classNode, 'rules');

        $printer = new \PhpParser\PrettyPrinter\Standard;
        $visitor = new \LaravelSpectrum\Analyzers\AST\Visitors\RulesExtractorVisitor($printer);
        $this->helper->traverse([$rulesMethod], $visitor);

        $rules = $visitor->getRules();
        $this->assertArrayHasKey('name', $rules);
        $this->assertEquals('required', $rules['name']);
    }

    // ========== Constructor tests ==========

    #[Test]
    public function it_creates_default_error_collector_when_not_provided(): void
    {
        $helper = new AstHelper($this->parser);

        // Should work without errors
        $ast = $helper->parseCode('<?php class Test {}');
        $this->assertNotNull($ast);
    }

    #[Test]
    public function it_uses_provided_parser(): void
    {
        $parser = (new \PhpParser\ParserFactory)->createForNewestSupportedVersion();
        $helper = new AstHelper($parser, $this->errorCollector);

        $ast = $helper->parseCode('<?php class Test {}');
        $this->assertNotNull($ast);
    }
}
