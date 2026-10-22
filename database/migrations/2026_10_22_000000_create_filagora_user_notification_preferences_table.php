<?php

declare(strict_types=1);

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserKey;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What each person wants to hear about, one row per person, category and channel, in the shape of
 * the forum the package was extracted from (the index and foreign key names are kept as that site
 * has them, so that its table can be adopted as it is). `value` is `1` or `0` for a switch, or a
 * plain word for a mode such as a digest frequency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::name('user_notification_preferences'), function (Blueprint $table): void {
            $table->id();
            UserKey::column($table, 'user_id');
            $table->string('preference_key', 128);
            $table->string('channel', 16)->default('email');
            $table->string('value')->nullable();
            $table->string('source', 32)->default('user');
            UserKey::column($table, 'updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'preference_key', 'channel'], 'unp_user_pref_channel_unq');
            $table->index(['updated_by_user_id', 'updated_at'], 'user_email_preferences_updated_by_user_id_updated_at_index');
            $table->index(['preference_key', 'channel', 'value'], 'user_notification_preferences_preference_key_channel_value_index');

            $table->foreign('user_id', 'user_email_preferences_user_id_foreign')
                ->references('id')->on(UserModel::table())->cascadeOnDelete();
            $table->foreign('updated_by_user_id', 'user_email_preferences_updated_by_user_id_foreign')
                ->references('id')->on(UserModel::table())->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('user_notification_preferences'));
    }
};
