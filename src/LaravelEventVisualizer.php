<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer;

use Closure;
use Illuminate\Support\Str;

class LaravelEventVisualizer
{
    private bool $showLaravelEvents;
    private bool $showSubscriberInternalHandlerMethods;
    private array $classesToIgnore;
    private string $eventColor;
    private string $listenerColor;
    private string $jobColor;
    private bool $autoDiscoverJobsAndEvents;
    private string $mermaidString = '';

    public function __construct(private CodeParser $parser)
    {
        $this->showLaravelEvents = config('event-visualizer.show_laravel_events', false);
        $this->showSubscriberInternalHandlerMethods = config('event-visualizer.show_subscriber_internal_handler_methods', false);
        $this->classesToIgnore = config('event-visualizer.classes_to_ignore', []);
        $this->eventColor = config('event-visualizer.theme.colors.event', '#55efc4');
        $this->listenerColor = config('event-visualizer.theme.colors.listener', '#74b9ff');
        $this->jobColor = config('event-visualizer.theme.colors.job', '#a29bfe');
        $this->autoDiscoverJobsAndEvents = config('event-visualizer.auto_discover_jobs_and_events', false);
    }

    public function getMermaidStringForEvents(): string
    {
        $events = $this->getRawAppEvents();
        return $this->buildMermaidString($events);
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

    public function buildMermaidString(array $events): string
    {
        foreach ($events as $event => $listeners) {
            if (!$this->showLaravelEvents && !Str::startsWith($event, 'App')) {
                // Get only our own events, not the default laravel ones
                continue;
            }

            foreach ($listeners as $listener) {
                // todo: this currently only ignores listeners. Should also ignore events and jobs.
                if (Str::contains($listener, $this->classesToIgnore)) {
                    continue;
                }

                $this->fromEventToListener($event, $listener);
            }
        }

        // Add styling to classes. Classes are defined with the ':::' above
        $this->mermaidString .= "classDef event fill:{$this->eventColor};" . PHP_EOL;
        $this->mermaidString .= "classDef listener fill:{$this->listenerColor};" . PHP_EOL;
        $this->mermaidString .= "classDef job fill:{$this->jobColor};" . PHP_EOL;

        return $this->mermaidString;
    }

    private function getName(string $className): string
    {
        $parts = explode( '\\', $className);
        $name = $parts[count($parts) - 1];

        if ($this->showSubscriberInternalHandlerMethods) {
            $name =  Str::replace('@', '-', $name);
        } else {
            $name = Str::before($name, '@');
        }

        return $name;
    }

    private function entryExists(string $entry): bool
    {
        return Str::contains($this->mermaidString, $entry);
    }

    private function fromEventToListener(string $event, string $listener): void
    {
        $eventName = $this->getName($event);
        $listenerName = $this->getName($listener);

        $entry = "{$eventName}[{$eventName}]:::event --> {$listenerName}[{$listenerName}]:::listener" . PHP_EOL;

        if ($this->entryExists($entry)) {
            return;
        }

        $this->mermaidString .= $entry;
        $this->handleChildren($listener, 'listener');
    }

    private function fromListenerToJob(string $listener, string $job): void
    {
        $listenerName = $this->getName($listener);
        $jobName = $this->getName($job);

        $entry = "{$listenerName}[{$listenerName}]:::listener --> {$jobName}[{$jobName}]:::job" . PHP_EOL;

        if ($this->entryExists($entry)) {
            return;
        }

        $this->mermaidString .= $entry;
        $this->handleChildren($job, 'job');
    }

    private function fromJobToEvent(string $job, string $event): void
    {
        $jobName = $this->getName($job);
        $eventName = $this->getName($event);

        $entry = "{$jobName}[{$jobName}]:::job --> {$eventName}[{$eventName}]:::event" . PHP_EOL;

        if ($this->entryExists($entry)) {
            return;
        }

        $this->mermaidString .= $entry;
    }

    private function fromListenerToEvent(string $listener, string $event): void
    {
        $listenerName = $this->getName($listener);
        $eventName = $this->getName($event);

        $entry = "{$listenerName}[{$listenerName}]:::listener --> {$eventName}[{$eventName}]:::event" . PHP_EOL;

        if ($this->entryExists($entry)) {
            return;
        }

        $this->mermaidString .= $entry;
    }

    private function fromJobToJob(string $job1, string $job2): void
    {
        $job1Name = $this->getName($job1);
        $job2Name = $this->getName($job2);

        $entry = "{$job1Name}[{$job1Name}]:::job --> {$job2Name}[{$job2Name}]:::job" . PHP_EOL;

        if ($this->entryExists($entry)) {
            return;
        }

        $this->mermaidString .= $entry;
        $this->handleChildren($job2, 'job');
    }

    private function handleChildren(string $className, string $classType): void
    {
        if (Str::contains($className, '@')) {
            $className = Str::before($className, '@');
        }

        if ($this->autoDiscoverJobsAndEvents) {
            $autoDiscoveredJobs = $this->parser->getDispatchedJobsFromClass($className);

            $autoDiscoveredJobs->each(function (string $job) use ($className, $classType) {
                if ($classType === 'job') {
                    $this->fromJobToJob($className, $job);
                } elseif ($classType === 'listener') {
                    $this->fromListenerToJob($className, $job);
                }
            });

            $autoDiscoveredEvents = $this->parser->getDispatchedEventsFromClass($className);

            $autoDiscoveredEvents->each(function (string $event) use ($className, $classType) {
                if ($classType === 'job') {
                    $this->fromJobToEvent($className, $event);
                } elseif ($classType === 'listener') {
                    $this->fromListenerToEvent($className, $event);
                }
            });
        } else {
            if (method_exists($className, 'dispatchesJobs')) {
                foreach ($className::dispatchesJobs() as $job) {
                    if ($classType === 'job') {
                        $this->fromJobToJob($className, $job);
                    } elseif ($classType === 'listener') {
                        $this->fromListenerToJob($className, $job);
                    }
                }
            }

            if (method_exists($className, 'dispatchesEvents')) {
                foreach ($className::dispatchesEvents() as $event) {
                    if ($classType === 'job') {
                        $this->fromJobToEvent($className, $event);
                    } elseif ($classType === 'listener') {
                        $this->fromListenerToEvent($className, $event);
                    }
                }
            }
        }
    }
}
