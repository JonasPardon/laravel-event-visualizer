<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class PropertyResolverTest extends TestCase
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
    public function it_resolves_a_property_that_was_injected_in_the_constructor(): void
    {
        $code = <<<'CODE'
            <?php declare(strict_types=1);
            
            namespace App\Subscribers;
            
            use App\Events\Event1;
            use App\Events\Event2;
            use App\Events\Event3;
            use App\Jobs\Job1;
            use App\Jobs\Job2;
            use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
            use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
            use Illuminate\Contracts\Queue\ShouldQueue;
            use Illuminate\Queue\InteractsWithQueue;
            
            final class ClassName implements ShouldQueue
            {
                use InteractsWithQueue;
            
                public function __construct(
                    private JobDispatcher $jobDispatcher
                ) {
                }
            
                public function handle(Event1|Event2|Event3 $event): void
                {
                    $var = $event->getVar();
            
                    $this->jobDispatcher->dispatchNow(new Job1($var));
                    $this->jobDispatcher->dispatchNow(new Job2($var));
                }
            
                public function subscribe(EventDispatcher $dispatcher): void
                {
                    $dispatcher->listen(Event1::class, self::class . '@handle');
                    $dispatcher->listen(Event2::class, self::class . '@handle');
                    $dispatcher->listen(Event3::class, self::class . '@handle');
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $syntaxTree = $this->parser->parse($code);
        $nodes = $this->nodeTraverser->traverse($syntaxTree);

        /** @var Node\Expr\PropertyFetch $propertyFetchNode */
        $propertyFetchNode = $this->nodeFinder->findFirst($nodes, function (Node $node) {
            return $node instanceof Node\Expr\PropertyFetch;
        });

        $resolvedClass = $codeParser->resolveClassFromProperty($propertyFetchNode);

        $this->assertEquals('Illuminate\Contracts\Bus\Dispatcher', $resolvedClass);
    }
}
