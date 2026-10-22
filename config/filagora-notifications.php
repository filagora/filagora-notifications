<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation group
    |--------------------------------------------------------------------------
    |
    | The Filament navigation group this package's resources are placed in.
    | `env()` is only ever used in config files, and config holds no closures.
    |
    */
    'navigation_group' => env('FILAGORA_NOTIFICATIONS_NAVIGATION_GROUP', 'Notifications'),

    /*
    |--------------------------------------------------------------------------
    | Standard categories
    |--------------------------------------------------------------------------
    |
    | Register the categories the Filagora packages use (mentions, private messages, thread
    | updates and their digest, moderation of your own posts, private-message contactability).
    | Turn off to register only your own.
    |
    */
    'register_standard' => true,

    /*
    |--------------------------------------------------------------------------
    | Contactability default
    |--------------------------------------------------------------------------
    |
    | Until a person chooses, whether they can be sent private messages comes from this attribute
    | of the user model (on when it is missing or null). Set to null for "always on".
    |
    */
    'standard' => [
        'contactability_attribute' => 'allow_private_messages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bridges
    |--------------------------------------------------------------------------
    |
    | When the sibling package is installed, answer its preference question from the person's
    | choices here. Each applies only while the sibling's own default is still the bound one, so a
    | host's own binding always wins. Turn one off to keep the sibling's default.
    |
    */
    'bridges' => [
        'push' => true,
        'forums' => true,
        'messages' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gates
    |--------------------------------------------------------------------------
    |
    | Gate abilities guarding the screens, each mapped to the entitlement key that grants it.
    | `view` shows a person's stored choices on the user screen. Remap the key to your own.
    |
    */
    'gates' => [
        'filagora.notifications.view' => 'filagora.notifications.view',
    ],

    /*
    |--------------------------------------------------------------------------
    | Suppression
    |--------------------------------------------------------------------------
    |
    | The default `EmailSuppression` stops optional mail to a person whose `receives_email`
    | attribute is false or whose address status is one of those listed. An attribute the user
    | model does not have never suppresses. Set a name to null to ignore that attribute.
    |
    */
    'suppression' => [
        'receives_email_attribute' => 'receives_email',
        'status_attribute' => 'email_status',
        'suppressed_statuses' => ['bounced', 'complained'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Unsubscribe routes
    |--------------------------------------------------------------------------
    |
    | Off by default. When on, the package serves the signed links a mail carries:
    | `GET {prefix}/unsubscribe/{user}/{category}` (a page) and `POST .../one-click` (for mail
    | clients). They sit outside the web group, so there is no session or CSRF token; the
    | signature is the authority. The default prefix keeps the paths an existing site has sent.
    |
    */
    'routes' => [
        'enabled' => false,
        'prefix' => 'email-preferences',
        'middleware' => ['throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Unsubscribe links
    |--------------------------------------------------------------------------
    |
    | `signed_ttl`: minutes a link stays valid, or null for links that never expire (as on the
    | site the package was extracted from). `view`: the page shown after a link is followed; it
    | receives `label` and `manageUrl`. `manage_url`: where "manage your preferences" goes.
    |
    */
    'unsubscribe' => [
        'signed_ttl' => null,
        'view' => 'filagora-notifications::unsubscribed',
        'manage_url' => null,
    ],

];
