<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-notifications.php', 'filagora-notifications');
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('notifications', 'Filagora Notifications', 'filagora/filagora-notifications', requires: ['core']),
        );

        $this->app->make(SchemaRegistry::class)->register(
            PackageSchema::fromFile('notifications', __DIR__.'/../database/schema.php', __DIR__.'/../database/migrations'),
        );

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
