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

            // if (str_contains($event, 'DocumentVersionWasCreated')) {
            //     foreach ($listeners as $listener) {
            //         $code = $this->getCodeFromClass($listener);
            //         $parser = new CodeParser($code);
            //
            //         dump(
            //             $listener,
            //             $code,
            //             $parser->getStaticCalls('Event', 'dispatch'),
            //         );
            //     }
            //
            //     die();
            // } else {
            //     continue;
            // }

            foreach ($listeners as $listener) {
                // todo: this currently only ignores listeners. Should also ignore events and jobs.
                if (Str::contains($listener, $this->classesToIgnore)) {
                    continue;
                }

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

        if (Str::contains($sanitizedClassName, 'Totango')) {
            return;
        }

        $code = $this->getCodeFromClass($sanitizedClassName);
        $parser = new CodeParser($code);

        try {
            $jobs = $this->getDispatchedJobs($parser);
            $events = $this->getDispatchedEvents($parser);

            if ($jobs->isNotEmpty()) {
                dump(
                    "Jobs dispatched by $sanitizedClassName:\n" .
                    $jobs->map(fn ($job) => " - {$job['className']}")->implode("\n"),
                );
            }

            if ($events->isNotEmpty()) {
                dump(
                    "Events dispatched by $sanitizedClassName:\n" .
                    $events->map(fn ($event) => " - {$event['className']}")->implode("\n"),
                );
            }
        } catch (Throwable $e) {
            dump("Failed to analyse $sanitizedClassName");
            throw $e;
        }

        // dump("$className\n\n$code");
    }

    private function getDispatchedEvents(CodeParser $codeParser): Collection
    {
        $classes = [
            '\Event',
            '\Illuminate\Support\Facades\Event',
        ];
        $methods = [
            'dispatch',
        ];

        $events = new Collection();

        foreach ($classes as $class) {
            foreach ($methods as $method) {
                $events->merge(collect($codeParser->getStaticCalls($class, $method)));
                $events->merge(collect($codeParser->getMethodCalls($class, $method)));
            }
        }

        return $events;
    }

    private function getDispatchedJobs(CodeParser $codeParser): Collection
    {
        $classes = [
            '\Bus',
            '\Illuminate\Support\Facades\Bus',
        ];
        $methods = [
            'dispatch',
            'dispatchNow',
            'dispatchSync',
            'dispatchToQueue',
            'dispatchAfterResponse',
            'dispatchAfterCommit',
        ];

        $jobs = new Collection();

        foreach ($classes as $class) {
            foreach ($methods as $method) {
                $jobs->merge(collect($codeParser->getStaticCalls($class, $method)));
                $jobs->merge(collect($codeParser->getMethodCalls($class, $method)));
            }
        }

        return $jobs;
    }

    private function getCodeFromClass(string $className): string
    {
        $reflection = new ReflectionClass($className);
        $source = file($reflection->getFileName());

        return implode('', $source);
    }
}
