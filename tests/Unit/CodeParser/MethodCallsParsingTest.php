<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use Exception;

final class MethodCallsParsingTest extends TestCase
{
    /**
     * @test
     * @dataProvider providesCodeSamples
     */
    public function it_can_find_method_calls(
        string $code,
        string $subjectClass,
        string $methodName,
        array $expectedMethodCalls,
    ): void {
        $this->markTestIncomplete('Not completed');
        dump("Looking for {$subjectClass}->{$methodName}", $code);

        $codeParser = new CodeParser($code);

        $methodCalls = $codeParser->getMethodCalls(
            subjectClass: $subjectClass,
            methodName: $methodName,
        );

        $this->assertCount(count($expectedMethodCalls), $methodCalls);
        $this->assertEquals($expectedMethodCalls, $methodCalls);
    }

    /** @test */
    public function it_throws_when_multiple_imports_are_defined_on_one_line(): void
    {
        $this->markTestIncomplete('Not implemented yet');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Multiple imports in one line not supported for now');

        $codeParser = new CodeParser(
            <<<'CODE'
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
                CODE
        );

        $codeParser->getStaticCalls(
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
