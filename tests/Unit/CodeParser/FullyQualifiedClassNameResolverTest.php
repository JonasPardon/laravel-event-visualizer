<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

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
}
