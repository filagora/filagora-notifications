<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Notifications\Contracts\EmailSuppression;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Notifications\Suppression\AttributeSuppression;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-notifications.php', 'filagora-notifications');

        $this->app->singleton(NotificationCategories::class);
        $this->app->bindIf(EmailSuppression::class, AttributeSuppression::class);
        $this->app->singleton(Preferences::class);
        $this->app->singleton(Unsubscribe::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('notifications', 'Filagora Notifications', 'filagora/filagora-notifications', requires: ['core']),
        );

        $this->app->make(SchemaRegistry::class)->register(
            PackageSchema::fromFile('notifications', __DIR__.'/../database/schema.php', __DIR__.'/../database/migrations'),
        );

        if (config('filagora-notifications.register_standard', true)) {
            StandardCategories::register($this->app->make(NotificationCategories::class));
        }

        // A queue worker lives across jobs, so what one job read must not answer for the next.
        Event::listen(JobProcessing::class, fn () => $this->app->make(Preferences::class)->flush());

        if (config('filagora-notifications.routes.enabled', false)) {
            Route::prefix((string) config('filagora-notifications.routes.prefix', 'email-preferences'))
                ->middleware((array) config('filagora-notifications.routes.middleware', []))
                ->group(__DIR__.'/../routes/unsubscribe.php');
        }

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
