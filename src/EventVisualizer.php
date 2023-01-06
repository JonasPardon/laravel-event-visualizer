<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JonasPardon\LaravelEventVisualizer\Models\Event;
use JonasPardon\LaravelEventVisualizer\Models\Listener;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\Mermaid\Models\Graph;
use ReflectionClass;
use Throwable;

class EventVisualizer
{
    private readonly bool $showLaravelEvents;
    private readonly array $classesToIgnore;
    private readonly bool $autoDiscoverJobsAndEvents;
    private readonly Graph $graph;

    public function __construct()
    {
        $this->showLaravelEvents = config('event-visualizer.show_laravel_events', false);
        $this->classesToIgnore = config('event-visualizer.classes_to_ignore', []);
        $this->autoDiscoverJobsAndEvents = config('event-visualizer.auto_discover_jobs_and_events', false);
        $this->graph = new Graph();
    }

    public function getMermaidStringForEvents(): string
    {
        $events = $this->getRawAppEvents();
        $this->addEventsToGraph($events);

        return '';
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
            if (!$this->showLaravelEvents && !Str::startsWith($event, 'App')) {
                // Get only our own events, not the default laravel ones
                continue;
            }

            foreach ($listeners as $listener) {
                $this->analyseClass($listener);

                // $this->connectNodes(new Event($event), new Listener($listener));
            }
        }
    }

    private function analyseClass(string $className)
    {
        $sanitizedClassName = Str::before($className, '@');

        if (Str::contains($sanitizedClassName, $this->classesToIgnore)) {
            return;
        }

        // if (Str::contains($sanitizedClassName, 'Totango')) {
        //     return;
        // }

        $code = $this->getCodeFromClass($sanitizedClassName);
        $parser = new CodeParser($code);

        try {
            $jobs = $this->getDispatchedJobs($parser);
            $events = $this->getDispatchedEvents($parser);

            if ($jobs->isNotEmpty()) {
                dump(
                    "Jobs dispatched by $sanitizedClassName:\n" .
                    $jobs->map(fn ($job) => " - {$job['argumentClass']}")->implode("\n"),
                );
            }

            if ($events->isNotEmpty()) {
                dump(
                    "Events dispatched by $sanitizedClassName:\n" .
                    $events->map(fn ($event) => " - {$event['argumentClass']}")->implode("\n"),
                );
            }
        } catch (Throwable $e) {
            dump("Failed to analyse $sanitizedClassName");
            throw $e;
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

        foreach ($classes as $class) {
            foreach ($methods as $method) {
                // dump("Looking for $class::$method");

                $foundStaticCalls = $codeParser->getStaticCalls($class, $method);
                if (count($foundStaticCalls) !== 0) {
                    $events = array_merge($events, $foundStaticCalls);
                }

                $foundMethodCalls = $codeParser->getMethodCalls($class, $method);
                if (count($foundMethodCalls) !== 0) {
                    $events = array_merge($events, $foundMethodCalls);
                }
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
            'dispatchNow',
            'dispatchSync',
            'dispatchToQueue',
            'dispatchAfterResponse',
            'dispatchAfterCommit',
        ];

        $jobs = [];

        foreach ($classes as $class) {
            foreach ($methods as $method) {
                // dump("Looking for $class::$method");

                $foundStaticCalls = $codeParser->getStaticCalls($class, $method);
                if (count($foundStaticCalls) !== 0) {
                    $jobs = array_merge($jobs, $foundStaticCalls);
                }

                $foundMethodCalls = $codeParser->getMethodCalls($class, $method);
                if (count($foundMethodCalls) !== 0) {
                    $jobs = array_merge($jobs, $foundMethodCalls);
                }
            }
        }

        return collect($jobs);
    }

    private function getCodeFromClass(string $className): string
    {
        $reflection = new ReflectionClass($className);
        $source = file($reflection->getFileName());

        return implode('', $source);
    }
}
