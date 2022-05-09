<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Models;

final class Event extends VisualizerNode
{
    public function getType(): string
    {
        return self::EVENT;
    }
}
