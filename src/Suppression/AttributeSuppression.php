<?php

declare(strict_types=1);

namespace Filagora\Notifications\Suppression;

use Filagora\Notifications\Contracts\EmailSuppression;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Suppresses a person whose "receives email" attribute is explicitly false, or whose address
 * status is one of the suppressed ones (bounced or complained, by default). An attribute the user
 * model does not have never suppresses, so a site without those columns is not silenced.
 * The attribute names are in `filagora-notifications.suppression`.
 */
class AttributeSuppression implements EmailSuppression
{
    public function isSuppressed(Authenticatable $user): bool
    {
        $receives = config('filagora-notifications.suppression.receives_email_attribute');

        if (is_string($receives) && $receives !== '' && data_get($user, $receives) !== null && ! (bool) data_get($user, $receives)) {
            return true;
        }

        $status = config('filagora-notifications.suppression.status_attribute');

        if (is_string($status) && $status !== '') {
            $value = data_get($user, $status);

            return is_string($value) && in_array($value, (array) config('filagora-notifications.suppression.suppressed_statuses', []), true);
        }

        return false;
    }
}
