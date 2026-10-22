<?php

declare(strict_types=1);

namespace Filagora\Notifications\Models;

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's choice for one category on one channel. Absence of a row means "the category's
 * default", so a row exists only once someone (or an import) has decided.
 *
 * @property int $id
 * @property int $user_id
 * @property string $preference_key
 * @property string $channel
 * @property string|null $value
 * @property string $source
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationPreference extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return Tables::name('user_notification_preferences');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'updated_by_user_id');
    }
}
