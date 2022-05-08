<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;

class CodeParser
{
    private Parser $parser;

    public function __construct(
        private NodeTraverser $traverser,
        private NodeFinder $nodeFinder,
    )
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function getDispatchedJobsFromClass(string $className): Collection
    {
        try {
            $code = $this->getCodeFromClass($className);
        } catch (Exception $e) {
            return collect([]);
        }
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->traverser->traverse($syntaxTree);

        $imports = $this->getImports($nodes);
        $methodCalls = $this->getMethodCalls(
            $nodes,
            ['jobDispatcher'],
            [
                'dispatch',
                'dispatchNow',
                'dispatchSync',
            ],
        );
        $staticCalls = $this->getStaticCalls(
            $nodes,
            ['Bus'],
            [
                'dispatch',
                'dispatchNow',
                'dispatchSync',
                'dispatchAfterResponse',
                'dispatchToQueue',
            ],
        );

        $methodCalls = $methodCalls->map(function (MethodCall $call) use ($imports, $nodes) {
            if ($call->args[0]->value instanceof Node\Expr\Variable) {
                $className = $this->getVariableAssignment($nodes, $call->args[0]->value);
            } else {
                $className = $call->args[0]->value->class?->parts[0];
            }

            return $this->buildFullClassName($className, $imports);
        });
        $staticCalls = $staticCalls->map(function (StaticCall $call) use ($imports, $nodes) {
            if ($call->args[0]->value instanceof Node\Expr\Variable) {
                $className = $this->getVariableAssignment($nodes, $call->args[0]->value);
            } else {
                $className = $call->args[0]->value->class?->parts[0];
            }

            return $this->buildFullClassName($className, $imports);
        });

        return $methodCalls->merge($staticCalls);
    }

    public function getDispatchedEventsFromClass(string $className): Collection
    {
        try {
            $code = $this->getCodeFromClass($className);
        } catch (Exception $e) {
            return collect([]);
        }
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->traverser->traverse($syntaxTree);

        $imports = $this->getImports($nodes);
        $methodCalls = $this->getMethodCalls(
            $nodes,
            ['eventDispatcher'],
            ['dispatch'],
        );
        $staticCalls = $this->getStaticCalls(
            $nodes,
            ['Event'],
            ['dispatch'],
        );

        $methodCalls = $methodCalls->map(function (MethodCall $call) use ($nodes, $imports) {
            if ($call->args[0]->value instanceof Node\Expr\Variable) {
                $className = $this->getVariableAssignment($nodes, $call->args[0]->value);
            } else {
                $className = $call->args[0]->value->class?->parts[0];
            }

            return $this->buildFullClassName($className, $imports);
        });
        $staticCalls = $staticCalls->map(function (StaticCall $call) use ($nodes, $imports) {
            if ($call->args[0]->value instanceof Node\Expr\Variable) {
                $className = $this->getVariableAssignment($nodes, $call->args[0]->value);
            } else {
                $className = $call->args[0]->value->class?->parts[0];
            }

            return $this->buildFullClassName($className, $imports);
        });

        return $methodCalls->merge($staticCalls);
    }

    private function getImports(array $nodes): Collection
    {
        $uses = $this->nodeFinder->find($nodes, function (Node $node) {
            return $node instanceof Node\Stmt\Use_;
        });

        $imports = collect($uses)->map(function (Node\Stmt\Use_ $use) {
            return collect($use->uses)->map(function (Node\Stmt\UseUse $useUse) {
                return $useUse->name->toString();
            })->flatten();
        });

        return $imports->flatten();
    }

    private function getMethodCalls(
        array $nodes,
        array $varNames,
        array $callNames,
    ): Collection {
        $methodCalls = $this->nodeFinder->find($nodes, function (Node $node) use ($nodes, $varNames, $callNames) {
            if (
                !$node instanceof MethodCall
                || collect($varNames)->doesntContain(($node->var->name))
                || collect($callNames)->doesntContain($node->name->toString())
            ) {
                return false;
            }

            // When using $jobDispatcher->dispatch($job) with a variable
            if (
                collect($node->getSubNodeNames())->contains('args')
                && $node->args
                && $node->args[0]->value instanceof Node\Expr\Variable
            ) {
                return $this->getVariableAssignment($nodes, $node->args[0]->value) !== null;
            }

            return true;
        });

        return collect($methodCalls);
    }

    private function getStaticCalls(
        array $nodes,
        array $classNames,
        array $callNames,
    ): Collection {
        $methodCalls = $this->nodeFinder->find($nodes, function (Node $node) use ($nodes, $classNames, $callNames) {
            if (
                !$node instanceof StaticCall
                || collect($classNames)->doesntContain(($node->class->toString()))
                || collect($callNames)->doesntContain($node->name->toString())
            ) {
                return false;
            }

            // When using Bus::dispatch($job) with a variable
            if (
                collect($node->getSubNodeNames())->contains('args')
                && $node->args
                && $node->args[0]->value instanceof Node\Expr\Variable
            ) {
                return $this->getVariableAssignment($nodes, $node->args[0]->value) !== null;
            }

            return true;
        });

        return collect($methodCalls);
    }

    private function getVariableAssignment(array $nodes, Node\Expr\Variable $variable): ?string
    {
        $hits = $this->nodeFinder->find($nodes, function (Node $node) use ($variable) {
            return $node instanceof Node\Expr\Assign && $node->var->name === $variable->name;
        });

        if (!$hits || count($hits) === 0) {
            return null;
        }

        return $hits[0]->expr->class->parts[0];
    }

    private function buildFullClassName(string $className, ?Collection $imports = null): string
    {
        if ($imports) {
            return $imports->filter(function (string $import) use ($className) {
                $parts = explode('\\', $import);

                if (in_array($className, $parts)) {
                    return true;
                }

                return false;
            })->first() ?? $className;
        }

        return $className;
    }

    private function getCodeFromClass(string $className): string
    {
        $reflection = new ReflectionClass($className);
        $source = file($reflection->getFileName());

        return implode('', $source);
    }
}
