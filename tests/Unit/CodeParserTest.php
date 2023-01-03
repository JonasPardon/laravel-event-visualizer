<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

final class CodeParserTest extends TestCase
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
    public function it_can_find_static_calls(
        string $code,
        string $subjectClass,
        string $methodName,
        array $expectedStaticCalls,
    ): void {
        // dump("Looking for {$subjectClass}::{$methodName}", $code);

        $staticCalls = $this->codeParser->getStaticCalls(
            code: $code,
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
                        \Event::dispatch();
                    }
                }
                CODE,
                'Event',
                'dispatch',
                [
                    [
                        'class' => 'Event',
                        'method' => 'dispatch',
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
                        Event::dispatch();
                    }
                }
                CODE,
                'Event',
                'dispatch',
                [
                    [
                        'class' => 'Event',
                        'method' => 'dispatch',
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
                        \Illuminate\Support\Facades\Event::dispatch();
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
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
                        Event::dispatch();
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
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
                        Alias::dispatch();
                    }
                }
                CODE,
                'Illuminate\Support\Facades\Event',
                'dispatch',
                [
                    [
                        'class' => 'Illuminate\Support\Facades\Event',
                        'method' => 'dispatch',
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
        ];
    }
}
