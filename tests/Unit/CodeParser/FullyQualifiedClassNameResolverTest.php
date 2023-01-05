<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use Exception;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

final class FullyQualifiedClassNameResolverTest extends TestCase
{
    /** @test */
    public function it_returns_input_when_no_imports_are_present(): void
    {
        $code = <<<CODE
            <?php
            
            class ClassName
            {
                public function someMethod()
                {
                    Event::dispatch(new SomeEvent());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $resolvedClass = $codeParser->getFullyQualifiedClassName('SomeEvent');

        $this->assertEquals('SomeEvent', $resolvedClass);
    }

    /** @test */
    public function it_returns_the_fqn_when_import_is_present(): void
    {
        $code = <<<CODE
            <?php
            
            use App\Events\SomeEvent;
            
            class ClassName
            {
                public function someMethod()
                {
                    Event::dispatch(new SomeEvent());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $resolvedClass = $codeParser->getFullyQualifiedClassName('SomeEvent');

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
    }

    /** @test */
    public function it_returns_the_fqn_when_import_is_present_with_alias(): void
    {
        $code = <<<CODE
            <?php
            
            use App\Events\SomeEvent as SomeEventAlias;
            
            class ClassName
            {
                public function someMethod()
                {
                    Event::dispatch(new SomeEventAlias());
                }
            }
            CODE;

        $codeParser = new CodeParser($code);
        $resolvedClass = $codeParser->getFullyQualifiedClassName('SomeEventAlias');

        $this->assertEquals('App\Events\SomeEvent', $resolvedClass);
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

        $codeParser->getFullyQualifiedClassName('Event');
    }
}
