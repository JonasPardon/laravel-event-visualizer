<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Models;

use JonasPardon\Mermaid\Models\Style;

class Job extends VisualizerNode
{
    public function getType(): string
    {
        return self::JOB;
    }

    public function getStyle(): Style
    {
        return new Style(
            backgroundColor: '#cd6133',
            fontColor: '#ffffff',
            borderColor: '#cd6133',
        );
    }
}
