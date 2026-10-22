<?php

declare(strict_types=1);

use Filagora\Core\Privacy\PrivacyContributorRegistry;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Enums\PreferenceSource;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Tests\Support\TestUser;

beforeEach(function () {
    $this->contributor = app(PrivacyContributorRegistry::class)->all()['notifications'];
    $this->travelTo(now()->setTime(12, 0));
});

it('exports a person\'s stored choices and nobody else\'s', function () {
    $alice = TestUser::make('Alice');
    $bob = TestUser::make('Bob');
    $staff = TestUser::make('Staff');
    app(Preferences::class)->set($alice, 'forum.mention', Channel::PUSH, false, $staff, PreferenceSource::ADMIN);
    app(Preferences::class)->set($alice, 'forum.thread_updates_digest', Channel::EMAIL, true);
    app(Preferences::class)->set($bob, 'forum.mention', Channel::PUSH, false);

    $export = $this->contributor->export($alice);

    expect($export['preferences'])->toHaveCount(2)
        ->and($export['preferences'][0])->toBe([
            'category' => 'forum.mention',
            'channel' => 'push',
            'value' => '0',
            'source' => 'admin',
            'changed_by_user_id' => $staff->id,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ])
        ->and($export['preferences'][1]['category'])->toBe('forum.thread_updates_digest')
        ->and($export['preferences'][1]['changed_by_user_id'])->toBeNull();
});

it('exports nothing for a person with no stored choices', function () {
    expect($this->contributor->export(TestUser::make()))->toBe(['preferences' => []]);
});

it('erases a person\'s choices and leaves other people\'s', function () {
    $alice = TestUser::make('Alice');
    $bob = TestUser::make('Bob');
    app(Preferences::class)->set($alice, 'forum.mention', Channel::PUSH, false);
    app(Preferences::class)->set($bob, 'forum.mention', Channel::PUSH, false);

    $this->contributor->erase($alice);

    expect(NotificationPreference::where('user_id', $alice->id)->count())->toBe(0)
        ->and(NotificationPreference::where('user_id', $bob->id)->count())->toBe(1);
});

it('keeps a choice staff made for someone else but forgets who made it, when the staff member is erased', function () {
    $member = TestUser::make('Member');
    $staff = TestUser::make('Staff');
    app(Preferences::class)->set($member, 'forum.mention', Channel::EMAIL, false, $staff, PreferenceSource::ADMIN);

    $this->contributor->erase($staff);

    $row = NotificationPreference::sole();

    expect($row->user_id)->toBe($member->id)
        ->and($row->value)->toBe('0')
        ->and($row->updated_by_user_id)->toBeNull();
});
