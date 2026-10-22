<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Closure;
use Filagora\Notifications\Enums\NotificationChannel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Lang;

/**
 * A kind of notification a person can choose about. The id is stored in preference rows and in
 * signed unsubscribe links, so it never changes once a site has used it.
 *
 * A category is either a switch (on or off for each channel it supports) or a mode (one of a few
 * words, such as how often a digest is sent). A switch is on unless `defaults` says otherwise for
 * that channel, or `defaultResolver` answers for the person.
 */
final readonly class Category
{
    /**
     * @param  string  $id  for example `forum.mention`
     * @param  string  $label  a language key (`filagora-notifications::messages.x`) or plain text
     * @param  list<NotificationChannel>  $channels  the channels this category can be delivered on
     * @param  array<string, bool>  $defaults  channel value => default, for the channels that are not on
     * @param  bool  $unsubscribable  whether a signed link in a mail may switch it off
     * @param  list<string>|null  $modes  the allowed words, for a mode category
     * @param  Closure(Authenticatable, NotificationChannel): bool|null  $defaultResolver  answers the default per person
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $channels = [NotificationChannel::EMAIL],
        public array $defaults = [],
        public bool $unsubscribable = false,
        public ?array $modes = null,
        public ?string $defaultMode = null,
        public ?Closure $defaultResolver = null,
    ) {}

    public function label(): string
    {
        return Lang::has($this->label) ? (string) __($this->label) : $this->label;
    }

    public function supports(NotificationChannel $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function isMode(): bool
    {
        return $this->modes !== null;
    }

    public function defaultFor(Authenticatable $user, NotificationChannel $channel): bool
    {
        if (! $this->supports($channel)) {
            return false;
        }

        if ($this->defaultResolver !== null) {
            return (bool) ($this->defaultResolver)($user, $channel);
        }

        return $this->defaults[$channel->value] ?? true;
    }
}
