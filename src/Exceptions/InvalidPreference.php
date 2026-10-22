<?php

declare(strict_types=1);

namespace Filagora\Notifications\Exceptions;

use Filagora\Notifications\Enums\NotificationChannel;
use InvalidArgumentException;

/**
 * A preference was asked for or set that the registered categories do not allow.
 */
class InvalidPreference extends InvalidArgumentException
{
    public static function unknownCategory(string $id): self
    {
        return new self("No notification category [{$id}] is registered.");
    }

    public static function unsupportedChannel(string $id, NotificationChannel $channel): self
    {
        return new self("The category [{$id}] cannot be delivered on the [{$channel->value}] channel.");
    }

    public static function notUnsubscribable(string $id): self
    {
        return new self("The category [{$id}] cannot be switched off by an unsubscribe link.");
    }

    public static function notASwitch(string $id): self
    {
        return new self("The category [{$id}] holds a mode, not a switch.");
    }

    public static function notAMode(string $id): self
    {
        return new self("The category [{$id}] is a switch, not a mode.");
    }

    public static function unknownMode(string $id, string $mode): self
    {
        return new self("[{$mode}] is not a mode of the category [{$id}].");
    }
}
