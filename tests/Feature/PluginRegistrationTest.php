<?php

declare(strict_types=1);

use Filagora\Notifications\NotificationsPlugin;
use Filament\Panel;

it('merges the package config', function () {
    expect(config('filagora-notifications.navigation_group'))->toBe('Notifications');
});

it('registers on a panel', function () {
    $panel = Panel::make()->id('admin')->plugin(NotificationsPlugin::make());

    expect($panel->hasPlugin('filagora-notifications'))->toBeTrue();
});
