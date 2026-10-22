<?php

declare(strict_types=1);

namespace Filagora\Notifications\Bridges;

use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\StandardCategories;
use Filagora\Push\Contracts\PushPreferences;
use Filagora\Push\Enums\PushCategory;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Answers `filagora-push`'s question from the person's choices on the push channel. A person with
 * no browser subscription still gets no push: that is checked by push itself.
 */
final class PushBridge implements PushPreferences
{
    public function __construct(private readonly Preferences $preferences) {}

    public function allows(Authenticatable $user, PushCategory $category): bool
    {
        return $this->preferences->allows($user, match ($category) {
            PushCategory::MENTION => StandardCategories::MENTION,
            PushCategory::THREAD_UPDATE => StandardCategories::THREAD_UPDATES_INSTANT,
            PushCategory::PRIVATE_MESSAGE => StandardCategories::MESSAGES_RECEIVED,
        }, NotificationChannel::PUSH);
    }
}
