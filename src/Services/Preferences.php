<?php

declare(strict_types=1);

namespace Filagora\Notifications\Services;

use Filagora\Notifications\Category;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Enums\PreferenceSource;
use Filagora\Notifications\Events\PreferenceChanged;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\NotificationCategories;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What a person wants to hear about, per category and channel. No stored row means the category's
 * default. A person's rows are read once and kept for the life of this instance (it is a
 * singleton, so one queued notification's several questions cost one query); `flush()` forgets
 * them, and the package does so at every job boundary, so a long-running worker never answers
 * from stale rows.
 */
class Preferences
{
    /**
     * @var array<int|string, array<string, string|null>> user key => "category|channel" => stored value
     */
    private array $rows = [];

    public function __construct(private readonly NotificationCategories $categories) {}

    public function flush(): void
    {
        $this->rows = [];
    }

    public function allows(Authenticatable $user, Category|string $category, NotificationChannel $channel = NotificationChannel::EMAIL): bool
    {
        $category = $this->resolve($category);

        if (! $category->supports($channel)) {
            return false;
        }

        if ($category->isMode()) {
            throw InvalidPreference::notASwitch($category->id);
        }

        $stored = $this->stored($user, $category->id, $channel);

        return $stored === null
            ? $category->defaultFor($user, $channel)
            : $this->toBool($stored, $category->defaultFor($user, $channel));
    }

    /**
     * The word a person chose for a mode category, or the category's default when they have not
     * chosen or the stored word is no longer one of its modes.
     */
    public function mode(Authenticatable $user, Category|string $category, NotificationChannel $channel = NotificationChannel::EMAIL): string
    {
        $category = $this->resolve($category);

        if (! $category->isMode()) {
            throw InvalidPreference::notAMode($category->id);
        }

        $stored = $this->stored($user, $category->id, $channel);

        return $stored !== null && in_array($stored, $category->modes ?? [], true)
            ? $stored
            : (string) ($category->defaultMode ?? ($category->modes[0] ?? ''));
    }

    public function set(
        Authenticatable $user,
        Category|string $category,
        NotificationChannel $channel,
        bool $enabled,
        ?Authenticatable $updatedBy = null,
        PreferenceSource|string $source = PreferenceSource::USER,
    ): void {
        $category = $this->resolve($category);
        $this->assertSupports($category, $channel);

        if ($category->isMode()) {
            throw InvalidPreference::notASwitch($category->id);
        }

        $this->write($user, $category, $channel, $enabled ? '1' : '0', $updatedBy, $source);
    }

    public function setMode(
        Authenticatable $user,
        Category|string $category,
        string $mode,
        NotificationChannel $channel = NotificationChannel::EMAIL,
        ?Authenticatable $updatedBy = null,
        PreferenceSource|string $source = PreferenceSource::USER,
    ): void {
        $category = $this->resolve($category);
        $this->assertSupports($category, $channel);

        if (! $category->isMode()) {
            throw InvalidPreference::notAMode($category->id);
        }

        if (! in_array($mode, $category->modes ?? [], true)) {
            throw InvalidPreference::unknownMode($category->id, $mode);
        }

        $this->write($user, $category, $channel, $mode, $updatedBy, $source);
    }

    /**
     * Everything a settings page needs: each category with the person's current answer on each
     * channel it supports, or its current mode.
     *
     * @return list<array{category: Category, channels: array<string, bool>, mode: string|null}>
     */
    public function matrix(Authenticatable $user): array
    {
        $matrix = [];

        foreach ($this->categories->all() as $category) {
            if ($category->isMode()) {
                $matrix[] = ['category' => $category, 'channels' => [], 'mode' => $this->mode($user, $category, $category->channels[0])];

                continue;
            }

            $channels = [];
            foreach ($category->channels as $channel) {
                $channels[$channel->value] = $this->allows($user, $category, $channel);
            }

            $matrix[] = ['category' => $category, 'channels' => $channels, 'mode' => null];
        }

        return $matrix;
    }

    private function resolve(Category|string $category): Category
    {
        return $category instanceof Category ? $category : $this->categories->get($category);
    }

    private function assertSupports(Category $category, NotificationChannel $channel): void
    {
        if (! $category->supports($channel)) {
            throw InvalidPreference::unsupportedChannel($category->id, $channel);
        }
    }

    private function write(
        Authenticatable $user,
        Category $category,
        NotificationChannel $channel,
        string $value,
        ?Authenticatable $updatedBy,
        PreferenceSource|string $source,
    ): void {
        $userId = $user->getAuthIdentifier();
        $old = $this->stored($user, $category->id, $channel);
        $source = $source instanceof PreferenceSource ? $source->value : $source;
        $updatedById = $updatedBy?->getAuthIdentifier();

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $userId, 'preference_key' => $category->id, 'channel' => $channel->value],
            ['value' => $value, 'source' => $source, 'updated_by_user_id' => $updatedById],
        );

        unset($this->rows[$userId]);

        if ($old !== $value) {
            event(new PreferenceChanged($userId, $category->id, $channel, $old, $value, $source, $updatedById));
        }
    }

    private function stored(Authenticatable $user, string $category, NotificationChannel $channel): ?string
    {
        $userId = $user->getAuthIdentifier();

        $this->rows[$userId] ??= NotificationPreference::query()
            ->where('user_id', $userId)
            ->get(['preference_key', 'channel', 'value'])
            ->mapWithKeys(fn (NotificationPreference $row): array => [$row->preference_key.'|'.$row->channel => $row->value])
            ->all();

        return $this->rows[$userId][$category.'|'.$channel->value] ?? null;
    }

    private function toBool(string $value, bool $default): bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
}
