<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-notifications.php', 'filagora-notifications');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filagora-notifications');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filagora-notifications');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filagora-notifications.php' => config_path('filagora-notifications.php'),
            ], 'filagora-notifications-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filagora-notifications-migrations');
        }
    }
}
