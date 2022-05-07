<?php

namespace JonasPardon\LaravelEventVisualizer\Commands;

use Illuminate\Console\Command;

class LaravelEventVisualizerCommand extends Command
{
    public $signature = 'laravel-event-visualizer';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
