<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Forums\Subscriptions\UserAttributePreferences;
use Filagora\Messages\Contracts\MessagingPolicy;
use Filagora\Messages\Messaging\AllowAllMessaging;
use Filagora\Notifications\Bridges\ForumsBridge;
use Filagora\Notifications\Bridges\MessagesBridge;
use Filagora\Notifications\Bridges\PushBridge;
use Filagora\Notifications\Contracts\EmailSuppression;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Notifications\Suppression\AttributeSuppression;
use Filagora\Push\Contracts\PushPreferences;
use Filagora\Push\Preferences\AllowsAllPush;
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

        $this->bridge('push', PushPreferences::class, AllowsAllPush::class, PushBridge::class);
        $this->bridge('forums', SubscriptionPreferences::class, UserAttributePreferences::class, ForumsBridge::class);
        $this->bridge('messages', MessagingPolicy::class, AllowAllMessaging::class, MessagesBridge::class);
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

    /**
     * Binds the bridge for a sibling package that is installed, but only once every provider has
     * booted and only if the contract still resolves to that package's own default. A host that
     * bound its own class (in any provider, before or after this one) is left alone.
     *
     * @param  class-string  $contract
     * @param  class-string  $default
     * @param  class-string  $bridge
     */
    private function bridge(string $key, string $contract, string $default, string $bridge): void
    {
        if (! interface_exists($contract) || ! class_exists($default)) {
            return;
        }

        $this->app->booted(function () use ($key, $contract, $default, $bridge): void {
            if (! config("filagora-notifications.bridges.{$key}", true) || ! $this->app->bound($contract)) {
                return;
            }

            if ($this->app->make($contract) instanceof $default) {
                $this->app->singleton($contract, $bridge);
            }
        });
    }
}
