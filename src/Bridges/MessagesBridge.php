<?php

declare(strict_types=1);

namespace Filagora\Notifications\Bridges;

use Filagora\Messages\Contracts\MessagingPolicy;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\StandardCategories;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One rule for `filagora-messages`: a person who switched private messages off cannot be
 * messaged. Other rules a site has (an ignore list, a suspended account) belong in the site's own
 * policy, which can call this one.
 */
final class MessagesBridge implements MessagingPolicy
{
    public function __construct(private readonly Preferences $preferences) {}

    public function canMessage(Authenticatable $sender, Authenticatable $recipient): bool
    {
        return $this->preferences->allows($recipient, StandardCategories::PRIVATE_MESSAGE_CONTACTABILITY);
    }
}
