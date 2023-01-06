<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Services;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Exception;

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

            // 1. Check if call matches what we're looking for ('like 'dispatch')
            if ($node->name->toString() !== $methodName) {
                return false;
            }

            // 2. Check if variable it's called on is an instance of the subject class
            return $this->areClassesSame($node->class->toString(), $subjectClass);
        });

        return collect($calls)->map(function (StaticCall $node) use ($subjectClass) {
            return [
                'class' => $subjectClass,
                'method' => $node->name->toString(),
                'argumentClass' => $this->resolveClassFromArgument($node->args[0]),
            ];
        })->toArray();
    }

    public function getMethodCalls(
        string $subjectClass,
        string $methodName,
    ): array {
        $calls = $this->nodeFinder->find($this->nodes, function (Node $node) use ($subjectClass, $methodName) {
            if (!$node instanceof MethodCall) {
                return false;
            }

            // Check if call matches what we're looking for ('like 'dispatch')
            if ($node->name->toString() !== $methodName) {
                return false;
            }

            // This happens when you inject the dispatcher and do '$dispatcher->dispatch()'
            if ($node->var instanceof Variable) {
                $variableClass = $this->resolveClassFromVariable($node->var);

                return $this->areClassesSame($variableClass, $subjectClass);
            }

            // This happens when you inject the dispatcher and do '$this->dispatcher->dispatch()'
            if ($node->var instanceof PropertyFetch) {
                $propertyClass = $this->resolveClassFromProperty($node->var);

                return $this->areClassesSame($propertyClass, $subjectClass);
            }

            throw new Exception('Not implemented');
        });

        return collect($calls)->map(function (MethodCall $node) use ($subjectClass) {
            return [
                'class' => $subjectClass,
                'method' => $node->name->toString(),
                'argumentClass' => $this->resolveClassFromArgument($node->args[0]),
            ];
        })->toArray();
    }

    public function areClassesSame(string $class1, string $class2): bool
    {
        return $this->getFullyQualifiedClassName($class1) === $this->getFullyQualifiedClassName($class2);
    }

    public function resolveClassFromVariable(Variable $variable): ?string
    {
        /** @var Assign[] $assignmentNodes */
        $assignmentNodes = $this->nodeFinder->find($this->nodes, function (Node $node) use ($variable) {
            if (!$node instanceof Assign) {
                return false;
            }

            return $node->var instanceof Variable && $node->var->name === $variable->name;
        });

        foreach ($assignmentNodes as $assignmentNode) {
            if ($assignmentNode->expr instanceof New_) {
                return $this->getFullyQualifiedClassName($assignmentNode->expr->class->toString());
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

                return $this->getFullyQualifiedClassName($className);
            }

            // todo: handle other types of assignments
        }

        // If we've gotten here, we haven't found a class name.
        return null;
    }

    public function resolveClassFromArgument(Arg $argument): ?string
    {
        if ($argument->value instanceof Variable) {
            return $this->resolveClassFromVariable($argument->value);
        }

        if ($argument->value instanceof New_) {
            if ($argument->value->class instanceof FullyQualified) {
                return $argument->value->class->toString();
            }

            return $this->getFullyQualifiedClassName($argument->value->class->toString());
        }

        return null;
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

        return $className; // Not imported, this is the FQN
    }
}
