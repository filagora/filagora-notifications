<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Notifications',

    'categories' => [
        'messages_received' => 'Private messages',
        'mention' => 'Mentions',
        'thread_updates_instant' => 'New posts in threads you follow',
        'thread_updates_digest' => 'Digest of new posts in threads you follow',
        'thread_updates_digest_frequency' => 'How often to send the digest',
        'moderation_own_content' => 'Moderation of your own posts',
        'private_message_contactability' => 'Allow others to send you private messages',
    ],
    'unsubscribed' => [
        'title' => 'Email preferences updated',
        'message' => 'You will no longer receive emails for: :label.',
        'manage' => 'Manage your notification preferences',
    ],
];
