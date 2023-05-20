<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JonasPardon\LaravelEventVisualizer\Models\Event;
use JonasPardon\LaravelEventVisualizer\Models\Job;
use JonasPardon\LaravelEventVisualizer\Models\Listener;
use JonasPardon\LaravelEventVisualizer\Models\VisualizerNode;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser\CodeParser;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser\ValueObjects\ResolvedCall;
use JonasPardon\Mermaid\Models\Graph;
use JonasPardon\Mermaid\Models\Link;
use JonasPardon\Mermaid\Models\Node;
use JonasPardon\Mermaid\VO\LinkStyle;
use JonasPardon\Mermaid\VO\NodeShape;
use ReflectionClass;
use Throwable;

class EventVisualizer
{
    private readonly bool $showLaravelEvents;
    private readonly array $classesToIgnore;
    private readonly Graph $graph;
    private readonly Collection $analysedClasses;

    public function __construct()
    {
        $this->showLaravelEvents = config('event-visualizer.show_laravel_events', false);
        $this->classesToIgnore = config('event-visualizer.classes_to_ignore', []);
        $this->graph = new Graph();

        $this->analysedClasses = new Collection();
    }

    public function getMermaidStringForEvents(): string
    {
        $events = $this->getRawAppEvents();
        $this->addEventsToGraph($events);

        return $this->graph->render();
    }

    private function getRawAppEvents(): array
    {
        $rawEvents = app()->make('events')->getRawListeners();
        $sanitizedEvents = [];

        foreach ($rawEvents as $event => $rawListeners) {
            foreach ($rawListeners as $rawListener) {
                if (is_string($rawListener)) {
                    $sanitizedEvents[$event][] = $rawListener;
                } elseif ($rawListener instanceof Closure) {
                    unset($sanitizedEvents[$event]);
                } elseif (is_array($rawListener) && count($rawListener) === 2) {
                    if (is_object($rawListener[0])) {
                        $rawListener[0] = get_class($rawListener[0]);
                    }

                    $sanitizedEvents[$event][] = implode('@', $rawListener);
                }
            }
        }

        return $sanitizedEvents;
    }

    public function addEventsToGraph(array $events): void
    {
        foreach ($events as $event => $listeners) {
            if ($this->isIgnored($event)) {
                continue;
            }

            if (! $this->showLaravelEvents && ! Str::startsWith($event, 'App')) {
                continue; // Get only our own events, not the default laravel ones
            }

            foreach ($listeners as $listener) {
                if ($this->isIgnored($listener)) {
                    continue;
                }

                $this->connectNodes(
                    from: new Event($event),
                    to: new Listener($listener),
                );

                $this->analyseClass($listener);
            }
        }

        foreach (array_keys($events) as $event) {
            if ($this->isIgnored($event)) {
                continue;
            }

            if (! $this->showLaravelEvents && ! Str::startsWith($event, 'App')) {
                continue; // Get only our own events, not the default laravel ones
            }

            foreach (array_keys($events) as $interface) {
                if ($this->isIgnored($interface)) {
                    continue;
                }

                if (! is_subclass_of($event, $interface)) {
                    continue;
                }

                $this->connectNodes(
                    from: new Event($interface),
                    to: new Event($event),
                );
            }
        }
    }

    private function analyseClass(string $className): void
    {
        if ($this->isIgnored($className)) {
            return;
        }

        $sanitizedClassName = Str::before($className, '@');

        if ($this->analysedClasses->contains($sanitizedClassName)) {
            return; // Prevent infinite loops since this method is recursive. Also, performance, ya know.
        }

        $this->analysedClasses->push($sanitizedClassName);

        if (Str::contains($sanitizedClassName, 'StoreEventHistory')) {
            return;
        }

        try {
            $code = $this->getCodeFromClass($sanitizedClassName);
            $parser = new CodeParser($code);

            $jobs = $this->getDispatchedJobs($parser);
            $events = $this->getDispatchedEvents($parser);

            $events->each(function (ResolvedCall $resolvedCall) use ($sanitizedClassName) {
                $this->connectNodes(
                    from: new Event($sanitizedClassName),
                    to: new Listener($resolvedCall->dispatchedClass),
                );

                $this->analyseClass($resolvedCall->dispatchedClass);
            });

            $jobs->each(function (ResolvedCall $resolvedCall) use ($sanitizedClassName) {
                $this->connectNodes(
                    from: new Event($sanitizedClassName),
                    to: new Job($resolvedCall->dispatchedClass),
                );

                $this->analyseClass($resolvedCall->dispatchedClass);
            });
        } catch (Throwable $e) {
            dump("Failed to analyse $sanitizedClassName");
            // throw $e;
        }
    }

    private function getDispatchedEvents(CodeParser $codeParser): Collection
    {
        $classes = [
            'Event',
            'Illuminate\Support\Facades\Event',
            'Illuminate\Contracts\Events\Dispatcher',
        ];
        $methods = [
            'dispatch',
        ];

        $events = [];

        $foundFunctionCalls = $codeParser->getFunctionCalls('event');
        $events = array_merge($events, $foundFunctionCalls);

        foreach ($classes as $class) {
            foreach ($methods as $method) {
                $foundStaticCalls = $codeParser->getStaticCalls($class, $method);
                $events = array_merge($events, $foundStaticCalls);

                $foundMethodCalls = $codeParser->getMethodCalls($class, $method);
                $events = array_merge($events, $foundMethodCalls);
            }
        }

        return collect($events);
    }

    private function getDispatchedJobs(CodeParser $codeParser): Collection
    {
        $classes = [
            'Bus',
            'Illuminate\Support\Facades\Bus',
            'Illuminate\Contracts\Bus\Dispatcher',
        ];
        $methods = [
            'dispatch',
            'dispatchChain',
            'dispatchNow',
            'dispatchSync',
            'dispatchToQueue',
            'dispatchAfterResponse',
            'dispatchAfterCommit',
        ];

        $jobs = [];

        $foundFunctionCalls = array_merge(
            $codeParser->getFunctionCalls('dispatch'),
            $codeParser->getFunctionCalls('dispatch_sync'),
        );
        $jobs = array_merge($jobs, $foundFunctionCalls);

        foreach ($classes as $class) {
            foreach ($methods as $method) {
                $foundStaticCalls = $codeParser->getStaticCalls($class, $method);
                $jobs = array_merge($jobs, $foundStaticCalls);

                $foundMethodCalls = $codeParser->getMethodCalls($class, $method);
                $jobs = array_merge($jobs, $foundMethodCalls);
            }
        }

        return collect($jobs);
    }

    private function connectNodes(VisualizerNode $from, VisualizerNode $to): void
    {
        $fromNode = new Node(
            identifier: $from->getIdentifier(),
            title: $from->getName(),
            shape: new NodeShape(NodeShape::ROUND_EDGES),
            style: $from->getStyle(),
        );
        $toNode = new Node(
            identifier: $to->getIdentifier(),
            title: $to->getName(),
            shape: new NodeShape(NodeShape::ROUND_EDGES),
            style: $to->getStyle(),
        );
        $link = new Link(
            from: $fromNode,
            to: $toNode,
            text: null,
            linkStyle: new LinkStyle(LinkStyle::ARROW_HEAD),
        );

        $this->graph->addNode($fromNode)
            ->addNode($toNode)
            ->addLink($link);
    }

    private function getCodeFromClass(string $className): string
    {
        $reflection = new ReflectionClass($className);
        $source = file($reflection->getFileName());

        return implode('', $source);
    }

    private function sanitizeClassName(string $className): string
    {
        return Str::before($className, '@');
    }

    private function isIgnored(string $className): bool
    {
        return Str::contains($this->sanitizeClassName($className), $this->classesToIgnore);
    }
}
