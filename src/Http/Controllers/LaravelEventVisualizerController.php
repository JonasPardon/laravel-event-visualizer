<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use JonasPardon\LaravelEventVisualizer\EventVisualizerLegacy;
use JonasPardon\Mermaid\Models\Graph;
use JonasPardon\Mermaid\Models\Link;
use JonasPardon\Mermaid\Models\Node;
use JonasPardon\Mermaid\Models\Style;
use JonasPardon\Mermaid\VO\GraphDirection;
use JonasPardon\Mermaid\VO\LinkStyle;
use JonasPardon\Mermaid\VO\NodeShape;

final class LaravelEventVisualizerController extends Controller
{
    public function __construct(
        private EventVisualizerLegacy $visualizer,
    ) {
    }

    public function visualizeEvents(): View
    {
        $events = $this->visualizer->getMermaidStringForEvents();

//        $graph = new Graph(new GraphDirection(GraphDirection::LEFT_TO_RIGHT));
//
//        $from = new Node(
//            identifier: 'A',
//            title: 'This package',
//            shape: new NodeShape(NodeShape::ROUND_EDGES),
//            style: new Style(
//                backgroundColor: '#16a085',
//                fontColor: '#ffffff',
//                borderColor: '#333333',
//            ),
//        );
//
//        $to = new Node(
//            identifier: 'B',
//            title: 'Your application',
//            shape: new NodeShape(NodeShape::HEXAGON),
//            style: new Style(
//                backgroundColor: '#55efc4',
//                fontColor: '#000',
//                borderColor: '#333333',
//                borderWidth: '3px',
//            ),
//        );
//
//        $link = new Link($from, $to, null, new LinkStyle(LinkStyle::OPEN));
//
//        $events = $graph->addNode($from)
//            ->addNode($to)
//            ->addLink($link)
//            ->render();

        return view('event-visualizer::events')->with([
            'events' => $events,
        ]);
    }
}
