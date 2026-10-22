<?php

declare(strict_types=1);

use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\Tests\Support\TestUser;
use Illuminate\Database\QueryException;

it('stores a choice with the email channel and the user source by default', function () {
    $user = TestUser::make();

    $preference = NotificationPreference::create(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'value' => '0'])->refresh();

    expect($preference->channel)->toBe('email')
        ->and($preference->source)->toBe('user')
        ->and($preference->user->is($user))->toBeTrue()
        ->and($preference->getTable())->toBe('filagora_user_notification_preferences');
});

it('keeps one row per person, category and channel', function () {
    $user = TestUser::make();
    NotificationPreference::create(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'channel' => 'push', 'value' => '1']);

    expect(fn () => NotificationPreference::create(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'channel' => 'push', 'value' => '0']))
        ->toThrow(QueryException::class);

    NotificationPreference::create(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'channel' => 'email', 'value' => '0']);

    expect(NotificationPreference::count())->toBe(2);
});

it('removes a person\'s choices with the person, and forgets who changed them when they go', function () {
    $user = TestUser::make('Member');
    $staff = TestUser::make('Staff');
    $preference = NotificationPreference::create(['user_id' => $user->id, 'preference_key' => 'forum.mention', 'value' => '0', 'source' => 'admin', 'updated_by_user_id' => $staff->id]);

    $staff->delete();
    expect($preference->refresh()->updated_by_user_id)->toBeNull();

    $user->delete();
    expect(NotificationPreference::count())->toBe(0);
});
