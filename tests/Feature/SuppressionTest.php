<?php

declare(strict_types=1);

use Filagora\Notifications\Contracts\EmailSuppression;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Suppression\AttributeSuppression;
use Filagora\Notifications\Tests\Support\TestUser;
use Illuminate\Contracts\Auth\Authenticatable;

function userWith(array $attributes): TestUser
{
    return TestUser::make()->forceFill($attributes);
}

it('does not suppress a person whose model has neither attribute', function () {
    expect((new AttributeSuppression)->isSuppressed(TestUser::make()))->toBeFalse();
});

it('suppresses a person whose receives-email attribute is explicitly false', function (mixed $value, bool $suppressed) {
    expect((new AttributeSuppression)->isSuppressed(userWith(['receives_email' => $value])))->toBe($suppressed);
})->with([
    'false' => [false, true],
    'zero' => [0, true],
    'true' => [true, false],
    'one' => [1, false],
    'null (not recorded)' => [null, false],
]);

it('suppresses a bounced or complained address and no other status', function (mixed $status, bool $suppressed) {
    expect((new AttributeSuppression)->isSuppressed(userWith(['email_status' => $status])))->toBe($suppressed);
})->with([
    'bounced' => ['bounced', true],
    'complained' => ['complained', true],
    'deliverable' => ['deliverable', false],
    'unknown' => [null, false],
]);

it('reads the attribute names and statuses from config, and ignores an attribute set to null', function () {
    config([
        'filagora-notifications.suppression.receives_email_attribute' => 'mail_ok',
        'filagora-notifications.suppression.status_attribute' => 'mail_state',
        'filagora-notifications.suppression.suppressed_statuses' => ['blocked'],
    ]);

    expect((new AttributeSuppression)->isSuppressed(userWith(['mail_ok' => false])))->toBeTrue()
        ->and((new AttributeSuppression)->isSuppressed(userWith(['mail_state' => 'blocked'])))->toBeTrue()
        ->and((new AttributeSuppression)->isSuppressed(userWith(['mail_state' => 'bounced', 'receives_email' => false])))->toBeFalse();

    config(['filagora-notifications.suppression.receives_email_attribute' => null, 'filagora-notifications.suppression.status_attribute' => null]);

    expect((new AttributeSuppression)->isSuppressed(userWith(['mail_ok' => false, 'mail_state' => 'blocked'])))->toBeFalse();
});

it('stops email but not push or the bell for a suppressed person', function () {
    $user = userWith(['receives_email' => false]);
    $preferences = app(Preferences::class);

    expect($preferences->isSuppressed($user))->toBeTrue()
        ->and($preferences->canDeliver($user, 'forum.mention'))->toBeFalse()
        ->and($preferences->canDeliver($user, 'forum.mention', Channel::PUSH))->toBeTrue()
        ->and($preferences->canDeliver($user, 'forum.mention', Channel::IN_APP))->toBeTrue();
});

it('needs both the person\'s choice and no suppression to deliver email', function () {
    $user = TestUser::make();
    $preferences = app(Preferences::class);

    expect($preferences->canDeliver($user, 'forum.mention'))->toBeTrue();

    $preferences->set($user, 'forum.mention', Channel::EMAIL, false);

    expect($preferences->canDeliver($user, 'forum.mention'))->toBeFalse();
});

it('uses a suppression the host binds', function () {
    $this->app->singleton(EmailSuppression::class, fn () => new class implements EmailSuppression
    {
        public function isSuppressed(Authenticatable $user): bool
        {
            return $user->getAuthIdentifier() === 1;
        }
    });
    $this->app->forgetInstance(Preferences::class);

    $first = TestUser::make('First');
    $second = TestUser::make('Second');

    expect(app(Preferences::class)->canDeliver($first, 'forum.mention'))->toBe($first->id !== 1)
        ->and(app(Preferences::class)->canDeliver($second, 'forum.mention'))->toBe($second->id !== 1);
});
