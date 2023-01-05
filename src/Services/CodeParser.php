<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
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

    public function __construct()
    {
        $this->nodeTraverser = new NodeTraverser();
        $this->nodeFinder = new NodeFinder();
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function getStaticCalls(
        string $code,
        string $subjectClass,
        string $methodName,
    ): array {
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $calls = $this->nodeFinder->find($nodes, function (Node $node) use ($nodes, $subjectClass, $methodName) {
            if (!$node instanceof StaticCall) {
                return false;
            }

            // 1. Check if call matches what we're looking for ('like 'dispatch')
            if ($node->name->toString() !== $methodName) {
                return false;
            }

            // 2. Check if variable it's called on is an instance of the subject class
            return $this->isStaticInstanceOf($nodes, $node->class->toString(), $subjectClass);
        });

        return collect($calls)->map(function (StaticCall $node) use ($code, $subjectClass, $methodName) {
            return [
                // 'class' => $node->class->toString(),
                'class' => $subjectClass,
                'method' => $node->name->toString(),
                // 'arguments' => $node->args,
            ];
        })->toArray();
    }

    public function getMethodCalls(
        string $code,
        string $subjectClass,
        string $methodName,
    ): array {
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $calls = $this->nodeFinder->find($nodes, function (Node $node) use ($code, $subjectClass, $methodName) {
            if (!$node instanceof MethodCall) {
                return false;
            }

            // 1. Check if call matches what we're looking for ('like 'dispatch')
            if ($node->name->toString() !== $methodName) {
                return false;
            }

            if (!$node->var instanceof Variable) {
                throw new Exception('Not implemented');
            }

            // 2. Check if variable it's called on is an instance of the subject class
            return $this->isVariableInstanceOf($code, $node->var->name, $subjectClass);
        });

        return collect($calls)->map(function (StaticCall $node) use ($code, $subjectClass, $methodName) {
            return [
                // 'class' => $node->class->toString(),
                'class' => $subjectClass,
                'method' => $node->name->toString(),
                // 'arguments' => $node->args,
            ];
        })->toArray();
    }

    public function isStaticInstanceOf(array $nodes, string $classToCheck, string $classToCheckAgainst): bool
    {
        $classToCheck = implode('\\', explode('\\', $classToCheck));
        $classToCheckAgainst = implode('\\', explode('\\', $classToCheckAgainst));

        if ($classToCheck === $classToCheckAgainst) {
            return true;
        }

        $foundImport = $this->findImport($nodes, $classToCheck);

        if ($foundImport !== null) {
            return $foundImport['alias'] === $classToCheckAgainst || $foundImport['class'] === $classToCheckAgainst;
        }

        return false;
    }

    public function isVariableInstanceOf(array $nodes, string $variable, string $class): bool
    {
        $class = implode('\\', explode('\\', $class));

        // $syntaxTree = $this->parser->parse($code);
        // $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $calls = $this->nodeFinder->find($nodes, function (Node $node) use ($variable, $class, $nodes) {
            if (!$node instanceof Variable) {
                return false;
            }

            $variableName = $node->name;
            // $foundAssignment = $this->findVariableAssignmentFromNode($code, $variableName, $node);
            $foundAssignment = $this->resolveClassFromVariable($node, $nodes);
            dd($foundAssignment);

            $foundImport = $this->findImport($nodes, $class);

            // dd($node, $foundImport);

            return $foundImport !== null;
        });

        return false;
    }

    public function findImport(array $nodes, string $class): ?array
    {
        $class = implode('\\', explode('\\', $class));
        // $syntaxTree = $this->parser->parse($code);
        // $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $importNodes = $this->nodeFinder->find($nodes, function (Node $node) use ($class) {
            if (!$node instanceof Node\Stmt\Use_) {
                return false;
            }

            if ($node->type !== Node\Stmt\Use_::TYPE_NORMAL) {
                // We're only looking for class imports
                return false;
            }

            if (count($node->uses) > 1) {
                throw new Exception('Multiple imports in one line not supported for now');
            }

            foreach ($node->uses as $use) {
                if ($use->alias !== null) {
                    return $use->alias->toString() === $class;
                }
            }

            return true;
        });

        if (count($importNodes) === 0) {
            return null;
        }

        $importNode = $importNodes[0];

        if (!$importNode) {
            return null;
        }

        return [
            'class' => $importNode->uses[0]->name->toString(),
            'alias' => $importNode->uses[0]->alias?->name,
        ];
    }

    public function findVariableAssignmentFromNode(string $code, string $variable, Node $variableNode): ?array
    {
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $assignmentNodes = $this->nodeFinder->find($nodes, function (Node $node) use ($variable, $variableNode) {
            // dump($node::class, $node);
            dump($node === $variableNode);
            if (!$node instanceof Assign) {
                return false;
            }

            dd($node);

            if ($node->var->getType() !== 'Expr_Variable') {
                return false;
            }

            return $node->var->name === $variable;
        });

        return null;
    }

    public function resolveClassFromVariable(
        Variable $variable,
        array $nodes,
    ): ?string {
        /** @var Assign[] $foundNodes */
        $foundNodes = $this->nodeFinder->find($nodes, function (Node $node) use ($variable) {
            if (!$node instanceof Assign) {
                return false;
            }

            return $node->var instanceof Variable && $node->var->name === $variable->name;
        });

        foreach ($foundNodes as $foundNode) {
            if ($foundNode->expr instanceof New_) {
                // First check if it's imported with a use statement
                $import = $this->findImport($nodes, $foundNode->expr->class->toString());

                if ($import !== null) {
                    return $import['class'];
                }

                // Not imported, this is the FQN
                return $foundNode->expr->class->toString();
            }

            // todo: handle other types of assignments
        }

        // If we've gotten here, we haven't found a class name.
        return null;
    }
}
