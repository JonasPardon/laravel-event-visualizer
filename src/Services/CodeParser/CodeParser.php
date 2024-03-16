<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Services\CodeParser;

use Exception;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser\ValueObjects\ResolvedCall;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Throwable;

class CodeParser
{
    private NodeTraverser $nodeTraverser;
    private NodeFinder $nodeFinder;
    private Parser $parser;

    private array $nodes;

    public function __construct(string $code)
    {
        $this->nodeTraverser = new NodeTraverser();
        $this->nodeFinder = new NodeFinder();
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $syntaxTree = $this->parser->parse($code);
        $this->nodes = $this->nodeTraverser->traverse($syntaxTree);
    }

    public function getStaticCalls(
        string $subjectClass,
        string $methodName,
    ): array {
        $calls = $this->nodeFinder->find($this->nodes, function (Node $node) use ($subjectClass, $methodName) {
            if (!$node instanceof StaticCall) {
                return false;
            }

            // Check if call matches what we're looking for ('like 'dispatch')
            if ($node->name->toString() !== $methodName) {
                return false;
            }

            // Check if variable it's called on is an instance of the subject class
            return $this->areClassesSame($node->class->toString(), $subjectClass);
        });

        $items = collect($calls)->map(function (StaticCall $node) use ($subjectClass) {
            return collect($this->resolveClassesFromArgument($node->args[0]))
                ->map(function (string $dispatchedClass) use ($subjectClass, $node) {
                    return new ResolvedCall(
                        dispatcherClass: $subjectClass,
                        dispatchedClass: $dispatchedClass,
                        method: $node->name->toString(),
                    );
                });
        })->flatten(1)->all();

        return $items;
    }

    public function getMethodCalls(
        string $subjectClass,
        string $methodName,
    ): array {
        $calls = $this->nodeFinder->find($this->nodes, function (Node $node) use ($subjectClass, $methodName) {
            if (!$node instanceof MethodCall) {
                return false;
            }

            // Check if call matches what we're looking for (like 'dispatch')
            if ($node->name->toString() !== $methodName) {
                return false;
            }

            // This happens when you inject the dispatcher and do '$dispatcher->dispatch()'
            if ($node->var instanceof Variable) {
                $variableClasses = $this->resolveClassesFromVariable($node->var);

                return count($variableClasses) === 1 && $this->areClassesSame($variableClasses[0], $subjectClass);
            }

            // This happens when you inject the dispatcher and do '$this->dispatcher->dispatch()'
            if ($node->var instanceof PropertyFetch) {
                $propertyClass = $this->resolveClassFromProperty($node->var);

                return $this->areClassesSame($propertyClass, $subjectClass);
            }

            if ($node->var instanceof StaticCall) {
                throw new Exception('Static calls within method calls are not supported yet. If this is a Bus::chain, support is coming.');
            }

            // throw new Exception('Not supported yet. Please open an issue here: https://github.com/JonasPardon/laravel-event-visualizer/issues/new');

            return false;
        });

        return collect($calls)->map(function (MethodCall $node) use ($subjectClass) {
            return collect($this->resolveClassesFromArgument($node->args[0]))
                ->map(function (string $dispatchedClass) use ($subjectClass, $node) {
                    return new ResolvedCall(
                        dispatcherClass: $subjectClass,
                        dispatchedClass: $dispatchedClass,
                        method: $node->name->toString(),
                    );
                });
        })->flatten(1)->all();
    }

    public function getFunctionCalls(string $functionName): array
    {
        $calls = $this->nodeFinder->find($this->nodes, function (Node $node) use ($functionName) {
            return $node instanceof Expression &&
                $node->expr instanceof FuncCall &&
                $node->expr->name instanceof \PhpParser\Node\Name &&
                $node->expr->name->toString() === $functionName;
        });

        return collect($calls)->map(function (Expression $node) use ($functionName) {
            /** @var FuncCall $functionCall */
            $functionCall = $node->expr;

            return collect($this->resolveClassesFromArgument($functionCall->args[0]))
                ->map(function (string $dispatchedClass) use ($functionName) {
                    return new ResolvedCall(
                        dispatcherClass: 'none',
                        dispatchedClass: $dispatchedClass,
                        method: $functionName,
                    );
                });
        })->flatten(1)->all();
    }

    public function areClassesSame(string $class1, string $class2): bool
    {
        return $this->getFullyQualifiedClassName($class1) === $this->getFullyQualifiedClassName($class2);
    }

    public function resolveClassesFromVariable(Variable $variable): array
    {
        /** @var Assign[] $assignmentNodes */
        $assignmentNodes = $this->nodeFinder->find($this->nodes, function (Node $node) use ($variable) {
            if (!$node instanceof Assign) {
                return false;
            }

            return $node->var instanceof Variable && $node->var->name === $variable->name;
        });

        $classes = [];

        foreach ($assignmentNodes as $assignmentNode) {
            if ($assignmentNode->expr instanceof New_) {
                $classes[] = $this->getFullyQualifiedClassName($assignmentNode->expr->class->toString());
            }

            if ($assignmentNode->expr instanceof Array_) {
                array_push(
                    $classes,
                    ...collect($assignmentNode->expr->items)
                        ->map(function (ArrayItem $item) {
                            return $this->getFullyQualifiedClassName($item->value->class->toString());
                        })
                        ->all()
                );
            }

            // todo: handle other types of assignments
        }

        /** @var ClassMethod[] $injectionNodes */
        $injectionNodes = $this->nodeFinder->find($this->nodes, function (Node $node) use ($variable) {
            if (!$node instanceof ClassMethod) {
                return false;
            }

            foreach ($node->params as $param) {
                if ($param->var instanceof Variable && $param->var->name === $variable->name) {
                    return true;
                }
            }

            return false;
        });

        foreach ($injectionNodes as $injectionNode) {
            foreach ($injectionNode->params as $param) {
                $className = implode('\\', $param->type->parts);

                $classes[] = $this->getFullyQualifiedClassName($className);
            }

            // todo: handle other types of assignments
        }

        return $classes;
    }

    public function resolveClassesFromArgument(Arg $argument): array
    {
        if ($argument->value instanceof Variable) {
            return $this->resolveClassesFromVariable($argument->value);
        }

        if ($argument->value instanceof New_) {
            if ($argument->value->class instanceof FullyQualified) {
                return [$argument->value->class->toString()];
            }

            return [$this->getFullyQualifiedClassName($argument->value->class->toString())];
        }

        if ($argument->value instanceof Array_) {
            return collect($argument->value->items)
                ->map(function (ArrayItem $item) {
                    return $this->getFullyQualifiedClassName($item->value->class->toString());
                })
                ->all();
        }

        return [];
    }

    public function resolveClassFromProperty(PropertyFetch $property): ?string
    {
        // Constructor injection
        /** @var ClassMethod $constructorNode */
        $constructorNode = $this->nodeFinder->findFirst($this->nodes, function (Node $node) {
            return $node instanceof ClassMethod && $node->name->toString() === '__construct';
        });

        foreach ($constructorNode->params as $param) {
            if ($param->var instanceof Variable && $param->var->name === $property->name->toString()) {
                return $this->getFullyQualifiedClassName($param->type->toString());
            }
        }

        // todo: other types of injection

        return null;
    }

    public function getFullyQualifiedClassName(string $className): string
    {
        /** @var Use_[] $importNodes */
        $importNodes = $this->nodeFinder->find($this->nodes, function (Node $node) {
            return $node instanceof Use_ && $node->type === Use_::TYPE_NORMAL; // We're only looking for class imports
        });

        foreach ($importNodes as $importNode) {
            foreach ($importNode->uses as $use) {
                if ($use->alias !== null) {
                    if ($use->alias->toString() === $className) {
                        return $use->name->toString();
                    }
                } else {
                    if ($use->name->getLast() === $className) {
                        return $use->name->toString();
                    }
                }
            }
        }

        /** @var Namespace_|null $namespaceNode */
        $namespaceNode = $this->nodeFinder->findFirstInstanceOf($this->nodes, Namespace_::class);

        if ($namespaceNode !== null) {
            // Classes are not imported if they share the same namespace as the current class
            return $namespaceNode->name->toString() . '\\' . $className;
        }

        return $className; // Not imported, this is the FQN
    }
}
