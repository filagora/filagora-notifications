<?php

declare(strict_types=1);

namespace Filagora\Notifications\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who holds an access key. Access in Filagora is answered one person at a time (`AccessChecker`),
 * so nothing else can list everyone who may, say, moderate plugins: the host does, from its own
 * grants or roles. `Recipients` then keeps only the people who want the alert on a channel.
 *
 * The default binding returns nobody, so a staff category reaches no one until the host binds its
 * own. Returning a person who does not hold the key is harmless: `Recipients` checks again.
 */
interface StaffAudience
{
    /**
     * @return iterable<Authenticatable>
     */
    public function holding(string $access): iterable;
}
