<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Contracts;

interface VisualizerNodeInterface
{
    public function getType(): string;

    public function getName(): string;

    public function getIdentifier(): string;
}
