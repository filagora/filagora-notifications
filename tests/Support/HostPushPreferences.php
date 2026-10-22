<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Support;

use Filagora\Push\Contracts\PushPreferences;
use Filagora\Push\Enums\PushCategory;
use Illuminate\Contracts\Auth\Authenticatable;

class HostPushPreferences implements PushPreferences
{
    public function allows(Authenticatable $user, PushCategory $category): bool
    {
        return false;
    }
}
