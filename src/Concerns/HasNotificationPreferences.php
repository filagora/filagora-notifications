<?php

declare(strict_types=1);

namespace Filagora\Notifications\Concerns;

use Filagora\Notifications\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Add to the user model to give it its stored notification choices (and so the relation manager on
 * the user screen).
 *
 * @mixin Model
 */
trait HasNotificationPreferences
{
    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class, 'user_id');
    }
}
