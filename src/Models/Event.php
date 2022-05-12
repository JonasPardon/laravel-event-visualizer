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
            backgroundColor: '#16a085',
            fontColor: '#ffffff',
            borderColor: '#16a085',
        );
    }
}
