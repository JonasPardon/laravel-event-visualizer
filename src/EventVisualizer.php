<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer;

use Closure;
use Illuminate\Support\Str;
use JonasPardon\LaravelEventVisualizer\Models\Event;
use JonasPardon\LaravelEventVisualizer\Models\Job;
use JonasPardon\LaravelEventVisualizer\Models\Listener;
use JonasPardon\LaravelEventVisualizer\Models\VisualizerNode;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser;
use JonasPardon\Mermaid\Models\Graph;
use JonasPardon\Mermaid\Models\Link;
use JonasPardon\Mermaid\Models\Node;
use JonasPardon\Mermaid\VO\NodeShape;

class EventVisualizer
{
    private bool $showLaravelEvents;
    private array $classesToIgnore;
    private string $eventColor;
    private string $listenerColor;
    private string $jobColor;
    private bool $autoDiscoverJobsAndEvents;
    private string $mermaidString = '';
    private Graph $graph;

    public function __construct(private CodeParser $parser)
    {
        $this->showLaravelEvents = config('event-visualizer.show_laravel_events', false);
        $this->classesToIgnore = config('event-visualizer.classes_to_ignore', []);
        $this->eventColor = config('event-visualizer.theme.colors.event', '#55efc4');
        $this->listenerColor = config('event-visualizer.theme.colors.listener', '#74b9ff');
        $this->jobColor = config('event-visualizer.theme.colors.job', '#a29bfe');
        $this->autoDiscoverJobsAndEvents = config('event-visualizer.auto_discover_jobs_and_events', false);
        $this->graph = new Graph();
    }

    public function getMermaidStringForEvents(): string
    {
        $events = $this->getRawAppEvents();
        $mermaid = $this->buildMermaidString($events);

//        return $mermaid;

//        dd(
//            $mermaid,
//            $this->graph->render(),
//        );

        return $this->graph->render();

//        return $this->buildMermaidString($events);
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

                $this->connectNodes(new Event($event), new Listener($listener));
            }
        }

        // Add styling to classes. Classes are defined with the ':::' above
        $this->mermaidString .= "classDef event fill:{$this->eventColor};" . PHP_EOL;
        $this->mermaidString .= "classDef listener fill:{$this->listenerColor};" . PHP_EOL;
        $this->mermaidString .= "classDef job fill:{$this->jobColor};" . PHP_EOL;

        return $this->mermaidString;
    }

    private function entryExists(string $entry): bool
    {
        return Str::contains($this->mermaidString, $entry);
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
        $link = new Link($fromNode, $toNode);

        $this->graph->addNode($fromNode)
            ->addNode($toNode)
            ->addLink($link);

        $entry = $from->toString() . ' --> ' . $to->toString() . ';' . PHP_EOL;

        if ($this->entryExists($entry)) {
            return;
        }

        $this->mermaidString .= $entry;
        $this->handleChildren($to);
    }

    private function handleChildren(VisualizerNode $parentNode): void
    {
        $className = $parentNode->getClassName();

        if ($this->autoDiscoverJobsAndEvents) {
            $this->parser
                ->getDispatchedJobsFromVisualizerNode($parentNode)
                ->each(function (Job $job) use ($parentNode) {
                    $this->connectNodes($parentNode, $job);
                });

            $this->parser
                ->getDispatchedEventsFromVisualizerNode($parentNode)
                ->each(function (Event $event) use ($parentNode) {
                    $this->connectNodes($parentNode, $event);
                });
        } else {
            if (method_exists($className, 'dispatchesJobs')) {
                foreach ($className::dispatchesJobs() as $job) {
                    $this->connectNodes($parentNode, new Job($job));
                }
            }

            if (method_exists($className, 'dispatchesEvents')) {
                foreach ($className::dispatchesEvents() as $event) {
                    $this->connectNodes($parentNode, new Event($event));
                }
            }
        }
    }
}
