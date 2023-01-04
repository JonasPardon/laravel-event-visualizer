<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use Exception;

final class MethodCallsParsingTest extends TestCase
{
    private CodeParser $codeParser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codeParser = new CodeParser();
    }

    /**
     * @test
     * @dataProvider providesCodeSamples
     */
    public function it_can_find_method_calls(
        string $code,
        string $subjectClass,
        string $methodName,
        array $expectedStaticCalls,
    ): void {
        dump("Looking for {$subjectClass}::{$methodName}", $code);

        $staticCalls = $this->codeParser->getMethodCalls(
            code: $code,
            subjectClass: $subjectClass,
            methodName: $methodName,
        );

        $this->assertCount(count($expectedStaticCalls), $staticCalls);
        $this->assertEquals($expectedStaticCalls, $staticCalls);
    }

    /** @test */
    public function it_throws_when_multiple_imports_are_defined_on_one_line(): void
    {
        $this->markTestSkipped('Not implemented yet');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Multiple imports in one line not supported for now');

        $this->codeParser->getStaticCalls(
            code: <<<'CODE'
                <?php declare(strict_types=1);
                
                use \Event, \Bus;
                
                final class ClassName
                {
                    public function __construct()
                    {
                    }
                
                    public function classMethod(): void
                    {
                        Event::dispatch();
                    }
                }
                CODE,
            subjectClass: 'Illuminate\Support\Facades\Event',
            methodName: 'dispatch',
        );
    }

    public function providesCodeSamples(): array
    {
        return [
            'dispatch call on Event contract without import' => [
                <<<'CODE'
                <?php declare(strict_types=1);
                
                final class ClassName
                {
                    public function __construct(private \Illuminate\Contracts\Events\Dispatcher $dispatcher)
                    {
                    }
                
                    public function classMethod(): void
                    {
                        $dispatcher->dispatch();
                    }
                }
                CODE,
                'Illuminate\Contracts\Events\Dispatcher',
                'dispatch',
                [
                    [
                        'class' => 'Illuminate\Contracts\Events\Dispatcher',
                        'method' => 'dispatch',
                    ],
                ],
            ],
            'dispatch call on Event contract with import' => [
                <<<'CODE'
                <?php declare(strict_types=1);
                
                use \Illuminate\Contracts\Events\Dispatcher;
                
                final class ClassName
                {
                    public function __construct(private Dispatcher $dispatcher)
                    {
                    }
                
                    public function classMethod(): void
                    {
                        $dispatcher->dispatch();
                    }
                }
                CODE,
                'Illuminate\Contracts\Events\Dispatcher',
                'dispatch',
                [
                    [
                        'class' => 'Illuminate\Contracts\Events\Dispatcher',
                        'method' => 'dispatch',
                    ],
                ],
            ],
        ];
    }
}
