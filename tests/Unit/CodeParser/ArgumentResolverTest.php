<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class ArgumentResolverTest extends TestCase
{
    private readonly NodeTraverser $nodeTraverser;
    private readonly NodeFinder $nodeFinder;
    private readonly Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nodeTraverser = new NodeTraverser();
        $this->nodeFinder = new NodeFinder();
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    /** @test */
    public function it_can_resolve_the_class_of_an_inline_argument_with_the_new_keyword(): void
    {
        $code = <<<'CODE'
            <?php

            class ClassName
            {
                public function someMethod()
                {
                    Event::dispatch(new \App\Events\SomeEvent());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Arg $argument */
        $argument = $this->nodeFinder->findFirstInstanceOf($nodes, Arg::class);
        $resolvedClasses = $codeParser->resolveClassesFromArgument($argument);

        $this->assertCount(1, $resolvedClasses);
        $this->assertEquals('App\Events\SomeEvent', $resolvedClasses[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_an_inline_argument_with_the_new_keyword_and_imported_class(): void
    {
        $code = <<<'CODE'
            <?php

            use App\Events\SomeEvent;

            class ClassName
            {
                public function someMethod()
                {
                    Event::dispatch(new SomeEvent());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Arg $argument */
        $argument = $this->nodeFinder->findFirstInstanceOf($nodes, Arg::class);
        $resolvedClasses = $codeParser->resolveClassesFromArgument($argument);

        $this->assertCount(1, $resolvedClasses);
        $this->assertEquals('App\Events\SomeEvent', $resolvedClasses[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_an_inline_argument_with_the_new_keyword_and_imported_class_with_alias(): void
    {
        $code = <<<'CODE'
            <?php

            use App\Events\SomeEvent as SomeEventAlias;

            class ClassName
            {
                public function someMethod()
                {
                    Event::dispatch(new SomeEventAlias());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Arg $argument */
        $argument = $this->nodeFinder->findFirstInstanceOf($nodes, Arg::class);
        $resolvedClasses = $codeParser->resolveClassesFromArgument($argument);

        $this->assertCount(1, $resolvedClasses);
        $this->assertEquals('App\Events\SomeEvent', $resolvedClasses[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_an_argument_from_a_variable(): void
    {
        $code = <<<'CODE'
            <?php

            class ClassName
            {
                public function someMethod()
                {
                    $someEvent = new \App\Events\SomeEvent();
                    Event::dispatch($someEvent);
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Arg $argument */
        $argument = $this->nodeFinder->findFirstInstanceOf($nodes, Arg::class);
        $resolvedClasses = $codeParser->resolveClassesFromArgument($argument);

        $this->assertCount(1, $resolvedClasses);
        $this->assertEquals('App\Events\SomeEvent', $resolvedClasses[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_an_argument_from_a_variable_and_imported_class(): void
    {
        $code = <<<'CODE'
            <?php

            use \App\Events\SomeEvent;

            class ClassName
            {
                public function someMethod()
                {
                    $someEvent = new SomeEvent();
                    Event::dispatch($someEvent);
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Arg $argument */
        $argument = $this->nodeFinder->findFirstInstanceOf($nodes, Arg::class);
        $resolvedClasses = $codeParser->resolveClassesFromArgument($argument);

        $this->assertCount(1, $resolvedClasses);
        $this->assertEquals('App\Events\SomeEvent', $resolvedClasses[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_an_argument_from_a_variable_and_imported_class_with_alias(): void
    {
        $code = <<<'CODE'
            <?php

            use \App\Events\SomeEvent as SomeEventAlias;

            class ClassName
            {
                public function someMethod()
                {
                    $someEvent = new SomeEventAlias();
                    Event::dispatch($someEvent);
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Arg $argument */
        $argument = $this->nodeFinder->findFirstInstanceOf($nodes, Arg::class);
        $resolvedClasses = $codeParser->resolveClassesFromArgument($argument);

        $this->assertCount(1, $resolvedClasses);
        $this->assertEquals('App\Events\SomeEvent', $resolvedClasses[0]);
    }

    /** @test */
    public function it_can_resolve_the_classes_of_an_array_of_jobs(): void
    {
        $code = <<<'CODE'
            <?php

            use Some\Namespace\SomeClass;
            use Some\Namespace\SomeOtherClass;
            use \Bus;

            class ClassName
            {
                public function someMethod()
                {
                    Bus::dispatchChain([new Some\Namespace\SomeClass(), new Some\Namespace\SomeOtherClass()]);
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Arg $argument */
        $argument = $this->nodeFinder->findFirstInstanceOf($nodes, StaticCall::class)->args[0];

        $resolvedClasses = $codeParser->resolveClassesFromArgument($argument);

        $this->assertCount(2, $resolvedClasses);
        $this->assertEquals(['Some\Namespace\SomeClass', 'Some\Namespace\SomeOtherClass'], $resolvedClasses);
    }

}
