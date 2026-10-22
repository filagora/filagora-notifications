<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Support;

use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Forums\Enums\DeliveryMode;
use Illuminate\Contracts\Auth\Authenticatable;

class HostSubscriptionPreferences implements SubscriptionPreferences
{
    public function deliveryMode(Authenticatable $user): DeliveryMode
    {
        return DeliveryMode::WEEKLY;
    }

    public function autoSubscribeOnPost(Authenticatable $user): bool
    {
        return true;
    }
}
