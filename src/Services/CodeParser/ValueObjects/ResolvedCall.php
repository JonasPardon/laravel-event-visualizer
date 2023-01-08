<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Services\CodeParser\ValueObjects;

class ResolvedCall
{
    public function __construct(
        public readonly string $dispatcherClass,
        public readonly string $dispatchedClass,
        public readonly string $method,
    ) {
    }
}
