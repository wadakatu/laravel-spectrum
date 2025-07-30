<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\AST\Visitors;

use LaravelSpectrum\Analyzers\AST\Visitors\AnonymousClassFindingVisitor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;

class AnonymousClassFindingVisitorTest extends TestCase
{
    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    #[Test]
    public function it_finds_simple_anonymous_class()
    {
        $code = <<<'PHP'
        <?php
        $object = new class {
            public function method() {
                return 'test';
            }
        };
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Class_::class, $classNode);
        $this->assertNull($classNode->name);
        $this->assertCount(1, $classNode->stmts);
    }

    #[Test]
    public function it_finds_anonymous_class_with_constructor()
    {
        $code = <<<'PHP'
        <?php
        $logger = new class($config) {
            private $config;
            
            public function __construct($config) {
                $this->config = $config;
            }
            
            public function log($message) {
                echo $message;
            }
        };
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        $this->assertCount(3, $classNode->stmts); // property, constructor, log method

        // Check for constructor
        $hasConstructor = false;
        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod && $stmt->name->toString() === '__construct') {
                $hasConstructor = true;
                break;
            }
        }
        $this->assertTrue($hasConstructor);
    }

    #[Test]
    public function it_finds_anonymous_class_extending_parent()
    {
        $code = <<<'PHP'
        <?php
        abstract class BaseClass {
            abstract public function process();
        }
        
        $processor = new class extends BaseClass {
            public function process() {
                return 'processed';
            }
        };
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        $this->assertNotNull($classNode->extends);
        $this->assertEquals('BaseClass', $classNode->extends->toString());
    }

    #[Test]
    public function it_finds_anonymous_class_implementing_interface()
    {
        $code = <<<'PHP'
        <?php
        interface Processor {
            public function process($data);
        }
        
        $handler = new class implements Processor {
            public function process($data) {
                return strtoupper($data);
            }
        };
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        $this->assertCount(1, $classNode->implements);
        $this->assertEquals('Processor', $classNode->implements[0]->toString());
    }

    #[Test]
    public function it_finds_first_anonymous_class_when_multiple_exist()
    {
        $code = <<<'PHP'
        <?php
        $first = new class {
            public function first() {
                return 'first';
            }
        };
        
        $second = new class {
            public function second() {
                return 'second';
            }
        };
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        // Should find the first anonymous class
        $this->assertCount(1, $classNode->stmts);
        $method = $classNode->stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $method);
        $this->assertEquals('first', $method->name->toString());
    }

    #[Test]
    public function it_returns_null_when_no_anonymous_class_found()
    {
        $code = <<<'PHP'
        <?php
        class RegularClass {
            public function method() {
                return 'test';
            }
        }
        
        $object = new RegularClass();
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNull($classNode);
    }

    #[Test]
    public function it_finds_anonymous_class_in_return_statement()
    {
        $code = <<<'PHP'
        <?php
        function createHandler() {
            return new class {
                public function handle($request) {
                    return 'handled';
                }
            };
        }
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        $this->assertCount(1, $classNode->stmts);
    }

    #[Test]
    public function it_finds_anonymous_class_with_traits()
    {
        $code = <<<'PHP'
        <?php
        trait LoggableTrait {
            public function log($message) {
                echo $message;
            }
        }
        
        $logger = new class {
            use LoggableTrait;
            
            public function info($message) {
                $this->log('[INFO] ' . $message);
            }
        };
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        $this->assertCount(2, $classNode->stmts); // use trait statement and info method

        // Check for trait usage
        $hasTraitUse = false;
        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\TraitUse) {
                $hasTraitUse = true;
                $this->assertEquals('LoggableTrait', $stmt->traits[0]->toString());
                break;
            }
        }
        $this->assertTrue($hasTraitUse);
    }

    #[Test]
    public function it_finds_anonymous_class_with_properties()
    {
        $code = <<<'PHP'
        <?php
        $storage = new class {
            private array $data = [];
            public string $name = 'Storage';
            protected int $count = 0;
            
            public function add($item) {
                $this->data[] = $item;
                $this->count++;
            }
        };
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);

        // Count properties
        $propertyCount = 0;
        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\Property) {
                $propertyCount++;
            }
        }
        $this->assertEquals(3, $propertyCount);
    }

    #[Test]
    public function it_finds_anonymous_class_in_array()
    {
        $code = <<<'PHP'
        <?php
        $handlers = [
            'default' => new class {
                public function handle() {
                    return 'default handler';
                }
            },
            'special' => new SpecialHandler(),
        ];
        PHP;

        $visitor = new AnonymousClassFindingVisitor;
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $classNode = $visitor->getClassNode();

        $this->assertNotNull($classNode);
        $this->assertCount(1, $classNode->stmts);
    }
}
