<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;
use Exception;

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

    /** @test */
    public function it_throws_when_multiple_imports_are_defined_on_one_line(): void
    {
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
                    [
                        'class' => 'Event',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
                    ],
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
                    [
                        'class' => 'Event',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
                    ],
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
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
                    ],
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
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
                    ],
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
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
                    ],
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
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeEvent',
                    ],
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
                        'argumentClass' => 'App\Events\SomeOtherEvent',
                    ],
                ],
            ],
        ];
    }
}
