<?php

declare(strict_types=1);

use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Entitlements\Registry\EntitlementRegistry;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Filament\RelationManagers\UserNotificationPreferencesRelationManager;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Support\Abilities;
use Filagora\Notifications\Tests\Support\TestUser;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->member = TestUser::make('Member');
    $this->staff = TestUser::make('Staff');
    UserEntitlementGrant::create(['user_id' => $this->staff->id, 'entitlement_key' => Abilities::VIEW, 'grant_type' => 'manual']);
    actingAs($this->staff);
});

function preferencesManagerFor(TestUser $owner): Testable
{
    return Livewire::test(UserNotificationPreferencesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditRecord::class]);
}

it('shows the choices only to people who may see them', function () {
    expect(UserNotificationPreferencesRelationManager::canViewForRecord($this->member, EditRecord::class))->toBeTrue();

    actingAs(TestUser::make('Nobody'));

    expect(UserNotificationPreferencesRelationManager::canViewForRecord($this->member, EditRecord::class))->toBeFalse();
});

it('declares the staff ability in the entitlement definitions', function () {
    expect(app(EntitlementRegistry::class)->definitions())->toHaveKey(Abilities::VIEW);
});

it('lists only the person\'s own stored choices', function () {
    $other = TestUser::make('Other');
    app(Preferences::class)->set($this->member, 'forum.mention', Channel::PUSH, false);
    app(Preferences::class)->set($other, 'forum.mention', Channel::PUSH, false);

    $mine = NotificationPreference::where('user_id', $this->member->id)->sole();
    $theirs = NotificationPreference::where('user_id', $other->id)->sole();

    preferencesManagerFor($this->member)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
});

it('shows a category by its label and a channel by its name, and what was chosen', function () {
    app(Preferences::class)->set($this->member, 'forum.mention', Channel::PUSH, false);
    app(Preferences::class)->setMode($this->member, 'forum.thread_updates_digest_frequency', 'weekly');

    preferencesManagerFor($this->member)
        ->assertSee('Mentions')
        ->assertSee('forum.mention')
        ->assertSee('Browser push')
        ->assertSee('Off')
        ->assertSee('weekly');
});

it('shows an id nobody registered as it is', function () {
    $row = NotificationPreference::create(['user_id' => $this->member->id, 'preference_key' => 'old.removed_category', 'channel' => 'email', 'value' => '1']);

    preferencesManagerFor($this->member)->assertSee('old.removed_category')->assertTableColumnFormattedStateSet('preference_key', 'old.removed_category', $row);
});

it('words each kind of stored value', function (?string $stored, string $shown) {
    $row = NotificationPreference::create(['user_id' => $this->member->id, 'preference_key' => 'forum.mention', 'channel' => 'email', 'value' => $stored]);

    preferencesManagerFor($this->member)->assertTableColumnFormattedStateSet('value', $shown, $row);
})->with([
    'on' => ['1', 'On'],
    'off' => ['0', 'Off'],
    'a mode word' => ['daily', 'daily'],
    'nothing' => [null, '-'],
]);

it('names who changed a choice', function () {
    app(Preferences::class)->set($this->member, 'forum.mention', Channel::EMAIL, false, $this->staff);

    preferencesManagerFor($this->member)->assertSee('Staff');
});

it('is read-only', function () {
    app(Preferences::class)->set($this->member, 'forum.mention', Channel::EMAIL, false);
    $row = NotificationPreference::sole();

    $manager = preferencesManagerFor($this->member);
    $instance = $manager->instance();

    expect((fn () => $this->canCreate())->call($instance))->toBeFalse()
        ->and((fn () => $this->canEdit($row))->call($instance))->toBeFalse()
        ->and((fn () => $this->canDelete($row))->call($instance))->toBeFalse();
});

it('filters by channel', function () {
    app(Preferences::class)->set($this->member, 'forum.mention', Channel::EMAIL, false);
    app(Preferences::class)->set($this->member, 'forum.mention', Channel::PUSH, false);
    $email = NotificationPreference::where('channel', 'email')->sole();
    $push = NotificationPreference::where('channel', 'push')->sole();

    preferencesManagerFor($this->member)
        ->filterTable('channel', 'push')
        ->assertCanSeeTableRecords([$push])
        ->assertCanNotSeeTableRecords([$email]);
});
