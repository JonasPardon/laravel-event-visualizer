<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Models;

use Illuminate\Support\Str;
use JonasPardon\LaravelEventVisualizer\Contracts\VisualizerNodeInterface;

abstract class VisualizerNode implements VisualizerNodeInterface
{
    public const EVENT = 'event';
    public const LISTENER = 'listener';
    public const JOB = 'job';

    private bool $showSubscriberInternalHandlerMethods;

    public function __construct(
        private string $class,
    ) {
        $this->showSubscriberInternalHandlerMethods = config('event-visualizer.show_subscriber_internal_handler_methods', false);
    }

    public function getName(): string
    {
        $parts = explode( '\\', $this->class);
        $name = $parts[count($parts) - 1];

        if ($this->showSubscriberInternalHandlerMethods) {
            $name =  Str::replace('@', '-', $name);
        } else {
            $name = Str::before($name, '@');
        }

        return $name;
    }

    public function getIdentifier(): string
    {
        // todo
        return $this->getName();
    }

    public function getClassName(): string
    {
        if (Str::contains($this->class, '@')) {
            return Str::before($this->class, '@');
        }

        return $this->class;
    }
}
