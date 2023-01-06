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
        // dump("Looking for {$subjectClass}->{$methodName}", $code);
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
                        $dispatcher->dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Illuminate\Contracts\Events\Dispatcher',
                'dispatch',
                [
                    [
                        'class' => 'Illuminate\Contracts\Events\Dispatcher',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
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
                        $dispatcher->dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Illuminate\Contracts\Events\Dispatcher',
                'dispatch',
                [
                    [
                        'class' => 'Illuminate\Contracts\Events\Dispatcher',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
                    ],
                ],
            ],
            'Subscriber with injected job and event dispatchers' => [
                <<<'CODE'
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
                CODE,
                'Illuminate\Contracts\Bus\Dispatcher',
                'dispatchNow',
                [
                    [
                        'class' => 'Illuminate\Contracts\Bus\Dispatcher',
                        'method' => 'dispatchNow',
                        'argumentClass' => 'App\Jobs\Job1',
                    ],
                    [
                        'class' => 'Illuminate\Contracts\Bus\Dispatcher',
                        'method' => 'dispatchNow',
                        'argumentClass' => 'App\Jobs\Job2',
                    ],
                ],
            ]
        ];
    }
}
