<?php

declare(strict_types=1);

use Filagora\Notifications\Category;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\NotificationCategories;
use Filagora\Notifications\NotificationsServiceProvider;
use Filagora\Notifications\StandardCategories;
use Filagora\Notifications\Tests\Support\TestUser;

it('registers the standard categories under the ids a site stores', function () {
    expect(array_keys(app(NotificationCategories::class)->all()))->toBe([
        'messages.received',
        'forum.mention',
        'forum.thread_updates_instant',
        'forum.thread_updates_digest',
        'forum.thread_updates_digest_frequency',
        'moderation.own_content',
        'messages.private_message_contactability',
    ]);
});

it('offers push and the bell for the five deliverable categories only', function () {
    $ids = fn (Channel $channel) => array_map(fn (Category $category) => $category->id, app(NotificationCategories::class)->forChannel($channel));
    $deliverable = ['messages.received', 'forum.mention', 'forum.thread_updates_instant', 'moderation.own_content'];

    expect($ids(Channel::PUSH))->toBe($deliverable)
        ->and($ids(Channel::IN_APP))->toBe($deliverable)
        ->and($ids(Channel::EMAIL))->toContain('forum.thread_updates_digest', 'messages.private_message_contactability');
});

it('lets a signed link switch off only the thread update categories', function () {
    expect(app(NotificationCategories::class)->unsubscribable())->toBe(['forum.thread_updates_instant', 'forum.thread_updates_digest']);
});

it('keeps the first registration of an id, so a host that registered its own wins', function () {
    $categories = new NotificationCategories;
    $mine = new Category('forum.mention', 'My mentions', [Channel::EMAIL]);

    expect($categories->register($mine))->toBeTrue();

    StandardCategories::register($categories);

    expect($categories->get('forum.mention'))->toBe($mine)
        ->and($categories->register(new Category('forum.mention', 'Again')))->toBeFalse();
});

it('refuses an id nobody registered', function () {
    expect(fn () => app(NotificationCategories::class)->get('no.such'))->toThrow(InvalidPreference::class, 'No notification category [no.such]');
});

it('does not register the standard categories when switched off', function () {
    config(['filagora-notifications.register_standard' => false]);
    $this->app->forgetInstance(NotificationCategories::class);
    $this->app->getProvider(NotificationsServiceProvider::class)->boot();

    expect(app(NotificationCategories::class)->all())->toBe([]);
});

it('reads its label from the language file, or uses plain text', function () {
    expect(app(NotificationCategories::class)->get('forum.mention')->label())->toBe('Mentions')
        ->and((new Category('x.y', 'Plain words'))->label())->toBe('Plain words');
});

it('answers a default per person when a resolver is given, and never for a channel it does not support', function () {
    $user = TestUser::make();
    $category = new Category('x.y', 'X', [Channel::EMAIL, Channel::PUSH], defaultResolver: fn ($user, Channel $channel) => $channel === Channel::PUSH);

    expect($category->defaultFor($user, Channel::PUSH))->toBeTrue()
        ->and($category->defaultFor($user, Channel::EMAIL))->toBeFalse()
        ->and($category->defaultFor($user, Channel::IN_APP))->toBeFalse();

    $plain = new Category('x.z', 'Z', [Channel::EMAIL]);

    expect($plain->defaultFor($user, Channel::EMAIL))->toBeTrue()
        ->and($plain->defaultFor($user, Channel::PUSH))->toBeFalse();
});
