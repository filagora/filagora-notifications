<?php

declare(strict_types=1);

use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Messages\Contracts\MessagingPolicy;
use Filagora\Notifications\Tests\Support\HostMessagingPolicy;
use Filagora\Notifications\Tests\Support\HostPushPreferences;
use Filagora\Notifications\Tests\Support\HostSubscriptionPreferences;
use Filagora\Push\Contracts\PushPreferences;

it('leaves a binding the host made before the packages registered', function () {
    expect(app(PushPreferences::class))->toBeInstanceOf(HostPushPreferences::class);
});

it('leaves a binding the host made after the packages registered', function () {
    expect(app(SubscriptionPreferences::class))->toBeInstanceOf(HostSubscriptionPreferences::class)
        ->and(app(MessagingPolicy::class))->toBeInstanceOf(HostMessagingPolicy::class);
});
