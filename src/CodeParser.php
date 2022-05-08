<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer;

use Exception;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;

class CodeParser
{
    private Parser $parser;

    public function __construct()
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

//        $dumper = new NodeDumper();
//        dd($dumper->dump($syntaxTree));
        $traverser = new NodeTraverser();
        $nodes = $traverser->traverse($syntaxTree);

        $imports = $this->getImports($nodes);
//        $properties = $this->getClassProperties($nodes);
//        dd();

//        $nodeFinder = new NodeFinder();
//        $methodCalls = $nodeFinder->find($nodes, function (Node $node) {
//            return $node instanceof MethodCall
//                && collect(['dispatch', 'dispatchNow'])->contains($node->name->toString());
//        });
        $methodCalls = $this->getMethodCalls(
            $nodes,
            ['jobDispatcher'],
            [
                'dispatch',
                'dispatchNow',
                'dispatchSync',
            ],
        );

//        $staticCalls = $nodeFinder->find($nodes, function (Node $node) {
//            return $node instanceof StaticCall
//                && collect(['dispatch', 'dispatchNow'])->contains($node->name->toString());
//        });

//        $calls = collect(array_merge($methodCalls, $staticCalls));

        $jobDispatcherCalls = $methodCalls->map(function (MethodCall $call) use ($imports) {
            $className = $call->args[0]->value->class?->parts[0];

            return $this->buildFullClassName($className, $imports);
        });

        return $jobDispatcherCalls;
    }

    public function getDispatchedEventsFromClass(string $className): Collection
    {
        try {
            $code = $this->getCodeFromClass($className);
        } catch (Exception $e) {
            return collect([]);
        }
        $syntaxTree = $this->parser->parse($code);

        $traverser = new NodeTraverser();
        $nodes = $traverser->traverse($syntaxTree);

        $imports = $this->getImports($nodes);
        $methodCalls = $this->getMethodCalls(
            $nodes,
            ['eventDispatcher'],
            [
                'dispatch',
                'dispatchNow',
                'dispatchSync',
            ],
        );

        $eventDispatcherCalls = $methodCalls->map(function (MethodCall $call) use ($imports) {
            $className = $call->args[0]->value->class?->parts[0];

            return $this->buildFullClassName($className, $imports);
        });

        return $eventDispatcherCalls;
    }

    private function getImports(array $nodes): Collection
    {
        $nodeFinder = new NodeFinder();

        $uses = $nodeFinder->find($nodes, function (Node $node) {
            return $node instanceof Node\Stmt\Use_;
        });

        $imports = collect($uses)->map(function (Node\Stmt\Use_ $use) {
            return collect($use->uses)->map(function (Node\Stmt\UseUse $useUse) {
                return $useUse->name->toString();
            })->flatten();
        });

        return $imports->flatten();
    }

    private function getClassProperties(array $nodes): Collection
    {
        $nodeFinder = new NodeFinder();

        $uses = $nodeFinder->find($nodes, function (Node $node) {
            dump($node);
            return $node instanceof Node\Stmt\Property;
        });

        $properties = collect($uses)->map(function (Node\Stmt\Use_ $use) {
            return collect($use->uses)->map(function (Node\Stmt\UseUse $useUse) {
                return $useUse->name->toString();
            })->flatten();
        });

        return $properties->flatten();
    }

    private function getMethodCalls(
        array $nodes,
        array $varNames,
        array $callNames,
    ): Collection {
        $nodeFinder = new NodeFinder();

        $methodCalls = $nodeFinder->find($nodes, function (Node $node) use ($varNames, $callNames) {
            return $node instanceof MethodCall
                && collect($varNames)->contains(($node->var->name))
                && collect($callNames)->contains($node->name->toString())
                && !$node->args[0]->value instanceof Node\Expr\Variable; // When calling jobDispatcher->dispatch($job)
        });

        return collect($methodCalls);
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
