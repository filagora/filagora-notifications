# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- The `user_notification_preferences` table (one row per person, category and channel), its migration, schema snapshot for `filagora:adopt`, the `NotificationPreference` model, and the `NotificationChannel` and `PreferenceSource` enums.
- The category registry (`NotificationCategories`, `Category`): packages and the host register the kinds of notification; the first registration of an id wins. The seven standard categories are registered under the ids a site stores (`register_standard` switches that off).
- `Preferences`: `allows()`, `mode()`, `set()`, `setMode()` and `matrix()` per person, category and channel, with defaults (on, unless a category says otherwise or a resolver answers per person), one read per person that is forgotten at every queue job, and a `PreferenceChanged` event when a stored value changes.
- Email suppression: the `EmailSuppression` contract (the default `AttributeSuppression` reads `receives_email` and `email_status` of the user model, configurable, and never suppresses a model that lacks them), `Preferences::isSuppressed()` and `canDeliver()` (the choice and, for email, no suppression).
- Signed unsubscribe links: `Unsubscribe::url()`, `oneClickUrl()`, `decorate()` (adds `List-Unsubscribe` and `List-Unsubscribe-Post` to a mail) and `apply()`, for categories registered as unsubscribable. Optional routes (`routes.enabled`, off by default) serve the confirming page and the one-click POST outside the web group, on the same paths an existing site has already mailed (`email-preferences/unsubscribe/{user}/{category}`), with an optional link lifetime (`unsubscribe.signed_ttl`), a replaceable page (`unsubscribe.view`) and a manage-preferences link (`unsubscribe.manage_url`).
- Bridges to the sibling packages, bound once every provider has booted and only while the sibling's own default is still the bound one (a host's own binding always wins; `bridges.push`, `.forums` and `.messages` switch each off): `PushBridge` answers `filagora-push`'s `PushPreferences` from the push channel, `ForumsBridge` answers `filagora-forums`' `SubscriptionPreferences` (the frequency and the matching kind of mail; nothing for a suppressed person; following by posting stays a user attribute), and `MessagesBridge` is a `MessagingPolicy` that refuses a recipient who switched private messages off.
- The private-message contactability default reads the user model's `allow_private_messages` (configurable), on when missing, until a person chooses.
