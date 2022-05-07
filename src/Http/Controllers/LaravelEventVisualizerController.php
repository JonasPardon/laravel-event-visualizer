<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use JonasPardon\LaravelEventVisualizer\LaravelEventVisualizer;

final class LaravelEventVisualizerController extends Controller
{
    public function __construct(
        private LaravelEventVisualizer $visualizer,
    ) {
    }

    public function visualizeEvents(): View
    {
        $events = $this->visualizer->getMermaidStringForEvents();

        return view('event-visualizer::events')->with([
            'events' => $events,
        ]);
    }
}
