<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\AST\Visitors;

use LaravelSpectrum\Analyzers\AST\Visitors\UseStatementExtractorVisitor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;

class UseStatementExtractorVisitorTest extends TestCase
{
    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    #[Test]
    public function it_extracts_simple_use_statements()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Http\Controllers;

        use Illuminate\Http\Request;
        use App\Models\User;
        use App\Services\UserService;

        class UserController extends Controller
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $this->assertCount(3, $useStatements);
        $this->assertEquals('Illuminate\Http\Request', $useStatements['Request']);
        $this->assertEquals('App\Models\User', $useStatements['User']);
        $this->assertEquals('App\Services\UserService', $useStatements['UserService']);
    }

    #[Test]
    public function it_extracts_use_statements_with_aliases()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Http\Resources;

        use Illuminate\Http\Resources\Json\JsonResource as BaseResource;
        use App\Models\User as UserModel;
        use App\Enums\StatusEnum as Status;

        class UserResource extends BaseResource
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $this->assertCount(3, $useStatements);
        $this->assertEquals('Illuminate\Http\Resources\Json\JsonResource', $useStatements['BaseResource']);
        $this->assertEquals('App\Models\User', $useStatements['UserModel']);
        $this->assertEquals('App\Enums\StatusEnum', $useStatements['Status']);
    }

    #[Test]
    public function it_handles_multiple_use_statements_in_one_line()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Http\Controllers;

        use App\Models\{User, Post, Comment};
        use Illuminate\Support\Facades\{DB, Cache, Log};

        class BlogController extends Controller
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $this->assertCount(6, $useStatements);
        $this->assertEquals('App\Models\User', $useStatements['User']);
        $this->assertEquals('App\Models\Post', $useStatements['Post']);
        $this->assertEquals('App\Models\Comment', $useStatements['Comment']);
        $this->assertEquals('Illuminate\Support\Facades\DB', $useStatements['DB']);
        $this->assertEquals('Illuminate\Support\Facades\Cache', $useStatements['Cache']);
        $this->assertEquals('Illuminate\Support\Facades\Log', $useStatements['Log']);
    }

    #[Test]
    public function it_handles_function_and_const_imports()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Helpers;

        use function str_contains;
        use function array_map;
        use const PHP_EOL;
        use const DIRECTORY_SEPARATOR;

        class StringHelper
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        // The current implementation processes all use statements regardless of type
        $this->assertCount(4, $useStatements);
        $this->assertEquals('str_contains', $useStatements['str_contains']);
        $this->assertEquals('array_map', $useStatements['array_map']);
        $this->assertEquals('PHP_EOL', $useStatements['PHP_EOL']);
        $this->assertEquals('DIRECTORY_SEPARATOR', $useStatements['DIRECTORY_SEPARATOR']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_use_statements()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Simple;

        class SimpleClass
        {
            public function method()
            {
                return 'Hello';
            }
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $this->assertEmpty($useStatements);
    }

    #[Test]
    public function it_handles_deeply_nested_namespaces()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Domain\User\Services\Implementations;

        use App\Domain\User\Contracts\UserRepositoryInterface;
        use App\Infrastructure\Database\Eloquent\Models\User;
        use Illuminate\Support\Collection;

        class UserService
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $this->assertCount(3, $useStatements);
        $this->assertEquals('App\Domain\User\Contracts\UserRepositoryInterface', $useStatements['UserRepositoryInterface']);
        $this->assertEquals('App\Infrastructure\Database\Eloquent\Models\User', $useStatements['User']);
        $this->assertEquals('Illuminate\Support\Collection', $useStatements['Collection']);
    }

    #[Test]
    public function it_handles_same_class_name_with_different_namespaces()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Http\Controllers;

        use App\Models\User;
        use App\DTOs\User as UserDTO;
        use External\Library\User as ExternalUser;

        class UserController extends Controller
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $this->assertCount(3, $useStatements);
        $this->assertEquals('App\Models\User', $useStatements['User']);
        $this->assertEquals('App\DTOs\User', $useStatements['UserDTO']);
        $this->assertEquals('External\Library\User', $useStatements['ExternalUser']);
    }

    #[Test]
    public function it_preserves_order_of_use_statements()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Services;

        use Illuminate\Support\Str;
        use App\Models\User;
        use Illuminate\Support\Collection;
        use App\Repositories\UserRepository;

        class UserService
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $keys = array_keys($useStatements);
        $this->assertEquals(['Str', 'User', 'Collection', 'UserRepository'], $keys);
    }

    #[Test]
    public function it_handles_trait_use_statements()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Traits;

        use Illuminate\Support\Traits\Macroable;
        use App\Traits\HasTimestamps;
        use App\Traits\Searchable as SearchableTrait;

        trait CompositeTrait
        {
            use Macroable;
            use HasTimestamps;
            use SearchableTrait;
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        // Only namespace-level use statements are captured, not trait uses
        $this->assertCount(3, $useStatements);
        $this->assertEquals('Illuminate\Support\Traits\Macroable', $useStatements['Macroable']);
        $this->assertEquals('App\Traits\HasTimestamps', $useStatements['HasTimestamps']);
        $this->assertEquals('App\Traits\Searchable', $useStatements['SearchableTrait']);
    }

    #[Test]
    public function it_handles_global_namespace_references()
    {
        $code = <<<'PHP'
        <?php
        namespace App\Helpers;

        use DateTime;
        use Exception;
        use stdClass;

        class DateHelper
        {
            // ...
        }
        PHP;

        $visitor = new UseStatementExtractorVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $useStatements = $visitor->getUseStatements();

        $this->assertCount(3, $useStatements);
        $this->assertEquals('DateTime', $useStatements['DateTime']);
        $this->assertEquals('Exception', $useStatements['Exception']);
        $this->assertEquals('stdClass', $useStatements['stdClass']);
    }
}
