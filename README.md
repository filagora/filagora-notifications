# Filagora Notifications

Notification preferences for Filagora: what each person wants to hear about, on which channel
(email, push, in-app), a registry of the categories packages and hosts add to, email suppression,
signed and one-click unsubscribe links, and bridges that answer the questions the push and forums
packages ask. Requires [`filagora/filagora-core`](https://github.com/filagora/filagora-core).

> The package is at `0.x` and under construction: the API may change between minor versions.
> There is no settings screen: `Preferences::matrix()` and `set()` give your page what it needs.

## What it does

- **Stores choices.** One row per person, category and channel in `user_notification_preferences`
  (`NotificationPreference`). No row means the category's default. The table has the shape of the
  site the package was extracted from, so an existing site can adopt it with `filagora:adopt`.
- More follows: the category registry and `Preferences` service, suppression, unsubscribe links,
  and the bridges to push and forums.

## Install

```bash
composer require filagora/filagora-notifications
php artisan vendor:publish --tag=filagora-notifications-config
```

Register the plugin on a Filament panel (it adds a read-only list of a person's stored choices
to the user resource):

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

## License

MIT.
