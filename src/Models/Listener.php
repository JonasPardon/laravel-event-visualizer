<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Models;

use JonasPardon\Mermaid\Models\Style;

final class Listener extends VisualizerNode
{
    public function getType(): string
    {
        return self::LISTENER;
    }

    public function getStyle(): Style
    {
        return new Style(
            backgroundColor: config('event-visualizer.theme.colors.listener', '#74b9ff'),
            fontColor: null,
            borderColor: null
        );
    }
}
