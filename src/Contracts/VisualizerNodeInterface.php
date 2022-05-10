<?php

declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Contracts;

use JonasPardon\Mermaid\Models\Style;

interface VisualizerNodeInterface
{
    public function getType(): string;

    public function getName(): string;

    public function getIdentifier(): string;

    public function getStyle(): Style;
}
