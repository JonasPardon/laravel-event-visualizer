<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class VariableResolverTest extends TestCase
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
    public function it_can_resolve_the_class_of_a_variable_with_the_new_keyword(): void
    {
        $code = <<<'CODE'
            <?php

            use Some\Namespace\SomeClass;

            class ClassName
            {
                public function someMethod()
                {
                    $someVariable = new Some\Namespace\SomeClass();
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $node = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Assign;
        });

        $variable = $node->var;

        $resolvedClass = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(1, $resolvedClass);
        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass[0]);
    }

    /** @test */
    public function it_can_resolve_the_classes_of_a_variable_with_an_array_of_jobs(): void
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
                    $chain = [new Some\Namespace\SomeClass(), new Some\Namespace\SomeOtherClass()];

                    Bus::dispatchChain($chain);
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $node = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Assign;
        });

        $variable = $node->var;

        $resolvedClasses = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(2, $resolvedClasses);
        $this->assertEquals(['Some\Namespace\SomeClass', 'Some\Namespace\SomeOtherClass'], $resolvedClasses);
    }

    /** @test */
    public function it_can_resolve_the_class_of_a_variable_with_the_new_keyword_and_imported_class(): void
    {
        $code = <<<'CODE'
            <?php

            use Some\Namespace\SomeClass;

            class ClassName
            {
                public function someMethod()
                {
                    $someVariable = new SomeClass();
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $node = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Assign;
        });

        $variable = $node->var;
        $resolvedClass = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(1, $resolvedClass);
        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_a_variable_with_the_new_keyword_and_imported_class_with_alias(): void
    {
        $code = <<<'CODE'
            <?php

            use Some\Namespace\SomeClass as AliasName;

            class ClassName
            {
                public function someMethod()
                {
                    $someVariable = new AliasName();
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $node = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Assign;
        });

        $variable = $node->var;

        $resolvedClass = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(1, $resolvedClass);
        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_a_variable_injected_in_the_constructor_with_shorthand_notation(): void
    {
        $code = <<<'CODE'
            <?php

            class ClassName
            {
                public function __construct(private \Some\Namespace\SomeClass $someVariable)
                {
                }

                public function someMethod()
                {
                    $this->someVariable->someMethod();
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(1, $resolvedClass);
        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_a_variable_injected_in_the_constructor_with_shorthand_notation_and_import(): void
    {
        $code = <<<'CODE'
            <?php

            use Some\Namespace\SomeClass;

            class ClassName
            {
                public function __construct(private SomeClass $someVariable)
                {
                }

                public function someMethod()
                {
                    $this->someVariable->someMethod();
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(1, $resolvedClass);
        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_a_variable_injected_in_a_normal_method(): void
    {
        $code = <<<'CODE'
            <?php

            class ClassName
            {
                public function someMethod(\Some\Namespace\SomeClass $someVariable)
                {
                    $someVariable->someMethod();
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(1, $resolvedClass);
        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass[0]);
    }

    /** @test */
    public function it_can_resolve_the_class_of_a_variable_injected_in_a_normal_method_with_import(): void
    {
        $code = <<<'CODE'
            <?php

            use Some\Namespace\SomeClass;

            class ClassName
            {
                public function someMethod(SomeClass $someVariable)
                {
                    $someVariable->someMethod();
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $codeParser->resolveClassesFromVariable($variable);

        $this->assertCount(1, $resolvedClass);
        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass[0]);
    }
}
