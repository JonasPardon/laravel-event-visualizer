<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit;

use Illuminate\Support\Facades\Config;
use JonasPardon\LaravelEventVisualizer\Models\Event;
use JonasPardon\LaravelEventVisualizer\Models\Job;
use JonasPardon\LaravelEventVisualizer\Models\Listener;
use JonasPardon\LaravelEventVisualizer\Models\VisualizerNode;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

final class VisualizerNodeTest extends TestCase
{
    /**
     * @dataProvider providesClasses
     * @test
     */
    public function it_can_get_the_name_of_the_node(
        string $FQN,
        string $type,
        string $expectedName,
    ): void
    {
        /** @var VisualizerNode $node */
        $node = new $type($FQN);

        $this->assertEquals($expectedName, $node->getName());
    }

    /**
     * @dataProvider providesClasses
     * @test
     */
    public function it_can_get_the_name_of_the_node_if_subscriber_internal_handlers_should_be_shown(
        string $FQN,
        string $type,
        string $expectedName,
    ): void
    {
        Config::set('event-visualizer.show_subscriber_internal_handler_methods', true);

        /** @var VisualizerNode $node */
        $node = new $type($FQN . '@handler');

        $this->assertEquals($expectedName . '-handler', $node->getName());
    }

    /**
     * @dataProvider providesClasses
     * @test
     */
    public function it_can_get_the_class_name_of_the_node(
        string $FQN,
        string $type,
    ): void
    {
        /** @var VisualizerNode $node */
        $node = new $type($FQN);

        $this->assertEquals($FQN, $node->getClassName());
    }

    /**
     * @dataProvider providesClasses
     * @test
     */
    public function it_can_get_the_class_name_of_the_node_without_the_handler_appended(
        string $FQN,
        string $type,
    ): void
    {
        /** @var VisualizerNode $node */
        $node = new $type($FQN . '@handler');

        $this->assertEquals($FQN, $node->getClassName());
    }

    /**
     * @dataProvider providesClasses
     * @test
     */
    public function it_gets_the_correct_mermaid_string(
        string $FQN,
        string $type,
        string $expectedName,
        string $expectedMermaidString,
    ): void
    {
        /** @var VisualizerNode $node */
        $node = new $type($FQN);

        $this->assertEquals($expectedMermaidString, $node->toString());
    }

    /**
     * Params:
     * - FQN
     * - Node type
     * - Expected node name
     * - Expected mermaid string
     */
    public function providesClasses(): array
    {
        return [
            ['App\\Events\\Event', Event::class, 'Event', 'Event(Event):::event'],
            ['App\\Jobs\\Job', Job::class, 'Job', 'Job(Job):::job'],
            ['App\\Listeners\\Listener', Listener::class, 'Listener', 'Listener(Listener):::listener'],
            ['Event', Event::class, 'Event', 'Event(Event):::event'],
            ['Job', Job::class, 'Job', 'Job(Job):::job'],
            ['Listener', Listener::class, 'Listener', 'Listener(Listener):::listener'],
        ];
    }
}
