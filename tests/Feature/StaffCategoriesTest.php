<?php

declare(strict_types=1);

use Filagora\Core\Contracts\AccessChecker;
use Filagora\Notifications\Category;
use Filagora\Notifications\Contracts\EmailSuppression;
use Filagora\Notifications\Contracts\StaffAudience;
use Filagora\Notifications\Enums\CategoryKind;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\NotificationCategories;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Recipients;
use Filagora\Notifications\Support\NoStaffAudience;
use Filagora\Notifications\Tests\Support\TestUser;
use Illuminate\Contracts\Auth\Authenticatable;

const ALL_CHANNELS = [Channel::EMAIL, Channel::PUSH, Channel::IN_APP];

/**
 * Binds an access checker that grants each key to the listed user ids, and a staff audience that
 * lists them, then rebuilds the services that captured the old ones.
 *
 * @param  array<string, list<int>>  $holders  access key => user ids
 */
function staffHolders(array $holders): void
{
    app()->instance(AccessChecker::class, new class($holders) implements AccessChecker
    {
        /** @param array<string, list<int>> $holders */
        public function __construct(private array $holders) {}

        public function allows(?Authenticatable $user, string $key): bool
        {
            return $user !== null && in_array($user->getAuthIdentifier(), $this->holders[$key] ?? [], true);
        }

        public function allowsAny(?Authenticatable $user, array $keys): bool
        {
            return array_any($keys, fn (string $key): bool => $this->allows($user, $key));
        }

        public function allowsAll(?Authenticatable $user, array $keys): bool
        {
            return array_all($keys, fn (string $key): bool => $this->allows($user, $key));
        }
    });

    app()->instance(StaffAudience::class, new class($holders) implements StaffAudience
    {
        /** @param array<string, list<int>> $holders */
        public function __construct(private array $holders) {}

        public function holding(string $access): iterable
        {
            return TestUser::query()->whereIn('id', $this->holders[$access] ?? [])->orderBy('id')->get();
        }
    });

    app()->forgetInstance(Preferences::class);
    app()->forgetInstance(Recipients::class);
}

/**
 * Suppresses the mail of the listed user ids, as a bounced address would be.
 *
 * @param  list<int>  $ids
 */
function suppressMailTo(array $ids): void
{
    app()->instance(EmailSuppression::class, new class($ids) implements EmailSuppression
    {
        /** @param list<int> $ids */
        public function __construct(private array $ids) {}

        public function isSuppressed(Authenticatable $user): bool
        {
            return in_array($user->getAuthIdentifier(), $this->ids, true);
        }
    });

    app()->forgetInstance(Preferences::class);
    app()->forgetInstance(Recipients::class);
}

function staffCategory(string $id = 'plugins.moderation', string $access = 'operator.plugin.moderate'): Category
{
    $category = new Category($id, 'Plugin moderation', ALL_CHANNELS, kind: CategoryKind::STAFF, access: $access);
    app(NotificationCategories::class)->register($category);

    return $category;
}

// ------------------------------------------------------------------ the category

it('needs an access key to be a staff category', function () {
    new Category('plugins.moderation', 'x', kind: CategoryKind::STAFF);
})->throws(InvalidArgumentException::class, 'needs an access key');

it('cannot be required and also a mode or unsubscribable', function (array $extra) {
    new Category('security.notice', 'x', ...[...$extra, 'kind' => CategoryKind::REQUIRED]);
})->with([
    'a mode' => [['modes' => ['a', 'b']]],
    'unsubscribable' => [['unsubscribable' => true]],
])->throws(InvalidArgumentException::class, 'required category');

it('is a user category unless it says otherwise, so existing registrations are unchanged', function () {
    $category = new Category('x.y', 'x');

    expect($category->kind)->toBe(CategoryKind::USER)
        ->and($category->isStaff())->toBeFalse()
        ->and($category->isRequired())->toBeFalse()
        ->and($category->access)->toBeNull();
});

// ------------------------------------------------------------------ who is offered it

it('offers a staff category only to people who hold its key', function () {
    $moderator = TestUser::make('Moderator');
    $member = TestUser::make('Member');
    staffHolders(['operator.plugin.moderate' => [$moderator->id]]);
    $category = staffCategory();
    $preferences = app(Preferences::class);

    expect($preferences->isOfferedTo($moderator, $category))->toBeTrue()
        ->and($preferences->isOfferedTo($member, $category))->toBeFalse()
        ->and($preferences->allows($moderator, $category))->toBeTrue()
        ->and($preferences->allows($member, $category))->toBeFalse()
        ->and($preferences->canDeliver($member, $category, Channel::IN_APP))->toBeFalse()
        ->and(array_map(fn (array $row) => $row['category']->id, $preferences->matrix($moderator)))->toContain('plugins.moderation')
        ->and(array_map(fn (array $row) => $row['category']->id, $preferences->matrix($member)))->not->toContain('plugins.moderation');
});

it('refuses to store a choice about a staff category for someone who is not offered it', function () {
    $member = TestUser::make('Member');
    staffHolders([]);
    staffCategory();

    app(Preferences::class)->set($member, 'plugins.moderation', Channel::EMAIL, false);
})->throws(InvalidPreference::class, 'not offered');

it('lets a moderator choose each channel of a staff category independently', function () {
    $moderator = TestUser::make('Moderator');
    staffHolders(['operator.plugin.moderate' => [$moderator->id]]);
    staffCategory();
    $preferences = app(Preferences::class);

    $preferences->set($moderator, 'plugins.moderation', Channel::EMAIL, false);

    expect($preferences->allows($moderator, 'plugins.moderation', Channel::EMAIL))->toBeFalse()
        ->and($preferences->allows($moderator, 'plugins.moderation', Channel::IN_APP))->toBeTrue()
        ->and($preferences->allows($moderator, 'plugins.moderation', Channel::PUSH))->toBeTrue();
});

// ------------------------------------------------------------------ additive channels, and none

it('lets any combination of channels be on, and none mean none', function () {
    $user = TestUser::make();
    $preferences = app(Preferences::class);

    $preferences->set($user, 'forum.mention', Channel::EMAIL, false);
    expect($preferences->canDeliver($user, 'forum.mention', Channel::IN_APP))->toBeTrue()
        ->and($preferences->canDeliver($user, 'forum.mention', Channel::PUSH))->toBeTrue();

    $preferences->set($user, 'forum.mention', Channel::PUSH, false);
    $preferences->set($user, 'forum.mention', Channel::IN_APP, false);

    foreach (ALL_CHANNELS as $channel) {
        expect($preferences->canDeliver($user, 'forum.mention', $channel))->toBeFalse();
    }
});

// ------------------------------------------------------------------ required

it('always delivers a required category, even to a suppressed address, and offers it as locked', function () {
    app(NotificationCategories::class)->register(new Category('security.notice', 'Security notices', [Channel::EMAIL], kind: CategoryKind::REQUIRED));
    $user = TestUser::make();
    suppressMailTo([$user->id]);
    $preferences = app(Preferences::class);
    $row = collect($preferences->matrix($user))->first(fn (array $row) => $row['category']->id === 'security.notice');

    expect($preferences->isSuppressed($user))->toBeTrue()
        ->and($preferences->allows($user, 'security.notice'))->toBeTrue()
        ->and($preferences->canDeliver($user, 'security.notice'))->toBeTrue()
        ->and($preferences->canDeliver($user, 'security.notice', Channel::PUSH))->toBeFalse()
        ->and($row['locked'])->toBeTrue()
        ->and($row['channels'])->toBe(['email' => true]);
});

it('refuses to switch a required category off', function () {
    app(NotificationCategories::class)->register(new Category('security.notice', 'Security notices', [Channel::EMAIL], kind: CategoryKind::REQUIRED));

    app(Preferences::class)->set(TestUser::make(), 'security.notice', Channel::EMAIL, false);
})->throws(InvalidPreference::class, 'required');

it('does not lock the ordinary categories', function () {
    $user = TestUser::make();

    foreach (app(Preferences::class)->matrix($user) as $row) {
        expect($row['locked'])->toBeFalse();
    }
});

// ------------------------------------------------------------------ who to notify

it('has no recipients until the host says who holds a key', function () {
    expect(app(StaffAudience::class))->toBeInstanceOf(NoStaffAudience::class);

    staffCategory();

    expect(app(Recipients::class)->for('plugins.moderation'))->toBe([]);
});

it('notifies each holder on the channels they want, and leaves out anyone who opted out', function () {
    [$on, $emailOff, $allOff] = [TestUser::make('On'), TestUser::make('EmailOff'), TestUser::make('AllOff')];
    staffHolders(['operator.plugin.moderate' => [$on->id, $emailOff->id, $allOff->id]]);
    staffCategory();
    $preferences = app(Preferences::class);
    $preferences->set($emailOff, 'plugins.moderation', Channel::EMAIL, false);
    foreach (ALL_CHANNELS as $channel) {
        $preferences->set($allOff, 'plugins.moderation', $channel, false);
    }

    $ids = fn (array $people) => array_map(fn ($person) => $person->id, $people);
    $recipients = app(Recipients::class);

    expect($ids($recipients->for('plugins.moderation', Channel::EMAIL)))->toBe([$on->id])
        ->and($ids($recipients->for('plugins.moderation', Channel::IN_APP)))->toBe([$on->id, $emailOff->id])
        ->and(array_map($ids, $recipients->byChannel('plugins.moderation')))->toBe([
            'email' => [$on->id],
            'push' => [$on->id, $emailOff->id],
            'in_app' => [$on->id, $emailOff->id],
        ]);
});

it('leaves a suppressed address out of email but still reaches it on the bell', function () {
    $moderator = TestUser::make('Moderator');
    staffHolders(['operator.plugin.moderate' => [$moderator->id]]);
    suppressMailTo([$moderator->id]);
    staffCategory();
    $recipients = app(Recipients::class);

    expect($recipients->for('plugins.moderation', Channel::EMAIL))->toBe([])
        ->and($recipients->for('plugins.moderation', Channel::IN_APP))->toHaveCount(1);
});

it('lists a person once however many times the audience names them, and checks the key again', function () {
    $moderator = TestUser::make('Moderator');
    $stranger = TestUser::make('Stranger');
    staffHolders(['operator.plugin.moderate' => [$moderator->id]]);
    staffCategory();
    app()->instance(StaffAudience::class, new class($moderator, $stranger) implements StaffAudience
    {
        public function __construct(private TestUser $moderator, private TestUser $stranger) {}

        public function holding(string $access): iterable
        {
            return [$this->moderator, $this->stranger, $this->moderator];
        }
    });
    app()->forgetInstance(Recipients::class);

    expect(array_map(fn ($person) => $person->id, app(Recipients::class)->for('plugins.moderation')))->toBe([$moderator->id]);
});

it('only answers for staff categories', function () {
    app(Recipients::class)->for('forum.mention');
})->throws(InvalidPreference::class, 'not a staff category');

it('writes no rows just to answer who to notify', function () {
    $moderator = TestUser::make('Moderator');
    staffHolders(['operator.plugin.moderate' => [$moderator->id]]);
    staffCategory();

    app(Recipients::class)->byChannel('plugins.moderation');

    expect(NotificationPreference::count())->toBe(0);
});
