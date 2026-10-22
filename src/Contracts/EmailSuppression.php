<?php

declare(strict_types=1);

namespace Filagora\Notifications\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether optional notification mail to a person must stop altogether, whatever their category
 * choices say: the address bounced, they complained, or the site has switched their mail off.
 * Required mail (verification, password reset, security notices) does not ask. The default reads
 * attributes of the user model; bind your own to add rules, for example an entitlement.
 */
interface EmailSuppression
{
    public function isSuppressed(Authenticatable $user): bool;
}
