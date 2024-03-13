<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser\CodeParser;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser\ValueObjects\ResolvedCall;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

final class StaticCallsParsingTest extends TestCase
{
    /**
     * @test
     * @dataProvider providesCodeSamples
     */
    public function it_can_find_static_calls(
        string $code,
        string $subjectClass,
        string $methodName,
        array $expectedStaticCalls,
    ): void {
        $codeParser = new CodeParser($code);
        $staticCalls = $codeParser->getStaticCalls(
            subjectClass: $subjectClass,
            methodName: $methodName,
        );

        $this->assertCount(count($expectedStaticCalls), $staticCalls);
        $this->assertEquals($expectedStaticCalls, $staticCalls);
    }

    public function providesCodeSamples(): array
    {
        return [
            'static dispatch call on Event facade without import' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        \Event::dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Event',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Event',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
            'static dispatch call on Event facade with import' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                use \Event;

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        Event::dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Event',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Event',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
            'static dispatch call on Event facade FQN without import' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        \Illuminate\Support\Facades\Event::dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Support\Facades\Event',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
            'static dispatch call on Event facade FQN with import' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                use \Illuminate\Support\Facades\Event;

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        Event::dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Support\Facades\Event',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
            'static dispatch call on Event facade FQN with import and alias' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                use \Illuminate\Support\Facades\Event as Alias;

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        Alias::dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Support\Facades\Event',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
            'no calls but import as alias' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                use \Illuminate\Support\Facades\Event as Alias;

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        // something
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [],
            ],
            'no calls but normal import' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                use \Illuminate\Support\Facades\Event;

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        // something
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [],
            ],
            'commented static dispatch call on Event facade FQN with import' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                use \Illuminate\Support\Facades\Event;

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        // Event::dispatch();
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [],
            ],
            'multiple static dispatch calls on Event facade FQN with import' => [
                <<<'CODE'
                <?php declare(strict_types=1);

                use \Illuminate\Support\Facades\Event;

                final class ClassName
                {
                    public function __construct()
                    {
                    }

                    public function classMethod(): void
                    {
                        $event1 = new \App\Events\SomeEvent();
                        $event2 = new \App\Events\SomeOtherEvent();

                        Event::dispatch($event1);
                        Event::dispatch($event2);
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Support\Facades\Event',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Support\Facades\Event',
                        dispatchedClass: 'App\Events\SomeOtherEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
        ];
    }
}
