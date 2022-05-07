
# Laravel package to visualize events with their handlers, including jobs to chain them together

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jonaspardon/laravel-event-visualizer.svg?style=flat-square)](https://packagist.org/packages/jonaspardon/laravel-event-visualizer)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/jonaspardon/laravel-event-visualizer/run-tests?label=tests)](https://github.com/jonaspardon/laravel-event-visualizer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/jonaspardon/laravel-event-visualizer/Check%20&%20fix%20styling?label=code%20style)](https://github.com/jonaspardon/laravel-event-visualizer/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jonaspardon/laravel-event-visualizer.svg?style=flat-square)](https://packagist.org/packages/jonaspardon/laravel-event-visualizer)

Laravel package to visualize events with their handlers, including jobs to chain them together.

<img src="./example.png" />

## Installation

You can install the package via composer:

```bash
composer require jonaspardon/laravel-event-visualizer
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

This package will currently not auto-discover listeners and jobs.

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
