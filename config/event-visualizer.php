<?php

return [
    /**
     * When enabled, this will include all default Laravel events
     * like Login, Logout, JobFailed, ...
     */
    'show_laravel_events' => false,

    /**
     * When using subscribers, you can use different handler methods in the
     * subscriber class. This will include the names of the handler methods
     * in the visualization in the format 'LoginSubscriber-handleUserLogin'.
     */
    'show_subscriber_internal_handler_methods' => false,

    /**
     * All classes included here will be omitted from the visualization.
     */
    'classes_to_ignore' => [
        // Illuminate\Auth\Events\Login::class,
    ],

    /**
     * WIP! Will not work as expected.
     */
    'auto_discover_jobs_and_events' => false,
];
