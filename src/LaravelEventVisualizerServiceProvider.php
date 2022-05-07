<?php

namespace JonasPardon\LaravelEventVisualizer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use JonasPardon\LaravelEventVisualizer\Commands\LaravelEventVisualizerCommand;

class LaravelEventVisualizerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-event-visualizer')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-event-visualizer_table')
            ->hasCommand(LaravelEventVisualizerCommand::class);
    }
}
