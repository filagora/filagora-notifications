<?php

declare(strict_types=1);

namespace Filagora\Notifications\Privacy;

use Filagora\Core\Contracts\PrivacyContributor;
use Filagora\Notifications\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Model;

/**
 * A person's stored choices for their data export, and their removal on erasure. Rows that record
 * the person as the one who changed someone else's choice keep the choice and lose the link to
 * them. There is no retention rule: the rows are settings, not activity.
 */
final class NotificationPreferencesContributor implements PrivacyContributor
{
    public function key(): string
    {
        return 'notifications';
    }

    public function export(Model $user): array
    {
        return [
            'preferences' => NotificationPreference::query()
                ->where('user_id', $user->getKey())
                ->orderBy('preference_key')
                ->orderBy('channel')
                ->get()
                ->map(fn (NotificationPreference $preference): array => [
                    'category' => $preference->preference_key,
                    'channel' => $preference->channel,
                    'value' => $preference->value,
                    'source' => $preference->source,
                    'changed_by_user_id' => $preference->updated_by_user_id,
                    'created_at' => $preference->created_at?->toIso8601String(),
                    'updated_at' => $preference->updated_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    public function erase(Model $user): void
    {
        NotificationPreference::query()->where('user_id', $user->getKey())->delete();
        NotificationPreference::query()->where('updated_by_user_id', $user->getKey())->update(['updated_by_user_id' => null]);
    }
}
