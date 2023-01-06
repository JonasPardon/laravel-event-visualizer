<?php

use Illuminate\Support\Facades\Route;
use JonasPardon\LaravelEventVisualizer\Http\Controllers\LaravelEventVisualizerController;

if (app()->environment('local')) {
    Route::get('/event-visualizer', [LaravelEventVisualizerController::class, 'visualizeEvents'])
        ->name('event-visualizer::visualize-events');
}
