<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser\CodeParser;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser\ValueObjects\ResolvedCall;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

final class FunctionCallsParsingTest extends TestCase
{
    /** @test */
    public function it_finds_an_event_helper_call(): void
    {
        $code = <<<'CODE'
            <?php
            
            namespace App\Domain\SomeDomain;
            
            use App\Events\SomeEvent;
            
            class SomeClass
            {
                public function handle(): void
                {
                    event(new SomeEvent());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);

        $helperCalls = $codeParser->getFunctionCalls('event');

        $expectedHelperCall = new ResolvedCall(
            dispatcherClass: 'none',
            dispatchedClass: 'App\Events\SomeEvent',
            method: 'event',
        );

        $this->assertCount(1, $helperCalls);
        $this->assertEquals($expectedHelperCall->dispatcherClass, $helperCalls[0]->dispatcherClass);
        $this->assertEquals($expectedHelperCall->method, $helperCalls[0]->method);
        $this->assertEquals($expectedHelperCall->dispatchedClass, $helperCalls[0]->dispatchedClass);
    }

    /** @test */
    public function it_finds_a_dispatch_helper_call(): void
    {
        $code = <<<'CODE'
            <?php
            
            namespace App\Domain\SomeDomain;
            
            use App\Events\SomeJob;
            
            class SomeClass
            {
                public function handle(): void
                {
                    dispatch(new SomeJob());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);

        $helperCalls = $codeParser->getFunctionCalls('dispatch');

        $expectedHelperCall = new ResolvedCall(
            dispatcherClass: 'none',
            dispatchedClass: 'App\Events\SomeJob',
            method: 'dispatch',
        );

        $this->assertCount(1, $helperCalls);
        $this->assertEquals($expectedHelperCall->dispatcherClass, $helperCalls[0]->dispatcherClass);
        $this->assertEquals($expectedHelperCall->method, $helperCalls[0]->method);
        $this->assertEquals($expectedHelperCall->dispatchedClass, $helperCalls[0]->dispatchedClass);
    }
}
