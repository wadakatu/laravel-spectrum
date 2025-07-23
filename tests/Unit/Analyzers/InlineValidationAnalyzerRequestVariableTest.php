<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class InlineValidationAnalyzerRequestVariableTest extends TestCase
{
    private InlineValidationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new InlineValidationAnalyzer(new TypeInference);
    }

    public function test_analyzes_request_variable_validate_method(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
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
        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('name', $result['rules']);
        $this->assertArrayHasKey('email', $result['rules']);
        $this->assertArrayHasKey('password', $result['rules']);
        $this->assertEquals('required|string|max:255', $result['rules']['name']);
        $this->assertEquals('required|email|unique:users', $result['rules']['email']);
        $this->assertEquals('required|string|min:8|confirmed', $result['rules']['password']);
    }

    public function test_analyzes_request_variable_validate_with_messages(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ], [
            'name.required' => 'The product name is required.',
            'name.max' => 'The product name cannot exceed 100 characters.',
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
        $this->assertArrayHasKey('messages', $result);
        $this->assertEquals('The product name is required.', $result['messages']['name.required']);
        $this->assertEquals('The product name cannot exceed 100 characters.', $result['messages']['name.max']);
    }

    public function test_analyzes_request_variable_validate_with_attributes(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'required|string|regex:/^[0-9]{10}$/',
        ], [], [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'phone_number' => 'Phone Number',
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
        $this->assertArrayHasKey('attributes', $result);
        $this->assertEquals('First Name', $result['attributes']['first_name']);
        $this->assertEquals('Last Name', $result['attributes']['last_name']);
        $this->assertEquals('Phone Number', $result['attributes']['phone_number']);
    }

    public function test_analyzes_different_request_variable_names(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function store(Request $req)
    {
        // Using different variable name
        $data1 = $req->validate([
            'field1' => 'required|string',
        ]);
        
        return response()->json($data1);
    }
    
    public function update(Request $httpRequest)
    {
        // Using another variable name
        $data2 = $httpRequest->validate([
            'field2' => 'required|integer',
        ]);
        
        return response()->json($data2);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        // Test first method
        $method1 = $this->findMethod($ast, 'store');
        $result1 = $this->analyzer->analyze($method1);

        $this->assertNotEmpty($result1);
        $this->assertArrayHasKey('field1', $result1['rules']);
        $this->assertEquals('required|string', $result1['rules']['field1']);

        // Test second method
        $method2 = $this->findMethod($ast, 'update');
        $result2 = $this->analyzer->analyze($method2);

        $this->assertNotEmpty($result2);
        $this->assertArrayHasKey('field2', $result2['rules']);
        $this->assertEquals('required|integer', $result2['rules']['field2']);
    }

    public function test_analyzes_request_variable_validate_with_file_upload(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'document' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'thumbnail' => 'nullable|image|dimensions:min_width=200,min_height=200',
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
        $this->assertArrayHasKey('rules', $result);
        $this->assertEquals('required|file|mimes:pdf,doc,docx|max:10240', $result['rules']['document']);
        $this->assertEquals('nullable|image|dimensions:min_width=200,min_height=200', $result['rules']['thumbnail']);
    }

    public function test_analyzes_multiple_request_variable_validate_calls(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function process(Request $request)
    {
        // First validation
        $step1 = $request->validate([
            'action' => 'required|string|in:create,update',
        ]);
        
        if ($step1['action'] === 'create') {
            // Second validation for create
            $createData = $request->validate([
                'title' => 'required|string',
                'content' => 'required|string',
            ]);
        } else {
            // Third validation for update
            $updateData = $request->validate([
                'id' => 'required|integer|exists:posts,id',
                'title' => 'sometimes|string',
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
        $this->assertArrayHasKey('rules', $result);

        // Should have all fields from all validate calls merged
        $this->assertArrayHasKey('action', $result['rules']);
        $this->assertArrayHasKey('title', $result['rules']);
        $this->assertArrayHasKey('content', $result['rules']);
        $this->assertArrayHasKey('id', $result['rules']);
    }

    public function test_analyzes_request_variable_validate_with_array_rules(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:20', 'unique:users'],
            'email' => ['required', 'email', 'confirmed'],
            'terms' => ['required', 'accepted'],
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
        $this->assertArrayHasKey('rules', $result);
        $this->assertIsArray($result['rules']['username']);
        $this->assertIsArray($result['rules']['email']);
        $this->assertIsArray($result['rules']['terms']);
        $this->assertContains('required', $result['rules']['username']);
        $this->assertContains('unique:users', $result['rules']['username']);
    }

    public function test_analyzes_mixed_validation_patterns(): void
    {
        $code = <<<'PHP'
<?php
class TestController extends Controller
{
    public function mixed(Request $request)
    {
        // Using $this->validate()
        $basicData = $this->validate($request, [
            'mode' => 'required|string|in:simple,advanced',
        ]);
        
        // Using $request->validate()
        $additionalData = $request->validate([
            'setting1' => 'required|string',
            'setting2' => 'required|boolean',
        ]);
        
        // Using request()->validate()
        $moreData = request()->validate([
            'option1' => 'nullable|string',
            'option2' => 'nullable|integer',
        ]);
        
        return response()->json(['success' => true]);
    }
}
PHP;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $method = $this->findMethod($ast, 'mixed');
        $result = $this->analyzer->analyze($method);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('rules', $result);

        // Should have fields from all three validation methods
        $this->assertArrayHasKey('mode', $result['rules']);
        $this->assertArrayHasKey('setting1', $result['rules']);
        $this->assertArrayHasKey('setting2', $result['rules']);
        $this->assertArrayHasKey('option1', $result['rules']);
        $this->assertArrayHasKey('option2', $result['rules']);
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
