<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\DTO\InlineValidationInfo;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class InlineValidationAnalyzerRequestValidateTest extends TestCase
{
    private InlineValidationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new InlineValidationAnalyzer(new TypeInference);
    }

    public function test_analyzes_request_validate_method(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function store(Request $request)
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'age' => 'required|integer|min:18',
        ]);
        
        return response()->json($validated);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'store');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(InlineValidationInfo::class, $result);
        $this->assertTrue($result->hasRules());
        $this->assertArrayHasKey('name', $result->rules);
        $this->assertArrayHasKey('email', $result->rules);
        $this->assertArrayHasKey('age', $result->rules);
        $this->assertEquals('required|string|max:255', $result->rules['name']);
        $this->assertEquals('required|email|unique:users', $result->rules['email']);
        $this->assertEquals('required|integer|min:18', $result->rules['age']);
    }

    public function test_analyzes_request_validate_with_messages(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function update(Request $request)
    {
        $validated = request()->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string',
        ], [
            'title.required' => 'タイトルは必須です',
            'content.required' => '内容は必須です',
        ]);
        
        return response()->json($validated);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'update');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertTrue($result->hasMessages());
        $this->assertEquals('タイトルは必須です', $result->messages['title.required']);
        $this->assertEquals('内容は必須です', $result->messages['content.required']);
    }

    public function test_analyzes_request_validate_with_attributes(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function create(Request $request)
    {
        $validated = request()->validate([
            'user_name' => 'required|string',
            'user_email' => 'required|email',
        ], [], [
            'user_name' => 'ユーザー名',
            'user_email' => 'メールアドレス',
        ]);
        
        return response()->json($validated);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'create');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertTrue($result->hasAttributes());
        $this->assertEquals('ユーザー名', $result->attributes['user_name']);
        $this->assertEquals('メールアドレス', $result->attributes['user_email']);
    }

    public function test_analyzes_request_validate_with_file_upload(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function upload(Request $request)
    {
        $validated = request()->validate([
            'title' => 'required|string|max:255',
            'photo' => 'required|image|mimes:jpeg,png|max:2048',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);
        
        return response()->json(['success' => true]);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'upload');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(InlineValidationInfo::class, $result);
        $this->assertTrue($result->hasRules());
        $this->assertEquals('required|image|mimes:jpeg,png|max:2048', $result->rules['photo']);
        $this->assertEquals('nullable|file|mimes:pdf,doc,docx|max:10240', $result->rules['document']);
    }

    public function test_analyzes_multiple_request_validate_calls(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function process(Request $request)
    {
        // First validation
        $step1 = request()->validate([
            'step' => 'required|integer|in:1,2',
        ]);
        
        if ($step1['step'] === 1) {
            // Second validation
            $step1Data = request()->validate([
                'name' => 'required|string',
                'email' => 'required|email',
            ]);
        } else {
            // Third validation
            $step2Data = request()->validate([
                'company' => 'required|string',
                'position' => 'required|string',
            ]);
        }
        
        return response()->json(['success' => true]);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'process');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(InlineValidationInfo::class, $result);
        $this->assertTrue($result->hasRules());

        // Should have all fields from all validate calls merged
        $this->assertArrayHasKey('step', $result->rules);
        $this->assertArrayHasKey('name', $result->rules);
        $this->assertArrayHasKey('email', $result->rules);
        $this->assertArrayHasKey('company', $result->rules);
        $this->assertArrayHasKey('position', $result->rules);
    }

    public function test_analyzes_request_validate_with_array_rules(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function store(Request $request)
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        
        return response()->json($validated);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'store');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(InlineValidationInfo::class, $result);
        $this->assertTrue($result->hasRules());
        $this->assertIsArray($result->rules['name']);
        $this->assertIsArray($result->rules['email']);
        $this->assertIsArray($result->rules['password']);
        $this->assertContains('required', $result->rules['name']);
        $this->assertContains('string', $result->rules['name']);
        $this->assertContains('max:255', $result->rules['name']);
    }

    public function test_detects_both_this_validate_and_request_validate(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function mixed(Request $request)
    {
        // Using $this->validate()
        $basicData = $this->validate($request, [
            'type' => 'required|string|in:basic,advanced',
        ]);
        
        // Using request()->validate()
        if ($basicData['type'] === 'advanced') {
            $advancedData = request()->validate([
                'advanced_option' => 'required|string',
                'level' => 'required|integer|between:1,10',
            ]);
        }
        
        return response()->json(['success' => true]);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'mixed');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(InlineValidationInfo::class, $result);
        $this->assertTrue($result->hasRules());

        // Should have fields from both validation methods
        $this->assertArrayHasKey('type', $result->rules);
        $this->assertArrayHasKey('advanced_option', $result->rules);
        $this->assertArrayHasKey('level', $result->rules);
    }

    /**
     * Helper method to find a method node in AST
     */
    private function findMethod(array $ast, string $methodName): ?Node\Stmt\ClassMethod
    {
        $visitor = new class($methodName) extends NodeVisitorAbstract
        {
            private string $targetMethod;

            private ?Node\Stmt\ClassMethod $method = null;

            public function __construct(string $targetMethod)
            {
                $this->targetMethod = $targetMethod;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod &&
                    $node->name->toString() === $this->targetMethod) {
                    $this->method = $node;

                    return NodeTraverser::STOP_TRAVERSAL;
                }
            }

            public function getMethod(): ?Node\Stmt\ClassMethod
            {
                return $this->method;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getMethod();
    }
}
