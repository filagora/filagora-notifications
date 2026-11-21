<?php

declare(strict_types=1);

namespace Filagora\Notifications\Support;

use Filagora\Notifications\Contracts\StaffAudience;

/**
 * The default: no one is a recipient until the host says who holds an access key.
 */
final class NoStaffAudience implements StaffAudience
{
    public function holding(string $access): iterable
    {
        return [];
    }
}
