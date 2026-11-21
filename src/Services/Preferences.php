<?php

declare(strict_types=1);

namespace Filagora\Notifications\Services;

use Filagora\Core\Contracts\AccessChecker;
use Filagora\Notifications\Category;
use Filagora\Notifications\Contracts\EmailSuppression;
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

    public function __construct(
        private readonly NotificationCategories $categories,
        private readonly EmailSuppression $suppression,
        private ?AccessChecker $access = null,
    ) {}

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

        if ($category->isRequired()) {
            return true;
        }

        if (! $this->isOfferedTo($user, $category)) {
            return false;
        }

        $stored = $this->stored($user, $category->id, $channel);

        return $stored === null
            ? $category->defaultFor($user, $channel)
            : $this->toBool($stored, $category->defaultFor($user, $channel));
    }

    /**
     * Whether this person is offered the category at all: everyone is offered a user or required
     * one, and a staff one only when they hold its access key.
     */
    public function isOfferedTo(Authenticatable $user, Category|string $category): bool
    {
        $category = $this->resolve($category);

        $this->access ??= app(AccessChecker::class);

        return ! $category->isStaff() || $this->access->allows($user, (string) $category->access);
    }

    /**
     * Whether optional mail to this person must stop altogether (see `EmailSuppression`).
     */
    public function isSuppressed(Authenticatable $user): bool
    {
        return $this->suppression->isSuppressed($user);
    }

    /**
     * The one question to ask before sending optional mail, a push or a bell entry: the person
     * wants this category on this channel and, for email, is not suppressed.
     */
    public function canDeliver(Authenticatable $user, Category|string $category, NotificationChannel $channel = NotificationChannel::EMAIL): bool
    {
        $category = $this->resolve($category);

        // Required mail (verification, security notices) does not ask: not even suppression stops it.
        if ($category->isRequired()) {
            return $category->supports($channel);
        }

        if ($channel === NotificationChannel::EMAIL && $this->isSuppressed($user)) {
            return false;
        }

        return $this->allows($user, $category, $channel);
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
        $this->assertChangeable($user, $category);

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
        $this->assertChangeable($user, $category);

        if (! $category->isMode()) {
            throw InvalidPreference::notAMode($category->id);
        }

        if (! in_array($mode, $category->modes ?? [], true)) {
            throw InvalidPreference::unknownMode($category->id, $mode);
        }

        $this->write($user, $category, $channel, $mode, $updatedBy, $source);
    }

    /**
     * Everything a settings page needs: each category offered to this person, with their current
     * answer on each channel it supports, or its current mode. A required category is listed with
     * `locked` true (always on, not a choice); a staff category appears only for people who hold
     * its access key.
     *
     * @return list<array{category: Category, channels: array<string, bool>, mode: string|null, locked: bool}>
     */
    public function matrix(Authenticatable $user): array
    {
        $matrix = [];

        foreach ($this->categories->all() as $category) {
            if (! $this->isOfferedTo($user, $category)) {
                continue;
            }

            if ($category->isMode()) {
                $matrix[] = ['category' => $category, 'channels' => [], 'mode' => $this->mode($user, $category, $category->channels[0]), 'locked' => false];

                continue;
            }

            $channels = [];
            foreach ($category->channels as $channel) {
                $channels[$channel->value] = $this->allows($user, $category, $channel);
            }

            $matrix[] = ['category' => $category, 'channels' => $channels, 'mode' => null, 'locked' => $category->isRequired()];
        }

        return $matrix;
    }

    private function resolve(Category|string $category): Category
    {
        return $category instanceof Category ? $category : $this->categories->get($category);
    }

    private function assertChangeable(Authenticatable $user, Category $category): void
    {
        if ($category->isRequired()) {
            throw InvalidPreference::required($category->id);
        }

        if (! $this->isOfferedTo($user, $category)) {
            throw InvalidPreference::notAvailable($category->id);
        }
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
