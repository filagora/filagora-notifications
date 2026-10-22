<?php

declare(strict_types=1);

namespace Filagora\Notifications\Events;

use Filagora\Notifications\Enums\NotificationChannel;

/**
 * A stored choice was written with a different value. `old` is null when the person had no row
 * yet (they were on the category's default).
 */
final class PreferenceChanged
{
    public function __construct(
        public readonly int|string $userId,
        public readonly string $category,
        public readonly NotificationChannel $channel,
        public readonly ?string $old,
        public readonly string $new,
        public readonly string $source,
        public readonly int|string|null $updatedById,
    ) {}
}
