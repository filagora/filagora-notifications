<?php

declare(strict_types=1);

use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Forums\Subscriptions\UserAttributePreferences;
use Filagora\Messages\Contracts\MessagingPolicy;
use Filagora\Messages\Messaging\AllowAllMessaging;
use Filagora\Push\Contracts\PushPreferences;
use Filagora\Push\Preferences\AllowsAllPush;

it('keeps each sibling\'s own default when its bridge is switched off', function () {
    expect(app(PushPreferences::class))->toBeInstanceOf(AllowsAllPush::class)
        ->and(app(SubscriptionPreferences::class))->toBeInstanceOf(UserAttributePreferences::class)
        ->and(app(MessagingPolicy::class))->toBeInstanceOf(AllowAllMessaging::class);
});
