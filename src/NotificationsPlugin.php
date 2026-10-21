<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Filament\Contracts\Plugin;
use Filament\Panel;

class NotificationsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filagora-notifications';
    }

    public function register(Panel $panel): void
    {
        // Register resources, pages and widgets here.
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
