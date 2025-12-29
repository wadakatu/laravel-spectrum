<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\FormRequestAnalysisContext;
use LaravelSpectrum\DTO\FormRequestAnalysisContextType;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use stdClass;

class FormRequestAnalysisContextTest extends TestCase
{
    #[Test]
    public function it_creates_skip_context(): void
    {
        $context = FormRequestAnalysisContext::skip();

        $this->assertTrue($context->isSkip());
        $this->assertFalse($context->isAnonymous());
        $this->assertFalse($context->isReady());
        $this->assertEquals(FormRequestAnalysisContextType::Skip, $context->type);
        $this->assertNull($context->reflection);
        $this->assertNull($context->ast);
        $this->assertNull($context->classNode);
        $this->assertEquals([], $context->useStatements);
        $this->assertNull($context->namespace);
    }

    #[Test]
    public function it_creates_anonymous_context(): void
    {
        $reflection = new ReflectionClass(stdClass::class);

        $context = FormRequestAnalysisContext::anonymous($reflection);

        $this->assertFalse($context->isSkip());
        $this->assertTrue($context->isAnonymous());
        $this->assertFalse($context->isReady());
        $this->assertEquals(FormRequestAnalysisContextType::Anonymous, $context->type);
        $this->assertSame($reflection, $context->reflection);
        $this->assertNull($context->ast);
        $this->assertNull($context->classNode);
        $this->assertEquals([], $context->useStatements);
        $this->assertNull($context->namespace);
    }

    #[Test]
    public function it_creates_ready_context(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $ast = [new Return_];
        $classNode = new Class_('TestClass');
        $useStatements = ['Request' => 'Illuminate\Http\Request'];
        $namespace = 'App\Http\Requests';

        $context = FormRequestAnalysisContext::ready(
            $reflection,
            $ast,
            $classNode,
            $useStatements,
            $namespace
        );

        $this->assertFalse($context->isSkip());
        $this->assertFalse($context->isAnonymous());
        $this->assertTrue($context->isReady());
        $this->assertEquals(FormRequestAnalysisContextType::Ready, $context->type);
        $this->assertSame($reflection, $context->reflection);
        $this->assertSame($ast, $context->ast);
        $this->assertSame($classNode, $context->classNode);
        $this->assertEquals($useStatements, $context->useStatements);
        $this->assertEquals($namespace, $context->namespace);
    }

    #[Test]
    public function it_returns_type_as_string(): void
    {
        $this->assertEquals('skip', FormRequestAnalysisContext::skip()->getTypeAsString());
        $this->assertEquals('anonymous', FormRequestAnalysisContext::anonymous(new ReflectionClass(stdClass::class))->getTypeAsString());

        $context = FormRequestAnalysisContext::ready(
            new ReflectionClass(stdClass::class),
            [],
            new Class_('Test'),
            [],
            'App'
        );
        $this->assertEquals('ready', $context->getTypeAsString());
    }

    #[Test]
    public function it_checks_reflection_availability(): void
    {
        $this->assertFalse(FormRequestAnalysisContext::skip()->hasReflection());
        $this->assertTrue(FormRequestAnalysisContext::anonymous(new ReflectionClass(stdClass::class))->hasReflection());

        $context = FormRequestAnalysisContext::ready(
            new ReflectionClass(stdClass::class),
            [],
            new Class_('Test'),
            [],
            'App'
        );
        $this->assertTrue($context->hasReflection());
    }

    #[Test]
    public function it_checks_ast_availability(): void
    {
        $this->assertFalse(FormRequestAnalysisContext::skip()->hasAst());
        $this->assertFalse(FormRequestAnalysisContext::anonymous(new ReflectionClass(stdClass::class))->hasAst());

        $context = FormRequestAnalysisContext::ready(
            new ReflectionClass(stdClass::class),
            [new Return_],
            new Class_('Test'),
            [],
            'App'
        );
        $this->assertTrue($context->hasAst());
    }

    #[Test]
    public function it_converts_skip_context_to_array(): void
    {
        $context = FormRequestAnalysisContext::skip();
        $array = $context->toArray();

        $this->assertEquals(['type' => 'skip'], $array);
    }

    #[Test]
    public function it_converts_anonymous_context_to_array(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $context = FormRequestAnalysisContext::anonymous($reflection);
        $array = $context->toArray();

        $this->assertEquals([
            'type' => 'anonymous',
            'reflection' => $reflection,
        ], $array);
    }

    #[Test]
    public function it_converts_ready_context_to_array(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $ast = [new Return_];
        $classNode = new Class_('TestClass');
        $useStatements = ['Request' => 'Illuminate\Http\Request'];
        $namespace = 'App\Http\Requests';

        $context = FormRequestAnalysisContext::ready(
            $reflection,
            $ast,
            $classNode,
            $useStatements,
            $namespace
        );
        $array = $context->toArray();

        $this->assertEquals([
            'type' => 'ready',
            'reflection' => $reflection,
            'ast' => $ast,
            'classNode' => $classNode,
            'useStatements' => $useStatements,
            'namespace' => $namespace,
        ], $array);
    }

    #[Test]
    public function it_creates_skip_context_from_array(): void
    {
        $context = FormRequestAnalysisContext::fromArray(['type' => 'skip']);

        $this->assertTrue($context->isSkip());
        $this->assertFalse($context->isAnonymous());
        $this->assertFalse($context->isReady());
    }

    #[Test]
    public function it_creates_anonymous_context_from_array(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $context = FormRequestAnalysisContext::fromArray([
            'type' => 'anonymous',
            'reflection' => $reflection,
        ]);

        $this->assertTrue($context->isAnonymous());
        $this->assertSame($reflection, $context->reflection);
    }

    #[Test]
    public function it_creates_ready_context_from_array(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $ast = [new Return_];
        $classNode = new Class_('TestClass');
        $useStatements = ['Request' => 'Illuminate\Http\Request'];
        $namespace = 'App\Http\Requests';

        $context = FormRequestAnalysisContext::fromArray([
            'type' => 'ready',
            'reflection' => $reflection,
            'ast' => $ast,
            'classNode' => $classNode,
            'useStatements' => $useStatements,
            'namespace' => $namespace,
        ]);

        $this->assertTrue($context->isReady());
        $this->assertSame($reflection, $context->reflection);
        $this->assertSame($ast, $context->ast);
        $this->assertSame($classNode, $context->classNode);
        $this->assertEquals($useStatements, $context->useStatements);
        $this->assertEquals($namespace, $context->namespace);
    }

    #[Test]
    public function it_handles_missing_type_in_from_array(): void
    {
        $context = FormRequestAnalysisContext::fromArray([]);

        $this->assertTrue($context->isSkip());
    }

    #[Test]
    public function it_performs_roundtrip_conversion_for_skip(): void
    {
        $original = FormRequestAnalysisContext::skip();
        $array = $original->toArray();
        $restored = FormRequestAnalysisContext::fromArray($array);

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->isSkip(), $restored->isSkip());
    }

    #[Test]
    public function it_performs_roundtrip_conversion_for_anonymous(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $original = FormRequestAnalysisContext::anonymous($reflection);
        $array = $original->toArray();
        $restored = FormRequestAnalysisContext::fromArray($array);

        $this->assertEquals($original->type, $restored->type);
        $this->assertSame($original->reflection, $restored->reflection);
    }

    #[Test]
    public function it_performs_roundtrip_conversion_for_ready(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $ast = [new Return_];
        $classNode = new Class_('TestClass');
        $useStatements = ['Request' => 'Illuminate\Http\Request'];
        $namespace = 'App\Http\Requests';

        $original = FormRequestAnalysisContext::ready(
            $reflection,
            $ast,
            $classNode,
            $useStatements,
            $namespace
        );
        $array = $original->toArray();
        $restored = FormRequestAnalysisContext::fromArray($array);

        $this->assertEquals($original->type, $restored->type);
        $this->assertSame($original->reflection, $restored->reflection);
        $this->assertSame($original->ast, $restored->ast);
        $this->assertSame($original->classNode, $restored->classNode);
        $this->assertEquals($original->useStatements, $restored->useStatements);
        $this->assertEquals($original->namespace, $restored->namespace);
    }

    #[Test]
    public function it_uses_correct_enum_values(): void
    {
        $this->assertEquals('skip', FormRequestAnalysisContextType::Skip->value);
        $this->assertEquals('anonymous', FormRequestAnalysisContextType::Anonymous->value);
        $this->assertEquals('ready', FormRequestAnalysisContextType::Ready->value);
    }

    #[Test]
    public function it_creates_enum_from_string(): void
    {
        $this->assertEquals(FormRequestAnalysisContextType::Skip, FormRequestAnalysisContextType::from('skip'));
        $this->assertEquals(FormRequestAnalysisContextType::Anonymous, FormRequestAnalysisContextType::from('anonymous'));
        $this->assertEquals(FormRequestAnalysisContextType::Ready, FormRequestAnalysisContextType::from('ready'));
    }
}
