<?php

namespace JonasPardon\LaravelEventVisualizer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JonasPardon\LaravelEventVisualizer\EventVisualizerLegacy
 */
class LaravelEventVisualizer extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-event-visualizer';
    }
}
