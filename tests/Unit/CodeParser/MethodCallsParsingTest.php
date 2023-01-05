<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

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
