<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

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

        $nodes = $this->nodeFinder->find($nodes, function (Node $node) use ($code, $subjectClass, $methodName) {
            if (!$node instanceof StaticCall) {
                return false;
            }

            // 1. Check if call matches what we're looking for ('like 'dispatch')
            if ($node->name->toString() !== $methodName) {
                return false;
            }

            // 2. Check if variable it's called on is an instance of the subject class
            return $this->isInstanceOf($code, $node->class->toString(), $subjectClass);
        });

        return collect($nodes)->map(function (StaticCall $node) use ($code, $subjectClass, $methodName) {
            return [
                // 'class' => $node->class->toString(),
                'class' => $subjectClass,
                'method' => $node->name->toString(),
                // 'arguments' => $node->args,
            ];
        })->toArray();
    }

    public function isInstanceOf(string $code, string $classToCheck, string $classToCheckAgainst): bool
    {
        $classToCheck = implode('\\', explode('\\', $classToCheck));
        $classToCheckAgainst = implode('\\', explode('\\', $classToCheckAgainst));

        if ($classToCheck === $classToCheckAgainst) {
            return true;
        }

        $foundImport = $this->findImport($code, $classToCheck);

        if ($foundImport !== null) {
            return $foundImport['alias'] === $classToCheckAgainst || $foundImport['class'] === $classToCheckAgainst;
        }
        
        return false;
    }

    public function findImport(string $code, string $class): ?array
    {
        $class = implode('\\', explode('\\', $class));
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        $importNodes = $this->nodeFinder->find($nodes, function (Node $node) use ($class) {
            if (!$node instanceof Node\Stmt\Use_) {
                return false;
            }

            if ($node->type !== Node\Stmt\Use_::TYPE_NORMAL) {
                // We're only looking for class imports
                return false;
            }

            if (count($node->uses) > 1) {
                throw new \Exception('Multiple imports in one line not supported for now');
            }

            foreach ($node->uses as $use) {
                if ($use->alias !== null) {
                    return $use->alias->toString() === $class;
                }
            }

            return true;
        });

        $importNode = $importNodes[0];

        if (!$importNode) {
            return null;
        }

        return [
            'class' => $importNode->uses[0]->name->toString(),
            'alias' => $importNode->uses[0]->alias?->name,
        ];
    }
}
