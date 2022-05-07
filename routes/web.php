<?php

use Illuminate\Support\Facades\Route;
use JonasPardon\LaravelEventVisualizer\Http\Controllers\LaravelEventVisualizerController;

if (config('app.env') !== 'production') {
    Route::get('/event-visualizer', [LaravelEventVisualizerController::class, 'visualizeEvents']);
}
