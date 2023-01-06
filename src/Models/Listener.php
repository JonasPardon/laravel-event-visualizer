<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Models;

use JonasPardon\Mermaid\Models\Style;

class Listener extends VisualizerNode
{
    public function getType(): string
    {
        return self::LISTENER;
    }

    public function getStyle(): Style
    {
        return new Style(
            backgroundColor: '#40407a',
            fontColor: '#ffffff',
            borderColor: '#40407a',
        );
    }
}
