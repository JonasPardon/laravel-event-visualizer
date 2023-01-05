<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
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
    private readonly CodeParser $codeParser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nodeTraverser = new NodeTraverser();
        $this->nodeFinder = new NodeFinder();
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->codeParser = new CodeParser();
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

        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $node = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Assign;
        });

        $variable = $node->var;

        $resolvedClass = $this->codeParser->resolveClassFromVariable(
            variable: $variable,
            nodes: $nodes,
        );

        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass);
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

        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $node = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Assign;
        });

        $variable = $node->var;

        $resolvedClass = $this->codeParser->resolveClassFromVariable(
            variable: $variable,
            nodes: $nodes,
        );

        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass);
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

        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $node = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Assign;
        });

        $variable = $node->var;

        $resolvedClass = $this->codeParser->resolveClassFromVariable(
            variable: $variable,
            nodes: $nodes,
        );

        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass);
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

        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $this->codeParser->resolveClassFromVariable(
            variable: $variable,
            nodes: $nodes,
        );

        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass);
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

        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $this->codeParser->resolveClassFromVariable(
            variable: $variable,
            nodes: $nodes,
        );

        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass);
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

        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $this->codeParser->resolveClassFromVariable(
            variable: $variable,
            nodes: $nodes,
        );

        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass);
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

        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Variable $variable */
        $variable = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Variable && $node->name === 'someVariable';
        });

        $resolvedClass = $this->codeParser->resolveClassFromVariable(
            variable: $variable,
            nodes: $nodes,
        );

        $this->assertEquals('Some\Namespace\SomeClass', $resolvedClass);
    }
}
