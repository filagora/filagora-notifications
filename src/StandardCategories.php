<?php

declare(strict_types=1);

namespace Filagora\Notifications;

use Filagora\Notifications\Enums\NotificationChannel as Channel;

/**
 * The categories the Filagora packages talk about, under the ids a site stores. They are
 * registered whether or not the package they belong to is installed (an id costs nothing), and
 * a host can turn that off with `register_standard` and register its own.
 *
 * `plugins.release_updates` and `privacy.request_updates` are not here: they belong to packages
 * and sites that have them, which register them.
 */
final class StandardCategories
{
    public const THREAD_UPDATES_INSTANT = 'forum.thread_updates_instant';

    public const THREAD_UPDATES_DIGEST = 'forum.thread_updates_digest';

    public const THREAD_UPDATES_DIGEST_FREQUENCY = 'forum.thread_updates_digest_frequency';

    public const MENTION = 'forum.mention';

    public const MESSAGES_RECEIVED = 'messages.received';

    public const PRIVATE_MESSAGE_CONTACTABILITY = 'messages.private_message_contactability';

    public const MODERATION_OWN_CONTENT = 'moderation.own_content';

    public static function register(NotificationCategories $categories): void
    {
        $everywhere = [Channel::EMAIL, Channel::PUSH, Channel::IN_APP];

        $categories->register(new Category(self::MESSAGES_RECEIVED, 'filagora-notifications::messages.categories.messages_received', $everywhere));
        $categories->register(new Category(self::MENTION, 'filagora-notifications::messages.categories.mention', $everywhere));
        $categories->register(new Category(self::THREAD_UPDATES_INSTANT, 'filagora-notifications::messages.categories.thread_updates_instant', $everywhere, unsubscribable: true));
        $categories->register(new Category(self::THREAD_UPDATES_DIGEST, 'filagora-notifications::messages.categories.thread_updates_digest', unsubscribable: true));
        $categories->register(new Category(
            self::THREAD_UPDATES_DIGEST_FREQUENCY,
            'filagora-notifications::messages.categories.thread_updates_digest_frequency',
            modes: ['instant', 'daily', 'weekly'],
            defaultMode: 'instant',
        ));
        $categories->register(new Category(self::MODERATION_OWN_CONTENT, 'filagora-notifications::messages.categories.moderation_own_content', $everywhere));
        $categories->register(new Category(self::PRIVATE_MESSAGE_CONTACTABILITY, 'filagora-notifications::messages.categories.private_message_contactability'));
    }
}
