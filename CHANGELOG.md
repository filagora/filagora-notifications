# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- The `user_notification_preferences` table (one row per person, category and channel), its migration, schema snapshot for `filagora:adopt`, the `NotificationPreference` model, and the `NotificationChannel` and `PreferenceSource` enums.
- The category registry (`NotificationCategories`, `Category`): packages and the host register the kinds of notification; the first registration of an id wins. The seven standard categories are registered under the ids a site stores (`register_standard` switches that off).
- `Preferences`: `allows()`, `mode()`, `set()`, `setMode()` and `matrix()` per person, category and channel, with defaults (on, unless a category says otherwise or a resolver answers per person), one read per person that is forgotten at every queue job, and a `PreferenceChanged` event when a stored value changes.
