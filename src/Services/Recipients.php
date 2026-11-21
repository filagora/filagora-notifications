<?php

declare(strict_types=1);

namespace Filagora\Notifications\Services;

use Filagora\Notifications\Category;
use Filagora\Notifications\Contracts\StaffAudience;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\NotificationCategories;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who should be told about a staff category: the people who hold its access key (the host's
 * `StaffAudience`) and want it on a channel. A person who switched the category off, or whose mail
 * is suppressed, is left out of that channel, so a moderator who opts out of an alert never gets it.
 */
class Recipients
{
    public function __construct(
        private readonly NotificationCategories $categories,
        private readonly Preferences $preferences,
        private readonly StaffAudience $audience,
    ) {}

    /**
     * The people to notify on one channel, each once, in the order the audience listed them.
     *
     * @return list<Authenticatable>
     *
     * @throws InvalidPreference When the category is not a staff category.
     */
    public function for(Category|string $category, NotificationChannel $channel = NotificationChannel::EMAIL): array
    {
        $category = $this->staffCategory($category);

        return $this->wanting($category, $channel, $this->holders($category));
    }

    /**
     * The people to notify on each channel the category supports, only for channels someone wants.
     *
     * @return array<string, list<Authenticatable>> channel value => people
     *
     * @throws InvalidPreference When the category is not a staff category.
     */
    public function byChannel(Category|string $category): array
    {
        $category = $this->staffCategory($category);
        $holders = $this->holders($category);
        $byChannel = [];

        foreach ($category->channels as $channel) {
            $wanting = $this->wanting($category, $channel, $holders);

            if ($wanting !== []) {
                $byChannel[$channel->value] = $wanting;
            }
        }

        return $byChannel;
    }

    private function staffCategory(Category|string $category): Category
    {
        $category = $category instanceof Category ? $category : $this->categories->get($category);

        if (! $category->isStaff()) {
            throw InvalidPreference::notStaff($category->id);
        }

        return $category;
    }

    /**
     * @return list<Authenticatable>
     */
    private function holders(Category $category): array
    {
        $holders = [];

        foreach ($this->audience->holding((string) $category->access) as $person) {
            $holders[(string) $person->getAuthIdentifier()] ??= $person;
        }

        return array_values($holders);
    }

    /**
     * @param  list<Authenticatable>  $holders
     * @return list<Authenticatable>
     */
    private function wanting(Category $category, NotificationChannel $channel, array $holders): array
    {
        return array_values(array_filter(
            $holders,
            fn (Authenticatable $person): bool => $this->preferences->canDeliver($person, $category, $channel),
        ));
    }
}
