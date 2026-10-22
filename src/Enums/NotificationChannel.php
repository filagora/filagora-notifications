<?php

declare(strict_types=1);

namespace Filagora\Notifications\Enums;

/**
 * Where a notification can reach a person. The values are stored in preference rows.
 */
enum NotificationChannel: string
{
    case EMAIL = 'email';
    case PUSH = 'push';
    case IN_APP = 'in_app';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PUSH => 'Browser push',
            self::IN_APP => 'On-site bell',
        };
    }
}
