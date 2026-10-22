<?php

declare(strict_types=1);

use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Forums\Enums\DeliveryMode;
use Filagora\Forums\Services\Subscriptions;
use Filagora\Messages\Contracts\MessagingPolicy;
use Filagora\Messages\Services\Messages;
use Filagora\Notifications\Bridges\ForumsBridge;
use Filagora\Notifications\Bridges\MessagesBridge;
use Filagora\Notifications\Bridges\PushBridge;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Tests\Support\TestUser;
use Filagora\Push\Contracts\PushPreferences;
use Filagora\Push\Enums\PushCategory;
use Filagora\Push\Services\PushNotifier;

function chooses(TestUser $user, string $category, Channel $channel, bool $enabled): void
{
    app(Preferences::class)->set($user, $category, $channel, $enabled);
}

it('answers each sibling from the bridge once everything has booted', function () {
    expect(app(PushPreferences::class))->toBeInstanceOf(PushBridge::class)
        ->and(app(SubscriptionPreferences::class))->toBeInstanceOf(ForumsBridge::class)
        ->and(app(MessagingPolicy::class))->toBeInstanceOf(MessagesBridge::class);
});

it('hands each sibling\'s service the bridge', function (string $service, string $property, string $bridge) {
    expect((new ReflectionProperty($service, $property))->getValue(app($service)))->toBeInstanceOf($bridge);
})->with([
    'forums subscriptions' => [Subscriptions::class, 'preferences', ForumsBridge::class],
    'push notifier' => [PushNotifier::class, 'preferences', PushBridge::class],
    'messages' => [Messages::class, 'policy', MessagesBridge::class],
]);

it('lets push alerts through by default and stops the kind a person switched off on the push channel', function (PushCategory $category, string $id) {
    $user = TestUser::make();

    expect(app(PushPreferences::class)->allows($user, $category))->toBeTrue();

    chooses($user, $id, Channel::EMAIL, false);
    expect(app(PushPreferences::class)->allows($user, $category))->toBeTrue('an email choice does not stop a push');

    chooses($user, $id, Channel::PUSH, false);
    expect(app(PushPreferences::class)->allows($user, $category))->toBeFalse();
})->with([
    'mentions' => [PushCategory::MENTION, 'forum.mention'],
    'thread updates' => [PushCategory::THREAD_UPDATE, 'forum.thread_updates_instant'],
    'private messages' => [PushCategory::PRIVATE_MESSAGE, 'messages.received'],
]);

it('keeps the other push kinds when one is switched off', function () {
    $user = TestUser::make();
    chooses($user, 'forum.mention', Channel::PUSH, false);

    expect(app(PushPreferences::class)->allows($user, PushCategory::THREAD_UPDATE))->toBeTrue()
        ->and(app(PushPreferences::class)->allows($user, PushCategory::PRIVATE_MESSAGE))->toBeTrue();
});

it('delivers followed-thread updates at once by default', function () {
    expect(app(SubscriptionPreferences::class)->deliveryMode(TestUser::make()))->toBe(DeliveryMode::INSTANT);
});

it('follows the chosen frequency when the matching kind of mail is wanted', function (string $frequency, DeliveryMode $expected) {
    $user = TestUser::make();
    app(Preferences::class)->setMode($user, 'forum.thread_updates_digest_frequency', $frequency);

    expect(app(SubscriptionPreferences::class)->deliveryMode($user))->toBe($expected);
})->with([
    'instant' => ['instant', DeliveryMode::INSTANT],
    'daily' => ['daily', DeliveryMode::DAILY],
    'weekly' => ['weekly', DeliveryMode::WEEKLY],
]);

it('delivers nothing when the kind of mail for the chosen frequency is switched off', function (string $frequency, string $switchedOff, bool $stillOtherKind) {
    $user = TestUser::make();
    app(Preferences::class)->setMode($user, 'forum.thread_updates_digest_frequency', $frequency);
    chooses($user, $switchedOff, Channel::EMAIL, false);

    expect(app(SubscriptionPreferences::class)->deliveryMode($user))->toBe(DeliveryMode::OFF);
})->with([
    'instant mail off' => ['instant', 'forum.thread_updates_instant', true],
    'digest mail off, daily' => ['daily', 'forum.thread_updates_digest', true],
    'digest mail off, weekly' => ['weekly', 'forum.thread_updates_digest', true],
]);

it('is not stopped by the kind of mail the chosen frequency does not use', function () {
    $user = TestUser::make();
    chooses($user, 'forum.thread_updates_digest', Channel::EMAIL, false);

    expect(app(SubscriptionPreferences::class)->deliveryMode($user))->toBe(DeliveryMode::INSTANT);

    app(Preferences::class)->setMode($user, 'forum.thread_updates_digest_frequency', 'daily');
    expect(app(SubscriptionPreferences::class)->deliveryMode($user))->toBe(DeliveryMode::OFF);

    chooses($user, 'forum.thread_updates_digest', Channel::EMAIL, true);
    chooses($user, 'forum.thread_updates_instant', Channel::EMAIL, false);
    expect(app(SubscriptionPreferences::class)->deliveryMode($user))->toBe(DeliveryMode::DAILY);
});

it('queues no thread mail for a person whose email is suppressed', function () {
    $user = TestUser::make()->forceFill(['receives_email' => false]);

    expect(app(SubscriptionPreferences::class)->deliveryMode($user))->toBe(DeliveryMode::OFF);
});

it('leaves following by posting to the user attribute forums reads itself', function () {
    $user = TestUser::make();

    expect(app(SubscriptionPreferences::class)->autoSubscribeOnPost($user))->toBeFalse()
        ->and(app(SubscriptionPreferences::class)->autoSubscribeOnPost($user->forceFill(['auto_subscribe_on_post' => true])))->toBeTrue();
});

it('lets anyone message someone who has not switched messages off', function () {
    expect(app(MessagingPolicy::class)->canMessage(TestUser::make('Sender'), TestUser::make('Recipient')))->toBeTrue();
});

it('refuses to let someone message a person who switched messages off, in either way', function () {
    $sender = TestUser::make('Sender');

    $viaChoice = TestUser::make('Choice');
    chooses($viaChoice, 'messages.private_message_contactability', Channel::EMAIL, false);

    $viaAttribute = TestUser::make('Attribute')->forceFill(['allow_private_messages' => false]);

    expect(app(MessagingPolicy::class)->canMessage($sender, $viaChoice))->toBeFalse()
        ->and(app(MessagingPolicy::class)->canMessage($sender, $viaAttribute))->toBeFalse();
});

it('prefers a stored choice to the user attribute, and the attribute only until a choice exists', function () {
    $user = TestUser::make()->forceFill(['allow_private_messages' => false]);

    expect(app(MessagingPolicy::class)->canMessage(TestUser::make('Sender'), $user))->toBeFalse();

    chooses($user, 'messages.private_message_contactability', Channel::EMAIL, true);

    expect(app(MessagingPolicy::class)->canMessage(TestUser::make('Other'), $user))->toBeTrue();
});

it('does not ask who is sending, only who receives', function () {
    $recipient = TestUser::make('Recipient');
    chooses($recipient, 'messages.private_message_contactability', Channel::EMAIL, false);

    expect(app(MessagingPolicy::class)->canMessage($recipient, $recipient))->toBeFalse();
});
