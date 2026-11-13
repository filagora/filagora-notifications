# Filagora Notifications

Notification preferences for Filagora: what each person wants to hear about, on which channel
(email, push, in-app), a registry of the categories packages and hosts add to, email suppression,
signed and one-click unsubscribe links, and bridges that answer the questions the push and forums
packages ask. Requires [`filagora/filagora-core`](https://github.com/filagora/filagora-core).

> The package is at `0.1`: the API may change between `0.x` minor versions.
> There is no settings screen: `Preferences::matrix()` and `set()` give your page what it needs.

## What it provides

### Overview

Notification preferences for Filagora: per-person, per-category, per-channel choices, a category registry, suppression, signed unsubscribe, and bridges to push and forums.

### Tables

`user_notification_preferences`, prefixed `filagora_` by default. A site that already has them can run `php artisan filagora:adopt` instead of migrating.

### Commands

This package adds no commands.

### Events

- `PreferenceChanged`: A stored choice was written with a different value.

### Access

| Ability | Entitlement key |
| --- | --- |
| `filagora.notifications.view` | `filagora.notifications.view` |

### Using it

- **Stores choices.** One row per person, category and channel in `user_notification_preferences`
  (`NotificationPreference`). No row means the category's default. The table has the shape of the
  site the package was extracted from, so an existing site can adopt it with `filagora:adopt`.
- **Registers categories.** `NotificationCategories::register(new Category(...))` adds a kind of
  notification: its id (stored for good, so never rename one), a label, the channels it can reach,
  per-channel defaults, whether a mailed link may switch it off, or a set of modes (such as
  `instant`, `daily`, `weekly`). The first registration of an id wins, so a host's own beats a
  package's. The mentions, private messages, thread updates, digest, moderation and
  private-message-contactability categories are registered for you (`register_standard`).
- **Answers and records choices.** `Preferences::allows($user, 'forum.mention', NotificationChannel::PUSH)`,
  `mode()`, `set()`, `setMode()`, and `matrix($user)` for a settings page. A category is on unless it
  says otherwise (or its `defaultResolver` answers per person). A person's rows are read once per
  instance and forgotten at every queue job. `PreferenceChanged` fires when a stored value changes.
- **Suppresses mail.** `Preferences::canDeliver($user, 'forum.mention')` is the one question to ask
  before optional mail, a push or a bell entry: the person's choice, and for email no suppression.
  `EmailSuppression` decides suppression; the default stops a person whose `receives_email` is
  false or whose `email_status` is `bounced` or `complained` (names in config, and a model without
  those attributes is never suppressed). Bind your own to add rules. Required mail (verification,
  password reset, security notices) does not ask.
- **Unsubscribes by signed link.** `Unsubscribe::url()` and `oneClickUrl()` build the links,
  `decorate($mailMessage, $user, $category)` adds the `List-Unsubscribe` headers, and only a
  category registered with `unsubscribable: true` can be switched off this way, on email only. Turn
  on `routes.enabled` to serve the page and the one-click POST; they live outside the web group (no
  session or CSRF token; the signature decides) and keep the path `email-preferences/unsubscribe/{user}/{category}`,
  so links a site has already mailed keep working. Replace the page with `unsubscribe.view`.
- **Bridges the siblings.** With `filagora-push`, `filagora-forums` and `filagora-messages`
  installed, their preference questions are answered from the person's choices: which push alerts
  (push channel), how and whether followed-thread updates arrive (the frequency and the matching
  kind of mail; nothing for a suppressed person), and whether a person can be sent private messages.
  A bridge applies only while the sibling's own default is still bound, so your own binding wins,
  and `bridges.push|forums|messages` switch each off. Whether posting follows a thread stays a user
  attribute, which forums reads itself.

- **Shows staff what a person chose.** Add the `HasNotificationPreferences` trait to your user
  model and `UserNotificationPreferencesRelationManager::class` to your user resource's relations.
  It lists the stored choices, read-only (a category with no row is on its default), for people
  holding `filagora.notifications.view`; remap that key in the `gates` config.
- **Exports and erases.** The privacy contributor adds a person's stored choices to their data
  export and removes them on erasure.

## Requirements

PHP 8.4, Laravel 13 and Filament 5. Tested against MariaDB 11.4. Requires [`filagora/filagora-core`](https://github.com/filagora/filagora-core).

## Install

```bash
composer require filagora/filagora-notifications
php artisan vendor:publish --tag=filagora-notifications-config
```

Register the plugin on a Filament panel (it registers nothing of its own yet; the relation manager
above is added to your own user resource):

```php
->plugin(\Filagora\Notifications\NotificationsPlugin::make())
```

## Configuration

`config/filagora-notifications.php` is merged with the host's copy, so list only what differs.
Environment variables are prefixed `FILAGORA_`.

## Extending

Extend through documented contracts, events and registries. Anything not documented is
`@internal`.

## Testing

```bash
composer test
```

Tests run against a real MariaDB, never SQLite, so engine behaviour (FULLTEXT,
`ONLY_FULL_GROUP_BY`, collation) matches production. Host and credentials come from
your environment; the database is always named `testing`, never your development one.

## Local development (DDEV)

`.ddev/` is included. `ddev start` creates both `db` and `testing` (run `ddev restart` once
if `testing` is missing), then `ddev exec vendor/bin/pest`.

## Licence

MIT. See [LICENSE](LICENSE).
