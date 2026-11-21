# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.2.0] - 2026-11-21

### Added

- Category kinds. A `Category` takes `kind` (`CategoryKind::USER`, the default and what every existing registration is, `STAFF` or `REQUIRED`) and, for staff, an `access` key. A staff category is offered, stored and delivered only to people who hold that key (`Preferences::isOfferedTo()`, checked through core's `AccessChecker`); a required category is always on, ignores email suppression, cannot be switched off (`InvalidPreference::required()`), and cannot also be a mode or unsubscribable.
- `Preferences::matrix()` leaves out staff categories the person does not hold the key for, and each row has `locked` (true for a required category). Existing keys are unchanged.
- `Recipients` and the `StaffAudience` contract: who to notify about a staff category. The host says who holds a key (access is answered one person at a time, so nothing else can list them); `Recipients::for()` and `byChannel()` keep only the people who want it on each channel, so a person who opted out never gets it. The default `NoStaffAudience` returns nobody.
- Channels were already independent per category; a test now pins that any combination of email, push and bell can be on, and that all three off means nothing is delivered.

## [0.1.3] - 2026-11-14

### Changed

- The licence names Ian Wilson as the copyright holder. No code changes.

## [0.1.2] - 2026-11-13

### Changed

- The README's What it provides section has the same sub-sections as every other Filagora package (Overview, Tables, Commands, Events, Access, Using it). No code changes.

## [0.1.1] - 2026-11-13

### Changed

- The README follows the layout shared by every Filagora package (What it provides, Requirements, Install, Configuration, Extending, Testing, Local development, Licence), so the docs page built from it reads the same as the others. No code changes.

## [0.1.0] - 2026-10-22

First release.

### Added

- The `user_notification_preferences` table (one row per person, category and channel), its migration, schema snapshot for `filagora:adopt`, the `NotificationPreference` model, and the `NotificationChannel` and `PreferenceSource` enums.
- The category registry (`NotificationCategories`, `Category`): packages and the host register the kinds of notification; the first registration of an id wins. The seven standard categories are registered under the ids a site stores (`register_standard` switches that off).
- `Preferences`: `allows()`, `mode()`, `set()`, `setMode()` and `matrix()` per person, category and channel, with defaults (on, unless a category says otherwise or a resolver answers per person), one read per person that is forgotten at every queue job, and a `PreferenceChanged` event when a stored value changes.
- Email suppression: the `EmailSuppression` contract (the default `AttributeSuppression` reads `receives_email` and `email_status` of the user model, configurable, and never suppresses a model that lacks them), `Preferences::isSuppressed()` and `canDeliver()` (the choice and, for email, no suppression).
- Signed unsubscribe links: `Unsubscribe::url()`, `oneClickUrl()`, `decorate()` (adds `List-Unsubscribe` and `List-Unsubscribe-Post` to a mail) and `apply()`, for categories registered as unsubscribable. Optional routes (`routes.enabled`, off by default) serve the confirming page and the one-click POST outside the web group, on the same paths an existing site has already mailed (`email-preferences/unsubscribe/{user}/{category}`), with an optional link lifetime (`unsubscribe.signed_ttl`), a replaceable page (`unsubscribe.view`) and a manage-preferences link (`unsubscribe.manage_url`).
- Bridges to the sibling packages, bound once every provider has booted and only while the sibling's own default is still the bound one (a host's own binding always wins; `bridges.push`, `.forums` and `.messages` switch each off): `PushBridge` answers `filagora-push`'s `PushPreferences` from the push channel, `ForumsBridge` answers `filagora-forums`' `SubscriptionPreferences` (the frequency and the matching kind of mail; nothing for a suppressed person; following by posting stays a user attribute), and `MessagesBridge` is a `MessagingPolicy` that refuses a recipient who switched private messages off.
- The private-message contactability default reads the user model's `allow_private_messages` (configurable), on when missing, until a person chooses.
- A read-only relation manager for the user screen (`UserNotificationPreferencesRelationManager`, with the `HasNotificationPreferences` trait for the user model): the choices a person has stored, by category label and channel name, filterable by channel and source, shown to people holding the `filagora.notifications.view` ability (`gates` config; declared in the entitlement definitions when `filagora-entitlements` is installed).
- A privacy contributor (`notifications`): the export lists a person's stored choices; erasure removes them and keeps, without the link to the erased staff member, choices that member changed for others. There is no retention rule.

### Differences from the site this was extracted from

- Categories are a registry, not a fixed enum: the pushes and the bell are derived from which channels a category supports instead of hard-coded lists. The stored ids are unchanged.
- Asking about an unregistered category, a channel it does not support, or a switch as a mode (or the reverse) throws `InvalidPreference`; the site answered "on" for an unknown id.
- A suppressed person (`receives_email` false, or a bounced or complained address) is stopped by `EmailSuppression`; the site's extra rule (an entitlement that the inactive-subscriber command revokes) is left to the host, which binds its own `EmailSuppression`.
- Unsubscribe links keep the site's paths but sit outside the web group, and are off until `routes.enabled` is set.
- The digest frequency is read from its preference row only; the site also kept it in a user column, which the host keeps in sync from `PreferenceChanged`.
- Choices are not audited (the site recorded an audit entry for each change); the audit log is a separate, future package.
