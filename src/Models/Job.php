<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Models;

use JonasPardon\Mermaid\Models\Style;

final class Job extends VisualizerNode
{
    public function getType(): string
    {
        return self::JOB;
    }

    public function getStyle(): Style
    {
        return new Style(
            backgroundColor: config('event-visualizer.theme.colors.job', '#a29bfe'),
            fontColor: null,
            borderColor: null
        );
    }
}
