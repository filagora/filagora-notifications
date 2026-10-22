<?php

declare(strict_types=1);

use Filagora\Core\Schema\Adopter;
use Filagora\Core\Schema\AdoptionStatus;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Core\Schema\TableShape;
use Filagora\Core\Support\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The table must match the site it was extracted from, so an existing site can adopt it as it is.
 */

const NOTIFICATIONS_MIGRATION = '2026_10_22_000000_create_filagora_user_notification_preferences_table';

function notificationsMigration(): object
{
    return require __DIR__.'/../../database/migrations/'.NOTIFICATIONS_MIGRATION.'.php';
}

afterEach(function () {
    Schema::dropIfExists('user_notification_preferences');
    config(['filagora.table_prefix' => 'filagora_']);

    if (! Schema::hasTable(Tables::name('user_notification_preferences'))) {
        notificationsMigration()->up();
    }
});

it('ships a snapshot of the table that matches the migration, column order included', function () {
    $snapshot = app(SchemaRegistry::class)->get('notifications');

    expect(array_keys($snapshot->tables))->toBe(['user_notification_preferences'])
        ->and($snapshot->migrationNames())->toBe([NOTIFICATIONS_MIGRATION])
        ->and(TableShape::inspect('user_notification_preferences')->differencesFrom($snapshot->tables['user_notification_preferences'], compareColumnOrder: true))->toBe([]);
});

it('keeps one row per person, category and channel, with the names the original site gave its keys', function () {
    $shape = TableShape::inspect('user_notification_preferences');

    expect(array_keys($shape->columns))->toBe(['id', 'user_id', 'preference_key', 'channel', 'value', 'source', 'updated_by_user_id', 'created_at', 'updated_at'])
        ->and($shape->columns['preference_key']['type'])->toBe('varchar(128)')
        ->and($shape->columns['channel'])->toMatchArray(['type' => 'varchar(16)', 'nullable' => false, 'default' => 'email'])
        ->and($shape->columns['value'])->toMatchArray(['type' => 'varchar(255)', 'nullable' => true])
        ->and($shape->columns['source'])->toMatchArray(['type' => 'varchar(32)', 'nullable' => false, 'default' => 'user'])
        ->and($shape->indexes['unp_user_pref_channel_unq'])->toMatchArray(['columns' => ['user_id', 'preference_key', 'channel'], 'unique' => true])
        ->and($shape->indexes['user_email_preferences_updated_by_user_id_updated_at_index']['columns'])->toBe(['updated_by_user_id', 'updated_at'])
        ->and($shape->indexes['user_notification_preferences_preference_key_channel_value_index']['columns'])->toBe(['preference_key', 'channel', 'value'])
        ->and($shape->foreignKeys['user_email_preferences_user_id_foreign']['on_delete'])->toBe('cascade')
        ->and($shape->foreignKeys['user_email_preferences_updated_by_user_id_foreign']['on_delete'])->toBe('set null');
});

it('adopts the table of an existing site and records the migration', function () {
    notificationsMigration()->down();
    config(['filagora.table_prefix' => '']);
    notificationsMigration()->up();
    DB::table('migrations')->where('migration', NOTIFICATIONS_MIGRATION)->delete();

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('notifications'));

    expect($plan->status)->toBe(AdoptionStatus::Adoptable, implode('; ', $plan->differences))
        ->and($plan->pendingMigrations)->toBe([NOTIFICATIONS_MIGRATION]);

    $this->artisan('filagora:adopt', ['--package' => 'notifications'])->expectsOutputToContain('Recorded 1 migration(s) as run.')->assertExitCode(0);
});

it('refuses a table that lost an index, and is already adopted on a site that ran the migration', function () {
    expect(app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('notifications'))->status)->toBe(AdoptionStatus::AlreadyAdopted);

    notificationsMigration()->down();
    config(['filagora.table_prefix' => '']);
    notificationsMigration()->up();
    Schema::table('user_notification_preferences', fn ($table) => $table->dropIndex('user_notification_preferences_preference_key_channel_value_index'));

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('notifications'));

    expect($plan->status)->toBe(AdoptionStatus::Mismatch)
        ->and($plan->differences)->toContain('user_notification_preferences: index user_notification_preferences_preference_key_channel_value_index is missing');
});
