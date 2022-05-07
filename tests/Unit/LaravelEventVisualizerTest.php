<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit;

use Illuminate\Support\Facades\Config;
use JonasPardon\LaravelEventVisualizer\LaravelEventVisualizer;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

final class LaravelEventVisualizerTest extends TestCase
{
    /** @test */
    public function it_parses_basic_events(): void
    {
        $visualizer = new LaravelEventVisualizer();

        $output = $visualizer->buildMermaidString([
            'App\\Events\\Event1' => [
                'App\\Listeners\\Listener1',
                'App\\Listeners\\Listener2',
            ],
        ]);

        $this->assertStringContainsString(
            'Event1[Event1]:::event --> Listener1[Listener1]:::listener',
            $output,
        );
        $this->assertStringContainsString(
            'Event1[Event1]:::event --> Listener2[Listener2]:::listener',
            $output,
        );
    }

    /** @test */
    public function it_defines_theme_colors(): void
    {
        $visualizer = new LaravelEventVisualizer();
        $output = $visualizer->buildMermaidString([]);

        $eventColor = config('event-visualizer.theme.colors.event');
        $listenerColor = config('event-visualizer.theme.colors.listener');
        $jobColor = config('event-visualizer.theme.colors.job');

        $this->assertStringContainsString(
            "classDef event fill:{$eventColor};",
            $output,
        );
        $this->assertStringContainsString(
            "classDef listener fill:{$listenerColor};",
            $output,
        );
        $this->assertStringContainsString(
            "classDef job fill:{$jobColor};",
            $output,
        );
    }

    /** @test */
    public function it_includes_laravel_events_if_so_configured(): void
    {
        Config::set('event-visualizer.show_laravel_events', true);

        $visualizer = new LaravelEventVisualizer();

        $output = $visualizer->buildMermaidString([
            'App\\Events\\Event' => [
                'App\\Listeners\\Listener',
            ],
            'Illuminate\\Auth\\Events\\Login' => [
                'App\\Listeners\\Listener',
            ],
        ]);

        $this->assertStringContainsString(
            'Event[Event]:::event --> Listener[Listener]:::listener',
            $output,
        );
        $this->assertStringContainsString(
            'Login[Login]:::event --> Listener[Listener]:::listener',
            $output,
        );
    }

    /** @test */
    public function it_excludes_laravel_events_if_so_configured(): void
    {
        Config::set('event-visualizer.show_laravel_events', false);

        $visualizer = new LaravelEventVisualizer();

        $output = $visualizer->buildMermaidString([
            'App\\Events\\Event' => [
                'App\\Listeners\\Listener',
            ],
            'Illuminate\\Auth\\Events\\Login' => [
                'App\\Listeners\\Listener',
            ],
        ]);

        $this->assertStringContainsString(
            'Event[Event]:::event --> Listener[Listener]:::listener',
            $output,
        );
        $this->assertStringNotContainsString(
            'Login[Login]:::event --> Listener[Listener]:::listener',
            $output,
        );
    }

    /** @test */
    public function it_includes_subscriber_handler_methods_if_so_configured(): void
    {
        Config::set('event-visualizer.show_subscriber_internal_handler_methods', true);

        $visualizer = new LaravelEventVisualizer();

        $output = $visualizer->buildMermaidString([
            'App\\Events\\Event' => [
                'App\\Listeners\\Listener@handlerMethod',
            ],
        ]);

        $this->assertStringContainsString(
            'Event[Event]:::event --> Listener-handlerMethod[Listener-handlerMethod]:::listener',
            $output,
        );
    }

    /** @test */
    public function it_excludes_subscriber_handler_methods_if_so_configured(): void
    {
        Config::set('event-visualizer.show_subscriber_internal_handler_methods', false);

        $visualizer = new LaravelEventVisualizer();

        $output = $visualizer->buildMermaidString([
            'App\\Events\\Event' => [
                'App\\Listeners\\Listener@handlerMethod',
            ],
        ]);

        $this->assertStringContainsString(
            'Event[Event]:::event --> Listener[Listener]:::listener',
            $output,
        );
        $this->assertStringNotContainsString(
            'handlerMethod',
            $output,
        );
    }

    /** @test */
    public function it_ignores_configured_listeners(): void
    {
        Config::set('event-visualizer.classes_to_ignore', [
            'ListenerToIgnore',
        ]);

        $visualizer = new LaravelEventVisualizer();

        $output = $visualizer->buildMermaidString([
            'App\\Events\\Event' => [
                'App\\Listeners\\Listener',
                'App\\Listeners\\ListenerToIgnore',
            ],
        ]);

        $this->assertStringContainsString(
            'Event[Event]:::event --> Listener[Listener]:::listener',
            $output,
        );
        $this->assertStringNotContainsString(
            'ListenerToIgnore',
            $output,
        );
    }

    /** @test */
    public function it_ignores_configured_events(): void
    {
        $this->markTestIncomplete('Not implemented yet');

        Config::set('event-visualizer.classes_to_ignore', [
            'EventToIgnore',
        ]);

        $visualizer = new LaravelEventVisualizer();

        $output = $visualizer->buildMermaidString([
            'App\\Events\\Event' => [
                'App\\Listeners\\Listener',
            ],
            'App\\Events\\EventToIgnore' => [
                'App\\Listeners\\Listener',
            ],
        ]);

        $this->assertStringContainsString(
            'Event[Event]:::event --> Listener[Listener]:::listener',
            $output,
        );
        $this->assertStringNotContainsString(
            'EventToIgnore',
            $output,
        );
    }
}
