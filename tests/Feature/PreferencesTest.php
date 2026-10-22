<?php

declare(strict_types=1);

use Filagora\Notifications\Category;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Enums\PreferenceSource;
use Filagora\Notifications\Events\PreferenceChanged;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\NotificationCategories;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Tests\Support\TestUser;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

function preferences(): Preferences
{
    return app(Preferences::class);
}

it('is on by default for every channel a category supports, and off for one it does not', function () {
    $user = TestUser::make();

    expect(preferences()->allows($user, 'forum.mention'))->toBeTrue()
        ->and(preferences()->allows($user, 'forum.mention', Channel::PUSH))->toBeTrue()
        ->and(preferences()->allows($user, 'forum.mention', Channel::IN_APP))->toBeTrue()
        ->and(preferences()->allows($user, 'forum.thread_updates_digest', Channel::PUSH))->toBeFalse()
        ->and(preferences()->allows($user, 'messages.private_message_contactability', Channel::IN_APP))->toBeFalse();
});

it('stores a choice per channel without touching the others', function () {
    $user = TestUser::make();

    preferences()->set($user, 'forum.mention', Channel::PUSH, false);

    expect(preferences()->allows($user, 'forum.mention', Channel::PUSH))->toBeFalse()
        ->and(preferences()->allows($user, 'forum.mention'))->toBeTrue()
        ->and(NotificationPreference::where('user_id', $user->id)->sole()->only(['preference_key', 'channel', 'value', 'source', 'updated_by_user_id']))
        ->toBe(['preference_key' => 'forum.mention', 'channel' => 'push', 'value' => '0', 'source' => 'user', 'updated_by_user_id' => null]);
});

it('updates the one row for a person, category and channel instead of adding another', function () {
    $user = TestUser::make();

    preferences()->set($user, 'forum.mention', Channel::EMAIL, false);
    preferences()->set($user, 'forum.mention', Channel::EMAIL, true);

    expect(NotificationPreference::count())->toBe(1)
        ->and(preferences()->allows($user, 'forum.mention'))->toBeTrue();
});

it('records who changed it and why', function () {
    $user = TestUser::make('Member');
    $staff = TestUser::make('Staff');

    preferences()->set($user, 'forum.mention', Channel::EMAIL, false, $staff, PreferenceSource::ADMIN);
    preferences()->set($user, 'forum.mention', Channel::PUSH, false, source: 'import');

    $rows = NotificationPreference::orderBy('id')->get();

    expect($rows[0]->source)->toBe('admin')->and($rows[0]->updated_by_user_id)->toBe($staff->id)
        ->and($rows[1]->source)->toBe('import')->and($rows[1]->updated_by_user_id)->toBeNull();
});

it('reads the standard contactability default from the category resolver, and a stored answer over it', function () {
    $categories = new NotificationCategories;
    $categories->register(new Category(
        'messages.private_message_contactability',
        'Contactable',
        defaultResolver: fn ($user) => $user->name === 'Open',
    ));
    $service = new Preferences($categories);
    $open = TestUser::make('Open');
    $closed = TestUser::make('Closed');

    expect($service->allows($open, 'messages.private_message_contactability'))->toBeTrue()
        ->and($service->allows($closed, 'messages.private_message_contactability'))->toBeFalse();

    $service->set($closed, 'messages.private_message_contactability', Channel::EMAIL, true);

    expect($service->allows($closed, 'messages.private_message_contactability'))->toBeTrue();
});

it('reads the words people have stored for a switch, and falls back to the default for any other', function () {
    $user = TestUser::make();

    foreach ([['yes', true], ['off', false], [' TRUE ', true], ['maybe', true], ['', true]] as [$value, $expected]) {
        NotificationPreference::updateOrCreate(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'channel' => 'email'], ['value' => $value]);
        preferences()->flush();

        expect(preferences()->allows($user, 'forum.mention'))->toBe($expected, "stored [{$value}]");
    }
});

it('treats a row with no value as the default', function () {
    $user = TestUser::make();
    NotificationPreference::create(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'channel' => 'email', 'value' => null]);

    expect(preferences()->allows($user, 'forum.mention'))->toBeTrue();
});

it('holds a mode for the digest frequency, defaulting to instant', function () {
    $user = TestUser::make();

    expect(preferences()->mode($user, 'forum.thread_updates_digest_frequency'))->toBe('instant');

    preferences()->setMode($user, 'forum.thread_updates_digest_frequency', 'weekly');

    expect(preferences()->mode($user, 'forum.thread_updates_digest_frequency'))->toBe('weekly');

    NotificationPreference::where('user_id', $user->id)->update(['value' => 'hourly']);
    preferences()->flush();

    expect(preferences()->mode($user, 'forum.thread_updates_digest_frequency'))->toBe('instant');
});

it('refuses what the categories do not allow', function (Closure $attempt, string $message) {
    expect($attempt)->toThrow(InvalidPreference::class, $message);
})->with([
    'a switch on an unsupported channel' => [fn () => preferences()->set(TestUser::make(), 'forum.thread_updates_digest', Channel::PUSH, true), 'cannot be delivered on the [push] channel'],
    'a mode word that is not one' => [fn () => preferences()->setMode(TestUser::make(), 'forum.thread_updates_digest_frequency', 'hourly'), '[hourly] is not a mode'],
    'a mode set as a switch' => [fn () => preferences()->set(TestUser::make(), 'forum.thread_updates_digest_frequency', Channel::EMAIL, true), 'holds a mode, not a switch'],
    'a switch set as a mode' => [fn () => preferences()->setMode(TestUser::make(), 'forum.mention', 'daily'), 'is a switch, not a mode'],
    'a mode asked as a switch' => [fn () => preferences()->allows(TestUser::make(), 'forum.thread_updates_digest_frequency'), 'holds a mode, not a switch'],
    'a switch asked as a mode' => [fn () => preferences()->mode(TestUser::make(), 'forum.mention'), 'is a switch, not a mode'],
    'an unknown category' => [fn () => preferences()->allows(TestUser::make(), 'no.such'), 'No notification category [no.such]'],
]);

it('writes nothing when a refusal comes first', function () {
    expect(fn () => preferences()->set(TestUser::make(), 'forum.thread_updates_digest', Channel::PUSH, true))->toThrow(InvalidPreference::class)
        ->and(NotificationPreference::count())->toBe(0);
});

it('fires an event only when the stored value changes, with the old value', function () {
    Event::fake([PreferenceChanged::class]);
    $user = TestUser::make();
    $staff = TestUser::make('Staff');

    preferences()->set($user, 'forum.mention', Channel::EMAIL, false, $staff, PreferenceSource::ADMIN);
    preferences()->set($user, 'forum.mention', Channel::EMAIL, false);
    preferences()->set($user, 'forum.mention', Channel::EMAIL, true);

    Event::assertDispatchedTimes(PreferenceChanged::class, 2);
    Event::assertDispatched(PreferenceChanged::class, fn (PreferenceChanged $event) => $event->userId === $user->id
        && $event->category === 'forum.mention'
        && $event->channel === Channel::EMAIL
        && $event->old === null && $event->new === '0'
        && $event->source === 'admin' && $event->updatedById === $staff->id);
    Event::assertDispatched(PreferenceChanged::class, fn (PreferenceChanged $event) => $event->old === '0' && $event->new === '1');
});

it('reads a person once for several questions, and forgets at a job boundary', function () {
    $user = TestUser::make();
    preferences()->allows($user, 'forum.mention');

    NotificationPreference::create(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'channel' => 'email', 'value' => '0']);

    expect(preferences()->allows($user, 'forum.mention'))->toBeTrue('still the remembered answer');

    event(new JobProcessing('sync', Mockery::mock(Job::class, ['payload' => []])));

    expect(preferences()->allows($user, 'forum.mention'))->toBeFalse();
});

it('does not serve one person another person\'s answer', function () {
    $a = TestUser::make('A');
    $b = TestUser::make('B');

    preferences()->set($a, 'forum.mention', Channel::EMAIL, false);

    expect(preferences()->allows($a, 'forum.mention'))->toBeFalse()
        ->and(preferences()->allows($b, 'forum.mention'))->toBeTrue();
});

it('builds the matrix a settings page draws', function () {
    $user = TestUser::make();
    preferences()->set($user, 'forum.mention', Channel::PUSH, false);
    preferences()->setMode($user, 'forum.thread_updates_digest_frequency', 'daily');

    $matrix = collect(preferences()->matrix($user))->keyBy(fn ($row) => $row['category']->id);

    expect($matrix->keys()->all())->toBe(array_keys(app(NotificationCategories::class)->all()))
        ->and($matrix['forum.mention']['channels'])->toBe(['email' => true, 'push' => false, 'in_app' => true])
        ->and($matrix['forum.mention']['mode'])->toBeNull()
        ->and($matrix['forum.thread_updates_digest']['channels'])->toBe(['email' => true])
        ->and($matrix['forum.thread_updates_digest_frequency'])->toMatchArray(['channels' => [], 'mode' => 'daily']);
});
