<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Exceptions\InvalidPreference;

/**
 * The categories a site offers. Packages and the host register theirs; the first registration of
 * an id wins, so a host that registers its own before or after a package does gets its own.
 */
class NotificationCategories
{
    /**
     * @var array<string, Category>
     */
    private array $categories = [];

    /**
     * @return bool Whether it was added (false when the id was already taken).
     */
    public function register(Category $category): bool
    {
        if (isset($this->categories[$category->id])) {
            return false;
        }

        $this->categories[$category->id] = $category;

        return true;
    }

    public function has(string $id): bool
    {
        return isset($this->categories[$id]);
    }

    public function get(string $id): Category
    {
        return $this->categories[$id] ?? throw InvalidPreference::unknownCategory($id);
    }

    /**
     * @return array<string, Category> In the order they were registered.
     */
    public function all(): array
    {
        return $this->categories;
    }

    /**
     * @return list<Category>
     */
    public function forChannel(NotificationChannel $channel): array
    {
        return array_values(array_filter($this->categories, fn (Category $category): bool => $category->supports($channel)));
    }

    /**
     * @return list<string> The ids a signed link may switch off.
     */
    public function unsubscribable(): array
    {
        return array_values(array_map(
            fn (Category $category): string => $category->id,
            array_filter($this->categories, fn (Category $category): bool => $category->unsubscribable),
        ));
    }
}
