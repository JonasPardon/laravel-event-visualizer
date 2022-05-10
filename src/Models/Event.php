<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Models;

use JonasPardon\Mermaid\Models\Style;

final class Event extends VisualizerNode
{
    public function getType(): string
    {
        return self::EVENT;
    }

    public function getStyle(): Style
    {
        return new Style(
            backgroundColor: config('event-visualizer.theme.colors.event', '#55efc4'),
            fontColor: null,
            borderColor: null
        );
    }
}
