<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation group
    |--------------------------------------------------------------------------
    |
    | The Filament navigation group this package's resources are placed in.
    | `env()` is only ever used in config files, and config holds no closures.
    |
    */
    'navigation_group' => env('FILAGORA_NOTIFICATIONS_NAVIGATION_GROUP', 'Notifications'),

    /*
    |--------------------------------------------------------------------------
    | Standard categories
    |--------------------------------------------------------------------------
    |
    | Register the categories the Filagora packages use (mentions, private messages, thread
    | updates and their digest, moderation of your own posts, private-message contactability).
    | Turn off to register only your own.
    |
    */
    'register_standard' => true,

];
