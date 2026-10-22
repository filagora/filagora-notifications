<?php

declare(strict_types=1);

namespace Filagora\Notifications\Filament\RelationManagers;

use Filagora\Core\Support\UserModel;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\NotificationCategories;
use Filagora\Notifications\Support\Abilities;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * A person's stored notification choices on the user screen, read-only: a row exists only once the
 * person (or an import, or staff) decided, so the list shows decisions, not defaults. Changing a
 * choice belongs to the person's own settings page. The user model needs the
 * `HasNotificationPreferences` trait (or its own `notificationPreferences` relation).
 */
class UserNotificationPreferencesRelationManager extends RelationManager
{
    protected static string $relationship = 'notificationPreferences';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filagora-notifications::messages.relation_manager.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows(Abilities::VIEW);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('preference_key')
            ->defaultSort('preference_key')
            ->description(__('filagora-notifications::messages.relation_manager.description'))
            ->columns([
                TextColumn::make('preference_key')
                    ->label(__('filagora-notifications::messages.relation_manager.category'))
                    ->formatStateUsing(function (string $state): string {
                        $categories = app(NotificationCategories::class);

                        return $categories->has($state) ? $categories->get($state)->label() : $state;
                    })
                    ->description(fn (NotificationPreference $record): string => $record->preference_key)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->label(__('filagora-notifications::messages.relation_manager.channel'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => NotificationChannel::tryFrom($state)?->label() ?? $state)
                    ->sortable(),
                TextColumn::make('value')
                    ->label(__('filagora-notifications::messages.relation_manager.value'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        '1' => __('filagora-notifications::messages.relation_manager.on'),
                        '0' => __('filagora-notifications::messages.relation_manager.off'),
                        null => '-',
                        default => $state,
                    }),
                TextColumn::make('source')
                    ->label(__('filagora-notifications::messages.relation_manager.source'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('updated_by_user_id')
                    ->label(__('filagora-notifications::messages.relation_manager.changed_by'))
                    ->formatStateUsing(function (?int $state): string {
                        if ($state === null) {
                            return '-';
                        }

                        $user = UserModel::class()::query()->find($state);

                        return $user instanceof Model ? (string) data_get($user, UserModel::titleAttribute(), '#'.$state) : '#'.$state;
                    }),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')->options(collect(NotificationChannel::cases())->mapWithKeys(fn (NotificationChannel $channel): array => [$channel->value => $channel->label()])->all()),
                SelectFilter::make('source')->options(fn (): array => NotificationPreference::query()->distinct()->orderBy('source')->pluck('source', 'source')->all()),
            ])
            ->headerActions([])
            ->recordActions([]);
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }
}
