<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
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

        $this->assertEquals('SomeClass', $resolvedClass);
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
}
