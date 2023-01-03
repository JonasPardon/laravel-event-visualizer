<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JonasPardon\LaravelEventVisualizer\Models\Event;
use JonasPardon\LaravelEventVisualizer\Models\Job;
use JonasPardon\LaravelEventVisualizer\Models\Listener;
use JonasPardon\LaravelEventVisualizer\Models\VisualizerNode;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;
use Throwable;

class CodeParserLegacy
{
    private Parser $parser;

    public function __construct(
        private NodeTraverser $traverser,
        private NodeFinder $nodeFinder,
    ) {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function getDispatchedJobsFromVisualizerNode(VisualizerNode $visualizerNode): Collection
    {
        try {
            $code = $this->getCodeFromClass($visualizerNode->getClassName());
        } catch (Exception $e) {
            return collect([]);
        }
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->traverser->traverse($syntaxTree);

        // if (Str::contains($visualizerNode->getClassName(), 'ProcessPdf')) {
        //     dd($this->getStaticCalls($nodes, ['Bus'], ['chain']));
        //     dd('test', $visualizerNode->getClassName());
        // }

        try {
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
            $chainCalls = $this->getStaticCalls(
                $nodes,
                ['Bus'],
                [
                    'chain',
                ],
            );
        } catch (Throwable $e) {
            return collect([]);
        }

        return $this->getVisualizerNodesOfTypeFromCalls(
            visualizerNodeType: VisualizerNode::JOB,
            traverserNodes: $nodes,
            methodCalls: $methodCalls,
            staticCalls: $staticCalls,
        );
    }

    public function getDispatchedEventsFromVisualizerNode(VisualizerNode $visualizerNode): Collection
    {
        try {
            $code = $this->getCodeFromClass($visualizerNode->getClassName());
        } catch (Exception $e) {
            return collect([]);
        }
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->traverser->traverse($syntaxTree);

        try {
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
        } catch (Throwable $e) {
            return collect([]);
        }

        return $this->getVisualizerNodesOfTypeFromCalls(
            visualizerNodeType: VisualizerNode::EVENT,
            traverserNodes: $nodes,
            methodCalls: $methodCalls,
            staticCalls: $staticCalls,
        );
    }

    private function getVisualizerNodesOfTypeFromCalls(
        string $visualizerNodeType,
        array $traverserNodes,
        Collection $methodCalls,
        Collection $staticCalls,
    ): Collection {
        $imports = $this->getImports($traverserNodes);

        $methodCalls = $methodCalls->map(function (MethodCall $call) use ($traverserNodes, $imports, $visualizerNodeType) {
            if ($call->args[0]->value instanceof Node\Expr\Variable) {
                $className = $this->getVariableAssignment($traverserNodes, $call->args[0]->value);
            } else {
                $className = $call->args[0]->value->class?->parts[0];
            }

            return $this->getVisualizerNodeFromClassNameAndImports(
                className: $className,
                imports: $imports,
                nodeType: $visualizerNodeType,
            );
        });

        $staticCalls = $staticCalls->map(function (StaticCall $call) use ($traverserNodes, $imports, $visualizerNodeType) {
            if ($call->args[0]->value instanceof Node\Expr\Variable) {
                $className = $this->getVariableAssignment($traverserNodes, $call->args[0]->value);
            } else {
                $className = $call->args[0]->value->class?->parts[0];
            }

            return $this->getVisualizerNodeFromClassNameAndImports(
                className: $className,
                imports: $imports,
                nodeType: $visualizerNodeType,
            );
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

        if (!$hits) {
            return null;
        }

        try {
            return $hits[0]->expr->class->parts[0];
        } catch (Throwable $e) {
            // dd($hits[0]->exp
            // );
        }

        return $hits[0]->expr->class->parts[0];
    }

    private function getVisualizerNodeFromClassNameAndImports(
        string $className,
        Collection $imports,
        string $nodeType,
    ): VisualizerNode {
        // todo: make sure we can also get the FQN if it's not in the imports
        // We initialize it to the classname as we might not get a hit on the imports
        $FQN = $imports->filter(function (string $import) use ($className) {
            $parts = explode('\\', $import);

            if (in_array($className, $parts)) {
                return true;
            }

            return false;
        })->first() ?? $className;

        return match ($nodeType) {
            VisualizerNode::JOB => new Job($FQN),
            VisualizerNode::EVENT => new Event($FQN),
            VisualizerNode::LISTENER => new Listener($FQN),
            default => throw new Exception("$nodeType is not a valid VisualizerNode type"),
        };
    }

    private function getCodeFromClass(string $className): string
    {
        $reflection = new ReflectionClass($className);
        $source = file($reflection->getFileName());

        return implode('', $source);
    }
}
