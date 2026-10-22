<?php

declare(strict_types=1);

namespace Filagora\Notifications\Bridges;

use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Forums\Enums\DeliveryMode;
use Filagora\Forums\Subscriptions\UserAttributePreferences;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\StandardCategories;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Answers `filagora-forums`' question about followed threads from the person's choices. How often
 * (`forum.thread_updates_digest_frequency`) says when; whether they want it at all is the switch
 * for that kind of mail (instant or digest). A person whose email is suppressed gets nothing, so
 * no digest rows are queued for mail that will not be sent. Whether posting follows a thread
 * stays a user attribute, which forums reads itself.
 */
final class ForumsBridge implements SubscriptionPreferences
{
    public function __construct(
        private readonly Preferences $preferences,
        private readonly UserAttributePreferences $attributes,
    ) {}

    public function deliveryMode(Authenticatable $user): DeliveryMode
    {
        if ($this->preferences->isSuppressed($user)) {
            return DeliveryMode::OFF;
        }

        $mode = DeliveryMode::tryFrom($this->preferences->mode($user, StandardCategories::THREAD_UPDATES_DIGEST_FREQUENCY)) ?? DeliveryMode::INSTANT;

        $wanted = $mode === DeliveryMode::INSTANT
            ? $this->preferences->allows($user, StandardCategories::THREAD_UPDATES_INSTANT)
            : $this->preferences->allows($user, StandardCategories::THREAD_UPDATES_DIGEST);

        return $wanted ? $mode : DeliveryMode::OFF;
    }

    public function autoSubscribeOnPost(Authenticatable $user): bool
    {
        return $this->attributes->autoSubscribeOnPost($user);
    }
}
