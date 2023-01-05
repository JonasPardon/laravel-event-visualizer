<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use PhpParser\Node\Arg;
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
        $resolvedClass = $codeParser->resolveClassFromArgument($argument);

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
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
        $resolvedClass = $codeParser->resolveClassFromArgument($argument);

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
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
        $resolvedClass = $codeParser->resolveClassFromArgument($argument);

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
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
        $resolvedClass = $codeParser->resolveClassFromArgument($argument);

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
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
        $resolvedClass = $codeParser->resolveClassFromArgument($argument);

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
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
        $resolvedClass = $codeParser->resolveClassFromArgument($argument);

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
    }
}
