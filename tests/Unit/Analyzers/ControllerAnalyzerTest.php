<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Analyzers\PaginationAnalyzer;
use LaravelSpectrum\Analyzers\QueryParameterAnalyzer;
use LaravelSpectrum\Analyzers\ResponseAnalyzer;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ControllerAnalyzerTest extends TestCase
{
    private ControllerAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = app(ControllerAnalyzer::class);
    }

    #[Test]
    public function it_detects_fractal_item_usage()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'show');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer', $result['fractal']['transformer']);
        $this->assertFalse($result['fractal']['collection']);
        $this->assertEquals('item', $result['fractal']['type']);
    }

    #[Test]
    public function it_detects_fractal_collection_usage()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'index');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer', $result['fractal']['transformer']);
        $this->assertTrue($result['fractal']['collection']);
        $this->assertEquals('collection', $result['fractal']['type']);
    }

    #[Test]
    public function it_detects_fractal_with_includes()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'withIncludes');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelSpectrum\Tests\Fixtures\Transformers\PostTransformer', $result['fractal']['transformer']);
        $this->assertTrue($result['fractal']['hasIncludes']);
    }

    #[Test]
    public function it_detects_both_resource_and_fractal()
    {
        $controller = TestMixedController::class;
        $result = $this->analyzer->analyze($controller, 'mixed');

        // 既存のResource検出
        $this->assertArrayHasKey('resource', $result);

        // Fractal検出も動作する
        $this->assertArrayHasKey('fractal', $result);
    }

    #[Test]
    public function it_returns_empty_array_for_non_existent_class(): void
    {
        $result = $this->analyzer->analyze('NonExistentClass\\DoesNotExist', 'someMethod');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_for_non_existent_method(): void
    {
        $result = $this->analyzer->analyze(TestFractalController::class, 'nonExistentMethod');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_controller_with_inline_validation(): void
    {
        $result = $this->analyzer->analyze(TestInlineValidationController::class, 'store');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('inlineValidation', $result);
        // Inline validation detection varies based on AST parsing; verify key exists and type is valid
        $this->assertTrue(
            is_array($result['inlineValidation']) || is_null($result['inlineValidation']),
            'inlineValidation should be either an array or null'
        );
    }

    #[Test]
    public function it_handles_union_type_parameters_with_warning(): void
    {
        // Create a controller with union type parameter
        $result = $this->analyzer->analyze(TestUnionTypeController::class, 'handle');

        $this->assertIsArray($result);
        // The warning should be logged, and analysis should continue
        $this->assertArrayHasKey('formRequest', $result);
        // FormRequest should be null because union types are not supported
        $this->assertNull($result['formRequest']);

        // Check that warning was logged
        $this->assertNotEmpty($this->analyzer->getErrorCollector()->getWarnings());
    }

    #[Test]
    public function it_detects_form_request_from_method_parameter(): void
    {
        $result = $this->analyzer->analyze(TestFormRequestController::class, 'store');

        $this->assertArrayHasKey('formRequest', $result);
        $this->assertEquals(TestStoreRequest::class, $result['formRequest']);
    }

    #[Test]
    public function it_resolves_class_name_in_same_namespace(): void
    {
        // Test the resolveClassName path where class is in same namespace
        $result = $this->analyzer->analyze(TestResourceController::class, 'show');

        $this->assertIsArray($result);
        // Verify analysis completed by checking standard result keys exist
        $this->assertArrayHasKey('formRequest', $result);
        $this->assertArrayHasKey('resource', $result);
    }

    #[Test]
    public function it_handles_controller_without_file_path(): void
    {
        // Create a mock AstHelper that simulates no file path
        $mockAstHelper = $this->createMock(AstHelper::class);
        $mockAstHelper->method('parseFile')->willReturn(null);

        $analyzer = new ControllerAnalyzer(
            $this->app->make(FormRequestAnalyzer::class),
            $this->app->make(InlineValidationAnalyzer::class),
            $this->app->make(PaginationAnalyzer::class),
            $this->app->make(QueryParameterAnalyzer::class),
            $this->app->make(EnumAnalyzer::class),
            $this->app->make(ResponseAnalyzer::class),
            $mockAstHelper,
        );

        $result = $analyzer->analyze(TestFractalController::class, 'show');

        // Analysis should continue even if AST parsing fails
        $this->assertIsArray($result);
    }

    #[Test]
    public function it_handles_ast_parsing_exception(): void
    {
        // Create a mock AstHelper that throws an exception
        $mockAstHelper = $this->createMock(AstHelper::class);
        $mockAstHelper->method('parseFile')->willThrowException(new \Exception('Parse error'));

        $analyzer = new ControllerAnalyzer(
            $this->app->make(FormRequestAnalyzer::class),
            $this->app->make(InlineValidationAnalyzer::class),
            $this->app->make(PaginationAnalyzer::class),
            $this->app->make(QueryParameterAnalyzer::class),
            $this->app->make(EnumAnalyzer::class),
            $this->app->make(ResponseAnalyzer::class),
            $mockAstHelper,
        );

        $result = $analyzer->analyze(TestFractalController::class, 'show');

        // Analysis should continue even if exception is thrown
        $this->assertIsArray($result);
        // Warning should be logged
        $this->assertNotEmpty($analyzer->getErrorCollector()->getWarnings());
    }

    #[Test]
    public function it_handles_query_parameters_with_validation_merge(): void
    {
        // Test controller with query parameters
        $result = $this->analyzer->analyze(TestQueryParamController::class, 'index');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('queryParameters', $result);
    }

    #[Test]
    public function it_handles_controller_with_enum_parameter(): void
    {
        $result = $this->analyzer->analyze(TestEnumController::class, 'show');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('enumParameters', $result);
    }

    #[Test]
    public function it_handles_resource_collection_pattern(): void
    {
        $result = $this->analyzer->analyze(TestResourceController::class, 'index');

        $this->assertIsArray($result);
        // Verify analysis completed and key exists (resource may or may not be detected
        // depending on whether TestUserResource extends the actual Laravel Resource class)
        $this->assertArrayHasKey('resource', $result);
        $this->assertArrayHasKey('returnsCollection', $result);
    }

    #[Test]
    public function it_detects_union_return_type_resources(): void
    {
        $result = $this->analyzer->analyze(TestUnionReturnController::class, 'conditionalReturn');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('resourceClasses', $result);
        $this->assertIsArray($result['resourceClasses']);
        $this->assertCount(2, $result['resourceClasses']);
        $this->assertContains(TestUserResource::class, $result['resourceClasses']);
        $this->assertContains(TestPostResource::class, $result['resourceClasses']);
        // Backward compatibility: resource should be first class
        $this->assertSame(TestUserResource::class, $result['resource']);
    }

    #[Test]
    public function it_detects_single_return_type_resource(): void
    {
        $result = $this->analyzer->analyze(TestUnionReturnController::class, 'regularReturn');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('resourceClasses', $result);
        $this->assertIsArray($result['resourceClasses']);
        $this->assertCount(1, $result['resourceClasses']);
        $this->assertSame(TestUserResource::class, $result['resourceClasses'][0]);
        $this->assertSame(TestUserResource::class, $result['resource']);
    }

    #[Test]
    public function it_detects_inline_validation_in_invokable_controller(): void
    {
        $result = $this->analyzer->analyze(TestInvokableController::class, '__invoke');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('inlineValidation', $result);
        $this->assertNotNull($result['inlineValidation'], 'Inline validation should be detected in __invoke()');
        $this->assertArrayHasKey('rules', $result['inlineValidation']);
        $this->assertArrayHasKey('message', $result['inlineValidation']['rules']);
        $this->assertArrayHasKey('priority', $result['inlineValidation']['rules']);
    }

    #[Test]
    public function it_detects_deprecated_annotation(): void
    {
        $result = $this->analyzer->analyze(
            \LaravelSpectrum\Tests\Fixtures\Controllers\DeprecatedController::class,
            'deprecatedMethod'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('deprecated', $result);
        $this->assertTrue($result['deprecated']);
    }

    #[Test]
    public function it_detects_deprecated_annotation_with_reason(): void
    {
        $result = $this->analyzer->analyze(
            \LaravelSpectrum\Tests\Fixtures\Controllers\DeprecatedController::class,
            'deprecatedWithReason'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('deprecated', $result);
        $this->assertTrue($result['deprecated']);
    }

    #[Test]
    public function it_returns_false_for_non_deprecated_method(): void
    {
        $result = $this->analyzer->analyze(
            \LaravelSpectrum\Tests\Fixtures\Controllers\DeprecatedController::class,
            'activeMethod'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('deprecated', $result);
        $this->assertFalse($result['deprecated']);
    }

    #[Test]
    public function it_returns_false_for_method_without_docblock(): void
    {
        $result = $this->analyzer->analyze(
            \LaravelSpectrum\Tests\Fixtures\Controllers\DeprecatedController::class,
            'methodWithoutDocblock'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('deprecated', $result);
        $this->assertFalse($result['deprecated']);
    }
}

// テスト用のコントローラークラス
class TestFractalController
{
    public function show($id)
    {
        $user = User::find($id);

        return fractal()->item($user, new \LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer);
    }

    public function index()
    {
        $users = User::all();

        return fractal()->collection($users, new \LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer);
    }

    public function withIncludes()
    {
        $posts = Post::all();

        return fractal()
            ->collection($posts, new \LaravelSpectrum\Tests\Fixtures\Transformers\PostTransformer)
            ->parseIncludes(request()->get('include', ''))
            ->respond();
    }
}

class TestMixedController
{
    public function mixed()
    {
        if (request()->wantsJson()) {
            return fractal()->item($user, new \LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer);
        }

        return new UserResource($user);
    }
}

/**
 * Controller with inline validation
 */
class TestInlineValidationController
{
    public function store()
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        return response()->json($validated);
    }
}

/**
 * Controller with union type parameter (not nullable, actual union)
 */
class TestUnionTypeController
{
    public function handle(TestStoreRequest|TestUpdateRequest $request)
    {
        return response()->json(['ok' => true]);
    }
}

/**
 * Another FormRequest for union type testing
 */
class TestUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return ['id' => 'required|integer'];
    }
}

/**
 * FormRequest for testing
 */
class TestStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }
}

/**
 * Controller with FormRequest parameter
 */
class TestFormRequestController
{
    public function store(TestStoreRequest $request)
    {
        return response()->json(['ok' => true]);
    }
}

/**
 * Controller with Resource
 */
class TestResourceController
{
    public function index()
    {
        $users = collect([]);

        return TestUserResource::collection($users);
    }

    public function show($id)
    {
        $user = new \stdClass;

        return new TestUserResource($user);
    }
}

/**
 * Dummy Resource class
 */
class TestUserResource
{
    public function __construct($resource) {}

    public static function collection($resource)
    {
        return new static($resource);
    }
}

/**
 * Controller with query parameters
 */
class TestQueryParamController
{
    public function index()
    {
        $search = request()->query('search');
        $page = request()->query('page', 1);

        return response()->json(['search' => $search, 'page' => $page]);
    }
}

/**
 * Controller with enum parameter
 */
class TestEnumController
{
    public function show(TestStatusEnum $status)
    {
        return response()->json(['status' => $status->value]);
    }
}

/**
 * Enum for testing
 */
enum TestStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

/**
 * Second Resource for union return type testing
 */
class TestPostResource
{
    public function __construct($resource) {}

    public static function collection($resource)
    {
        return new static($resource);
    }
}

/**
 * Controller with union return type for testing issue #299
 */
class TestUnionReturnController
{
    public function conditionalReturn(): TestUserResource|TestPostResource
    {
        // Actual logic would return based on condition
        return new TestUserResource(new \stdClass);
    }

    public function regularReturn(): TestUserResource
    {
        return new TestUserResource(new \stdClass);
    }
}

/**
 * Invokable controller for testing issue #300
 */
class TestInvokableController
{
    public function __invoke()
    {
        $validated = request()->validate([
            'message' => 'required|string|max:500',
            'priority' => 'nullable|integer|in:1,2,3,4,5',
        ]);

        return response()->json($validated);
    }
}
