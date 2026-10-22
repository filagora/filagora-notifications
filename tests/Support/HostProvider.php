<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Support;

use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Messages\Contracts\MessagingPolicy;
use Illuminate\Support\ServiceProvider;

/**
 * Stands in for the host application's provider, which registers after the packages'.
 */
class HostProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SubscriptionPreferences::class, HostSubscriptionPreferences::class);
        $this->app->bind(MessagingPolicy::class, HostMessagingPolicy::class);
    }
}
