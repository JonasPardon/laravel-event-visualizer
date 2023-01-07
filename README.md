
# Laravel Event Visualizer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jonaspardon/laravel-event-visualizer.svg?style=flat-square)](https://packagist.org/packages/jonaspardon/laravel-event-visualizer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jonaspardon/laravel-event-visualizer/run-tests.yml?branch=main&label=tests)](https://github.com/jonaspardon/laravel-event-visualizer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jonaspardon/laravel-event-visualizer/php-cs-fixer.yml?branch=main&label=code%20style)](https://github.com/jonaspardon/laravel-event-visualizer/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jonaspardon/laravel-event-visualizer.svg?style=flat-square)](https://packagist.org/packages/jonaspardon/laravel-event-visualizer)

Laravel package to visualize events with their handlers, including jobs to chain them together.

<img src="./example.png" />

## Installation

You can install the package via composer:

```bash
composer require jonaspardon/laravel-event-visualizer --dev
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="event-visualizer-config"
```

You can publish the views with:

```bash
php artisan vendor:publish --tag="event-visualizer-views"
```

## Usage

Visit `your-app.test/event-visualizer` on a non-production environment.

### Auto discovery

Auto discovery of events and jobs might still fail. If you're using this and encounter errors, please open an issue.

If you want to enable auto discovery, enable `auto_discover_jobs_and_events` in your config. This will currently disable manual discovery.
If this gives you any problems, you should opt for manual discovery.

Refer to the table below for support.

| Syntax                        | Examples                                           | Supported |
|-------------------------------|----------------------------------------------------|-----------|
| Static call with inline class | `Bus::dispatch(new Job())`                         | yes       |
|                               | `Bus::dispatchNow(new Job())`                      | yes       |
|                               | `Bus::dispatchSync(new Job())`                     | yes       |
|                               | `Bus::dispatchToQueue(new Job())`                  | yes       |
|                               | `Bus::dispatchAfterResponse(new Job())`            | yes       |
|                               | `Event::dispatch(new Event())`                     | yes       |
| Method call with inline class | `$jobDispatcher->dispatch(new Job())`              | yes       |
|                               | `$jobDispatcher->dispatchNow(new Job())`           | yes       |
|                               | `$jobDispatcher->dispatchSync(new Job())`          | yes       |
|                               | `$jobDispatcher->dispatchToQueue(new Job())`       | yes       |
|                               | `$jobDispatcher->dispatchAfterResponse(new Job())` | yes       |
|                               | `$eventDispatcher->dispatch(new Event())`          | yes       |
| Static call with variable     | `Bus::dispatch($job)`                              | yes (WIP) |
|                               | `Bus::dispatchNow($job)`                           | yes (WIP) |
|                               | `Bus::dispatchSync($job)`                          | yes (WIP) |
|                               | `Bus::dispatchToQueue($job)`                       | yes (WIP) |
|                               | `Bus::dispatchAfterResponse($job)`                 | yes (WIP) |
|                               | `Event::dispatch($event)`                          | yes (WIP) |
| Method call with variable     | `$jobDispatcher->dispatch($job)`                   | yes (WIP) |
|                               | `$jobDispatcher->dispatchNow($job)`                | yes (WIP) |
|                               | `$jobDispatcher->dispatchSync($job)`               | yes (WIP) |
|                               | `$jobDispatcher->dispatchToQueue($job)`            | yes (WIP) |
|                               | `$jobDispatcher->dispatchAfterResponse($job)`      | yes (WIP) |
|                               | `$eventDispatcher->dispatch($event)`               | yes (WIP) |

### Manual discovery

To make sure your listeners and jobs are linked together, add the following snippets wherever applicable:

```php
<?php

class ListenerOrJob {
    public function handle(): void
    {
        ...
        Event::dispatch(Event1::class);
        Event::dispatchNow(Event1::class);
        Bus::dispatch(Job1::class);
        Bus::dispatchNow(Job2::class);
        ...
    }

    public static function dispatchesEvents(): array
    {
        return [
            Event1::class,
            Event2::class,
        ];
    }
    
    public static function dispatchesJobs(): array
    {
        return [
            Job1::class,
            Job2::class,
        ];
    }
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jonas Pardon](https://github.com/JonasPardon)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
