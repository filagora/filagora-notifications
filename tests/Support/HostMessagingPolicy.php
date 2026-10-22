<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Support;

use Filagora\Messages\Contracts\MessagingPolicy;
use Illuminate\Contracts\Auth\Authenticatable;

class HostMessagingPolicy implements MessagingPolicy
{
    public function canMessage(Authenticatable $sender, Authenticatable $recipient): bool
    {
        return false;
    }
}
